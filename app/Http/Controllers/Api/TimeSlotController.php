<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\TimeSlot;
use App\Http\Requests\StoreTimeSlotRequest;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TimeSlotController extends Controller
{
    use AuthorizesRequests;

    /**
     * Liste des créneaux d'un terrain (filtrable par date)
     */
    public function index(Request $request, $fieldId)
    {
        $field = Field::findOrFail($fieldId);
        
        $query = $field->timeSlots();

        if ($request->filled('date')) {
            $query->where('date', $request->date);
        } else {
            // Par défaut, à partir d'aujourd'hui
            $query->where('date', '>=', now()->toDateString());
        }

        $slots = $query->orderBy('date')
            ->orderByRaw("CASE WHEN TIME(start_time) < '06:00:00' THEN 1 ELSE 0 END, start_time")
            ->get();

        return response()->json($slots);
    }

    /**
     * Ajouter un créneau à un terrain
     */
    public function store(StoreTimeSlotRequest $request, $fieldId)
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('update', $field);

        // Vérifier si un créneau existe déjà sur cette plage (simple check)
        $exists = $field->timeSlots()
            ->where('date', $request->date)
            ->where('start_time', $request->start_time)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Un créneau existe déjà à cette date et heure.'], 409);
        }

        $slot = $field->timeSlots()->create([
            'date'       => $request->date,
            'start_time' => $request->start_time,
            'end_time'   => $request->end_time,
            'price'      => $request->price,
            'status'     => 'available'
        ]);

        return response()->json([
            'message' => 'Créneau ajouté avec succès.',
            'slot'    => $slot
        ], 201);
    }

    /**
     * Supprimer un créneau
     */
    public function destroy($fieldId, $slotId)
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('update', $field);

        $slot = $field->timeSlots()->findOrFail($slotId);

        if (!$slot->isAvailable()) {
            return response()->json(['message' => 'Impossible de supprimer un créneau déjà réservé.'], 403);
        }

        $slot->delete();

        return response()->json(['message' => 'Créneau supprimé avec succès.']);
    }

    public function update(Request $request, $fieldId, $slotId)
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('update', $field);
        
        $slot = $field->timeSlots()->findOrFail($slotId);
        
        $data = $request->validate([
            'status' => 'sometimes|in:available,blocked',
            'price' => 'nullable|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_label' => 'nullable|string|max:100',
            'block_reason' => 'nullable|string|max:255'
        ]);

        if ($slot->status === 'reserved' && isset($data['status']) && $data['status'] === 'blocked') {
            return response()->json(['message' => 'Impossible de bloquer un créneau déjà réservé.'], 403);
        }

        if (isset($data['status']) && $data['status'] === 'available') {
            $data['block_reason'] = null; // Clear reason if made available
        }

        $slot->update($data);
        return response()->json(['message' => 'Créneau mis à jour.', 'slot' => $slot]);
    }

    public function bulkCreate(Request $request, $fieldId)
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('update', $field);

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $startTime = $request->input('start_time');
                    if (!$startTime) return;
                    if ($value === $startTime) {
                        $fail("L'heure de fin ne peut pas être identique à l'heure de début.");
                    }
                }
            ],
            'duration_minutes' => 'required|integer|min:30|max:240',
            'price' => 'nullable|numeric|min:0'
        ]);

        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);

        // Limite la période pour éviter de générer des milliers de créneaux d'un coup
        if ($startDate->diffInDays($endDate) > 90) {
            return response()->json(['message' => 'La période ne peut pas dépasser 90 jours.'], 422);
        }
        $duration = $request->duration_minutes;
        
        $created = 0;

        for ($date = clone $startDate; $date->lte($endDate); $date->addDay()) {
            $startTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $request->start_time);
            $endTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $request->end_time);

            // Si l'heure de fin est minuit (00:00) ou antérieure à l'heure de début, elle appartient au jour suivant
            if ($request->end_time === '00:00' || $endTime->lte($startTime)) {
                $endTime->addDay();
            }

            while ($startTime->copy()->addMinutes($duration)->lte($endTime)) {
                $slotEnd = $startTime->copy()->addMinutes($duration);

                $exists = $field->timeSlots()
                    ->where('date', $date->format('Y-m-d'))
                    ->where('start_time', $startTime->format('H:i:00'))
                    ->exists();

                if (!$exists) {
                    $field->timeSlots()->create([
                        'date' => $date->format('Y-m-d'),
                        'start_time' => $startTime->format('H:i:s'),
                        'end_time' => $slotEnd->format('H:i:s'),
                        'price' => $request->price,
                        'status' => 'available'
                    ]);
                    $created++;
                }
                
                $startTime->addMinutes($duration);
            }
        }

        return response()->json([
            'message' => "$created créneaux générés avec succès."
        ], 201);
    }
}
