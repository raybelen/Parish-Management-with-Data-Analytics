<?php

namespace Tests\Feature;

use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class BrevoMailTransportTest extends TestCase
{
    public function test_brevo_mailer_uses_the_https_api_transport(): void
    {
        config([
            'services.brevo.key' => 'xkeysib-test-key',
            'mail.mailers.brevo' => ['transport' => 'brevo'],
        ]);

        $transport = app(MailManager::class)
            ->mailer('brevo')
            ->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
    }
}
