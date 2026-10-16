<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

class SwaggerAuthDocs
{
    #[OA\Post(
        path: "/api/auth/login",
        operationId: "loginUser",
        tags: ["Authentification"],
        summary: "Connexion utilisateur",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@myterrain.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Connexion réussie"),
            new OA\Response(response: 401, description: "Identifiants invalides")
        ]
    )]
    public function login() {}

    #[OA\Get(
        path: "/api/auth/me",
        operationId: "getMe",
        tags: ["Authentification"],
        summary: "Obtenir les informations de l'utilisateur connecté",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Succès"),
            new OA\Response(response: 401, description: "Non autorisé")
        ]
    )]
    public function me() {}
}
