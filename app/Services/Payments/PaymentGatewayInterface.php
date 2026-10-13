<?php

namespace App\Services\Payments;

use App\Models\Reservation;

interface PaymentGatewayInterface
{
    /**
     * Initialise un paiement auprès de l'opérateur.
     *
     * @param Reservation $reservation
     * @param float $amount
     * @param string|null $phone
     * @return array ['success' => bool, 'url' => string|null, 'transaction_ref' => string, 'message' => string|null]
     */
    public function initiatePayment(Reservation $reservation, float $amount, ?string $phone = null): array;
}
