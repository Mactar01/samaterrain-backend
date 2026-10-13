<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Envoyer un message WhatsApp via l'API officielle Meta (WhatsApp Cloud API)
     * 
     * @param string $phone Numéro de téléphone du destinataire (format international, ex: +221770000000)
     * @param string $message Le texte du message à envoyer
     * @return bool
     */
    public function sendMessage(string $phone, string $message): bool
    {
        Log::info("=== WHATSAPP MESSAGE (META) ===");
        Log::info("TO: " . $phone);
        Log::info("MESSAGE:\n" . $message);
        Log::info("=============================");

        // Identifiants Meta WhatsApp récupérés depuis le fichier .env
        $accessToken = env('META_WA_ACCESS_TOKEN');
        $phoneNumberId = env('META_WA_PHONE_NUMBER_ID');

        // Nettoyer le numéro pour l'API Meta (retirer le + ou les espaces)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Si l'API Meta n'est pas configurée, on s'arrête là (utile en dév)
        if (!$accessToken || !$phoneNumberId) {
            Log::warning("META_WA_ACCESS_TOKEN ou META_WA_PHONE_NUMBER_ID manquant dans le .env.");
            return false; // Renvoie false car non envoyé physiquement, mais logué
        }

        try {
            $response = Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $cleanPhone,
                    'type'              => 'text',
                    'text'              => [
                        'preview_url' => false,
                        'body'        => $message
                    ],
                ]);
                
            if (!$response->successful()) {
                Log::error("Erreur Meta WhatsApp API: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Exception lors de l'envoi WhatsApp (Meta): " . $e->getMessage());
            return false;
        }
    }
}
