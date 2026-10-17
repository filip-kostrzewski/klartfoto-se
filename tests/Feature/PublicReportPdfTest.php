<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PublicReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_URL = 'https://pdf.wisegent.se/get_pdf';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::store('array')->flush();
        config([
            'services.report_pdf.url' => self::PDF_URL,
            'services.report_pdf.cache_store' => 'array',
            'services.report_pdf.cache_seconds' => 3600,
        ]);

        Http::preventStrayRequests();
    }

    public function test_public_pdf_uses_only_the_immutable_snapshot_and_assets_and_is_cached(): void
    {
        [$assignment, $version, $sourcePhotoPaths, $sourceLogoPath, $assetBytes] = $this->createVersionFixture();
        $company = $assignment->company()->firstOrFail();
        $company->forceFill([
            'name' => 'Changed company after version',
            'plan' => 'start',
            'trial_ends_at' => now()->subDay(),
            'logo_path' => null,
        ])->save();
        $assignment->forceFill([
            'customer_name' => 'Changed customer after version',
            'customer_email' => 'new-private@example.test',
            'address' => 'Changed address after version',
        ])->save();
        $assignment->photos()->delete();
        Storage::disk('local')->delete([...$sourcePhotoPaths, $sourceLogoPath]);

        $pdfBytes = "%PDF-1.7\nsynthetic document\n%%EOF\n";
        Http::fake([
            self::PDF_URL => Http::response($pdfBytes, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $response = $this->get(route('reports.pdf', ['token' => $version->token], false));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $pdfCacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $pdfCacheControl);
        $this->assertStringContainsString('no-store', $pdfCacheControl);
        $this->assertStringNotContainsString('public', $pdfCacheControl);
        $this->assertSame($pdfBytes, $response->getContent());

        Http::assertSent(function (Request $request) use ($version, $assetBytes): bool {
            $html = (string) ($request->data()['html'] ?? '');

            return $request->url() === self::PDF_URL
                && str_contains($html, 'Snapshot Cleaning AB')
                && str_contains($html, 'Snapshot Customer')
                && str_contains($html, 'Snapshotvägen 1, '.str_repeat('Lång adress ', 8))
                && str_contains($html, 'Spis')
                && str_contains($html, 'Extra lång offentlig kommentar '.str_repeat('text ', 20))
                && str_contains($html, 'data:image/svg+xml;base64,'.base64_encode($assetBytes['logo']))
                && str_contains($html, 'data:image/jpeg;base64,'.base64_encode($assetBytes['before-one']))
                && str_contains($html, 'data:image/png;base64,'.base64_encode($assetBytes['after-one']))
                && str_contains($html, 'data:image/jpeg;base64,'.base64_encode($assetBytes['before-two']))
                && str_contains($html, 'data:image/png;base64,'.base64_encode($assetBytes['after-two']))
                && str_contains($html, 'Version 1')
                && ! str_contains($html, 'private.customer@example.test')
                && ! str_contains($html, 'PRIVATE_INTERNAL_NOTE')
                && ! str_contains($html, 'PRIVATE_BEARER_TOKEN')
                && ! str_contains($html, 'Changed company after version')
                && ! str_contains($html, 'Changed customer after version')
                && ! str_contains($html, 'Changed address after version')
                && ! str_contains($html, $version->token)
                && ! str_contains($html, 'reports/version-assets');
        });

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertOk()
            ->assertContent($pdfBytes);

        Http::assertSentCount(1);
    }

    public function test_pdf_service_errors_or_non_pdf_responses_show_a_retry_page_and_are_not_cached(): void
    {
        [, $version] = $this->createVersionFixture();
        $pdfBytes = "%PDF-1.7\nsynthetic retry\n%%EOF\n";
        Http::fake([
            self::PDF_URL => Http::sequence()
                ->push('<html>temporary upstream error</html>', 200, ['Content-Type' => 'text/html'])
                ->push("%PDF-1.7\ntruncated response", 200, ['Content-Type' => 'application/pdf'])
                ->push($pdfBytes, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertStatus(503)
            ->assertSee('PDF kan inte skapas just nu')
            ->assertSee(route('reports.show', ['token' => $version->token], false), false);

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertStatus(503)
            ->assertSee('PDF kan inte skapas just nu');

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertOk()
            ->assertContent($pdfBytes);

        Http::assertSentCount(3);
    }

    public function test_pdf_requires_an_immutable_report_version(): void
    {
        Http::fake();

        $this->get(route('reports.pdf', ['token' => Str::random(48)], false))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_pdf_connection_failure_returns_retry_page_without_caching_the_failure(): void
    {
        [, $version] = $this->createVersionFixture();
        $pdfBytes = "%PDF-1.7\nsynthetic connection retry\n%%EOF\n";
        $requestCount = 0;
        Http::fake([
            self::PDF_URL => function (Request $request) use (&$requestCount, $pdfBytes) {
                $requestCount++;

                if ($requestCount === 1) {
                    throw new ConnectionException('Synthetic upstream timeout.');
                }

                return Http::response($pdfBytes, 200, ['Content-Type' => 'application/pdf']);
            },
        ]);

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertStatus(503)
            ->assertSee('PDF kan inte skapas just nu');

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertOk()
            ->assertContent($pdfBytes);

        $this->assertSame(2, $requestCount);
        Http::assertSentCount(1);
    }

    public function test_pdf_missing_a_snapshot_asset_shows_retry_page_without_requesting_the_service(): void
    {
        [, $version] = $this->createVersionFixture();
        $missingAsset = $version->photoAssets()->firstOrFail();
        Storage::disk('local')->delete($missingAsset->path);
        Http::fake();

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertStatus(503)
            ->assertSee('PDF kan inte skapas just nu');

        Http::assertNothingSent();
    }

    public function test_pdf_unreadable_snapshot_asset_shows_retry_page_without_requesting_the_service(): void
    {
        [, $version] = $this->createVersionFixture();
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->twice()->andReturn(true);
        $disk->shouldReceive('get')->twice()->andReturn(null);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Http::fake();

        $this->get(route('reports.pdf', ['token' => $version->token], false))
            ->assertStatus(503)
            ->assertSee('PDF kan inte skapas just nu');

        Http::assertNothingSent();
    }

    public function test_pdf_renders_a_missing_photo_phase_as_a_placeholder(): void
    {
        [, $version, , , $assetBytes] = $this->createVersionFixture();
        $snapshot = $version->snapshot;
        $removedPhotoId = $snapshot['places'][0]['pairs'][1]['after'];
        $snapshot['places'][0]['pairs'][1]['after'] = null;
        $version->forceFill([
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ])->save();
        $version->photoAssets()->where('source_photo_id', $removedPhotoId)->delete();
        $pdfBytes = "%PDF-1.7\nsynthetic missing phase\n%%EOF\n";
        Http::fake([
            self::PDF_URL => Http::response($pdfBytes, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->get(route('reports.pdf', ['token' => $version->token], false))->assertOk();

        Http::assertSent(function (Request $request) use ($assetBytes): bool {
            $html = (string) ($request->data()['html'] ?? '');

            return str_contains($html, 'Efter saknas')
                && str_contains($html, 'data:image/jpeg;base64,'.base64_encode($assetBytes['before-two']))
                && ! str_contains($html, 'data:image/png;base64,'.base64_encode($assetBytes['after-two']));
        });
    }

    public function test_pdf_includes_frozen_visit_and_company_metadata(): void
    {
        [, $version] = $this->createVersionFixture();
        $snapshot = $version->snapshot;
        $snapshot['performed_time'] = '14:30';
        $snapshot['performed_end_time'] = '15:00';
        $snapshot['performed_time_label'] = '14:30–15:00';
        $snapshot['company_phone'] = '070-111 22 33';
        $snapshot['company_organization_number'] = '559000-0000';
        $snapshot['report_created_at'] = '2026-10-16 07:30:00';
        $version->forceFill([
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => '2026-10-16 07:30:00',
        ])->save();

        $pdfBytes = "%PDF-1.7\nfrozen metadata\n%%EOF\n";
        Http::fake([
            self::PDF_URL => Http::response($pdfBytes, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->get(route('reports.pdf', ['token' => $version->token], false))->assertOk();

        Http::assertSent(function (Request $request): bool {
            $html = (string) ($request->data()['html'] ?? '');

            return str_contains($html, 'kl. 14:30–15:00')
                && str_contains($html, 'Företagsuppgifter')
                && str_contains($html, '070-111 22 33')
                && str_contains($html, 'org.nr 559000-0000')
                && str_contains($html, 'Rapporten skapades')
                && str_contains($html, '16 oktober 2026 kl. 09:30')
                && ! str_contains($html, '<footer')
                && ! str_contains($html, 'PRIVATE_INTERNAL_NOTE')
                && ! str_contains($html, 'private.customer@example.test');
        });
    }

    /**
     * @return array{
     *     0: Assignment,
     *     1: AssignmentReportVersion,
     *     2: list<string>,
     *     3: string,
     *     4: array{logo: string, before-one: string, after-one: string, before-two: string, after-two: string}
     * }
     */
    private function createVersionFixture(): array
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Draft customer name',
            'customer_email' => 'draft.customer@example.test',
            'address' => 'Draft street 1',
            'performed_on' => '2026-10-15',
        ]);
        $company = $owner->company()->firstOrFail();
        $logoPath = 'companies/'.$company->id.'/logos/current-logo.svg';
        $company->forceFill([
            'name' => 'Current company name',
            'logo_path' => $logoPath,
        ])->save();

        $assetBytes = [
            'logo' => '<svg xmlns="http://www.w3.org/2000/svg"><text>VERSION LOGO</text></svg>',
            'before-one' => 'synthetic portrait photo before one',
            'after-one' => 'synthetic portrait photo after one',
            'before-two' => 'synthetic portrait photo before two',
            'after-two' => 'synthetic portrait photo after two',
        ];
        Storage::disk('local')->put($logoPath, 'changed company logo');

        $photoIds = [];
        $sourcePhotoPaths = [];
        foreach (['before-one' => PhotoPhase::Before, 'after-one' => PhotoPhase::After, 'before-two' => PhotoPhase::Before, 'after-two' => PhotoPhase::After] as $key => $phase) {
            $sourcePath = 'assignments/'.$assignment->id.'/source-'.$key.'.jpg';
            $sourcePhotoPaths[] = $sourcePath;
            Storage::disk('local')->put($sourcePath, 'source row bytes '.$key);
            $photo = $assignment->photos()->create([
                'phase' => $phase,
                'path' => $sourcePath,
                'sort_order' => count($photoIds) + 1,
            ]);
            $photoIds[$key] = (int) $photo->id;
        }

        $versionAssets = [];
        foreach ($assetBytes as $key => $contents) {
            $extension = match ($key) {
                'logo' => 'svg',
                'after-one', 'after-two' => 'png',
                default => 'jpg',
            };
            $path = 'report-versions/1/'.$key.'.'.$extension;
            Storage::disk('local')->put($path, $contents);

            $versionAssets[$key] = [
                'type' => $key === 'logo'
                    ? AssignmentReportVersionAsset::TYPE_LOGO
                    : AssignmentReportVersionAsset::TYPE_PHOTO,
                'source_photo_id' => $key === 'logo' ? null : $photoIds[$key],
                'phase' => str_starts_with($key, 'before') ? PhotoPhase::Before->value : (str_starts_with($key, 'after') ? PhotoPhase::After->value : null),
                'place_name' => $key === 'logo' ? null : 'Kök',
                'place_sort_order' => $key === 'logo' ? null : 1,
                'pair_sort_order' => match ($key) {
                    'before-one', 'after-one' => 1,
                    'before-two', 'after-two' => 2,
                    default => null,
                },
                'path' => $path,
            ];
        }

        $snapshot = [
            'schema_version' => 1,
            'company_name' => 'Snapshot Cleaning AB',
            'company_logo_path' => $versionAssets['logo']['path'],
            'customer_name' => 'Snapshot Customer',
            'customer_email' => 'private.customer@example.test',
            'address' => 'Snapshotvägen 1, '.str_repeat('Lång adress ', 8),
            'performed_on' => '2026-10-15',
            'internal_note' => 'PRIVATE_INTERNAL_NOTE',
            'private_token' => 'PRIVATE_BEARER_TOKEN',
            'places' => [[
                'id' => 1,
                'name' => 'Kök',
                'sort_order' => 1,
                'pairs' => [
                    [
                        'id' => 1,
                        'label' => 'Spis',
                        'customer_comment' => 'Extra lång offentlig kommentar '.str_repeat('text ', 20),
                        'sort_order' => 1,
                        'before' => $photoIds['before-one'],
                        'after' => $photoIds['after-one'],
                    ],
                    [
                        'id' => 2,
                        'label' => 'Diskho',
                        'customer_comment' => null,
                        'sort_order' => 2,
                        'before' => $photoIds['before-two'],
                        'after' => $photoIds['after-two'],
                    ],
                ],
            ]],
            'assets' => array_values($versionAssets),
        ];
        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $version = $assignment->reportVersions()->create([
            'version_number' => 1,
            'token' => Str::random(48),
            'status' => AssignmentReportVersion::STATUS_READY,
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', $snapshotJson),
            'created_by_user_id' => $owner->id,
        ]);

        foreach ($versionAssets as $asset) {
            $version->assets()->create($asset);
        }

        return [$assignment, $version, $sourcePhotoPaths, $logoPath, $assetBytes];
    }
}
