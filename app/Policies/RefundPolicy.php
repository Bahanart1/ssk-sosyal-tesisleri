<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function view(User $user, Refund $refund): bool
    {
        return $user->isAdmin() || $refund->user_id === $user->id;
    }

    public function act(User $user, Refund $refund): bool
    {
        return $refund->user_id === $user->id;
    }
}
