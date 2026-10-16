<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\User;
use App\PhotoPhase;
use App\Rules\AcceptedJobPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReportEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_shows_date_time_pair_notes_and_only_saved_company_details(): void
    {
        $this->travelTo('2026-10-16 07:30:00');
        Storage::fake('local');
        $user = User::factory()->create();
        $user->company->update([
            'name' => 'Kriti Städ',
            'phone' => null,
            'organization_number' => null,
        ]);

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'TEST Kriti Kund',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_time' => '14:30',
            'performed_end_time' => '15:00',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 80, 60)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 80, 60)],
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $before = $assignment->photos()->where('phase', PhotoPhase::Before)->firstOrFail();
        $after = $assignment->photos()->where('phase', PhotoPhase::After)->firstOrFail();
        $this->assertNotNull($before->assignment_photo_pair_id);
        $this->assertSame($before->assignment_photo_pair_id, $after->assignment_photo_pair_id);
        $pair = $before->photoPair()->firstOrFail();
        $place = $pair->place()->firstOrFail();
        $firstVersion = $assignment->reportVersions()->sole();
        $this->assertSame('14:30', $firstVersion->snapshot['performed_time']);
        $this->assertSame('15:00', $firstVersion->snapshot['performed_end_time']);
        $this->assertSame('14:30–15:00', $firstVersion->snapshot['performed_time_label']);

        $this->actingAs($user)
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $place, $pair], false), [
                'label' => 'Kök',
                'customer_comment' => 'Bänken är ren.',
            ])->assertRedirect();

        $this->get(route('reports.show', ['token' => $firstVersion->token], false))
            ->assertOk()
            ->assertSee('16 oktober 2026', false)
            ->assertSee('kl. 14:30–15:00', false)
            ->assertSee('Rapporten skapades 16 oktober 2026 kl. 09:30', false)
            ->assertSee('<figcaption>Före', false)
            ->assertSee('<figcaption>Efter', false)
            ->assertDontSee('Kök', false)
            ->assertDontSee('Bänken är ren.', false)
            ->assertDontSee('org.nr', false)
            ->assertDontSee('070', false);

        $this->actingAs($user)->put('/installningar/foretag', [
            'company_name' => 'Kriti Städ',
            'phone' => '070-111 22 33',
            'organization_number' => '559000-0000',
        ])->assertRedirect();
        $this->assertSame('070-111 22 33', $user->company->refresh()->phone);
        $this->assertSame('559000-0000', $user->company->reportOrganizationNumber());

        $this->actingAs($user)
            ->post(route('assignments.versions.store', $assignment, false))
            ->assertRedirect(route('assignments.show', $assignment, false));
        $secondVersion = $assignment->fresh()->reportVersions()->firstOrFail();
        $this->assertSame(2, $secondVersion->version_number);
        $this->assertSame('14:30–15:00', $secondVersion->snapshot['performed_time_label']);

        $this->get(route('reports.show', ['token' => $secondVersion->token], false))
            ->assertOk()
            ->assertSee('16 oktober 2026', false)
            ->assertSee('kl. 14:30–15:00', false)
            ->assertSee('Kök', false)
            ->assertSee('Bänken är ren.', false)
            ->assertSee('org.nr 559000-0000', false)
            ->assertSee('070-111 22 33', false);

        $this->actingAs($user)->put('/installningar/foretag', [
            'company_name' => 'Kriti Städ',
            'phone' => '   ',
            'organization_number' => '',
        ])->assertRedirect()
            ->assertSessionHas('toast', 'Företagsuppgifterna är sparade.');

        $this->assertNull($user->company->refresh()->reportPhone());
        $this->assertNull($user->company->reportOrganizationNumber());
        $this->get(route('reports.show', ['token' => $secondVersion->token], false))
            ->assertOk()
            ->assertSee('070-111 22 33', false)
            ->assertSee('org.nr 559000-0000', false);
    }

    public function test_a_legacy_unpaired_report_can_be_published_without_changing_source_photos(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $assignment = Assignment::factory()->for($user)->create([
            'performed_on' => '2026-10-02',
            'performed_time' => null,
        ]);
        $beforePath = 'assignments/legacy/fore.jpg';
        $afterPath = 'assignments/legacy/efter.jpg';
        Storage::disk('local')->put($beforePath, 'legacy before bytes');
        Storage::disk('local')->put($afterPath, 'legacy after bytes');
        $before = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $beforePath,
            'sort_order' => 1,
        ]);
        $after = $assignment->photos()->create([
            'phase' => PhotoPhase::After,
            'path' => $afterPath,
            'sort_order' => 1,
        ]);

        $version = app(CreateAssignmentReportVersion::class)->create($assignment, $user, preserveCurrentToken: true);

        $this->assertEquals([
            'before' => $before->id,
            'after' => $after->id,
            'label' => null,
            'customer_comment' => null,
        ], collect($version->snapshot['places'][0]['pairs'][0])->only([
            'before',
            'after',
            'label',
            'customer_comment',
        ])->all());
        $this->assertSame($beforePath, $before->fresh()->path);
        $this->assertSame($afterPath, $after->fresh()->path);
        $this->assertNull($before->fresh()->assignment_photo_pair_id);
        $this->assertNull($after->fresh()->assignment_photo_pair_id);
        $this->assertSame('legacy before bytes', Storage::disk('local')->get($version->photoAssets()->where('source_photo_id', $before->id)->sole()->path));
        $this->assertSame('legacy after bytes', Storage::disk('local')->get($version->photoAssets()->where('source_photo_id', $after->id)->sole()->path));

        $publicReport = $this->get(route('reports.show', ['token' => $version->token], false))->assertOk();
        preg_match('/<p class="customer-report-date">(.*?)<\/p>/s', $publicReport->getContent(), $dateMarkup);
        $this->assertArrayHasKey(1, $dateMarkup);
        $performedDate = trim(preg_replace('/\s+/', ' ', strip_tags($dateMarkup[1])) ?? '');
        $this->assertStringContainsString('2 oktober 2026', $performedDate);
        $this->assertStringNotContainsString('kl.', $performedDate);
    }

    public function test_a_non_image_gets_one_format_error_and_heic_is_converted_or_rejected_clearly(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'before_photos' => [UploadedFile::fake()->create('anteckning.txt', 12, 'text/plain')],
        ]);

        $response->assertRedirect('/uppdrag/nytt')->assertSessionHasErrors('before_photos.0');
        $errors = session('errors')?->get('before_photos.0');
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertSame(AcceptedJobPhoto::FORMAT_MESSAGE, $errors[0]);
        $this->assertStringNotContainsString('för hög upplösning', $errors[0]);
        $this->assertDatabaseCount('assignments', 0);

        $oversized = $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'before_photos' => [UploadedFile::fake()->image('stor.jpg', 8001, 20)],
        ]);
        $oversized->assertRedirect('/uppdrag/nytt')->assertSessionHasErrors('before_photos.0');
        $sizeErrors = session('errors')?->get('before_photos.0');
        $this->assertIsArray($sizeErrors);
        $this->assertCount(1, $sizeErrors);
        $this->assertStringContainsString('för hög upplösning', $sizeErrors[0]);
        $this->assertStringNotContainsString('HEIC', $sizeErrors[0]);

        $heicPath = base_path('tests/Fixtures/room.heic');
        $this->assertFileExists($heicPath);
        $heicUpload = new UploadedFile($heicPath, 'IMG_0001.heic', 'image/heic', null, true);
        $converter = (new ExecutableFinder)->find('heif-convert')
            ?? (new ExecutableFinder)->find('magick');

        $heicResponse = $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'before_photos' => [$heicUpload],
        ]);

        if ($converter === null) {
            $heicResponse->assertRedirect('/uppdrag/nytt')
                ->assertSessionHasErrors([
                    'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
                ]);
            $this->assertDatabaseCount('assignments', 0);

            return;
        }

        $heicResponse->assertRedirect();
        $photo = AssignmentPhoto::query()->firstOrFail();
        $this->assertSame(PhotoPhase::Before, $photo->phase);
        $this->assertSame('jpg', pathinfo($photo->path, PATHINFO_EXTENSION));
        $this->assertNotFalse(getimagesize(Storage::disk('local')->path($photo->path)));
    }

    public function test_pair_notes_and_company_details_stay_inside_the_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $place = $assignment->places()->create(['name' => 'Hall', 'sort_order' => 1]);
        $pair = $place->photoPairs()->create(['sort_order' => 1]);
        $foreignAssignment = Assignment::factory()->for($other)->create();
        $foreignPlace = $foreignAssignment->places()->create(['name' => 'Kök', 'sort_order' => 1]);
        $foreignPair = $foreignPlace->photoPairs()->create(['sort_order' => 1]);

        $this->actingAs($owner)
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $foreignPlace, $foreignPair], false), [
                'label' => 'Kök',
                'customer_comment' => 'Foreign customer comment',
            ])
            ->assertNotFound();
        $this->assertNull($pair->fresh()->label);
        $this->assertNull($foreignPair->fresh()->customer_comment);

        $this->actingAs($member)->put('/installningar/foretag', [
            'company_name' => 'Annat namn',
            'phone' => '070-111 22 33',
            'organization_number' => '559000-0000',
        ])->assertForbidden();
        $this->assertNull($owner->company->refresh()->phone);
        $this->assertNotSame('Annat namn', $owner->company->name);

        $this->actingAs($owner)->put('/installningar/foretag', [
            'company_name' => $owner->company->name,
            'phone' => '070-111 22 33',
            'organization_number' => '559000-0000',
        ])->assertRedirect();
        $this->assertSame('070-111 22 33', $owner->company->refresh()->phone);
        $this->get('/installningar')->assertOk()->assertSee('value="070-111 22 33"', false);
    }

    public function test_photo_controls_are_side_by_side_until_a_very_narrow_screen_and_app_buttons_have_no_arrow(): void
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $pairRule = strpos($css, ".photo-pair {\n    grid-template-columns: 1fr 1fr;");
        $narrowRule = strpos($css, '@media (max-width: 360px)');
        $hiddenInput = strpos($css, ".photo-picker input[type=\"file\"] {\n    position: absolute;");

        $this->assertNotFalse($pairRule);
        $this->assertNotFalse($narrowRule);
        $this->assertNotFalse($hiddenInput);
        $this->assertLessThan($narrowRule, $pairRule);
        $this->assertStringContainsString('clip: rect(0, 0, 0, 0);', substr($css, $hiddenInput, 280));
        $this->assertStringContainsString(".photo-pair {\n        grid-template-columns: 1fr;", substr($css, $narrowRule, 120));

        $this->get('/register')->assertOk()->assertDontSee('↗', false);

        $user = User::factory()->create();
        $this->travelTo('2026-10-16 07:30:00');
        $this->actingAs($user)->get('/uppdrag/nytt')
            ->assertOk()
            ->assertSee('>Spara och lägg till bilder</button>', false)
            ->assertSee('name="performed_time"', false)
            ->assertSee('name="performed_end_time"', false)
            ->assertSee('Starttid', false)
            ->assertSee('Sluttid', false)
            ->assertSee('type="time"', false)
            ->assertDontSee('value="09:30"', false)
            ->assertDontSee('↗', false);
    }

    public function test_a_cleaning_time_range_is_shown_in_the_report_and_an_earlier_end_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_time' => '09:00',
            'performed_end_time' => '11:30',
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $this->assertSame('09:00', substr((string) $assignment->performed_time, 0, 5));
        $this->assertSame('11:30', substr((string) $assignment->performed_end_time, 0, 5));
        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('<p>16 oktober 2026 kl. 09:00–11:30</p>', false);

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_time' => '11:30',
            'performed_end_time' => '09:00',
        ])->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors(['performed_end_time' => 'Sluttiden måste vara senare än starttiden.']);

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_end_time' => 'ab',
        ])->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors(['performed_end_time' => 'Ange en giltig tid.']);

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_end_time' => '11:30',
        ])->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors(['performed_time' => 'Ange en starttid när sluttiden är ifylld.']);

        $this->assertSame(1, Assignment::query()->count());
    }

    public function test_photo_forms_report_validation_errors_without_leaving_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/uppdrag/nytt')
            ->assertOk()
            ->assertSee('data-form-errors', false)
            ->assertDontSee('data-photos-required', false);

        $this->actingAs($user)->postJson('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_end_time' => '11:30',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.performed_time.0', 'Ange en starttid när sluttiden är ifylld.');
        $this->assertDatabaseCount('assignments', 0);

        $created = $this->actingAs($user)->postJson('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'performed_time' => '09:00',
            'performed_end_time' => '11:30',
        ])->assertOk()
            ->assertSessionHas('toast', 'Uppdraget är sparat.');

        $assignment = Assignment::query()->firstOrFail();
        $created->assertJsonPath('redirect', route('assignments.show', $assignment));

        $this->actingAs($user)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('data-photo-upload-mode="new-pairs"', false)
            ->assertSee('data-upload-submit', false);

        $this->actingAs($user)->post(route('assignments.photos.store', $assignment), [
            'before_photos' => [UploadedFile::fake()->create('anteckning.txt', 12, 'text/plain')],
        ], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['before_photos.0' => AcceptedJobPhoto::FORMAT_MESSAGE]);
        $this->assertSame(0, $assignment->photos()->count());

        $process = new Process(['node', 'tests/js/job-form.mjs'], base_path());
        $process->mustRun();
        $this->assertStringContainsString('ok', $process->getOutput());
    }

    public function test_the_report_footer_formats_a_swedish_mobile_and_the_company_name_can_be_edited(): void
    {
        $owner = User::factory()->create();
        $owner->company->update([
            'name' => 'Gammalt namn',
            'phone' => null,
            'organization_number' => null,
        ]);
        $assignment = Assignment::factory()->for($owner)->create([
            'performed_on' => '2026-10-16',
        ]);

        $this->put('/installningar/foretag', [
            'company_name' => 'Företag',
        ])->assertRedirect(route('login'));
        $this->assertSame('Gammalt namn', $owner->company->refresh()->name);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('Företagsnamn', false)
            ->assertSee('name="company_name"', false)
            ->assertSee('value="Gammalt namn"', false);

        $this->actingAs($owner)->from('/installningar')->put('/installningar/foretag', [
            'company_name' => '   ',
            'phone' => '0700000095',
            'organization_number' => '000000-0000',
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors(['company_name' => 'Ange företagsnamnet.']);

        $this->actingAs($owner)->from('/installningar')->put('/installningar/foretag', [
            'company_name' => str_repeat('a', 121),
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors(['company_name' => 'Företagsnamnet får vara högst 120 tecken.']);

        $this->assertSame('Gammalt namn', $owner->company->refresh()->name);

        $this->actingAs($owner)->put('/installningar/foretag', [
            'company_name' => 'Företag',
            'phone' => '0700000095',
            'organization_number' => '000000-0000',
        ])->assertRedirect()
            ->assertSessionHas('toast', 'Företagsuppgifterna är sparade.');

        $this->assertSame('Företag', $owner->company->refresh()->name);
        $this->assertSame('0700000095', $owner->company->phone);
        $this->get('/installningar')->assertOk()->assertSee('value="Företag"', false);
        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('Företag · 070-000 00 95 · org.nr 000000-0000', false)
            ->assertSee('Kontakta Företag, 070-000 00 95.', false);

        $this->actingAs($owner)->put('/installningar/foretag', [
            'company_name' => 'Företag',
            'phone' => '+46 70 000 00 95',
            'organization_number' => '000000-0000',
        ])->assertRedirect();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('Företag · 070-000 00 95 · org.nr 000000-0000', false);

        $this->actingAs($owner)->put('/installningar/foretag', [
            'company_name' => 'Företag',
            'phone' => '08-12 34 56',
            'organization_number' => '',
        ])->assertRedirect();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('Företag · 08-12 34 56', false)
            ->assertDontSee('org.nr', false);
    }

    public function test_a_real_heic_fixture_decodes_in_the_client_converter(): void
    {
        $fixture = base_path('tests/Fixtures/room.heic');
        $this->assertFileExists($fixture);
        $head = file_get_contents($fixture, false, null, 0, 32);
        $this->assertIsString($head);
        $this->assertTrue(str_contains($head, 'ftypheic') || str_contains($head, 'ftypmif1'));

        $script = file_get_contents(resource_path('js/site.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.', $script);
        $this->assertStringNotContainsString('Leave the original file', $script);

        $process = new Process(['node', 'tests/js/heic-fixture.mjs'], base_path());
        $process->setTimeout(30);
        $process->mustRun();
        $this->assertStringContainsString('ok', $process->getOutput());
    }

    public function test_a_job_is_not_saved_when_selected_photos_are_missing_from_the_upload(): void
    {
        $user = User::factory()->create();
        $payload = [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
        ];

        $this->actingAs($user)->postJson('/uppdrag', [
            ...$payload,
            'selected_before_count' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['before_photos' => StoreAssignmentRequest::PHOTOS_NOT_ATTACHED]);
        $this->assertDatabaseCount('assignments', 0);

        $this->actingAs($user)->post('/uppdrag', [
            ...$payload,
            'selected_before_count' => 2,
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['before_photos' => StoreAssignmentRequest::PHOTOS_NOT_ATTACHED]);
        $this->assertDatabaseCount('assignments', 0);

        $created = $this->actingAs($user)->post('/uppdrag', [
            ...$payload,
            'selected_before_count' => 1,
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
        ], ['Accept' => 'application/json'])->assertOk();

        $assignment = Assignment::query()->firstOrFail();
        $created->assertJsonPath('redirect', route('assignments.show', $assignment));
        $this->assertSame(1, $assignment->photos()->count());

        $this->actingAs($user)->post(route('assignments.photos.store', $assignment), [
            'selected_after_count' => 1,
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['after_photos' => StoreAssignmentRequest::PHOTOS_NOT_ATTACHED]);
        $this->assertSame(1, $assignment->photos()->count());

        $script = file_get_contents(resource_path('js/site.js'));
        $this->assertIsString($script);
        $this->assertStringNotContainsString("dataset.keepPhotos === 'true'", $script);
        $this->assertStringNotContainsString("dataset.photosRequired === 'true'", $script);
        $formScript = file_get_contents(resource_path('js/job-form.js'));
        $this->assertIsString($formScript);
        $this->assertStringContainsString('bilder valda – max 10 per uppdrag. Ta bort', $formScript);
    }
}
