<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClientPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $response->assertSee('rel="icon" type="image/png" href="'.asset('images/logo-modified.png').'"', false);

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

    public function test_about_page_contains_parish_images_and_history_section(): void
    {
        $this->withoutVite();

        $response = $this->get('/about');

        $response->assertSee('About the Church')
            ->assertSee('History of the Parish')
            ->assertSee('St. John Nepomucene')
            ->assertSee('images/church-about-natural.png', false)
            ->assertSee('Our parish church and bell tower in natural daylight')
            ->assertSee('images/parish-history.png', false)
            ->assertSee('Historical photograph of the former parish church and bell tower')
            ->assertSee('images/patron-saint.png', false)
            ->assertSee('Mosaic of Saint John Nepomucene holding a crucifix with a finger to his lips');
    }

    public function test_services_page_dialogs_show_the_current_appointment_requirements(): void
    {
        $this->withoutVite();

        $response = $this->get('/services');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $requirementsByDialog = [
            'baptism-requirements' => [
                'Photocopy of Birth Certificate with registry number (PSA or Local Civil Registry)',
                'Marriage Contract, if parents are married',
            ],
            'wedding-requirements' => [
                'PSA Birth Certificate',
                'Updated Baptismal & Confirmation Certificate (Purpose: For Marriage)',
                'Marriage License, or, if civilly married, Marriage Contract with registry number',
                'Pre-Cana Seminar',
            ],
            'funeral-requirements' => ['Photocopy of Death Certificate'],
        ];

        foreach ($requirementsByDialog as $dialogId => $requirements) {
            $dialog = $xpath->query('//dialog[@id="'.$dialogId.'"]')->item(0);

            $this->assertNotNull($dialog);

            foreach ($requirements as $requirement) {
                $this->assertStringContainsString($requirement, $dialog->textContent);
            }
        }

        $response->assertDontSee('Requirement list placeholder.');
    }

    public function test_gallery_page_displays_all_photos_and_homepage_keeps_three_photo_preview(): void
    {
        $this->withoutVite();

        $galleryResponse = $this->get('/gallery');
        $homeResponse = $this->get('/');

        foreach (range(1, 6) as $photoNumber) {
            $galleryResponse->assertSee("images/gallery-{$photoNumber}.jpg", false);
        }

        $galleryDocument = new DOMDocument;
        @$galleryDocument->loadHTML($galleryResponse->getContent());
        $gallery = new DOMXPath($galleryDocument);

        $homeDocument = new DOMDocument;
        @$homeDocument->loadHTML($homeResponse->getContent());
        $home = new DOMXPath($homeDocument);

        $this->assertSame(6, $gallery->query('//section[@id="gallery"]//button[@data-gallery-index]')->length);
        $this->assertSame(3, $home->query('//section[@id="gallery"]//button[@data-gallery-index]')->length);
        $this->assertSame(0, $gallery->query('//section[@id="gallery"]//button[@data-gallery-index]/*[not(self::img)]')->length);
        $galleryResponse->assertDontSee('Illustrative church photography')
            ->assertDontSee('id="gallery-caption"', false);
    }

    public function test_ministries_page_does_not_show_photo_placeholders_when_photos_are_unavailable(): void
    {
        $this->withoutVite();

        $this->get('/ministries-and-organizations')
            ->assertSee('Liturgical Ministries')
            ->assertDontSee('Photo Placeholder')
            ->assertDontSee('Photo placeholder');
    }

    public function test_contact_page_shows_contact_methods_and_church_location_map(): void
    {
        $this->withoutVite();

        $response = $this->get('/contact');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $response->assertSee('stjohnnepomucene88@gmail.com')
            ->assertSee('To be followed')
            ->assertSee('Purok 5, Bonga, Bacacay, Albay 4509, Philippines')
            ->assertSee('Visit Facebook Page')
            ->assertSee('Send Email')
            ->assertSee('Visit the Parish Church')
            ->assertSee('href="https://www.facebook.com/profile.php?id=100080696044407"', false)
            ->assertSee('href="mailto:stjohnnepomucene88@gmail.com"', false)
            ->assertSee('href="https://www.google.com/maps/search/?api=1&amp;query=13.28137007718145%2C123.7579731871621"', false);

        $map = $xpath->query('//section[@aria-labelledby="contact-location-heading"]//iframe')->item(0);

        $this->assertNotNull($map);
        $this->assertSame('Map showing the location of St. John Nepomucene Parish', $map->getAttribute('title'));
        $this->assertSame('lazy', $map->getAttribute('loading'));
        $this->assertStringContainsString('13.28137007718145%2C123.7579731871621', $map->getAttribute('src'));
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
