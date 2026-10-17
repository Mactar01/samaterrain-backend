<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\FieldImage;
use App\Http\Requests\UploadFieldImageRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FieldImageController extends Controller
{
    use AuthorizesRequests;

    /**
     * Upload d'une image pour un terrain
     */
    public function store(UploadFieldImageRequest $request, $fieldId)
    {
        $field = Field::findOrFail($fieldId);
        
        $this->authorize('update', $field);

        if ($request->hasFile('image')) {
            $imgFile = $request->file('image');
            $path = $imgFile->store('fields', 'public');
            $dataUri = Field::uploadedFileToDataUri($imgFile) ?: Storage::url($path);
            
            // Si c'est la première image, on la met en principale
            $isFirstImage = $field->images()->count() === 0;
            $isPrimary = $request->input('is_primary', $isFirstImage);

            if ($isPrimary) {
                // Enlever le statut principal des autres
                $field->images()->update(['is_primary' => false]);
                $field->update(['photo' => $dataUri]);
            }

            $image = $field->images()->create([
                'url' => $dataUri,
                'is_primary' => $isPrimary,
                'sort_order' => ($field->images()->max('sort_order') ?? 0) + 1
            ]);

            return response()->json([
                'message' => 'Image uploadée avec succès.',
                'image' => $image
            ], 201);
        }

        return response()->json(['message' => 'Aucune image fournie.'], 400);
    }

    /**
     * Suppression d'une image
     */
    public function destroy($fieldId, $imageId)
    {
        $field = Field::findOrFail($fieldId);
        
        $this->authorize('update', $field);

        $image = $field->images()->findOrFail($imageId);

        $rawUrl = $image->getRawOriginal('url');
        if ($rawUrl && !str_starts_with($rawUrl, 'data:image')) {
            $path = str_replace('/storage/', '', $rawUrl);
            Storage::disk('public')->delete($path);
        }

        $wasPrimary = $image->is_primary || $field->photo === $rawUrl;
        $image->delete();

        if ($wasPrimary) {
            $nextImg = $field->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();
            if ($nextImg) {
                $nextImg->update(['is_primary' => true]);
                $field->update(['photo' => $nextImg->getRawOriginal('url')]);
            } else {
                $field->update(['photo' => null]);
            }
        }

        return response()->json(['message' => 'Image supprimée avec succès.']);
    }

    /**
     * Définir comme image principale
     */
    public function setPrimary($fieldId, $imageId)
    {
        $field = Field::findOrFail($fieldId);
        
        $this->authorize('update', $field);

        $image = $field->images()->findOrFail($imageId);

        // Reset all to false
        $field->images()->update(['is_primary' => false]);

        // Set the requested image to true
        $image->update(['is_primary' => true]);

        return response()->json(['message' => 'Image définie comme principale.']);
    }
}
