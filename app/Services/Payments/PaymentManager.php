<?php

namespace App\Services\Payments;

use Exception;

class PaymentManager
{
    /**
     * Résout et retourne le bon service de paiement en fonction de la méthode choisie.
     *
     * @param string $method
     * @return PaymentGatewayInterface
     * @throws Exception
     */
    public function driver(string $method): PaymentGatewayInterface
    {
        switch ($method) {
            case 'wave':
                return new WaveService();
            case 'orange_money':
                return new OrangeMoneyService();
            // Vous pourrez ajouter 'free_money' ici plus tard
            default:
                throw new Exception("Le moyen de paiement '{$method}' n'est pas encore supporté par notre agrégateur interne.");
        }
    }
}
