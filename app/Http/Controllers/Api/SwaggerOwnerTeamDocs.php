<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

class SwaggerOwnerTeamDocs
{
    #[OA\Get(
        path: "/api/owner/team",
        operationId: "getOwnerTeamMembers",
        summary: "Lister les membres et collaborateurs du complexe",
        description: "Retourne la liste complète des membres rattachés au complexe avec leurs rôles et permissions",
        tags: ["Gestion Utilisateurs - Collaborateurs"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des collaborateurs récupérée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "owner", type: "object"),
                        new OA\Property(property: "members", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total_members", type: "integer", example: 4)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Droits insuffisants"),
            new OA\Response(response: 404, description: "Complexe introuvable")
        ]
    )]
    public function index() {}

    #[OA\Post(
        path: "/api/owner/team",
        operationId: "createOwnerCollaborator",
        summary: "Créer un nouveau collaborateur pour le complexe",
        description: "Crée un compte ou associe un utilisateur existant avec un rôle et des droits spécifiques au complexe",
        tags: ["Gestion Utilisateurs - Collaborateurs"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Mamadou Sow"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "mamadou@example.com"),
                    new OA\Property(property: "phone", type: "string", example: "+221770000000"),
                    new OA\Property(property: "role", type: "string", example: "receptionist", description: "manager, receptionist, field_staff, accountant, ou ID d'un custom role"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["reservations:manage", "slots:manage"]),
                    new OA\Property(property: "password", type: "string", format: "password", example: "Pass1234!")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Collaborateur ajouté avec succès"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 403, description: "Droits insuffisants")
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: "/api/owner/team/{id}",
        operationId: "updateOwnerCollaborator",
        summary: "Mettre à jour un collaborateur (rôle, droits, statut)",
        description: "Permet de modifier le rôle attribué, les permissions sur-mesure ou suspendre/activer le compte",
        tags: ["Gestion Utilisateurs - Collaborateurs"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID de la liaison collaborateur", schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "role", type: "string", example: "manager"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                    new OA\Property(property: "phone", type: "string", example: "+221771112233")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Collaborateur mis à jour avec succès"),
            new OA\Response(response: 404, description: "Collaborateur introuvable")
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: "/api/owner/team/{id}",
        operationId: "deleteOwnerCollaborator",
        summary: "Retirer un collaborateur du complexe",
        description: "Supprime l'accès du collaborateur au complexe (le compte maître titulaire ne peut pas être supprimé)",
        tags: ["Gestion Utilisateurs - Collaborateurs"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID de la liaison collaborateur", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Collaborateur retiré avec succès"),
            new OA\Response(response: 403, description: "Impossible de supprimer le titulaire"),
            new OA\Response(response: 404, description: "Collaborateur introuvable")
        ]
    )]
    public function destroy() {}

    #[OA\Get(
        path: "/api/owner/team/permissions-matrix",
        operationId: "getPermissionsMatrix",
        summary: "Matrice des droits et rôles du complexe",
        description: "Retourne l'ensemble exhaustif des droits système disponibles, des rôles standards et des rôles personnalisés créés",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrice des permissions récupérée",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "available_permissions", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "default_roles", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "custom_roles", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function getPermissionsMatrix() {}

    #[OA\Post(
        path: "/api/owner/team/assign-role-rights",
        operationId: "assignRoleRights",
        summary: "Assigner les droits à un rôle (style eyassur)",
        description: "Enregistre la liste exhaustive des droits alloués à un rôle spécifique pour le complexe",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["role_id", "permissions"],
                properties: [
                    new OA\Property(property: "role_id", type: "string", example: "receptionist"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["reservations:manage", "slots:manage"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Droits du rôle mis à jour avec succès")
        ]
    )]
    public function assignRoleRights() {}

    #[OA\Post(
        path: "/api/owner/team/custom-roles",
        operationId: "createCustomRole",
        summary: "Créer un rôle personnalisé",
        description: "Permet au gérant du complexe de concevoir un nouveau rôle sur-mesure avec son intitulé et son pack de droits",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "permissions"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Superviseur Tournois"),
                    new OA\Property(property: "description", type: "string", example: "Gestion des événements et matchs du week-end"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["reservations:manage", "fields:manage"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Rôle personnalisé créé")
        ]
    )]
    public function createCustomRole() {}

    #[OA\Put(
        path: "/api/owner/team/custom-roles/{id}",
        operationId: "updateCustomRole",
        summary: "Mettre à jour un rôle personnalisé",
        description: "Modifie l'intitulé, la description ou les droits d'un rôle personnalisé",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID du rôle (ex: custom_superviseur_...)")
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Superviseur Principal Tournois"),
                    new OA\Property(property: "description", type: "string", example: "Mise à jour des attributions"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Rôle personnalisé mis à jour")
        ]
    )]
    public function updateCustomRole() {}

    #[OA\Delete(
        path: "/api/owner/team/custom-roles/{id}",
        operationId: "deleteCustomRole",
        summary: "Supprimer un rôle personnalisé",
        description: "Supprime un rôle personnalisé créé par le complexe",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID du rôle personnalisé")
        ],
        responses: [
            new OA\Response(response: 200, description: "Rôle supprimé")
        ]
    )]
    public function deleteCustomRole() {}

    #[OA\Get(
        path: "/api/owner/me/permissions",
        operationId: "getMyPermissions",
        summary: "Obtenir les permissions de l'utilisateur connecté sur son complexe",
        tags: ["Gestion Utilisateurs - Rôles & Droits"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Permissions actuelles")
        ]
    )]
    public function getMyPermissions() {}
}
