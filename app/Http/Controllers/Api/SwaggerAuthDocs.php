<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class SwaggerAuthDocs
{
    /**
     * @OA\Post(
     *      path="/api/v1/auth/login",
     *      operationId="loginUser",
     *      tags={"Authentification"},
     *      summary="Connexion utilisateur",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="admin@myterrain.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password")
     *          )
     *      ),
     *      @OA\Response(response=200, description="Connexion réussie"),
     *      @OA\Response(response=401, description="Identifiants invalides")
     * )
     */
    public function login() {}

    /**
     * @OA\Get(
     *      path="/api/v1/auth/me",
     *      operationId="getMe",
     *      tags={"Authentification"},
     *      summary="Obtenir les informations de l'utilisateur connecté",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Succès"),
     *      @OA\Response(response=401, description="Non autorisé")
     * )
     */
    public function me() {}
}
