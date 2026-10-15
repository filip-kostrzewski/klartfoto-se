<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Tests\TestCase;

class PostmarkMailTest extends TestCase
{
    public function test_postmark_mailer_is_wired_to_the_api_token(): void
    {
        config([
            'mail.default' => 'postmark',
            'mail.from.address' => 'noreply@klartfoto.se',
            'services.postmark.key' => 'postmark-test-token',
        ]);

        $transport = Mail::mailer('postmark')->getSymfonyTransport();

        $this->assertInstanceOf(PostmarkApiTransport::class, $transport);
        $this->assertSame('postmark', config('mail.mailers.postmark.transport'));
        $this->assertSame('postmark-test-token', config('services.postmark.key'));
        $this->assertSame('noreply@klartfoto.se', config('mail.from.address'));
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
