<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Payments\PaymentManager;
use App\Services\WhatsAppService;
use App\Notifications\NewReservationNotification;

class PaymentController extends Controller
{
    /**
     * Initialise un paiement via le manager unifié
     */
    public function pay(Request $request, $reservationId, PaymentManager $paymentManager)
    {
        $request->validate([
            'method'       => 'required|in:wave,orange_money,free_money,card,cash',
            'phone'        => 'required_unless:method,card,cash|string',
            'payment_type' => 'required|in:full,half,custom',
            'custom_amount'=> 'required_if:payment_type,custom|numeric|min:0',
        ]);

        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Cette réservation ne peut plus être payée.'], 400);
        }

        $totalPrice = $reservation->total_price;
        $minAmount  = $totalPrice / 2;

        if ($request->payment_type === 'custom') {
            $amountToPay = (float) $request->custom_amount;
            if ($amountToPay < $minAmount) {
                return response()->json(['message' => "Le montant minimum est de {$minAmount} FCFA."], 422);
            }
            if ($amountToPay > $totalPrice) {
                $amountToPay = $totalPrice;
            }
        } elseif ($request->payment_type === 'half') {
            $amountToPay = $minAmount;
        } else {
            $amountToPay = $totalPrice;
        }

        try {
            // Utilisation de notre propre agrégateur (Strategy Pattern)
            $gateway = $paymentManager->driver($request->method);
            $invoice = $gateway->initiatePayment($reservation, $amountToPay, $request->phone);

            if (!$invoice['success']) {
                return response()->json(['message' => $invoice['message'] ?? 'Erreur lors de l\'initialisation du paiement.'], 500);
            }

            // On enregistre une tentative de paiement "pending"
            \App\Models\Payment::create([
                'reservation_id'  => $reservation->id,
                'amount'          => $amountToPay,
                'method'          => $request->method,
                'status'          => 'pending',
                'transaction_ref' => $invoice['transaction_ref'],
                'metadata'        => json_encode(['phone' => $request->phone, 'type' => $request->payment_type])
            ]);

            return response()->json([
                'message' => 'Redirection vers la plateforme de paiement.',
                'payment_url' => $invoice['url']
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Webhook appelé par WAVE
     */
    public function waveWebhook(Request $request)
    {
        if (!$this->isAuthenticWaveWebhook($request)) {
            Log::warning('Webhook Wave refusé : signature absente ou invalide', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        Log::info('WAVE Webhook Received: ', $request->all());

        $status = $request->input('status'); // Adapter selon le vrai payload de Wave
        $transactionRef = $request->input('transaction_ref'); // Adapter selon Wave

        if ($status !== 'success' && $status !== 'completed') {
            return response()->json(['status' => 'ignored']);
        }

        return $this->processSuccessfulPayment($transactionRef);
    }

    /**
     * Webhook appelé par ORANGE MONEY
     */
    public function orangeMoneyWebhook(Request $request)
    {
        $transactionRef = $request->input('pay_token'); // Souvent pay_token chez OM

        if (!$this->isAuthenticOrangeMoneyWebhook($request, $transactionRef)) {
            Log::warning('Webhook Orange Money refusé : notif_token absent ou invalide', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        Log::info('ORANGE MONEY Webhook Received: ', $request->all());

        $status = $request->input('status'); // Adapter selon Orange Money

        if ($status !== 'SUCCESS') {
            return response()->json(['status' => 'ignored']);
        }

        return $this->processSuccessfulPayment($transactionRef);
    }

    /**
     * Mode simulation (sans vraie passerelle) : webhooks non signés acceptés,
     * uniquement en dev / preprod ET si PAYMENTS_SIMULATION=true. Jamais en production.
     */
    private function simulationAllowed(): bool
    {
        return app()->environment(['local', 'develop', 'development', 'preprod', 'staging']) && config('services.payments.simulation') === true;
    }

    /**
     * Wave signe chaque webhook : en-tête "Wave-Signature: t=<timestamp>,v1=<hmac>"
     * avec hmac = HMAC-SHA256(secret, timestamp . corps brut).
     * (Format à confirmer avec la documentation Wave Business au moment de l'intégration.)
     */
    private function isAuthenticWaveWebhook(Request $request): bool
    {
        $secret = config('services.wave.webhook_secret');
        if (empty($secret)) {
            return $this->simulationAllowed();
        }

        $header = (string) $request->header('Wave-Signature', '');
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') $timestamp = $value;
            if ($key === 'v1' && $value) $signatures[] = $value;
        }

        // Refuse les vieux messages rejoués (plus de 5 minutes)
        if (!$timestamp || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $request->getContent(), $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Orange Money renvoie le notif_token propre à la transaction, reçu à l'initialisation
     * du paiement et stocké dans les métadonnées du Payment.
     */
    private function isAuthenticOrangeMoneyWebhook(Request $request, ?string $transactionRef): bool
    {
        $payment = $transactionRef ? Payment::where('transaction_ref', $transactionRef)->first() : null;
        $metadata = $payment ? (is_array($payment->metadata) ? $payment->metadata : json_decode($payment->metadata ?? '{}', true)) : [];
        $expected = $metadata['notif_token'] ?? null;

        if (empty($expected)) {
            return $this->simulationAllowed();
        }

        return hash_equals((string) $expected, (string) $request->input('notif_token', ''));
    }

    /**
     * Logique commune de validation du paiement
     */
    protected function processSuccessfulPayment($transactionRef)
    {
        $payment = Payment::where('transaction_ref', $transactionRef)->where('status', 'pending')->first();

        if (!$payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $reservation = $payment->reservation;

        $confirmed = DB::transaction(function () use ($reservation, $payment) {
            $slot = \App\Models\TimeSlot::where('id', $reservation->time_slot_id)->lockForUpdate()->first();

            $payment->update([
                'status' => 'completed',
                'paid_at' => now()
            ]);

            // Paiement arrivé alors que la réservation a été annulée, ou que le créneau
            // a été pris entre-temps : on garde la trace de l'argent reçu sans double réservation.
            if ($reservation->status === 'cancelled' || ($slot && !$slot->isAvailable())) {
                return false;
            }

            $reservation->update(['status' => 'confirmed']);
            if ($slot) $slot->update(['status' => 'reserved']);
            return true;
        });

        if (!$confirmed) {
            Log::warning("Paiement {$payment->transaction_ref} reçu mais réservation #{$reservation->id} non confirmable : remboursement à traiter.");
            return response()->json(['status' => 'refund_required']);
        }

        // Notifications
        $ownerUser = $reservation->field->owner->user ?? null;
        if ($ownerUser) {
            try {
                $ownerUser->notify(new NewReservationNotification($reservation));
            } catch (\Exception $e) {
                Log::error('Erreur notification email: ' . $e->getMessage());
            }

            try {
                $whatsappService = new WhatsAppService();
                $whatsappService->sendMessage(
                    $ownerUser->phone ?? '+221000000000', 
                    "✅ *NOUVELLE RÉSERVATION* \nTerrain: {$reservation->field->name}\nMontant payé: {$payment->amount} FCFA"
                );

                if ($reservation->user->phone) {
                    $whatsappService->sendMessage(
                        $reservation->user->phone, 
                        "✅ *RÉSERVATION CONFIRMÉE* \nTerrain: {$reservation->field->name}\nMontant payé: {$payment->amount} FCFA"
                    );
                }
            } catch (\Exception $e) {
                Log::error('Erreur notification WhatsApp: ' . $e->getMessage());
            }
        }

        return response()->json(['status' => 'success']);
    }
}
