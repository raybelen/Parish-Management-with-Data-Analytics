<?php

namespace App\Http\Controllers;

use App\Support\WebsiteContent;
use Illuminate\Contracts\View\View;

class ParishPageController extends Controller
{
    public function __invoke(string $page = 'home'): View
    {
        $websiteContent = WebsiteContent::values();
        $churchImage = 'https://images.unsplash.com/photo-1548625149-d37da68f9a7f?auto=format&fit=crop&w=2200&q=85';
        $interiorImage = 'https://images.unsplash.com/photo-1519491050282-cf00c82424b4?auto=format&fit=crop&w=1200&q=85';
        $architectureImage = 'https://images.unsplash.com/photo-1438032005730-c779502df39b?auto=format&fit=crop&w=1000&q=85';
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
            ['src' => $churchImage, 'alt' => 'Sunlight illuminating the architecture of a church', 'title' => $websiteContent['gallery_photo_one_title'], 'class' => 'sm:col-span-7 sm:row-span-2'],
            ['src' => $interiorImage, 'alt' => 'A church interior offering a quiet space for reflection', 'title' => $websiteContent['gallery_photo_two_title'], 'class' => 'sm:col-span-5'],
            ['src' => $architectureImage, 'alt' => 'Church architecture framed by natural light', 'title' => $websiteContent['gallery_photo_three_title'], 'class' => 'sm:col-span-5'],
        ];

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
            'churchImage', 'interiorImage', 'architectureImage',
            'ministries', 'announcements', 'photos', 'pageTitle', 'websiteContent',
        ));
    }
}
