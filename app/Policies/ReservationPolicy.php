<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin()) {
            return true;
        }
    }

    public function view(User $user, Reservation $reservation)
    {
        // Un joueur voit ses réservations. Un loueur voit les réservations de ses terrains.
        if ($reservation->user_id === $user->id) return true;
        if ($user->isOwner() && $reservation->field->owner->user_id === $user->id) return true;
        return false;
    }

    public function cancel(User $user, Reservation $reservation)
    {
        if ($reservation->user_id === $user->id) return true;
        if ($user->isOwner() && $reservation->field->owner->user_id === $user->id) return true;
        return false;
    }

    public function confirm(User $user, Reservation $reservation)
    {
        // Seul le loueur du terrain concerné peut confirmer
        return $user->isOwner() && $reservation->field->owner->user_id === $user->id;
    }
}
