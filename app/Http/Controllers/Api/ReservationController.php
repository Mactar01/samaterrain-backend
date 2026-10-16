<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Owner;
use App\Http\Requests\StoreReservationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ReservationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Liste des réservations du joueur connecté
     */
    public function index(Request $request)
    {
        $reservations = $request->user()->reservations()
            ->with(['field', 'timeSlot', 'payment'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($reservations);
    }

    /**
     * Liste des réservations pour le loueur connecté
     */
    public function ownerIndex(Request $request)
    {
        $user = $request->user();
        $owner = $user->getOwner();
        if (!$owner) return response()->json([]);

        if (!$user->hasOwnerPermission('reservations:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour voir les réservations.'], 403);
        }
        
        $reservations = Reservation::with(['user', 'field', 'timeSlot', 'payment'])
            ->whereHas('field', function ($query) use ($owner) {
                $query->where('owner_id', $owner->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($reservations);
    }

    /**
     * Création manuelle d'une réservation par le gérant (ex: réservation par téléphone)
     */
    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'time_slot_id' => 'required|exists:time_slots,id',
            'player_name'  => 'required|string|max:100',
            'player_phone' => 'required|string|max:20',
        ]);

        $user = $request->user();
        $owner = $user->getOwner();
        if (!$owner) {
            return response()->json(['message' => 'Complexe partenaire introuvable.'], 404);
        }

        if (!$user->hasOwnerPermission('reservations:manage')) {
            return response()->json(['message' => 'Droits insuffisants pour enregistrer une réservation.'], 403);
        }

        // Vérifier que le créneau appartient bien au gérant connecté
        $slot = TimeSlot::with('field')->findOrFail($data['time_slot_id']);
        if ($slot->field->owner_id !== $owner->id) {
            abort(403, 'Non autorisé à modifier ce terrain.');
        }

        $price = $slot->effectivePrice();
        $commission = $price * ($owner->commission_rate / 100);

        // Le client n'a pas forcément de compte : on note son nom et son numéro sur la réservation,
        // rattachée au compte du gérant. (Avant, un compte joueur était créé avec ce numéro,
        // ce qui empêchait la vraie personne de s'inscrire ensuite.)
        $res = null;
        DB::transaction(function () use ($slot, $request, $data, $price, $commission, &$res) {
            $locked = TimeSlot::where('id', $slot->id)->lockForUpdate()->first();
            if (!$locked->isAvailable()) {
                abort(409, "Ce créneau n'est pas disponible.");
            }

            $res = Reservation::create([
                'user_id'      => $request->user()->id,
                'guest_name'   => $data['player_name'],
                'guest_phone'  => $data['player_phone'],
                'field_id'     => $slot->field_id,
                'time_slot_id' => $slot->id,
                'status'       => 'confirmed',
                'total_price'  => $price,
                'commission'   => $commission,
                'notes'        => 'Réservation manuelle par le gérant (Téléphone)',
            ]);

            $locked->update(['status' => 'reserved']);
        });

        if ($res) {
            try {
                $request->user()->notify(new \App\Notifications\NewReservationNotification($res));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Erreur notification manuelle: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Réservation manuelle ajoutée avec succès.',
        ], 201);
    }

    /**
     * Détails d'une réservation
     */
    public function show(Request $request, $id)
    {
        $reservation = Reservation::with(['field', 'timeSlot', 'payment'])->findOrFail($id);
        $this->authorize('view', $reservation);

        return response()->json($reservation);
    }

    /**
     * Créer une réservation (Anti double-booking via transactions & locks)
     */
    public function store(StoreReservationRequest $request)
    {
        try {
            $reservation = DB::transaction(function () use ($request) {
                // VERROUILLAGE PESSIMISTE : on verrouille la ligne du créneau en BDD
                // Personne d'autre ne peut lire/modifier ce créneau jusqu'à la fin de la transaction.
                $slot = TimeSlot::where('id', $request->time_slot_id)->lockForUpdate()->firstOrFail();

                if (!$slot->isAvailable()) {
                    // Annulation de la transaction, le créneau est déjà pris !
                    abort(409, "Ce créneau n'est plus disponible.");
                }

                // Créneau déjà commencé ou terrain masqué par son loueur : pas réservable
                $slotStart = \Carbon\Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->start_time);
                if ($slotStart->isPast() || !$slot->field->is_active) {
                    abort(409, "Ce créneau n'est plus disponible.");
                }

                $user = $request->user();

                // Le joueur retouche un créneau qu'il a déjà en attente : on reprend la même réservation
                $existing = Reservation::where('user_id', $user->id)
                    ->where('time_slot_id', $slot->id)
                    ->where('status', 'pending')
                    ->first();
                if ($existing) {
                    return $existing;
                }

                // Évite d'accumuler des réservations jamais payées
                $pendingCount = Reservation::where('user_id', $user->id)->where('status', 'pending')->count();
                if ($pendingCount >= 3) {
                    abort(429, 'Vous avez déjà 3 réservations en attente de paiement. Payez-les ou annulez-les avant d\'en faire une nouvelle.');
                }
                $field = $slot->field;
                $price = $slot->effectivePrice();
                
                // Calcul de la commission du loueur (ex: 10%)
                $owner = $field->owner;
                $commission = $price * ($owner->commission_rate / 100);

                // 1. Créer la réservation en 'pending'
                $reservation = Reservation::create([
                    'user_id'      => $user->id,
                    'field_id'     => $field->id,
                    'time_slot_id' => $slot->id,
                    'status'       => 'pending',
                    'total_price'  => $price,
                    'commission'   => $commission,
                    'notes'        => $request->notes,
                ]);

                // On NE met PAS le créneau en 'reserved' ici car il n'a pas encore payé l'acompte.
                // Il restera 'available' jusqu'au paiement.

                return $reservation;
            });

            return response()->json([
                'message'     => 'Réservation mise en attente de paiement.',
                'reservation' => $reservation->load(['field', 'timeSlot'])
            ], 201);

        } catch (QueryException $e) {
            // Sécurité niveau 2 : La base de données a rejeté l'insertion (Unique Index)
            return response()->json([
                'message' => 'Conflit de réservation. Le créneau a été réservé à la même milliseconde par un autre joueur.'
            ], 409);
        } catch (\Exception $e) {
            // Récupère l'abort(409)
            if ($e->getCode() == 409 || $e->getMessage() == 'Ce créneau n\'est plus disponible.') {
                return response()->json(['message' => 'Ce créneau n\'est plus disponible.'], 409);
            }
            throw $e;
        }
    }

    /**
     * Annuler une réservation
     */
    public function cancel(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('cancel', $reservation);

        if ($reservation->status === 'cancelled') {
            return response()->json(['message' => 'Cette réservation est déjà annulée.'], 400);
        }

        DB::transaction(function () use ($reservation, $request) {
            $isOwner = $request->user()->isOwner();
            $reason = $isOwner ? 'Annulée par le gérant' : 'Annulée par le joueur';

            $reservation->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancel_reason' => $reason
            ]);

            if ($reservation->timeSlot) {
                $reservation->timeSlot->update(['status' => 'available']);
            }
        });

        return response()->json(['message' => 'Réservation annulée avec succès.']);
    }
    public function confirm(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('confirm', $reservation);

        if (!$reservation->isPending()) {
            return response()->json(['message' => "La réservation n'est pas en attente."], 400);
        }

        // Comme après un paiement : le créneau passe en 'reserved' pour bloquer les autres joueurs.
        $confirmed = DB::transaction(function () use ($reservation) {
            $slot = TimeSlot::where('id', $reservation->time_slot_id)->lockForUpdate()->first();
            if ($slot && !$slot->isAvailable()) {
                return false;
            }

            $reservation->update(['status' => 'confirmed']);
            $slot?->update(['status' => 'reserved']);
            return true;
        });

        if (!$confirmed) {
            return response()->json(['message' => "Ce créneau n'est plus disponible (déjà réservé ou bloqué)."], 409);
        }

        return response()->json([
            'message' => 'Réservation confirmée.',
            'reservation' => $reservation
        ]);
    }
}




