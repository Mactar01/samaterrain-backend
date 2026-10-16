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
        if ($user->isAdmin()) return true;
        if ($reservation->user_id === $user->id) return true;
        $owner = $user->getOwner();
        if ($user->isOwner() && $owner && $reservation->field && $reservation->field->owner_id === $owner->id) {
            return true;
        }
        return false;
    }

    public function cancel(User $user, Reservation $reservation)
    {
        if ($user->isAdmin()) return true;
        if ($reservation->user_id === $user->id) return true;
        $owner = $user->getOwner();
        if ($user->isOwner() && $owner && $reservation->field && $reservation->field->owner_id === $owner->id && $user->hasOwnerPermission('reservations:manage')) {
            return true;
        }
        return false;
    }

    public function confirm(User $user, Reservation $reservation)
    {
        if ($user->isAdmin()) return true;
        $owner = $user->getOwner();
        return $user->isOwner() && $owner && $reservation->field && $reservation->field->owner_id === $owner->id && $user->hasOwnerPermission('reservations:manage');
    }
}
