<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "myTerrain API Documentation",
    description: "Documentation interactive des APIs de myTerrain",
    contact: new OA\Contact(email: "contact@myterrain.sn")
)]
#[OA\Server(url: "/", description: "Serveur API")]
#[OA\SecurityScheme(securityScheme: "bearerAuth", type: "http", scheme: "bearer")]
abstract class Controller
{
    //
}
