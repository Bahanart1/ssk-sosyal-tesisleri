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

    public function civilRegistry(ReservationGuest $guest)
    {
        $guest->loadMissing('reservation');

        $this->authorizeAccess($guest->reservation);

        abort_unless($guest->civil_registry_path, 404);

        return $this->documents->response(
            $guest->civil_registry_path,
            'nufus-kayit-' . $guest->reservation->code . '-' . $guest->id . '.' . pathinfo($guest->civil_registry_path, PATHINFO_EXTENSION)
        );
    }

    /** Aidat dekontu: yalnızca sahibi ve yöneticiler açabilir. */
    public function duesReceipt(\App\Models\MembershipDue $due)
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || $due->user_id === $user->id), 403);
        abort_unless($due->receipt_path, 404);

        return $this->documents->response(
            $due->receipt_path,
            'aidat-dekont-' . $due->year . '-' . $due->id . '.' . pathinfo($due->receipt_path, PATHINFO_EXTENSION)
        );
    }

    /** Dilekçe görseli: yalnızca sahibi ve yöneticiler açabilir. */
    public function petition(\App\Models\Petition $petition)
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || $petition->user_id === $user->id), 403);
        abort_unless($petition->attachment_path, 404);

        return $this->documents->response(
            $petition->attachment_path,
            'dilekce-' . $petition->id . '.' . pathinfo($petition->attachment_path, PATHINFO_EXTENSION)
        );
    }

    private function authorizeAccess(Reservation $reservation): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || $reservation->user_id === $user->id), 403);
    }
}
