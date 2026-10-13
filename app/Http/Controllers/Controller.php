<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="myTerrain API Documentation",
 *      description="Documentation interactive des APIs de myTerrain",
 *      @OA\Contact(
 *          email="contact@myterrain.sn"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="Serveur Local"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
abstract class Controller
{
    //
}
