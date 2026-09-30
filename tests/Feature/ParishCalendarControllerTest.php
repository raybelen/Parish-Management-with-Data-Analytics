<?php

namespace Tests\Feature;

use App\Models\Appointment;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParishCalendarControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_calendar_renders_current_and_next_month_with_regular_and_confirmed_activities(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-09-15 10:00:00');
        Appointment::factory()->create([
            'status' => 'confirmed',
            'service_type' => 'wedding',
            'preferred_date' => '2026-09-17',
            'preferred_time' => '09:30',
            'client_full_name' => 'Private Parishioner',
        ]);
        Appointment::factory()->create([
            'status' => 'pending',
            'service_type' => 'baptism',
            'preferred_date' => '2026-10-03',
            'preferred_time' => '10:00',
        ]);
        Appointment::factory()->create([
            'status' => 'confirmed',
            'service_type' => 'funeral',
            'preferred_date' => '2026-11-01',
            'preferred_time' => '08:00',
        ]);

        $response = $this->get(route('calendar'));

        $response->assertViewIs('client-side.calendar')
            ->assertViewHas('calendarMonths', fn (array $months): bool => count($months) === 2)
            ->assertSee('September–October 2026')
            ->assertSee('September 2026')
            ->assertSee('October 2026')
            ->assertSee('Daily Mass')
            ->assertSee('Perpetual Mass')
            ->assertSee('Anticipated Mass')
            ->assertSee('Sunday Mass')
            ->assertSee('Wedding')
            ->assertSee('9:30 AM')
            ->assertDontSee('Baptism')
            ->assertDontSee('Funeral')
            ->assertDontSee('Private Parishioner');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $septemberWednesday = $xpath->query('//td[@data-date="2026-09-16"]')->item(0);
        $this->assertNotNull($septemberWednesday);
        $this->assertStringContainsString('Daily Mass', $septemberWednesday->textContent);
        $this->assertStringContainsString('Perpetual Mass', $septemberWednesday->textContent);
    }

    public function test_navigation_places_calendar_link_beside_the_appointment_action(): void
    {
        $this->withoutVite();

        $response = $this->get(route('home'));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $calendarLink = $xpath->query('//header/div/div/a[@aria-label="View parish calendar"]')->item(0);

        $this->assertNotNull($calendarLink);
        $this->assertSame(route('calendar'), $calendarLink->getAttribute('href'));
        $this->assertSame('Set an Appointment', trim($calendarLink->nextElementSibling->textContent));
    }

    public function test_calendar_range_handles_the_turn_of_the_year(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-12-15 10:00:00');

        $this->get(route('calendar'))
            ->assertSee('December 2026–January 2027')
            ->assertSee('December 2026')
            ->assertSee('January 2027')
            ->assertDontSee('February 2027');
    }
}
