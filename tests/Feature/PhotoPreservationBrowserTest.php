<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class PhotoPreservationBrowserTest extends TestCase
{
    public function test_a_browser_keeps_selected_photos_when_the_server_rejects_the_job(): void
    {
        $this->assertFileExists(public_path('build/manifest.json'));
        $this->assertFileDoesNotExist(public_path('hot'));

        $process = new Process(
            ['npx', 'playwright', 'test', '--reporter=line'],
            base_path(),
            [
                'APP_ENV' => 'local',
                'APP_URL' => 'http://127.0.0.1:8765',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => '/tmp/klartfoto-e2e.sqlite',
                'DB_URL' => '',
                'SESSION_DRIVER' => 'file',
                'SESSION_SECURE_COOKIE' => 'false',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'log',
            ],
            null,
            180,
        );
        $process->mustRun();

        $output = $process->getOutput().$process->getErrorOutput();
        $this->assertStringContainsString('selected photos survive a validation error', $output);
    }
}
