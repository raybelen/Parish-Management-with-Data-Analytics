<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ParishPageController extends Controller
{
    public function __invoke(string $page = 'home'): View
    {
        $navigation = ['Home' => 'home', 'About' => 'about', 'Services' => 'services', 'Announcements' => 'announcements', 'Ministries & Organizations' => 'ministries', 'Gallery' => 'gallery', 'Contact' => 'contact'];
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
                'date' => 'November 22, 2026',
                'title' => 'Solemnity of Christ the King',
                'copy' => 'Let us celebrate Christ as King of the Universe and renew our commitment to follow Him in faith, love, and service.',
                'schedule' => [
                    ['heading' => null, 'items' => ['5:00 PM — Solemn Mass']],
                ],
            ],
            [
                'date' => 'December 16–24, 2026',
                'title' => 'Simbang Gabi',
                'copy' => 'Prepare your hearts for the celebration of the birth of Jesus through the traditional nine-day novena Masses of Simbang Gabi.',
                'schedule' => [
                    ['heading' => null, 'items' => ['Every evening: 6:00 PM']],
                ],
            ],
            [
                'date' => 'December 24–25, 2026',
                'title' => 'Christmas Eve & Christmas Day',
                'copy' => 'Celebrate the birth of our Savior, Jesus Christ, with our parish community.',
                'schedule' => [
                    ['heading' => 'Christmas Eve — December 24', 'items' => ["6:00 PM — Children's Christmas Mass", '9:00 PM — Christmas Eve Mass']],
                    ['heading' => 'Christmas Day — December 25', 'items' => ['8:00 AM']],
                ],
            ],
        ];
        $photos = [
            ['src' => $churchImage, 'alt' => 'Sunlight illuminating the architecture of a church', 'title' => 'A sacred place', 'class' => 'sm:col-span-7 sm:row-span-2'],
            ['src' => $interiorImage, 'alt' => 'A church interior offering a quiet space for reflection', 'title' => 'In stillness and prayer', 'class' => 'sm:col-span-5'],
            ['src' => $architectureImage, 'alt' => 'Church architecture framed by natural light', 'title' => 'Beauty that lifts the spirit', 'class' => 'sm:col-span-5'],
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
            'navigation', 'churchImage', 'interiorImage', 'architectureImage',
            'ministries', 'announcements', 'photos', 'pageTitle',
        ));
    }
}
