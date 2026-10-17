<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Support\Facades\Storage;
use App\Models\Owner;
use App\Http\Requests\StoreFieldRequest;
use App\Http\Requests\UpdateFieldRequest;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FieldController extends Controller
{
    use AuthorizesRequests;

    /**
     * Liste publique des terrains actifs avec filtres
     */
    public function index(Request $request)
    {
        $query = Field::active()->with('primaryImage');

        // Filtre par ville
        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtre par prix max
        if ($request->filled('max_price')) {
            $query->where('price_per_hour', '<=', $request->max_price);
        }

        // Recherche par nom
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filtrage GPS (proximité)
        if ($request->filled('lat') && $request->filled('lng')) {
            $radius = $request->input('radius', 10); // défaut 10 km
            $query->nearby((float)$request->lat, (float)$request->lng, (int)$radius);
        }

        $fields = $query->paginate(15);

        // Add is_favorite boolean if user is authenticated
        if ($user = auth('sanctum')->user()) {
            $favorites = \Illuminate\Support\Facades\DB::table('favorites')
                ->where('user_id', $user->id)
                ->pluck('field_id')
                ->toArray();
                
            $fields->getCollection()->transform(function($field) use ($favorites) {
                $field->is_favorite = in_array($field->id, $favorites);
                return $field;
            });
        }

        return response()->json($fields);
    }

    /**
     * Détail d'un terrain (public)
     */
    public function show($id)
    {
        // Route publique : du loueur on n'expose que le contact (pas commission, SIRET ni email)
        $field = Field::with([
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            'owner:id,user_id,business_name',
            'owner.user:id,name,phone',
        ])->findOrFail($id);

        $user = auth('sanctum')->user();

        // Terrain masqué par son loueur : visible seulement par lui et par l'admin
        if (!$field->is_active) {
            $canSee = $user && ($user->role === 'admin' || $field->owner?->user_id === $user->id);
            if (!$canSee) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        }

        $isFavorite = false;
        if ($user) {
            $isFavorite = \Illuminate\Support\Facades\DB::table('favorites')
                ->where('user_id', $user->id)
                ->where('field_id', $field->id)
                ->exists();
        }
        $field->is_favorite = $isFavorite;

        return response()->json([
            'field' => $field,
            'avg_rating' => $field->avg_rating,
            'reviews_count' => $field->reviews_count
        ]);
    }

    /**
     * Création d'un terrain par un loueur
     */
    public function store(StoreFieldRequest $request)
    {
        $this->authorize('create', Field::class);

        $user = $request->user();
        
        $owner = $user->getOwner();
        if (!$owner) {
            $owner = Owner::firstOrCreate(
                ['user_id' => $user->id],
                ['business_name' => $user->name . ' Sports']
            );
        }

        $field = $owner->fields()->create($request->validated());

        $images = [];
        if ($request->hasFile('images')) {
            $images = $request->file('images');
        } elseif ($request->hasFile('photos')) {
            $images = $request->file('photos');
        } elseif ($request->hasFile('image')) {
            $images = [$request->file('image')];
        } elseif ($request->hasFile('photo')) {
            $images = [$request->file('photo')];
        }

        foreach ($images as $index => $imgFile) {
            $path = $imgFile->store('fields', 'public');
            $dataUri = Field::uploadedFileToDataUri($imgFile) ?: ('/storage/' . $path);
            $field->images()->create([
                'url' => $dataUri,
                'is_primary' => $index === 0,
                'sort_order' => $index + 1
            ]);
            if ($index === 0) {
                $field->update(['photo' => $dataUri]);
            }
        }

        return response()->json([
            'message' => 'Terrain créé avec succès.',
            'field' => $field->fresh(['primaryImage', 'images'])
        ], 201);
    }

    /**
     * Mise à jour d'un terrain
     */
    public function update(UpdateFieldRequest $request, $id)
    {
        $field = Field::findOrFail($id);
        
        $this->authorize('update', $field);

        $validated = $request->validated();

        $uploadedPhotos = [];
        if ($request->hasFile('photos')) {
            $uploadedPhotos = $request->file('photos');
        } elseif ($request->hasFile('images')) {
            $uploadedPhotos = $request->file('images');
        } elseif ($request->hasFile('photo')) {
            $uploadedPhotos = [$request->file('photo')];
        } elseif ($request->hasFile('image')) {
            $uploadedPhotos = [$request->file('image')];
        }

        if (!empty($uploadedPhotos)) {
            $this->pruneMissingFieldImages($field);
            $currentMaxOrder = $field->images()->max('sort_order') ?? 0;
            foreach ($uploadedPhotos as $index => $photoFile) {
                $path = $photoFile->store('fields', 'public');
                $dataUri = Field::uploadedFileToDataUri($photoFile) ?: ('/storage/' . $path);

                if ($index === 0 && (!$field->photo || !Field::resolveImageUrl($field->photo) || count($uploadedPhotos) === 1)) {
                    if ($field->photo && !str_starts_with($field->photo, 'data:image')) {
                        Storage::disk('public')->delete($field->photo);
                    }
                    $validated['photo'] = $dataUri;
                }

                $field->images()->create([
                    'url' => $dataUri,
                    'is_primary' => $index === 0 && $field->images()->count() === 0,
                    'sort_order' => $currentMaxOrder + $index + 1
                ]);
            }
        }

        $field->update($validated);
        $this->pruneMissingFieldImages($field);

        return response()->json([
            'message' => 'Terrain mis à jour avec succès.',
            'field' => $field->fresh(['primaryImage', 'images'])
        ]);
    }
    /**
     * Suppression d'un terrain
     */
    public function destroy($id)
    {
        $field = Field::findOrFail($id);
        
        $this->authorize('delete', $field);

        $field->delete();

        return response()->json([
            'message' => 'Terrain supprimé avec succès.'
        ]);
    }

    /**
     * Terrains appartenant au loueur connecté
     */
    public function myFields(Request $request)
    {
        $user = $request->user();
        if (!$user->isOwner() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json([]); // Aucun terrain encore
        }

        $fields = $owner->fields()->with(['primaryImage', 'images'])->get();
        foreach ($fields as $field) {
            if ($this->pruneMissingFieldImages($field)) {
                $field->load(['primaryImage', 'images']);
            }
        }

        return response()->json($fields);
    }

    private function pruneMissingFieldImages(Field $field): bool
    {
        $changed = false;
        $images = $field->relationLoaded('images') ? $field->images : $field->images()->get();
        foreach ($images as $img) {
            $rawUrl = $img->getRawOriginal('url');
            if (Field::resolveImageUrl($rawUrl) === null) {
                $img->delete();
                $changed = true;
            }
        }
        if ($field->photo && Field::resolveImageUrl($field->photo) === null) {
            $validFirst = $field->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();
            $field->photo = $validFirst ? $validFirst->getRawOriginal('url') : null;
            $field->saveQuietly();
            $changed = true;
        }
        return $changed;
    }
}
