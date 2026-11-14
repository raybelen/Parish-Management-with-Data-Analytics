<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_unknown_public_page_shows_the_parish_not_found_page(): void
    {
        $this->withoutVite();

        $this->get('/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSeeText("We couldn't find that page.")
            ->assertSee('Go to parish home')
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('Contact the parish office')
            ->assertSee('href="'.route('contact').'"', false)
            ->assertDontSee('Return to administration');
    }

    public function test_unknown_admin_page_shows_a_safe_route_back_to_administration(): void
    {
        $this->withoutVite();

        $this->get('/admin/statisticst')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('The administration page at this address does not exist.')
            ->assertSee('Return to administration')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('Visit parish website')
            ->assertDontSee('Contact the parish office');
    }
}
