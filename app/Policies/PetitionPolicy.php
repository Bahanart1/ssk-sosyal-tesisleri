<?php

namespace App\Policies;

use App\Models\Petition;
use App\Models\User;

class PetitionPolicy
{
    public function view(User $user, Petition $petition): bool
    {
        return $user->isAdmin() || $petition->user_id === $user->id;
    }
}
