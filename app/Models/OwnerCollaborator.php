<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OwnerCollaborator extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'role',
        'permissions',
        'is_active',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active'   => 'boolean',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ─── Permissions & Roles Helpers ────────────────────────────────

    /**
     * Matrice des droits par défaut selon le rôle attribué par le gérant
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            'manager' => [
                'fields:manage',
                'slots:manage',
                'reservations:manage',
                'accounting:view',
                'settings:manage',
                'team:manage',
            ],
            'receptionist' => [
                'slots:manage',
                'reservations:manage',
            ],
            'field_staff' => [
                'slots:manage',
                'reservations:manage',
            ],
            'accountant' => [
                'reservations:manage',
                'accounting:view',
            ],
            default => [
                'reservations:manage',
            ],
        };
    }

    /**
     * Dictionnaire exhaustif des permissions du système avec libellés et descriptions
     */
    public static function availablePermissions(): array
    {
        return [
            [
                'key'         => 'fields:manage',
                'label'       => 'Gestion des Terrains',
                'description' => 'Créer, modifier les informations et ajouter des photos aux terrains.',
                'group'       => 'Infrastructures',
            ],
            [
                'key'         => 'slots:manage',
                'label'       => 'Gestion des Créneaux & Tarifs',
                'description' => 'Définir les horaires, tarifs/heure, promos et bloquer des créneaux.',
                'group'       => 'Planning',
            ],
            [
                'key'         => 'reservations:manage',
                'label'       => 'Gestion des Réservations',
                'description' => 'Consulter les réservations, créer des réservations manuelles et valider les paiements sur place.',
                'group'       => 'Opérations',
            ],
            [
                'key'         => 'accounting:view',
                'label'       => 'Comptabilité & Finances',
                'description' => 'Accéder aux chiffres d\'affaires, commissions plateforme et exporter les relevés CSV.',
                'group'       => 'Finance',
            ],
            [
                'key'         => 'settings:manage',
                'label'       => 'Paramétrage Stratégique',
                'description' => 'Configurer les alertes WhatsApp/SMS, abonnements récurrents, matchmaking et fidélité.',
                'group'       => 'Configuration',
            ],
            [
                'key'         => 'team:manage',
                'label'       => 'Gestion de l\'Équipe',
                'description' => 'Inviter des collaborateurs, leur attribuer des rôles et modifier leurs droits d\'accès.',
                'group'       => 'Administration',
            ],
        ];
    }

    /**
     * Vérifie si le collaborateur possède une permission précise
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->role === 'manager') {
            return true;
        }

        $perms = $this->role === 'custom' && is_array($this->permissions)
            ? $this->permissions
            : self::defaultPermissionsForRole($this->role);

        return in_array($permission, $perms, true);
    }
}
