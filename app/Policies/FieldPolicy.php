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
        return ($user->isOwner() || $user->isAdmin()) && $user->hasOwnerPermission('fields:manage');
    }

    public function update(User $user, Field $field)
    {
        if ($user->isAdmin()) return true;
        $owner = $user->getOwner();
        return $user->isOwner() && $owner && $field->owner_id === $owner->id && $user->hasOwnerPermission('fields:manage');
    }

    public function delete(User $user, Field $field)
    {
        return $this->update($user, $field);
    }
}
