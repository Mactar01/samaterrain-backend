<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Field;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerSettingsController extends Controller
{
    /**
     * Récupère la configuration actuelle des modules du gérant
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Profil propriétaire introuvable.'], 404);
        }

        $settings = $owner->settings;

        // Si le numéro WhatsApp n'est pas encore renseigné dans les settings, pré-remplir avec le téléphone de l'utilisateur
        if (empty($settings['whatsapp_sms']['phone_number']) && !empty($user->phone)) {
            $settings['whatsapp_sms']['phone_number'] = $user->phone;
        }

        return response()->json([
            'settings'        => $settings,
            'business_name'   => $owner->business_name,
            'commission_rate' => (float)$owner->commission_rate,
        ]);
    }

    /**
     * Met à jour les paramètres de configuration du gérant
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Profil propriétaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('settings:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour modifier les paramètres.'], 403);
        }

        $validated = $request->validate([
            'settings'                                => 'required|array',
            'settings.whatsapp_sms'                   => 'nullable|array',
            'settings.whatsapp_sms.whatsapp_enabled'  => 'nullable|boolean',
            'settings.whatsapp_sms.sms_enabled'       => 'nullable|boolean',
            'settings.whatsapp_sms.phone_number'      => 'nullable|string|max:30',
            'settings.whatsapp_sms.notify_on_new_booking' => 'nullable|boolean',
            'settings.whatsapp_sms.notify_reminder_hours' => 'nullable|integer|min:0|max:48',
            'settings.whatsapp_sms.custom_welcome_message' => 'nullable|string|max:1000',

            'settings.subscriptions'                  => 'nullable|array',
            'settings.subscriptions.enabled'          => 'nullable|boolean',
            'settings.subscriptions.discount_quarterly' => 'nullable|integer|min:0|max:100',
            'settings.subscriptions.discount_semiannual' => 'nullable|integer|min:0|max:100',
            'settings.subscriptions.discount_annual'  => 'nullable|integer|min:0|max:100',
            'settings.subscriptions.allow_priority_booking' => 'nullable|boolean',
            'settings.subscriptions.corporate_billing' => 'nullable|boolean',

            'settings.matchmaking'                    => 'nullable|array',
            'settings.matchmaking.enabled'            => 'nullable|boolean',
            'settings.matchmaking.min_players_required' => 'nullable|integer|min:2|max:30',
            'settings.matchmaking.max_players_allowed'  => 'nullable|integer|min:2|max:30',
            'settings.matchmaking.auto_split_cost'    => 'nullable|boolean',
            'settings.matchmaking.auto_cancel_hours'  => 'nullable|integer|min:0|max:48',

            'settings.loyalty'                        => 'nullable|array',
            'settings.loyalty.loyalty_enabled'        => 'nullable|boolean',
            'settings.loyalty.bookings_required'      => 'nullable|integer|min:1|max:100',
            'settings.loyalty.reward_discount_fcfa'   => 'nullable|integer|min:0',
            'settings.loyalty.referral_enabled'       => 'nullable|boolean',
            'settings.loyalty.referral_reward_fcfa'   => 'nullable|integer|min:0',
            'settings.loyalty.custom_referral_code'   => 'nullable|string|max:50',

            'settings.accounting'                     => 'nullable|array',
            'settings.accounting.default_export_format' => 'nullable|string|in:csv,excel',
            'settings.accounting.include_customer_phone' => 'nullable|boolean',
        ]);

        $current = $owner->settings;
        $merged = array_replace_recursive($current, $validated['settings']);

        $owner->settings = $merged;
        $owner->save();

        return response()->json([
            'message'  => 'Paramètres enregistrés avec succès.',
            'settings' => $owner->settings,
        ]);
    }

    /**
     * Module 6 : Données du rapport comptable & financier
     */
    public function getAccountingReport(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Profil propriétaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('accounting:view')) {
            return response()->json(['message' => 'Droits insuffisants pour consulter la comptabilité.'], 403);
        }

        $year = (int)$request->input('year', date('Y'));
        $month = $request->has('month') && $request->input('month') !== 'all' ? (int)$request->input('month') : null;
        $fieldId = $request->input('field_id');

        $query = Reservation::with(['user', 'field', 'timeSlot', 'payment'])
            ->whereHas('field', function ($q) use ($owner, $fieldId) {
                $q->where('owner_id', $owner->id);
                if ($fieldId) {
                    $q->where('id', $fieldId);
                }
            });

        // Filtrer par année et mois (sur la date du créneau ou la date de création)
        if ($month) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate   = Carbon::createFromDate($year, 12, 31)->endOfYear();
        }

        $query->whereBetween('created_at', [$startDate, $endDate]);

        $reservations = $query->orderBy('created_at', 'desc')->get();

        // Statistiques financières
        $totalBookings = $reservations->count();
        $confirmedReservations = $reservations->filter(fn($r) => in_array($r->status, ['confirmed', 'completed']));
        
        $grossRevenue = $confirmedReservations->sum('total_price');
        $commissionTotal = $confirmedReservations->sum('commission');
        $netPayout = $grossRevenue - $commissionTotal;

        // Répartition par terrain
        $fieldsSummary = [];
        $ownerFields = Field::where('owner_id', $owner->id)->get();
        foreach ($ownerFields as $f) {
            $fReservations = $confirmedReservations->where('field_id', $f->id);
            $fieldsSummary[] = [
                'field_id'    => $f->id,
                'name'        => $f->name,
                'count'       => $fReservations->count(),
                'revenue'     => (float)$fReservations->sum('total_price'),
            ];
        }

        // Évolution mensuelle pour le graphique
        $monthlyRevenue = array_fill(1, 12, 0);
        foreach ($confirmedReservations as $res) {
            $m = (int)Carbon::parse($res->created_at)->format('n');
            $monthlyRevenue[$m] += (float)$res->total_price;
        }

        return response()->json([
            'year'                   => $year,
            'month'                  => $month,
            'commission_rate'        => (float)$owner->commission_rate,
            'total_bookings'         => $totalBookings,
            'confirmed_bookings'     => $confirmedReservations->count(),
            'cancelled_bookings'     => $reservations->where('status', 'cancelled')->count(),
            'total_gross_revenue'    => (float)$grossRevenue,
            'total_commission'       => (float)$commissionTotal,
            'net_payout'             => (float)$netPayout,
            'fields_summary'         => $fieldsSummary,
            'monthly_revenue'        => array_values($monthlyRevenue),
            'recent_reservations'    => $reservations->take(25)->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'client_name'  => $r->guest_name ?: ($r->user?->name ?? 'Client'),
                    'client_phone' => $r->guest_phone ?: ($r->user?->phone ?? '-'),
                    'field_name'   => $r->field?->name ?? 'Terrain',
                    'date'         => $r->timeSlot ? Carbon::parse($r->timeSlot->date)->format('d/m/Y') : Carbon::parse($r->created_at)->format('d/m/Y'),
                    'time_slot'    => $r->timeSlot ? ($r->timeSlot->start_time . ' - ' . $r->timeSlot->end_time) : '-',
                    'total_price'  => (float)$r->total_price,
                    'commission'   => (float)$r->commission,
                    'net_amount'   => (float)($r->total_price - $r->commission),
                    'status'       => $r->status,
                    'created_at'   => Carbon::parse($r->created_at)->format('d/m/Y H:i'),
                ];
            }),
        ]);
    }

    /**
     * Module 6 : Téléchargement du relevé comptable au format CSV
     */
    public function exportAccountingCsv(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();

        if (!$owner) {
            return response()->json(['message' => 'Profil propriétaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('accounting:view')) {
            return response()->json(['message' => 'Droits insuffisants pour exporter la comptabilité.'], 403);
        }

        $year = (int)$request->input('year', date('Y'));
        $month = $request->has('month') && $request->input('month') !== 'all' ? (int)$request->input('month') : null;

        $query = Reservation::with(['user', 'field', 'timeSlot', 'payment'])
            ->whereHas('field', function ($q) use ($owner) {
                $q->where('owner_id', $owner->id);
            });

        if ($month) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate   = Carbon::createFromDate($year, 12, 31)->endOfYear();
        }

        $query->whereBetween('created_at', [$startDate, $endDate]);
        $reservations = $query->orderBy('created_at', 'desc')->get();

        $filename = 'releve_comptable_' . ($owner->business_name ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $owner->business_name) : 'owner') . '_' . $year . ($month ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($reservations) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour affichage parfait des accents sous Microsoft Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Entêtes CSV (séparateur point-virgule)
            fputcsv($handle, [
                'ID Réservation',
                'Date Enregistrement',
                'Client',
                'Téléphone',
                'Terrain',
                'Date du Match',
                'Créneau Horaire',
                'Statut',
                'Montant Brut (FCFA)',
                'Commission Plateforme (FCFA)',
                'Net Loueur (FCFA)',
            ], ';');

            foreach ($reservations as $r) {
                $clientName = $r->guest_name ?: ($r->user?->name ?? 'Client');
                $clientPhone = $r->guest_phone ?: ($r->user?->phone ?? '-');
                $fieldName = $r->field?->name ?? 'Terrain';
                $matchDate = $r->timeSlot ? Carbon::parse($r->timeSlot->date)->format('d/m/Y') : '-';
                $slotTime = $r->timeSlot ? ($r->timeSlot->start_time . ' - ' . $r->timeSlot->end_time) : '-';
                $net = $r->total_price - $r->commission;

                fputcsv($handle, [
                    '#' . $r->id,
                    Carbon::parse($r->created_at)->format('d/m/Y H:i'),
                    $clientName,
                    $clientPhone,
                    $fieldName,
                    $matchDate,
                    $slotTime,
                    $r->status,
                    number_format((float)$r->total_price, 0, ',', ' '),
                    number_format((float)$r->commission, 0, ',', ' '),
                    number_format((float)$net, 0, ',', ' '),
                ], ';');
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
