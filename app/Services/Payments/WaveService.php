<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use Illuminate\Support\Str;

class WaveService implements PaymentGatewayInterface
{
    public function initiatePayment(Reservation $reservation, float $amount, ?string $phone = null): array
    {
        // TODO: Insérer ici le code d'appel à l'API Wave Business (CURL / Http::post)
        // en utilisant vos futures clés (WAVE_API_KEY, WAVE_MERCHANT_ID).
        
        // Simulation d'une référence unique générée par Wave
        $transactionRef = 'WAVE-' . strtoupper(Str::random(10));
        
        // Simulation d'une URL de paiement retournée par Wave
        $paymentUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/payment-simulation?ref=' . $transactionRef . '&method=wave&amount=' . $amount;

        return [
            'success'         => true,
            'url'             => $paymentUrl,
            'transaction_ref' => $transactionRef,
        ];
    }
}
