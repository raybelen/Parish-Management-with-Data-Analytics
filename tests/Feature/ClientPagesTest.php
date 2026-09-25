<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class ClientPagesTest extends TestCase
{
    public function test_client_pages_render_their_content_and_current_navigation(): void
    {
        $this->withoutVite();

        $pages = [
            '/about' => ['about', 'About the Church'],
            '/services' => ['services', 'Gather With Us in Worship'],
            '/announcements' => ['announcements', "What's Happening in Our Parish"],
            '/ministries-and-organizations' => ['ministries', 'Liturgical Ministries'],
            '/gallery' => ['gallery', 'Moments of Faith'],
            '/contact' => ['contact', "We're Here to Help"],
        ];

        foreach ($pages as $path => [$page, $heading]) {
            $response = $this->get($path);

            $response->assertViewIs('client-side.'.$page)
                ->assertSee($heading)
                ->assertDontSee('href="#about"', false)
                ->assertDontSee('data-dialog="appointment-dialog"', false);

            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $navigation = (new DOMXPath($document))->query('//nav[@aria-label="Main navigation"]/a[@aria-current="page"]');

            $this->assertSame(1, $navigation->length);
            $this->assertSame(route($page), $navigation->item(0)->getAttribute('href'));
        }
    }

    public function test_homepage_navigation_and_calls_to_action_open_client_pages(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertViewIs('client-side.home');

        foreach (['about', 'services', 'announcements', 'ministries', 'gallery', 'contact'] as $page) {
            $response->assertSee('href="'.route($page).'"', false);
        }

        foreach (['#home', '#about', '#mass-schedule', '#announcements', '#ministries', '#gallery', '#contact', '#appointment'] as $anchor) {
            $response->assertDontSee('href="'.$anchor.'"', false);
        }

        $response->assertDontSee('data-dialog=', false);
        $response->assertSee('November 22, 2026')
            ->assertSee('December 16–24, 2026')
            ->assertSee('December 24–25, 2026')
            ->assertSee('Solemnity of Christ the King')
            ->assertSee('Simbang Gabi')
            ->assertSee('Christmas Eve & Christmas Day')
            ->assertDontSee('5:00 PM — Solemn Mass');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $this->assertSame(3, $xpath->query('//section[@id="announcements"]//article//time')->length);
        $this->assertSame(0, $xpath->query('//section[@id="announcements"]//details')->length);
    }

    public function test_about_page_contains_three_placeholder_sections(): void
    {
        $this->withoutVite();

        $response = $this->get('/about');

        $response->assertSee('About the Church')
            ->assertSee('History of the Parish')
            ->assertSee('St. John Nepomucene')
            ->assertSee('Church Photo Placeholder')
            ->assertSee('History Photo Placeholder')
            ->assertSee('Patron Saint Photo Placeholder');
    }

    public function test_announcements_show_concise_previews_with_closed_details(): void
    {
        $this->withoutVite();

        $response = $this->get('/announcements');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $response->assertSee('November 22, 2026')
            ->assertSee('December 16–24, 2026')
            ->assertSee('December 24–25, 2026')
            ->assertSee('Solemnity of Christ the King')
            ->assertSee('Simbang Gabi')
            ->assertSee('Christmas Eve & Christmas Day')
            ->assertSee('5:00 PM — Solemn Mass')
            ->assertSee('Every evening: 6:00 PM')
            ->assertSee("6:00 PM — Children's Christmas Mass")
            ->assertSee('9:00 PM — Christmas Eve Mass')
            ->assertSee('8:00 AM')
            ->assertSee('Learn More');
        $this->assertSame(3, $xpath->query('//article//time')->length);
        $this->assertSame(3, $xpath->query('//article/details[contains(@class, "parish-accordion")]')->length);
        $this->assertSame(0, $xpath->query('//article/details[@open]')->length);
    }
}
