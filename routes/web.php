<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Services\WhatsAppService;
use App\Notifications\NewReservationNotification;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payments/simulate/{id}', function ($id) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <title>PayDunya Simulation</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px; margin-top: 20px; border: none; cursor: pointer; width: 100%; }
            .btn-success { background: #28a745; }
            .btn-danger { background: #dc3545; margin-top: 10px; }
            .logo { font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="logo">PayDunya (Mode Test)</div>
            <h3>Paiement de la Réservation #'.$id.'</h3>
            <p>Ceci est une page de simulation car aucune clé d API PayDunya nest configurée dans le fichier .env.</p>
            
            <form action="/payments/simulate-confirm/'.$id.'" method="GET">
                <button type="submit" class="btn btn-success">✅ Simuler un Paiement Réussi</button>
            </form>
            
            <a href="http://localhost:8080/" class="btn btn-danger">❌ Annuler</a>
        </div>
    </body>
    </html>
    ';
});

Route::get('/payments/simulate-confirm/{id}', function ($id) {
    $reservation = Reservation::with(['field.owner.user', 'user'])->find($id);
    if (!$reservation) {
        return redirect('http://localhost:8080/');
    }

    $payment = Payment::where('reservation_id', $reservation->id)->where('status', 'pending')->first();

    DB::transaction(function () use ($reservation, $payment) {
        $slot = TimeSlot::where('id', $reservation->time_slot_id)->lockForUpdate()->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now()
            ]);
        } else {
             $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'amount' => $reservation->total_price,
                'method' => 'paydunya',
                'status' => 'completed',
                'transaction_ref' => 'SIMULATED_'.uniqid(),
                'paid_at' => now()
            ]);
        }

        $reservation->update(['status' => 'confirmed']);
        if ($slot) {
            $slot->update(['status' => 'reserved']);
        }
    });

    // Notifications
    $ownerUser = $reservation->field->owner->user ?? null;
    if ($ownerUser) {
        $ownerUser->notify(new NewReservationNotification($reservation));
        
        $whatsappService = new WhatsAppService();
        $amount = $payment ? $payment->amount : $reservation->total_price;
        
        $whatsappService->sendMessage(
            $ownerUser->phone ?? '+221000000000', 
            "✅ *NOUVELLE RÉSERVATION* \nTerrain: {$reservation->field->name}\nMontant payé: {$amount} FCFA"
        );

        if ($reservation->user && $reservation->user->phone) {
            $whatsappService->sendMessage(
                $reservation->user->phone, 
                "✅ *RÉSERVATION CONFIRMÉE* \nTerrain: {$reservation->field->name}\nMontant payé: {$amount} FCFA"
            );
        }
    }

    return redirect('http://localhost:8080/');
});

Route::get('/api/documentation', function () {
    return file_get_contents(resource_path('views/swagger.blade.php'));
});
