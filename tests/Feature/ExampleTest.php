<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_asks_guests_to_sign_in(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
