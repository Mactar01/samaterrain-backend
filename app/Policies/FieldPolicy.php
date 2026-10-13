<?php

namespace App\Policies;

use App\Models\Field;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FieldPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin()) {
            return true;
        }
    }

    public function viewAny(?User $user)
    {
        return true; // Everyone can view
    }

    public function view(?User $user, Field $field)
    {
        return true; // Everyone can view
    }

    public function create(User $user)
    {
        return $user->isOwner();
    }

    public function update(User $user, Field $field)
    {
        return $user->isOwner() && $field->owner && $field->owner->user_id === $user->id;
    }

    public function delete(User $user, Field $field)
    {
        return $this->update($user, $field);
    }
}
