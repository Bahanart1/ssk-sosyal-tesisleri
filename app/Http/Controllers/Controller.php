<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Yetkilendirme controller'lara serpiştirilmiş elle kontroller yerine
     * Policy sınıfları üzerinden yapılır: kural tek yerde durur ve yeni bir
     * endpoint kontrolü unutursa erişim açılmak yerine kapalı kalır.
     */
    use AuthorizesRequests;
}
