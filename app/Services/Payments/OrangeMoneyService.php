<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use Illuminate\Support\Str;

class OrangeMoneyService implements PaymentGatewayInterface
{
    public function initiatePayment(Reservation $reservation, float $amount, ?string $phone = null): array
    {
        // TODO: Insérer ici le code d'appel à l'API Orange Money Web Payment
        // nécessitant généralement l'obtention d'un Access Token au préalable.
        
        $transactionRef = 'OM-' . strtoupper(Str::random(10));
        
        $paymentUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/payment-simulation?ref=' . $transactionRef . '&method=orange_money&amount=' . $amount;

        return [
            'success'         => true,
            'url'             => $paymentUrl,
            'transaction_ref' => $transactionRef,
        ];
    }
}
