<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_root(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login', absolute: false));
    }
}
