<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * '/' redirects unauthenticated visitors to '/login' (see
     * Route::redirect('/', 'login') in routes/web.php) — it was never
     * meant to return 200 directly.
     */
    public function test_the_application_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
