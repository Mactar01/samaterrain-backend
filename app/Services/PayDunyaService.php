<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayDunyaService
{
    protected $masterKey;
    protected $privateKey;
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->masterKey = env('PAYDUNYA_MASTER_KEY', 'test_master_key');
        $this->privateKey = env('PAYDUNYA_PRIVATE_KEY', 'test_private_key');
        $this->token = env('PAYDUNYA_TOKEN', 'test_token');
        $this->baseUrl = 'https://app.paydunya.com/api/v1/checkout-invoice/create';
    }

    public function createInvoice($reservation, $amountToPay)
    {
        if ($this->masterKey === 'test_master_key') {
            return [
                'success' => true,
                'url' => env('APP_URL', 'http://192.168.1.4:8000') . '/payments/simulate/' . $reservation->id,
                'token' => 'simulated_token_' . $reservation->id
            ];
        }

        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY' => $this->masterKey,
            'PAYDUNYA-PRIVATE-KEY' => $this->privateKey,
            'PAYDUNYA-TOKEN' => $this->token,
        ])->post($this->baseUrl, [
            'invoice' => [
                'total_amount' => $amountToPay,
                'description' => "Réservation myTerrain (ID: {$reservation->id})"
            ],
            'store' => [
                'name' => 'myTerrain',
            ],
            'actions' => [
                'cancel_url' => env('FRONTEND_URL', 'http://192.168.1.4:8080') . '/payment/cancel',
                'return_url' => env('FRONTEND_URL', 'http://192.168.1.4:8080') . '/payment/success',
                'callback_url' => env('APP_URL', 'http://192.168.1.4:8000') . '/api/v1/payments/webhook'
            ],
            'custom_data' => [
                'reservation_id' => $reservation->id,
                'amount' => $amountToPay
            ]
        ]);

        if ($response->successful() && $response->json('response_code') === '00') {
            return [
                'success' => true,
                'url' => $response->json('response_text'),
                'token' => $response->json('token')
            ];
        }

        Log::error('PayDunya Error: ' . $response->body());

        return [
            'success' => false,
            'message' => 'Erreur lors de la génération du paiement.'
        ];
    }
}
