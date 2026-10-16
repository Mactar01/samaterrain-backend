<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

class SwaggerFieldDocs
{
    #[OA\Get(
        path: "/api/fields",
        operationId: "getFieldsList",
        tags: ["Terrains"],
        summary: "Récupérer la liste des terrains",
        description: "Retourne une liste de terrains paginée",
        responses: [
            new OA\Response(response: 200, description: "Opération réussie")
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: "/api/owner/fields",
        operationId: "getMyFields",
        tags: ["Terrains Propriétaire"],
        summary: "Récupérer les terrains du propriétaire connecté",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Opération réussie"),
            new OA\Response(response: 401, description: "Non autorisé")
        ]
    )]
    public function myFields() {}
}
