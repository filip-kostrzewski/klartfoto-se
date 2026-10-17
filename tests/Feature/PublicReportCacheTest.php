<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicReportCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_link_files_are_not_stored_in_a_shared_cache(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.report_pdf.cache_store' => 'array']);
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $photoPath = 'assignments/'.$assignment->id.'/report.jpg';
        Storage::disk('local')->put($photoPath, 'report-photo');
        $photo = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $photoPath,
            'sort_order' => 1,
        ]);
        $version = AssignmentReportVersion::factory()->create([
            'assignment_id' => $assignment->id,
            'token' => $assignment->public_token,
            'snapshot' => ['assets' => []],
            'snapshot_hash' => hash('sha256', '[]'),
        ]);
        $photoAsset = $version->assets()->create([
            'type' => AssignmentReportVersionAsset::TYPE_PHOTO,
            'source_photo_id' => $photo->id,
            'phase' => PhotoPhase::Before->value,
            'path' => $photoPath,
        ]);
        $logoPath = 'companies/'.$assignment->company_id.'/logo.svg';
        Storage::disk('local')->put($logoPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $version->assets()->create([
            'type' => AssignmentReportVersionAsset::TYPE_LOGO,
            'path' => $logoPath,
        ]);
        Cache::store('array')->put(
            'assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash,
            "%PDF-1.4\ncached\n%%EOF\n",
            60,
        );

        $legacy = Assignment::factory()->for($owner)->create();
        $legacyPath = 'assignments/'.$legacy->id.'/legacy.jpg';
        Storage::disk('local')->put($legacyPath, 'legacy-photo');
        $legacyPhoto = $legacy->photos()->create([
            'phase' => PhotoPhase::After,
            'path' => $legacyPath,
            'sort_order' => 1,
        ]);
        $legacyLogo = 'companies/'.$legacy->company_id.'/legacy.svg';
        Storage::disk('local')->put($legacyLogo, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $owner->company->forceFill(['logo_path' => $legacyLogo])->save();

        $this->assertPrivateNoStore($this->get('/r/'.$version->token.'/version-foton/'.$photoAsset->id));
        $this->assertPrivateNoStore($this->get('/r/'.$version->token.'/foton/'.$photo->id));
        $this->assertPrivateNoStore($this->get('/r/'.$version->token.'/logotyp'));
        $this->assertPrivateNoStore($this->get('/r/'.$version->token.'/pdf'));
        $this->assertPrivateNoStore($this->get('/r/'.$legacy->public_token.'/foton/'.$legacyPhoto->id));
        $this->assertPrivateNoStore($this->get('/r/'.$legacy->public_token.'/logotyp'));
    }

    private function assertPrivateNoStore(TestResponse $response): void
    {
        $response->assertOk();
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('max-age', $cacheControl);
    }
}
