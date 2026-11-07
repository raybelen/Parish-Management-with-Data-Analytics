<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class DeploymentProcessConfigurationTest extends TestCase
{
    public function test_render_container_supervises_the_web_scheduler_and_queue_processes(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $startupScript = file_get_contents(base_path('render-start.sh'));
        $supervisorConfiguration = file_get_contents(base_path('supervisord.conf'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString('supervisor unzip', $dockerfile);
        $this->assertStringContainsString('COPY supervisord.conf /etc/supervisor/conf.d/parish-management.conf', $dockerfile);
        $this->assertIsString($startupScript);
        $this->assertStringContainsString('php artisan appointments:expire-unverified --no-interaction', $startupScript);
        $this->assertStringContainsString('exec /usr/bin/supervisord --configuration=/etc/supervisor/supervisord.conf', $startupScript);
        $this->assertIsString($supervisorConfiguration);
        $this->assertStringContainsString('[program:apache]', $supervisorConfiguration);
        $this->assertStringContainsString('command=/usr/local/bin/apache2-foreground', $supervisorConfiguration);
        $this->assertStringContainsString('[program:scheduler]', $supervisorConfiguration);
        $this->assertStringContainsString('command=php artisan schedule:work --no-interaction', $supervisorConfiguration);
        $this->assertStringContainsString('[program:queue-worker]', $supervisorConfiguration);
        $this->assertStringContainsString('command=php artisan queue:work --sleep=5 --tries=3 --backoff=10 --timeout=20 --max-time=3600 --no-interaction', $supervisorConfiguration);
        $this->assertSame(2, substr_count($supervisorConfiguration, 'environment=HOME="/var/www",USER="www-data"'));
    }

    public function test_every_scheduled_office_command_uses_a_single_server_lock(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'office:reminders')
                || str_contains($event->command ?? '', 'appointments:expire-unverified')
                || str_contains($event->command ?? '', 'appointments:send-reminders')
                || str_contains($event->command ?? '', 'analytics:forecast'));

        $this->assertNotEmpty($events);
        $this->assertTrue($events->every(fn ($event): bool => $event->onOneServer));
    }
}
