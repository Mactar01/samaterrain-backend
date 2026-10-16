<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Owner;
use App\Models\OwnerCollaborator;

class OwnerTeamController extends Controller
{
    /**
     * Liste des collaborateurs du complexe pour le gérant connecté
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        // Vérification de permission
        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Vous n\'avez pas les droits pour gérer l\'équipe.'], 403);
        }

        // Gérant principal (fondateur du complexe)
        $primaryOwnerUser = $owner->user;
        $primaryMember = [
            'id'           => 0, // ID virtuel pour le gérant principal
            'user_id'      => $primaryOwnerUser->id,
            'name'         => $primaryOwnerUser->name,
            'email'        => $primaryOwnerUser->email,
            'phone'        => $primaryOwnerUser->phone,
            'role'         => 'owner_master',
            'role_label'   => 'Gérant Propriétaire (Principal)',
            'permissions'  => array_column(OwnerCollaborator::availablePermissions(), 'key'),
            'is_active'    => true,
            'is_primary'   => true,
            'created_at'   => $owner->created_at->format('d/m/Y'),
        ];

        // Collaborateurs rattachés
        $collaborators = OwnerCollaborator::with('user')
            ->where('owner_id', $owner->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($collab) {
                return [
                    'id'           => $collab->id,
                    'user_id'      => $collab->user_id,
                    'name'         => $collab->user?->name ?? 'Collaborateur',
                    'email'        => $collab->user?->email ?? '-',
                    'phone'        => $collab->user?->phone ?? '-',
                    'role'         => $collab->role,
                    'role_label'   => $this->getRoleLabel($collab->role),
                    'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                        ? $collab->permissions
                        : OwnerCollaborator::defaultPermissionsForRole($collab->role),
                    'is_active'    => $collab->is_active,
                    'is_primary'   => false,
                    'created_at'   => $collab->created_at->format('d/m/Y'),
                ];
            });

        return response()->json([
            'owner'         => [
                'id'            => $owner->id,
                'business_name' => $owner->business_name,
            ],
            'members'       => array_merge([$primaryMember], $collaborators->toArray()),
            'total_members' => count($collaborators) + 1,
        ]);
    }

    /**
     * Matrice des rôles et des droits disponibles
     */
    public function getPermissionsMatrix()
    {
        return response()->json([
            'available_permissions' => OwnerCollaborator::availablePermissions(),
            'default_roles'         => [
                [
                    'role'        => 'manager',
                    'label'       => 'Administrateur Complexe',
                    'description' => 'Accès complet à toutes les fonctionnalités du complexe (terrains, finances, paramètres, équipe).',
                    'permissions' => OwnerCollaborator::defaultPermissionsForRole('manager'),
                ],
                [
                    'role'        => 'receptionist',
                    'label'       => 'Accueil / Réceptionniste',
                    'description' => 'Gère les créneaux et les réservations manuelles au comptoir, sans accès financier.',
                    'permissions' => OwnerCollaborator::defaultPermissionsForRole('receptionist'),
                ],
                [
                    'role'        => 'field_staff',
                    'label'       => 'Responsable Terrain / Arbitre',
                    'description' => 'Visualise le planning des matchs du jour et peut bloquer un créneau pour maintenance.',
                    'permissions' => OwnerCollaborator::defaultPermissionsForRole('field_staff'),
                ],
                [
                    'role'        => 'accountant',
                    'label'       => 'Comptable / Gestionnaire',
                    'description' => 'Consulte les recettes, commissions plateforme et télécharge les relevés comptables CSV.',
                    'permissions' => OwnerCollaborator::defaultPermissionsForRole('accountant'),
                ],
                [
                    'role'        => 'custom',
                    'label'       => 'Rôle Sur-Mesure',
                    'description' => 'Sélectionnez manuellement chaque droit accordé à ce collaborateur.',
                    'permissions' => [],
                ],
            ],
        ]);
    }

    /**
     * Droits de l'utilisateur actuellement connecté
     */
    public function getMyPermissions(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json([
                'is_super_admin' => true,
                'is_owner'       => true,
                'role'           => 'admin',
                'role_label'     => 'Super Administrateur Plateforme',
                'permissions'    => array_column(OwnerCollaborator::availablePermissions(), 'key'),
            ]);
        }

        $owner = $user->getOwner();
        $isPrimary = $user->owner !== null;

        if ($isPrimary) {
            return response()->json([
                'is_super_admin' => false,
                'is_owner'       => true,
                'role'           => 'owner_master',
                'role_label'     => 'Gérant Propriétaire (Principal)',
                'permissions'    => array_column(OwnerCollaborator::availablePermissions(), 'key'),
            ]);
        }

        $collab = OwnerCollaborator::where('user_id', $user->id)->where('is_active', true)->first();

        if (!$collab) {
            return response()->json([
                'is_super_admin' => false,
                'is_owner'       => false,
                'role'           => $user->role,
                'role_label'     => 'Utilisateur',
                'permissions'    => [],
            ]);
        }

