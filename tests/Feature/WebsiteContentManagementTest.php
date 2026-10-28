<?php

namespace Tests\Feature;

use App\Models\ParishSetting;
use App\Models\User;
use App\Support\WebsiteContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteContentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_content_manager_requires_authentication_mfa_and_permission(): void
    {
        $this->get('/admin/website-content')->assertRedirect('/login');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/website-content')->assertRedirect('/admin/security');

        $staff = User::factory()->staff()->withTwoFactor()->create();
        $this->verified($staff)->get('/admin/website-content')->assertForbidden();
        $this->put('/admin/website-content', ['content' => WebsiteContent::defaults()])->assertForbidden();

        $this->assertDatabaseMissing('parish_settings', ['key' => WebsiteContent::SETTING_KEY]);
    }

    public function test_authorized_admin_can_open_content_manager(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->verified($admin)->get('/admin/website-content')
            ->assertOk()
            ->assertSee('Edit public website text')
            ->assertSee('Homepage')
            ->assertSee('About page')
            ->assertSee('Ministries &amp; organizations', false)
            ->assertSee('Save and publish changes');
    }

    public function test_admin_can_publish_escaped_text_and_change_is_audited(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $content = WebsiteContent::defaults();
        $content['home_hero_heading_primary'] = 'Welcome, parish family';
        $content['home_hero_copy'] = '<script>alert("unsafe")</script> Join us this Sunday.';

        $this->get('/')->assertSee('Welcome to');

        $this->verified($admin)->put('/admin/website-content', ['content' => $content])
            ->assertRedirect('/admin/website-content')
            ->assertSessionHas('status', 'Website content saved and published.');

        $storedContent = ParishSetting::query()->where('key', WebsiteContent::SETTING_KEY)->firstOrFail()->value;
        $this->assertSame('Welcome, parish family', $storedContent['home_hero_heading_primary']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'website_content.updated',
            'entity_type' => ParishSetting::class,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome, parish family')
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt; Join us this Sunday.', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);
    }

    public function test_public_content_is_reused_without_repeating_the_database_query(): void
    {
        ParishSetting::factory()->create([
            'key' => WebsiteContent::SETTING_KEY,
            'value' => ['home_hero_heading_primary' => 'Cached welcome'],
        ]);
        WebsiteContent::forgetCachedValues();
        DB::enableQueryLog();

        $first = WebsiteContent::values();
        $second = WebsiteContent::values();

        $settingQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'parish_settings'),
        );
        $this->assertSame('Cached welcome', $first['home_hero_heading_primary']);
        $this->assertSame($first, $second);
        $this->assertCount(1, $settingQueries);
    }

    public function test_contact_page_ignores_stale_legacy_website_content_cache(): void
    {
        $this->withoutVite();
        Cache::store((string) config('parish.website_content_cache_store', 'file'))->forever(
            'parish.website-content.v2',
            array_replace(WebsiteContent::defaults(), [
                'contact_phone_name' => 'Parish Office',
                'contact_email_name' => 'Parish Office Email',
            ]),
        );
        WebsiteContent::forgetCachedValues();

        $response = $this->get('/contact');

        $response->assertSee('To be followed')
            ->assertSee('stjohnnepomucene88@gmail.com')
            ->assertDontSee('Parish Office Email');
    }

    public function test_contact_links_address_map_and_footer_hours_use_managed_content(): void
    {
        $this->withoutVite();
        ParishSetting::factory()->create([
            'key' => WebsiteContent::SETTING_KEY,
            'value' => array_replace(WebsiteContent::defaults(), [
                'contact_facebook_url' => 'https://www.facebook.com/example.parish',
                'contact_email_name' => 'office@example.test',
                'contact_address' => 'Example Parish Address',
                'contact_map_query' => '13.111,123.222',
                'office_open_days' => 'Fridays to Sundays',
            ]),
        ]);
        WebsiteContent::forgetCachedValues();

        $this->get('/contact')
            ->assertSee('href="https://www.facebook.com/example.parish"', false)
            ->assertSee('href="mailto:office@example.test"', false)
            ->assertSee('Example Parish Address')
            ->assertSee('query=13.111%2C123.222', false);
        $this->get('/appointments')->assertSee('Fridays to Sundays');
    }

    public function test_content_update_rejects_missing_oversized_and_unknown_fields(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $content = WebsiteContent::defaults();
        unset($content['home_hero_label']);
        $content['home_hero_subtitle'] = Str::repeat('a', 256);
        $content['contact_facebook_url'] = 'javascript:alert(1)';
        $content['contact_email_name'] = 'not-an-email';
        $content['blade_template'] = '{{ config("app.key") }}';

        $this->verified($admin)->put('/admin/website-content', ['content' => $content])
            ->assertSessionHasErrors([
                'content',
                'content.home_hero_label',
                'content.home_hero_subtitle',
                'content.contact_facebook_url',
                'content.contact_email_name',
            ]);

        $this->assertDatabaseMissing('parish_settings', ['key' => WebsiteContent::SETTING_KEY]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'website_content.updated']);
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin.mfa_user_id' => $user->id,
            'admin.mfa_fingerprint' => $user->adminSessionFingerprint(),
        ]);
    }
}
