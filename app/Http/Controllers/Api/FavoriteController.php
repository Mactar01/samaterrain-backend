<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        
        $favorites = DB::table('favorites')
            ->join('fields', 'favorites.field_id', '=', 'fields.id')
            ->where('favorites.user_id', $userId)
            ->select('fields.*')
            ->get();

        return response()->json($favorites);
    }

    public function store(Request $request)
    {
        $request->validate([
            'field_id' => 'required|exists:fields,id'
        ]);

        $userId = $request->user()->id;
        
        DB::table('favorites')->insertOrIgnore([
            'user_id' => $userId,
            'field_id' => $request->field_id,
            'created_at' => now()
        ]);

        return response()->json(['message' => 'Ajouté aux favoris']);
    }

    public function destroy(Request $request, $fieldId)
    {
        $userId = $request->user()->id;
        
        DB::table('favorites')
            ->where('user_id', $userId)
            ->where('field_id', $fieldId)
            ->delete();

        return response()->json(['message' => 'Retiré des favoris']);
    }
}