        $perms = $collab->role === 'custom' && is_array($collab->permissions)
            ? $collab->permissions
            : OwnerCollaborator::defaultPermissionsForRole($collab->role);

        return response()->json([
            'is_super_admin' => false,
            'is_owner'       => true,
            'role'           => $collab->role,
            'role_label'     => $this->getRoleLabel($collab->role),
            'permissions'    => $perms,
        ]);
    }

    /**
     * Ajouter un nouveau collaborateur dans l'équipe du complexe
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour ajouter un membre.'], 403);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|max:150',
            'phone'       => 'nullable|string|max:25',
            'role'        => 'required|string|in:manager,receptionist,field_staff,accountant,custom',
            'permissions' => 'nullable|array',
            'password'    => 'nullable|string|min:6',
        ]);

        // Vérifier si le compte utilisateur existe déjà
        $targetUser = User::where('email', $validated['email'])->first();

        if ($targetUser) {
            // Vérifier s'il est déjà membre de ce complexe
            $exists = OwnerCollaborator::where('owner_id', $owner->id)
                ->where('user_id', $targetUser->id)
                ->exists();

            if ($exists || ($owner->user_id === $targetUser->id)) {
                return response()->json([
                    'message' => 'Cet utilisateur fait déjà partie de l\'équipe de ce complexe.'
                ], 422);
            }
        } else {
            // Créer un nouvel utilisateur avec rôle 'owner'
            $rawPassword = $validated['password'] ?: 'Pass1234!';
            $targetUser = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'] ?? null,
                'password'  => Hash::make($rawPassword),
                'role'      => 'owner',
                'is_active' => true,
            ]);
        }

        // Créer l'association collaborateur
        $collab = OwnerCollaborator::create([
            'owner_id'    => $owner->id,
            'user_id'     => $targetUser->id,
            'role'        => $validated['role'],
            'permissions' => $validated['role'] === 'custom' ? ($validated['permissions'] ?? []) : null,
            'is_active'   => true,
            'invited_by'  => $user->id,
        ]);

        return response()->json([
            'message'      => 'Collaborateur ajouté avec succès à votre équipe.',
            'collaborator' => [
                'id'           => $collab->id,
                'user_id'      => $targetUser->id,
                'name'         => $targetUser->name,
                'email'        => $targetUser->email,
                'phone'        => $targetUser->phone,
                'role'         => $collab->role,
                'role_label'   => $this->getRoleLabel($collab->role),
                'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                    ? $collab->permissions
                    : OwnerCollaborator::defaultPermissionsForRole($collab->role),
                'is_active'    => $collab->is_active,
                'is_primary'   => false,
                'created_at'   => $collab->created_at->format('d/m/Y'),
            ]
        ], 201);
    }

    /**
     * Modifier le rôle ou les droits d'un collaborateur
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour modifier un membre.'], 403);
        }

        $collab = OwnerCollaborator::with('user')
            ->where('id', $id)
            ->where('owner_id', $owner->id)
            ->firstOrFail();

        $validated = $request->validate([
            'role'        => 'sometimes|string|in:manager,receptionist,field_staff,accountant,custom',
            'permissions' => 'nullable|array',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($validated['role'])) {
            $collab->role = $validated['role'];
            $collab->permissions = $validated['role'] === 'custom' ? ($validated['permissions'] ?? []) : null;
        }

        if (isset($validated['is_active'])) {
            $collab->is_active = $validated['is_active'];
        }

        $collab->save();

        return response()->json([
            'message'      => 'Droits et rôle du collaborateur mis à jour.',
            'collaborator' => [
                'id'           => $collab->id,
                'user_id'      => $collab->user_id,
                'name'         => $collab->user?->name,
                'email'        => $collab->user?->email,
                'phone'        => $collab->user?->phone,
                'role'         => $collab->role,
                'role_label'   => $this->getRoleLabel($collab->role),
                'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                    ? $collab->permissions
                    : OwnerCollaborator::defaultPermissionsForRole($collab->role),
                'is_active'    => $collab->is_active,
                'is_primary'   => false,
                'created_at'   => $collab->created_at->format('d/m/Y'),
            ]
        ]);
    }

    /**
     * Retirer un collaborateur de l'équipe du complexe
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour supprimer un membre.'], 403);
        }

        $collab = OwnerCollaborator::where('id', $id)
            ->where('owner_id', $owner->id)
            ->firstOrFail();

        $collab->delete();

        return response()->json([
            'message' => 'Le collaborateur a été retiré de l\'équipe du complexe.'
        ]);
    }

    private function getRoleLabel(string $role): string
    {
        return match ($role) {
            'manager'      => 'Administrateur Complexe',
            'receptionist' => 'Accueil / Réceptionniste',
            'field_staff'  => 'Responsable Terrain',
            'accountant'   => 'Comptable / Gestionnaire',
            'custom'       => 'Rôle Sur-Mesure',
            default        => 'Collaborateur',
        };
    }
}
