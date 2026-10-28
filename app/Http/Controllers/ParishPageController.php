<?php

namespace App\Http\Controllers;

use App\Support\OfficeSettings;
use App\Support\WebsiteContent;
use Illuminate\Contracts\View\View;

class ParishPageController extends Controller
{
    public function __invoke(string $page = 'home'): View
    {
        $websiteContent = WebsiteContent::values();
        $ministries = [
            ['icon' => 'cross', 'title' => 'Worship & Liturgy', 'copy' => 'Help our community gather in prayer and celebrate the Eucharist.'],
            ['icon' => 'book', 'title' => 'Faith Formation', 'copy' => 'Learn, reflect, and grow together in our Catholic faith.'],
            ['icon' => 'sun', 'title' => 'Youth', 'copy' => 'Find friendship, purpose, and a place to share your gifts.'],
            ['icon' => 'heart', 'title' => 'Charity & Outreach', 'copy' => 'Bring the love of Christ to our neighbors through service.'],
            ['icon' => 'people', 'title' => 'Community & Fellowship', 'copy' => 'Build meaningful connections in the life of our parish.'],
        ];
        $announcements = [
            [
                'date' => $websiteContent['announcement_one_date'],
                'title' => $websiteContent['announcement_one_title'],
                'copy' => $websiteContent['announcement_one_copy'],
                'schedule' => [
                    ['heading' => null, 'items' => [$websiteContent['announcement_one_schedule']]],
                ],
            ],
            [
                'date' => $websiteContent['announcement_two_date'],
                'title' => $websiteContent['announcement_two_title'],
                'copy' => $websiteContent['announcement_two_copy'],
                'schedule' => [
                    ['heading' => null, 'items' => [$websiteContent['announcement_two_schedule']]],
                ],
            ],
            [
                'date' => $websiteContent['announcement_three_date'],
                'title' => $websiteContent['announcement_three_title'],
                'copy' => $websiteContent['announcement_three_copy'],
                'schedule' => [
                    ['heading' => $websiteContent['announcement_three_first_heading'], 'items' => [$websiteContent['announcement_three_first_item'], $websiteContent['announcement_three_second_item']]],
                    ['heading' => $websiteContent['announcement_three_second_heading'], 'items' => [$websiteContent['announcement_three_third_item']]],
                ],
            ],
        ];
        $photos = [
            ['src' => asset('images/gallery-1.jpg'), 'alt' => 'Parishioners holding lighted candles during worship'],
            ['src' => asset('images/gallery-2.jpg'), 'alt' => 'A priest baptizing an infant'],
            ['src' => asset('images/gallery-3.jpg'), 'alt' => 'A Marian image displayed with flowers inside the church'],
            ['src' => asset('images/gallery-4.jpg'), 'alt' => 'A flower-adorned Marian image carried in a parish procession'],
            ['src' => asset('images/gallery-5.jpg'), 'alt' => 'An altar server holding a rosary'],
            ['src' => asset('images/gallery-6.jpg'), 'alt' => 'The parish community gathered for Mass'],
        ];
        $photos = $page === 'home' ? array_slice($photos, 0, 3) : $photos;
        $appointmentServices = $page === 'services' ? OfficeSettings::services() : [];

        $pageTitle = match ($page) {
            'home' => 'Home',
            'about' => 'About',
            'services' => 'Services',
            'announcements' => 'Announcements',
            'ministries' => 'Ministries & Organizations',
            'gallery' => 'Gallery',
            'contact' => 'Contact',
        };

        return view('client-side.'.$page, compact(
            'ministries', 'announcements', 'photos', 'pageTitle', 'websiteContent', 'appointmentServices',
        ));
    }
}
