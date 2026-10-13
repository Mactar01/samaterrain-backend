<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class SwaggerFieldDocs
{
    /**
     * @OA\Get(
     *      path="/api/v1/fields",
     *      operationId="getFieldsList",
     *      tags={"Terrains"},
     *      summary="Récupérer la liste des terrains",
     *      description="Retourne une liste de terrains paginée",
     *      @OA\Response(
     *          response=200,
     *          description="Opération réussie"
     *       )
     *     )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/owner/fields",
     *      operationId="getMyFields",
     *      tags={"Terrains Propriétaire"},
     *      summary="Récupérer les terrains du propriétaire connecté",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Opération réussie"
     *       ),
     *      @OA\Response(response=401, description="Non autorisé")
     * )
     */
    public function myFields() {}
}
