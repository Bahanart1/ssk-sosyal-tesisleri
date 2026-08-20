<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Başvuru ve ona bağlı belgeler yalnızca sahibine ve yöneticilere açıktır.
 */
class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin() || $reservation->user_id === $user->id;
    }

    /** Üyenin kendi başvurusu üzerinde işlem yapması (iptal, ödeme, iade talebi). */
    public function act(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id;
    }
}
