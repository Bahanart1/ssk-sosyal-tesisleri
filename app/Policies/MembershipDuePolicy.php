<?php

namespace App\Policies;

use App\Models\MembershipDue;
use App\Models\User;

class MembershipDuePolicy
{
    public function view(User $user, MembershipDue $due): bool
    {
        return $user->isAdmin() || $due->user_id === $user->id;
    }

    public function act(User $user, MembershipDue $due): bool
    {
        return $due->user_id === $user->id;
    }
}
