<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Kök adres, oturumu olmayan ziyaretçiyi giriş ekranına yönlendirir. */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_login_page_is_reachable(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
