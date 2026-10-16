<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

class SwaggerOwnerSettingsDocs
{
    #[OA\Get(
        path: "/api/owner/settings",
        operationId: "getOwnerSettings",
        summary: "Récupérer la configuration des modules stratégiques du complexe",
        description: "Retourne les paramètres WhatsApp/SMS, Abonnements, Matchmaking, Fidélité, Export comptable et Marque blanche",
        tags: ["Paramétrage Complexe (Modules Gérant)"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paramètres récupérés avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "settings", type: "object"),
                        new OA\Property(property: "business_name", type: "string"),
                        new OA\Property(property: "commission_rate", type: "number")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Droits insuffisants (gérant requis)")
        ]
    )]
    public function getSettings() {}

    #[OA\Put(
        path: "/api/owner/settings",
        operationId: "updateOwnerSettings",
        summary: "Mettre à jour les paramètres des modules du complexe",
        description: "Enregistre les modifications de configuration pour l'un ou plusieurs des 5 modules stratégiques",
        tags: ["Paramétrage Complexe (Modules Gérant)"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "whatsapp_sms", type: "object"),
                    new OA\Property(property: "subscriptions", type: "object"),
                    new OA\Property(property: "matchmaking", type: "object"),
                    new OA\Property(property: "loyalty", type: "object"),
                    new OA\Property(property: "accounting", type: "object")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Paramètres mis à jour avec succès")
        ]
    )]
    public function updateSettings() {}

    #[OA\Get(
        path: "/api/owner/reports/accounting",
        operationId: "getAccountingReport",
        summary: "Générer le rapport et récapitulatif comptable",
        description: "Calcule les métriques financières (chiffre d'affaires brut, net, commissions, paiements par mode) sur la période demandée",
        tags: ["Paramétrage Complexe (Modules Gérant)"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "year", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "month", in: "query", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Rapport comptable généré")
        ]
    )]
    public function getAccountingReport() {}

    #[OA\Get(
        path: "/api/owner/reports/export-csv",
        operationId: "exportAccountingCsv",
        summary: "Télécharger l'export financier au format CSV / Excel",
        tags: ["Paramétrage Complexe (Modules Gérant)"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "year", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "month", in: "query", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Fichier CSV téléchargé")
        ]
    )]
    public function exportAccountingCsv() {}
}
