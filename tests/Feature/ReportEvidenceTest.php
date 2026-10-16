<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\User;
use App\PhotoPhase;
use App\Rules\AcceptedJobPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 80, 60)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 80, 60)],
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $before = $assignment->photos()->where('phase', PhotoPhase::Before)->firstOrFail();
        $after = $assignment->photos()->where('phase', PhotoPhase::After)->firstOrFail();
        $this->assertNotNull($before->assignment_photo_pair_id);
        $this->assertSame($before->assignment_photo_pair_id, $after->assignment_photo_pair_id);

        $this->actingAs($user)->put(route('assignments.pairs.update', $assignment), [
            'pair_key' => (string) $before->assignment_photo_pair_id,
            'pair_id' => $before->assignment_photo_pair_id,
            'before_photo_id' => $before->id,
            'after_photo_id' => $after->id,
            'room_name' => 'Kök',
            'note' => 'Bänken är ren.',
        ])->assertRedirect()
            ->assertSessionHas('toast', 'Uppgifterna om bildparet är sparade.');

        $report = $this->get('/r/'.$assignment->public_token)->assertOk();
        $report->assertSee('16 oktober 2026 kl. 14:30', false)
            ->assertSee('Kök', false)
            ->assertSee('Bänken är ren.', false)
            ->assertSee('Den som har länken kan se bilderna. Kontakta Kriti Städ.', false)
            ->assertSee('Rapporten skapades 16 oktober 2026 kl. 09:30', false)
            ->assertSee('>Före</figcaption>', false)
            ->assertSee('>Efter</figcaption>', false)
            ->assertDontSee('org.nr', false)
            ->assertDontSee('070', false);

        $user->company->update([
            'phone' => '070-111 22 33',
            'organization_number' => '559000-0000',
        ]);

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('Den som har länken kan se bilderna. Kontakta Kriti Städ, 070-111 22 33.', false)
            ->assertSee('org.nr 559000-0000', false)
            ->assertSee('070-111 22 33', false);

        $this->actingAs($user)->put('/installningar/foretag', [
            'phone' => '   ',
            'organization_number' => '',
        ])->assertRedirect()
            ->assertSessionHas('toast', 'Företagsuppgifterna är sparade.');

        $this->assertNull($user->company->refresh()->reportPhone());
        $this->assertNull($user->company->reportOrganizationNumber());
        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertDontSee('070-111 22 33', false)
            ->assertDontSee('org.nr', false)
            ->assertDontSee('559000-0000', false);
    }

    public function test_a_room_note_can_be_added_to_photos_that_were_saved_before_pairs_existed(): void
    {
        $user = User::factory()->create();
        $assignment = Assignment::factory()->for($user)->create([
            'performed_on' => '2026-10-02',
            'performed_time' => null,
        ]);
        $before = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/legacy/fore.jpg',
            'sort_order' => 1,
        ]);
        $after = $assignment->photos()->create([
            'phase' => PhotoPhase::After,
            'path' => 'assignments/legacy/efter.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->put(route('assignments.pairs.update', $assignment), [
            'pair_key' => 'new-0',
            'before_photo_id' => $before->id,
            'after_photo_id' => $after->id,
            'room_name' => 'Hall',
            'note' => '',
        ])->assertRedirect();

        $before->refresh();
        $after->refresh();
        $this->assertSame('assignments/legacy/fore.jpg', $before->path);
        $this->assertSame('assignments/legacy/efter.jpg', $after->path);
        $this->assertNotNull($before->assignment_photo_pair_id);
        $this->assertSame($before->assignment_photo_pair_id, $after->assignment_photo_pair_id);
        $this->assertSame('Hall', $before->pair?->room_name);
        $this->assertNull($before->pair?->note);

        $legacyReport = $this->get('/r/'.$assignment->public_token)->assertOk()->getContent();
        $this->assertStringContainsString('<p>2 oktober 2026</p>', $legacyReport);
        $this->assertStringNotContainsString('2 oktober 2026 kl.', $legacyReport);
        $this->assertStringContainsString('Hall', $legacyReport);
    }

    public function test_a_non_image_gets_one_format_error_and_a_heic_file_is_stored_as_jpeg(): void
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

        $jpeg = sys_get_temp_dir().'/klart-evidence-src.jpg';
        $heic = sys_get_temp_dir().'/klart-evidence-src.heic';
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $jpeg);
        imagedestroy($image);
        $process = new Process(['heif-enc', $jpeg, '-o', $heic]);
        $process->mustRun();

        try {
            $this->actingAs($user)->post('/uppdrag', [
                'customer_name' => 'Anna Andersson',
                'address' => 'Storgatan 1, 111 22 Stockholm',
                'performed_on' => '2026-10-16',
                'before_photos' => [new UploadedFile($heic, 'IMG_0001.heic', 'image/heic', null, true)],
            ])->assertRedirect();
        } finally {
            @unlink($jpeg);
            @unlink($heic);
        }

        $photo = AssignmentPhoto::query()->firstOrFail();
        $this->assertSame(PhotoPhase::Before, $photo->phase);
        [$width, $height, $type] = getimagesize(Storage::disk('local')->path($photo->path));
        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertSame(40, $width);
        $this->assertSame(30, $height);
    }

    public function test_pair_notes_and_company_details_stay_inside_the_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $before = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/legacy/fore.jpg',
            'sort_order' => 1,
        ]);

        $this->actingAs($other)->put(route('assignments.pairs.update', $assignment), [
            'pair_key' => 'new-0',
            'before_photo_id' => $before->id,
            'room_name' => 'Kök',
        ])->assertNotFound();
        $this->assertNull($before->refresh()->assignment_photo_pair_id);

        $this->actingAs($member)->put('/installningar/foretag', [
            'phone' => '070-111 22 33',
            'organization_number' => '559000-0000',
        ])->assertForbidden();
        $this->assertNull($owner->company->refresh()->phone);

        $this->actingAs($owner)->put('/installningar/foretag', [
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
        $this->actingAs($user)->get('/uppdrag/nytt')
            ->assertOk()
            ->assertSee('>Spara uppdraget</button>', false)
            ->assertSee('name="performed_time"', false)
            ->assertDontSee('↗', false);
    }
}
