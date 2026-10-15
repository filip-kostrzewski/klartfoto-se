<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Tests\TestCase;

class PostmarkMailTest extends TestCase
{
    public function test_postmark_mailer_is_wired_to_the_api_token(): void
    {
        config([
            'mail.default' => 'postmark',
            'mail.from.address' => 'noreply@klartfoto.se',
            'mail.from.name' => 'Klartfoto',
            'mail.mailers.postmark.token' => null,
            'services.postmark.token' => null,
            'services.postmark.key' => 'postmark-test-token',
        ]);
        Mail::purge('postmark');

        $transport = Mail::mailer('postmark')->getSymfonyTransport();

        $this->assertInstanceOf(PostmarkApiTransport::class, $transport);
        $this->assertSame('postmark-test-token', (new ReflectionProperty($transport, 'key'))->getValue($transport));
        $this->assertSame('postmark', config('mail.mailers.postmark.transport'));
        $this->assertSame('noreply@klartfoto.se', config('mail.from.address'));
        $this->assertSame('Klartfoto', config('mail.from.name'));
    }

    public function test_postmark_mailer_token_is_used_before_the_services_key(): void
    {
        config([
            'mail.mailers.postmark.token' => 'mailer-token',
            'services.postmark.token' => 'services-token',
            'services.postmark.key' => 'services-key',
        ]);
        Mail::purge('postmark');

        $transport = Mail::mailer('postmark')->getSymfonyTransport();

        $this->assertInstanceOf(PostmarkApiTransport::class, $transport);
        $this->assertSame('mailer-token', (new ReflectionProperty($transport, 'key'))->getValue($transport));
    }

    public function test_mail_config_falls_back_to_postmark_and_the_klartfoto_sender(): void
    {
        $mail = (string) file_get_contents(base_path('config/mail.php'));
        $services = (string) file_get_contents(base_path('config/services.php'));

        $this->assertStringContainsString("env('MAIL_MAILER', 'postmark')", $mail);
        $this->assertStringContainsString("'token' => env('POSTMARK_API_KEY')", $mail);
        $this->assertStringContainsString("env('MAIL_FROM_ADDRESS', 'noreply@klartfoto.se')", $mail);
        $this->assertStringContainsString("env('MAIL_FROM_NAME', 'Klartfoto')", $mail);
        $this->assertStringContainsString("'token' => env('POSTMARK_API_KEY')", $services);
        $this->assertStringContainsString("'key' => env('POSTMARK_API_KEY')", $services);
    }

    public function test_env_example_selects_postmark_without_committing_a_key(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('MAIL_MAILER=postmark', $example);
        $this->assertStringContainsString("POSTMARK_API_KEY=\n", $example);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=noreply@klartfoto.se', $example);
        $this->assertDoesNotMatchRegularExpression('/POSTMARK_API_KEY=\S+/', $example);
    }
}
