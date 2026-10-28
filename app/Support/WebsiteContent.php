<?php

namespace App\Support;

use App\Models\ParishSetting;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class WebsiteContent
{
    public const SETTING_KEY = 'website_content';

    private const CACHE_KEY = 'parish.website-content.v3';

    /**
     * @return array<string, array{title: string, description: string, fields: array<string, array{label: string, value: string, type: string, max: int}>}>
     */
    public static function groups(): array
    {
        return [
            'homepage' => [
                'title' => 'Homepage',
                'description' => 'Welcome message and the shared sections shown on the homepage.',
                'fields' => [
                    'home_hero_label' => self::field('Hero label', 'St. John Nepomucene Parish'),
                    'home_hero_heading_primary' => self::field('Hero heading, first line', 'Welcome to'),
                    'home_hero_heading_emphasis' => self::field('Hero heading, emphasized line', 'Our Parish'),
                    'home_hero_subtitle' => self::field('Hero subtitle', 'A place of faith, prayer, worship, and community.'),
                    'home_hero_copy' => self::field('Hero welcome message', 'Whether you are a longtime parishioner, returning member of our community, or visiting us for the first time, you are warmly welcome at St. John Nepomucene Parish.', 1000),
                    'home_about_image_caption' => self::field('Welcome image caption', 'Rooted in faith. Open to all.'),
                    'home_about_label' => self::field('Welcome section label', 'Welcome'),
                    'home_about_title' => self::field('Welcome section title', 'A Community United in Faith'),
                    'home_about_copy' => self::field('Welcome section message', 'St. John Nepomucene Parish is a community gathered in faith and prayer. Together, we celebrate the Eucharist, grow in our relationship with Christ, and live out our calling through service, fellowship, and love for one another.', 1500),
                    'home_about_quote' => self::field('Welcome section closing line', 'A place to worship, belong, serve, and grow.'),
                    'mass_label' => self::field('Mass schedule label', 'Mass Schedule'),
                    'mass_title' => self::field('Mass schedule title', 'Gather With Us in Worship'),
                    'mass_copy' => self::field('Mass schedule introduction', 'Come together with our parish community in the celebration of the Holy Mass.', 1000),
                    'mass_note' => self::field('Mass schedule closing line', 'There is always a place for you in our pews.'),
                    'announcements_label' => self::field('Announcements label', 'Parish Life'),
                    'announcements_title' => self::field('Announcements title', "What's Happening in Our Parish"),
                    'announcements_copy' => self::field('Announcements introduction', 'The latest parish news, celebrations, and opportunities to take part.', 1000),
                    'gallery_label' => self::field('Gallery label', 'Our Community'),
                    'gallery_title' => self::field('Gallery title', 'Moments of Faith'),
                ],
            ],
            'about' => [
                'title' => 'About page',
                'description' => 'Parish history, identity, and patron saint copy.',
                'fields' => [
                    'about_church_label' => self::field('Church section label', 'The Parish Church'),
                    'about_church_title' => self::field('Church section title', 'About the Church'),
                    'about_church_paragraph_one' => self::field('Church section, first paragraph', 'St. John Nepomucene Parish is a Roman Catholic parish established in 1958. For many years, our church has been a place of worship, prayer, and fellowship, bringing together families and individuals in the celebration of the Holy Eucharist and the Sacraments.', 2000),
                    'about_church_paragraph_two' => self::field('Church section, second paragraph', 'As a parish community, we strive to grow together in faith and follow the teachings of Jesus Christ. Through our liturgical celebrations, parish activities, and service to others, we continue to build a welcoming and faithful community centered on Christ.', 2000),
                    'about_church_quote' => self::field('Church section closing line', 'A sacred home for worship, prayer, and community.'),
                    'about_history_label' => self::field('History section label', 'Our Story'),
                    'about_history_title' => self::field('History section title', 'History of the Parish'),
                    'about_history_paragraph_one' => self::field('History section, first paragraph', 'Founded in 1958, St. John Nepomucene Parish has been part of the spiritual journey of generations of Catholic faithful. Through the years, the parish has grown alongside the community, providing a place where people can gather in prayer, celebrate their faith, and share important moments in their lives.', 2000),
                    'about_history_paragraph_two' => self::field('History section, second paragraph', 'Today, we continue to honor that heritage by keeping our faith alive and passing it on to future generations.', 2000),
                    'about_history_quote' => self::field('History section closing line', 'A living heritage of faith since 1958.'),
                    'about_patron_label' => self::field('Patron section label', 'Our Patron'),
                    'about_patron_title' => self::field('Patron section title', 'St. John Nepomucene'),
                    'about_patron_paragraph_one' => self::field('Patron section, first paragraph', 'Saint John Nepomucene is the patron saint of our parish. He was a Catholic priest and martyr known for his strong faith, courage, and devotion to the Church. His life is remembered as an example of remaining faithful to God even during difficult times.', 2000),
                    'about_patron_paragraph_two' => self::field('Patron section, second paragraph', 'As a parish dedicated to Saint John Nepomucene, we look to his life and example for inspiration. May his faithfulness encourage us to live our Catholic faith with courage, humility, and love, and to serve God and our fellow brothers and sisters.', 2000),
                    'about_patron_quote' => self::field('Patron section closing line', 'Faithful in witness. Courageous in service.'),
                ],
            ],
            'services' => [
                'title' => 'Services page',
                'description' => 'Sacrament, pastoral care, and other service descriptions. Service cards remain fixed in place.',
                'fields' => [
                    'services_major_label' => self::field('Major services label', 'Sacraments & Pastoral Care'),
                    'services_major_title' => self::field('Major services title', 'Services'),
                    'services_major_copy' => self::field('Major services introduction', 'Our parish accompanies individuals and families through life’s most meaningful moments. Begin by reviewing the service you need, then contact the parish office for scheduling and guidance.', 1500),
                    'service_baptism_title' => self::field('Baptism title', 'Baptism'),
                    'service_baptism_description' => self::field('Baptism description', 'Welcome a child or adult into the Catholic faith and the life of the Church through the Sacrament of Baptism.', 1000),
                    'service_wedding_title' => self::field('Wedding title', 'Wedding'),
                    'service_wedding_description' => self::field('Wedding description', 'Prepare to celebrate the Sacrament of Holy Matrimony with the guidance and support of our parish community.', 1000),
                    'service_funeral_title' => self::field('Funeral title', 'Funeral'),
                    'service_funeral_description' => self::field('Funeral description', 'Receive prayerful pastoral support as the parish accompanies your family in remembering and commending a loved one to God.', 1000),
                    'services_other_label' => self::field('Other services label', 'More Ways We Serve'),
                    'services_other_title' => self::field('Other services title', 'Other Services'),
                    'services_other_copy' => self::field('Other services introduction', 'For preparation, availability, and service-specific requirements, please coordinate directly with the parish office.', 1000),
                    'service_communion_title' => self::field('First Holy Communion title', 'First Holy Communion'),
                    'service_communion_description' => self::field('First Holy Communion description', 'Preparation for children who will receive Jesus in the Holy Eucharist for the first time.', 1000),
                    'service_confirmation_title' => self::field('Confirmation title', 'Confirmation'),
                    'service_confirmation_description' => self::field('Confirmation description', 'Faith formation for those preparing to receive the gifts of the Holy Spirit through Confirmation.', 1000),
                    'service_blessings_title' => self::field('Blessings title', 'House, Vehicle & Religious Item Blessings'),
                    'service_blessings_description' => self::field('Blessings description', 'Request a blessing for your home, vehicle, or religious items as an expression of faith and gratitude.', 1000),
                    'service_anointing_title' => self::field('Anointing title', 'Anointing of the Sick'),
                    'service_anointing_description' => self::field('Anointing description', 'Pastoral care and the Sacrament of Anointing for parishioners facing serious illness, frailty, or surgery.', 1000),
                    'service_confession_title' => self::field('Confession title', 'Confession'),
                    'service_confession_description' => self::field('Confession description', 'Encounter God’s mercy and reconciliation through the Sacrament of Penance.', 1000),
                    'service_celebratory_title' => self::field('Celebratory Mass title', 'Celebratory Mass'),
                    'service_celebratory_description' => self::field('Celebratory Mass description', 'Arrange a Mass for meaningful occasions such as anniversaries, birthdays, and family milestones.', 1000),
                    'services_assistance_label' => self::field('Assistance section label', 'Parish Assistance'),
                    'services_assistance_title' => self::field('Assistance section title', 'Let Us Help You Prepare'),
                    'services_assistance_copy' => self::field('Assistance section message', 'Contact the parish office for sacrament inquiries, scheduling, document guidance, and special celebrations.', 1000),
                ],
            ],
            'announcements' => [
                'title' => 'Announcements',
                'description' => 'The three existing announcement cards and their schedule details. The card count and layout stay fixed.',
                'fields' => [
                    'announcement_one_date' => self::field('First announcement date', 'November 22, 2026'),
                    'announcement_one_title' => self::field('First announcement title', 'Solemnity of Christ the King'),
                    'announcement_one_copy' => self::field('First announcement message', 'Let us celebrate Christ as King of the Universe and renew our commitment to follow Him in faith, love, and service.', 1500),
                    'announcement_one_schedule' => self::field('First announcement schedule', '5:00 PM — Solemn Mass'),
                    'announcement_two_date' => self::field('Second announcement date', 'December 16–24, 2026'),
                    'announcement_two_title' => self::field('Second announcement title', 'Simbang Gabi'),
                    'announcement_two_copy' => self::field('Second announcement message', 'Prepare your hearts for the celebration of the birth of Jesus through the traditional nine-day novena Masses of Simbang Gabi.', 1500),
                    'announcement_two_schedule' => self::field('Second announcement schedule', 'Every evening: 6:00 PM'),
                    'announcement_three_date' => self::field('Third announcement date', 'December 24–25, 2026'),
                    'announcement_three_title' => self::field('Third announcement title', 'Christmas Eve & Christmas Day'),
                    'announcement_three_copy' => self::field('Third announcement message', 'Celebrate the birth of our Savior, Jesus Christ, with our parish community.', 1500),
                    'announcement_three_first_heading' => self::field('Third announcement, first schedule heading', 'Christmas Eve — December 24'),
                    'announcement_three_first_item' => self::field('Third announcement, first schedule item', "6:00 PM — Children's Christmas Mass"),
                    'announcement_three_second_item' => self::field('Third announcement, second schedule item', '9:00 PM — Christmas Eve Mass'),
                    'announcement_three_second_heading' => self::field('Third announcement, second schedule heading', 'Christmas Day — December 25'),
                    'announcement_three_third_item' => self::field('Third announcement, third schedule item', '8:00 AM'),
                ],
            ],
            'ministries' => [
                'title' => 'Ministries & organizations',
                'description' => 'Descriptions for the existing ministry and organization entries. Admins can edit the copy but cannot add or remove sections.',
                'fields' => [
                    'ministries_intro_label' => self::field('Introduction label', 'Parish Life'),
                    'ministries_intro_title' => self::field('Introduction title', 'A Place to Serve and Belong'),
                    'ministries_intro_copy' => self::field('Introduction message', 'Our parish community grows through people who pray, serve, learn, and walk together in faith. Explore the ministries and organizations where you can share your gifts and become more deeply involved in parish life.', 1500),
                    'ministries_liturgical_label' => self::field('Liturgical section label', 'Serve in Worship'),
                    'ministries_liturgical_title' => self::field('Liturgical section title', 'Liturgical Ministries'),
                    'ministries_liturgical_copy' => self::field('Liturgical section introduction', 'Share your gifts in the prayer and sacramental life of our parish community.', 1000),
                    'ministry_readers_name' => self::field('Readers ministry name', 'Ministry of Readers'),
                    'ministry_readers_description' => self::field('Readers ministry description', 'The Ministry of Readers is responsible for proclaiming the Sacred Scriptures during Holy Mass and other liturgical celebrations. Readers prepare carefully and practice their assigned readings so that they can proclaim God’s Word clearly, faithfully, and reverently. Through their service, they help the congregation reflect on Scripture and deepen their encounter with Christ.', 2500),
                    'ministry_servers_name' => self::field('Altar servers ministry name', 'Ministry of Altar Servers'),
                    'ministry_servers_description' => self::field('Altar servers ministry description', 'The Ministry of Altar Servers assists the priest and deacon during Mass and other liturgical celebrations. Their duties include preparing the altar and other liturgical items, carrying the cross and candles during processions, assisting at the altar, and helping maintain proper order and reverence throughout the celebration.', 2500),
                    'ministry_music_name' => self::field('Music ministry name', 'Music Ministry'),
                    'ministry_music_description' => self::field('Music ministry description', 'The Music Ministry supports the liturgy through music and singing. Its members lead the congregation in hymns and songs, prepare appropriate music for Mass and other celebrations, and practice together to ensure that the music contributes to prayer and worship. Their purpose is to help the congregation participate more fully and prayerfully in the liturgy.', 2500),
                    'ministry_youth_name' => self::field('Youth ministry name', 'Parish Youth Ministry'),
                    'ministry_youth_description' => self::field('Youth ministry description', 'The Parish Youth Ministry focuses on helping young people grow in their faith and become active members of the Church. It organizes gatherings, retreats, faith-formation activities, outreach programs, and other events for young people. It also encourages youth to develop leadership skills, serve others, and strengthen their relationship with Christ.', 2500),
                    'ministry_catechists_name' => self::field('Catechists ministry name', 'Ministry of Catechists'),
                    'ministry_catechists_description' => self::field('Catechists ministry description', 'The Ministry of Catechists is responsible for teaching and strengthening the faith of parishioners. Catechists provide instruction about Catholic teachings, Scripture, prayer, and Christian living. They may also prepare children, young people, and adults for the sacraments and help families understand and practice their faith in daily life.', 2500),
                    'ministry_communion_name' => self::field('Holy Communion ministry name', 'Extraordinary Ministers of Holy Communion'),
                    'ministry_communion_description' => self::field('Holy Communion ministry description', 'The Extraordinary Ministers of Holy Communion assist the priest and deacon in distributing Holy Communion when their assistance is needed. They serve the Eucharist with reverence and care during Mass and, when properly commissioned and assigned, may also bring Holy Communion to sick or homebound parishioners. Their service helps ensure that the faithful can receive the Eucharist in a dignified and orderly manner.', 2500),
                    'ministries_organizations_label' => self::field('Organizations section label', 'Grow in Community'),
                    'ministries_organizations_title' => self::field('Organizations section title', 'Organizations'),
                    'ministries_organizations_copy' => self::field('Organizations section introduction', 'Find fellowship, deepen your faith, and serve the parish alongside others.', 1000),
                    'organization_knights_name' => self::field('Knights of Columbus name', 'Knights of Columbus'),
                    'organization_knights_description' => self::field('Knights of Columbus description', 'The Knights of Columbus is a Catholic organization that promotes faith, charity, fraternity, and service. Its members participate in charitable activities, support parish programs, assist people and families in need, and contribute to various Church and community projects. Through these activities, they put Catholic values of service and brotherhood into practice.', 2500),
                    'organization_mother_butler_name' => self::field('Mother Butler Guild name', 'Mother Butler Guild'),
                    'organization_mother_butler_description' => self::field('Mother Butler Guild description', 'The Mother Butler Guild is a parish service organization that traditionally supports the Church and its liturgical activities. Its members may help care for church furnishings, linens, and other items used during worship. They also assist with preparing the church for celebrations and participate in charitable and parish activities.', 2500),
                    'organization_neocatechumenal_name' => self::field('Neo-Catechumenal Way name', 'Neo-Catechumenal Way'),
                    'organization_neocatechumenal_description' => self::field('Neo-Catechumenal Way description', 'The Neo-Catechumenal Way is a Catholic community and formation program intended to help members deepen their Christian faith and live out their baptismal vocation. Members participate in Scripture-based formation, prayer, Eucharistic celebrations, and community activities. It also encourages evangelization and helps members grow in their commitment to Christian life and service.', 2500),
                    'ministries_cta_label' => self::field('Closing section label', 'Take the Next Step'),
                    'ministries_cta_title' => self::field('Closing section title', 'Find Your Place in Parish Life'),
                    'ministries_cta_copy' => self::field('Closing section message', 'Contact the parish office to learn more about joining a ministry or organization.', 1000),
                ],
            ],
            'contact' => [
                'title' => 'Contact page',
                'description' => 'Contact details, location, office hours, and appointment call-to-action copy.',
                'fields' => [
                    'contact_label' => self::field('Contact section label', 'Contact Us'),
                    'contact_title' => self::field('Contact section title', 'Connect With Our Parish'),
                    'contact_copy' => self::field('Contact section introduction', 'Reach the parish office through the contact method most convenient for you. We welcome questions about sacraments, documents, parish activities, and pastoral assistance.', 1500),
                    'contact_facebook_title' => self::field('Facebook label', 'Facebook'),
                    'contact_facebook_name' => self::field('Facebook display name', 'St. John Nepomucene Parish'),
                    'contact_facebook_detail' => self::field('Facebook detail', 'Visit our official Facebook page for parish updates and announcements.', 1000),
                    'contact_facebook_url' => self::field('Facebook page URL', 'https://www.facebook.com/profile.php?id=100080696044407', 255, 'url'),
                    'contact_phone_title' => self::field('Phone label', 'Phone'),
                    'contact_phone_name' => self::field('Phone display name', 'To be followed'),
                    'contact_phone_detail' => self::field('Phone detail', 'The parish office phone number will be posted here once available.', 1000),
                    'contact_email_title' => self::field('Email label', 'Email'),
                    'contact_email_name' => self::field('Email address', 'stjohnnepomucene88@gmail.com', 255, 'email'),
                    'contact_email_detail' => self::field('Email detail', 'Send us an email for parish inquiries and assistance.', 1000),
                    'contact_address' => self::field('Parish address', 'Purok 5, Bonga, Bacacay, Albay 4509, Philippines'),
                    'contact_map_query' => self::field('Map coordinates or search query', '13.28137007718145,123.7579731871621'),
                    'office_hours_label' => self::field('Office hours label', 'Office Hours'),
                    'office_hours_title' => self::field('Office hours title', "We're Here to Help"),
                    'office_open_days' => self::field('Open days', 'Tuesdays to Sundays'),
                    'office_morning_hours' => self::field('Morning hours', '8:00 AM – 11:30 AM'),
                    'office_afternoon_hours' => self::field('Afternoon hours', '2:00 PM – 5:00 PM'),
                    'office_closed_day' => self::field('Closed day', 'Mondays'),
                    'office_closed_message' => self::field('Closed day message', 'No Office Hours'),
                    'contact_cta_title' => self::field('Appointment section title', 'Need to Visit the Parish Office?'),
                    'contact_cta_copy' => self::field('Appointment section message', "Whether you are inquiring about a sacrament, seeking parish assistance, requesting information, or simply need to speak with someone at the parish office, we're here to help.", 1500),
                    'contact_visit_title' => self::field('Visit card title', 'Arrange a Parish Visit'),
                    'contact_visit_copy' => self::field('Visit card message', 'Submit an online request for Baptism, Wedding, or Funeral services. You can also use your reference number to review an existing request.', 1500),
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::groups() as $group) {
            foreach ($group['fields'] as $key => $field) {
                $defaults[$key] = $field['value'];
            }
        }

        return $defaults;
    }

    /** @return array<string, string> */
    public static function values(): array
    {
        return self::cache()->rememberForever(self::CACHE_KEY, function (): array {
            $stored = ParishSetting::query()->where('key', self::SETTING_KEY)->first()?->value;

            return self::merge($stored);
        });
    }

    public static function forgetCachedValues(): void
    {
        self::cache()->forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string>
     */
    public static function merge(?array $stored): array
    {
        $defaults = self::defaults();
        $allowedStoredValues = array_filter(
            array_intersect_key($stored ?? [], $defaults),
            fn (mixed $value): bool => is_string($value),
        );

        return array_replace($defaults, $allowedStoredValues);
    }

    /**
     * @return array<string, array{label: string, value: string, type: string, max: int}>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::groups() as $group) {
            $fields = array_merge($fields, $group['fields']);
        }

        return $fields;
    }

    /** @return array{label: string, value: string, type: string, max: int} */
    private static function field(string $label, string $value, int $max = 255, ?string $type = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'type' => $type ?? ($max > 255 ? 'textarea' : 'text'),
            'max' => $max,
        ];
    }

    private static function cache(): Repository
    {
        return Cache::store((string) config('parish.website_content_cache_store', 'file'));
    }
}
