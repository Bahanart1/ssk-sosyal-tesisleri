<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Services\DocumentStorage;
use Illuminate\Support\Facades\Auth;

/**
 * Kimlik belgesi, dekont ve sağlık raporu gibi kişisel veriler public diske
 * yazılmaz. Bu belgeler yalnızca başvuru sahibine ve yöneticilere, her istekte
 * yetki kontrolü yapılarak sunulur.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentStorage $documents) {}

    public function identity(ReservationGuest $guest)
    {
        $guest->loadMissing('reservation');

        $this->authorizeAccess($guest->reservation);

        abort_unless($guest->id_document_path, 404);

        return $this->documents->response(
            $guest->id_document_path,
            'kimlik-' . $guest->reservation->code . '-' . $guest->id . '.' . pathinfo($guest->id_document_path, PATHINFO_EXTENSION)
        );
    }

    public function receipt(Payment $payment)
    {
        $payment->loadMissing('reservation');

        $this->authorizeAccess($payment->reservation);

        abort_unless($payment->receipt_path, 404);

        return $this->documents->response(
            $payment->receipt_path,
            'dekont-' . $payment->reference_no . '.' . pathinfo($payment->receipt_path, PATHINFO_EXTENSION)
        );
    }

    public function healthReport(Reservation $reservation)
    {
        $this->authorizeAccess($reservation);

        abort_unless($reservation->health_report_path, 404);

        return $this->documents->response(
            $reservation->health_report_path,
            'saglik-raporu-' . $reservation->code . '.' . pathinfo($reservation->health_report_path, PATHINFO_EXTENSION)
        );
    }

    private function authorizeAccess(Reservation $reservation): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || $reservation->user_id === $user->id), 403);
    }
}
