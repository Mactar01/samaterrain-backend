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
            $path = $request->file('image')->store('fields', 'public');
            
            // Si c'est la première image, on la met en principale
            $isFirstImage = $field->images()->count() === 0;
            $isPrimary = $request->input('is_primary', $isFirstImage);

            if ($isPrimary) {
                // Enlever le statut principal des autres
                $field->images()->update(['is_primary' => false]);
            }

            $image = $field->images()->create([
                'url' => Storage::url($path),
                'is_primary' => $isPrimary,
                'sort_order' => $field->images()->max('sort_order') + 1
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

        // Extraire le chemin du fichier pour le supprimer du stockage
        $path = str_replace('/storage/', '', $image->url);
        Storage::disk('public')->delete($path);

        $image->delete();

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
