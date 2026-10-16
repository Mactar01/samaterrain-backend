<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'address',
        'siret',
        'verified_at',
        'commission_rate',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'verified_at'     => 'datetime',
            'commission_rate' => 'decimal:2',
            'settings'        => 'array',
        ];
    }

    /**
     * Default settings for the 5 manager modules:
     * 1: WhatsApp & SMS Automatiques
     * 2: Abonnements Récurrents
     * 4: Matchmaking & Matchs Ouverts
     * 5: Fidélité & Parrainage
     * 6: Export Comptable & Rapports
     */
    public static function defaultSettings(): array
    {
        return [
            'whatsapp_sms' => [
                'whatsapp_enabled'        => true,
                'sms_enabled'             => true,
                'phone_number'            => '',
                'notify_on_new_booking'   => true,
                'notify_reminder_hours'   => 2,
                'custom_welcome_message'  => 'Bonjour {client_name}, votre réservation pour le terrain {field_name} le {date} à {time} est confirmée ! À bientôt chez {business_name}.',
            ],
            'subscriptions' => [
                'enabled'                 => true,
                'discount_quarterly'      => 10,
                'discount_semiannual'     => 15,
                'discount_annual'         => 20,
                'allow_priority_booking'  => true,
                'corporate_billing'       => true,
                'billing_frequency'       => 'monthly',
            ],
            'matchmaking' => [
                'enabled'                 => true,
                'min_players_required'    => 10,
                'max_players_allowed'     => 14,
                'auto_split_cost'         => true,
                'auto_cancel_hours'       => 3,
                'allow_guest_join'        => true,
            ],
            'loyalty' => [
                'loyalty_enabled'         => true,
                'bookings_required'       => 5,
                'reward_discount_fcfa'    => 5000,
                'referral_enabled'        => true,
                'referral_reward_fcfa'    => 2000,
                'custom_referral_code'    => '',
            ],
            'accounting' => [
                'default_export_format'   => 'csv',
                'include_customer_phone'  => true,
                'fiscal_year_start_month' => 1,
            ],
        ];
    }

    /**
     * Accessor: deep merge stored settings with defaults so new keys are always present
     */
    public function getSettingsAttribute($value): array
    {
        $stored = is_array($value) ? $value : (json_decode($value ?? '{}', true) ?: []);
        $defaults = self::defaultSettings();

        return array_replace_recursive($defaults, $stored);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }

    public function collaborators()
    {
        return $this->hasMany(OwnerCollaborator::class);
    }
}
