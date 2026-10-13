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
        
        // Trouver ou créer le profil Owner pour cet utilisateur
        $owner = Owner::firstOrCreate(
            ['user_id' => $user->id],
            ['business_name' => $user->name . ' Sports']
        );

        $field = $owner->fields()->create($request->validated());

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('fields', 'public');
            $field->images()->create([
                'url' => '/storage/' . $path,
                'is_primary' => true
            ]);
        }

        return response()->json([
            'message' => 'Terrain créé avec succès.',
            'field' => $field->load('images')
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

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($field->photo) {
                Storage::disk('public')->delete($field->photo);
            }
            // Store new photo
            $path = $request->file('photo')->store('fields', 'public');
            $validated['photo'] = $path;
        }

        $field->update($validated);

        // Include full url for photo if needed
        $field->photo_url = $field->photo ? url('storage/' . $field->photo) : null;

        return response()->json([
            'message' => 'Terrain mis à jour avec succès.',
            'field' => $field
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
        if (!$request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $owner = Owner::where('user_id', $request->user()->id)->first();

        if (!$owner) {
            return response()->json([]); // Aucun terrain encore
        }

        $fields = $owner->fields()->with('primaryImage')->get();

        return response()->json($fields);
    }
}
