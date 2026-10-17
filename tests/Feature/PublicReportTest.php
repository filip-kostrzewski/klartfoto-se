<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_photos_are_resized_and_shown_on_the_public_report(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['name' => 'Glans Städ AB']);

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 2000, 1000)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 800, 600)],
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $this->actingAs($user)->post(route('assignments.versions.store', $assignment, false))->assertRedirect();
        $photos = AssignmentPhoto::query()->orderBy('id')->get();
        $this->assertCount(2, $photos);
        $this->assertSame(PhotoPhase::Before, $photos[0]->phase);
        $this->assertSame(PhotoPhase::After, $photos[1]->phase);

        [$beforeWidth, $beforeHeight, $beforeType] = getimagesize(Storage::disk('local')->path($photos[0]->path));
        [$afterWidth, $afterHeight] = getimagesize(Storage::disk('local')->path($photos[1]->path));
        $this->assertSame(IMAGETYPE_JPEG, $beforeType);
        $this->assertSame(1600, $beforeWidth);
        $this->assertSame(800, $beforeHeight);
        $this->assertSame(800, $afterWidth);
        $this->assertSame(600, $afterHeight);

        $version = $assignment->reportVersions()->sole();
        $beforeAsset = $version->photoAssets()->where('phase', PhotoPhase::Before->value)->sole();
        $afterAsset = $version->photoAssets()->where('phase', PhotoPhase::After->value)->sole();
        $beforeUrl = route('reports.version-assets.show', ['token' => $version->token, 'asset' => $beforeAsset], false);
        $afterUrl = route('reports.version-assets.show', ['token' => $version->token, 'asset' => $afterAsset], false);

        $report = $this->get('/r/'.$assignment->public_token);
        $report->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('Glans Städ AB', false)
            ->assertSee('1 oktober 2026', false)
            ->assertSee('Anna Andersson', false)
            ->assertSee($beforeUrl, false)
            ->assertSee($afterUrl, false)
            ->assertDontSee('Ta bort', false);

        $photoResponse = $this->get($beforeUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $cacheControl = strtolower((string) $photoResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_unknown_token_and_another_jobs_photo_are_not_found(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
        ]);

        $assignment = Assignment::query()->firstOrFail();
        $photo = AssignmentPhoto::query()->firstOrFail();
        $other = Assignment::factory()->for($user)->create();

        $this->get('/r/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/r/'.$other->public_token.'/foton/'.$photo->id)->assertNotFound();
        $this->assertNotSame($assignment->public_token, $other->public_token);
    }

    public function test_public_reports_ask_crawlers_to_skip_them(): void
    {
        $assignment = Assignment::factory()->for(User::factory()->create())->create();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        app()->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://klartfoto.se']);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nAllow: /\n\nSitemap: https://klartfoto.se/sitemap.xml", false)
            ->assertDontSee('Disallow: /r/', false);
    }

    public function test_public_report_does_not_require_a_login(): void
    {
        $assignment = Assignment::factory()->for(User::factory()->create(['name' => 'Glans Städ AB']))->create([
            'performed_on' => '2026-10-01',
        ]);

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('Glans Städ AB', false);
    }
}
