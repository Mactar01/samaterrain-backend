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
            ->map(function ($collab) use ($owner) {
                return [
                    'id'           => $collab->id,
                    'user_id'      => $collab->user_id,
                    'name'         => $collab->user?->name ?? 'Collaborateur',
                    'email'        => $collab->user?->email ?? '-',
                    'phone'        => $collab->user?->phone ?? '-',
                    'role'         => $collab->role,
                    'role_label'   => $this->getRoleLabel($collab->role, $owner),
                    'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                        ? $collab->permissions
                        : $owner->getRolePermissions($collab->role),
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
     * Matrice des rôles et des droits disponibles (Inspiré d'eyassur)
     */
    public function getPermissionsMatrix(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();
        $customRoles = $owner ? $owner->getCustomRoles() : [];

        $defaultRoles = [
            [
                'role'        => 'manager',
                'label'       => 'Administrateur Complexe',
                'description' => 'Accès complet à toutes les fonctionnalités du complexe (terrains, finances, paramètres, équipe).',
                'permissions' => $owner ? $owner->getRolePermissions('manager') : OwnerCollaborator::defaultPermissionsForRole('manager'),
                'is_system'   => true,
            ],
            [
                'role'        => 'receptionist',
                'label'       => 'Accueil / Réceptionniste',
                'description' => 'Gère les créneaux et les réservations manuelles au comptoir, sans accès financier.',
                'permissions' => $owner ? $owner->getRolePermissions('receptionist') : OwnerCollaborator::defaultPermissionsForRole('receptionist'),
                'is_system'   => true,
            ],
            [
                'role'        => 'field_staff',
                'label'       => 'Responsable Terrain / Arbitre',
                'description' => 'Visualise le planning des matchs du jour et peut bloquer un créneau pour maintenance.',
                'permissions' => $owner ? $owner->getRolePermissions('field_staff') : OwnerCollaborator::defaultPermissionsForRole('field_staff'),
                'is_system'   => true,
            ],
            [
                'role'        => 'accountant',
                'label'       => 'Comptable / Gestionnaire',
                'description' => 'Consulte les recettes, commissions plateforme et télécharge les relevés comptables CSV.',
                'permissions' => $owner ? $owner->getRolePermissions('accountant') : OwnerCollaborator::defaultPermissionsForRole('accountant'),
                'is_system'   => true,
            ],
            [
                'role'        => 'custom',
                'label'       => 'Rôle Sur-Mesure',
                'description' => 'Sélectionnez manuellement chaque droit accordé à ce collaborateur.',
                'permissions' => [],
                'is_system'   => true,
            ],
        ];

        return response()->json([
            'available_permissions' => OwnerCollaborator::availablePermissions(),
            'default_roles'         => $defaultRoles,
            'custom_roles'          => $customRoles,
        ]);
    }

    /**
     * Assigner / modifier les droits d'un rôle (Inspiré d'eyassur assignOrganismRights)
     */
    public function assignRoleRights(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour modifier les droits d\'un rôle.'], 403);
        }

        $validated = $request->validate([
            'role_id'       => 'required|string',
            'permissions'   => 'present|array',
            'permissions.*' => 'string',
        ]);

        $roleId = $validated['role_id'];
        $newPerms = $validated['permissions'];

        $settings = $owner->settings;

        if (str_starts_with($roleId, 'custom_')) {
            $customRoles = $settings['custom_roles'] ?? [];
            $found = false;
            foreach ($customRoles as &$cr) {
                if (($cr['id'] ?? '') === $roleId) {
                    $cr['permissions'] = $newPerms;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $settings['custom_roles'] = $customRoles;
            }
        } else {
            $rolePerms = $settings['role_permissions'] ?? [];
            $rolePerms[$roleId] = $newPerms;
            $settings['role_permissions'] = $rolePerms;
        }

        $owner->settings = $settings;
        $owner->save();

        return response()->json([
            'message'     => 'Droits du rôle mis à jour avec succès.',
            'role_id'     => $roleId,
            'permissions' => $newPerms,
        ]);
    }

    /**
     * Créer un nouveau rôle personnalisé pour le complexe
     */
    public function createCustomRole(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour créer un rôle.'], 403);
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:80',
            'description'   => 'nullable|string|max:200',
            'permissions'   => 'required|array|min:1',
            'permissions.*' => 'string',
        ]);

        $settings = $owner->settings;
        $customRoles = $settings['custom_roles'] ?? [];

        $roleId = 'custom_' . Str::slug($validated['name'], '_') . '_' . substr(md5(uniqid()), 0, 4);

        $newRole = [
            'id'          => $roleId,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? 'Rôle personnalisé du complexe',
            'permissions' => $validated['permissions'],
            'is_system'   => false,
            'created_at'  => now()->format('d/m/Y'),
        ];

        $customRoles[] = $newRole;
        $settings['custom_roles'] = $customRoles;
        $owner->settings = $settings;
        $owner->save();

        return response()->json([
            'message' => 'Nouveau rôle créé avec succès.',
            'role'    => $newRole,
            'roles'   => $customRoles,
        ], 201);
    }

    /**
     * Mettre à jour un rôle personnalisé pour le complexe
     */
    public function updateCustomRole(Request $request, $id)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour modifier un rôle.'], 403);
        }

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:80',
            'description'   => 'nullable|string|max:200',
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'string',
        ]);

        $settings = $owner->settings;
        $customRoles = $settings['custom_roles'] ?? [];
        $updatedRole = null;

        foreach ($customRoles as &$cr) {
            if (($cr['id'] ?? '') === $id) {
                if (isset($validated['name'])) $cr['name'] = $validated['name'];
                if (array_key_exists('description', $validated)) $cr['description'] = $validated['description'];
                if (isset($validated['permissions'])) $cr['permissions'] = $validated['permissions'];
                $updatedRole = $cr;
                break;
            }
        }

        if (!$updatedRole) {
            return response()->json(['message' => 'Rôle personnalisé introuvable.'], 404);
        }

        $settings['custom_roles'] = $customRoles;
        $owner->settings = $settings;
        $owner->save();

        return response()->json([
            'message' => 'Rôle personnalisé mis à jour avec succès.',
            'role'    => $updatedRole,
            'roles'   => $customRoles,
        ]);
    }

    /**
     * Supprimer un rôle personnalisé
     */
    public function deleteCustomRole(Request $request, $id)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Complexe introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('team:manage')) {
            return response()->json(['message' => 'Droits insuffisants.'], 403);
        }

        $settings = $owner->settings;
        $customRoles = $settings['custom_roles'] ?? [];

        $customRoles = array_values(array_filter($customRoles, fn($r) => ($r['id'] ?? '') !== $id));
        $settings['custom_roles'] = $customRoles;
        $owner->settings = $settings;
        $owner->save();

        return response()->json([
            'message' => 'Rôle supprimé avec succès.',
            'roles'   => $customRoles,
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
            'role'        => 'required|string|max:100',
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
                'role_label'   => $this->getRoleLabel($collab->role, $owner),
                'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                    ? $collab->permissions
                    : $owner->getRolePermissions($collab->role),
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
            'role'        => 'sometimes|string|max:100',
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
                'role_label'   => $this->getRoleLabel($collab->role, $owner),
                'permissions'  => $collab->role === 'custom' && is_array($collab->permissions)
                    ? $collab->permissions
                    : $owner->getRolePermissions($collab->role),
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

    private function getRoleLabel(string $role, ?Owner $owner = null): string
    {
        $labels = [
            'owner_master' => 'Gérant Propriétaire (Admin Complexe)',
            'manager'      => 'Administrateur Complexe',
            'receptionist' => 'Accueil / Réceptionniste',
            'field_staff'  => 'Responsable Terrain',
            'accountant'   => 'Comptable / Gestionnaire',
            'custom'       => 'Rôle Sur-Mesure',
        ];

        if (isset($labels[$role])) {
            return $labels[$role];
        }

        if ($owner) {
            foreach ($owner->getCustomRoles() as $cr) {
                if (($cr['id'] ?? '') === $role) {
                    return $cr['name'];
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $role));
    }
}
