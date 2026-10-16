<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_upload_returns_a_swedish_max_string_message_instead_of_the_raw_key(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('assignments.photos.store', $assignment, false), [
                'place_name' => str_repeat('A', 121),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.place_name.0', 'Plats får inte vara större än 120 tecken.');
    }

    public function test_polish_locale_translates_the_string_max_rule(): void
    {
        app()->setLocale('pl');

        $message = validator(
            ['customer_name' => str_repeat('A', 121)],
            ['customer_name' => ['required', 'string', 'max:120']],
        )->errors()->first('customer_name');

        $this->assertSame('Pole nazwa klienta nie może być dłuższe niż 120 znaków.', $message);
        $this->assertStringNotContainsString('validation.', (string) $message);
    }

    public function test_swedish_and_polish_validation_cover_every_framework_message(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        foreach (['sv', 'pl'] as $locale) {
            app()->setLocale($locale);
            $this->assertValidationMessagesExist($english, 'validation');
        }
    }

    /**
     * @param  array<string, mixed>  $messages
     */
    private function assertValidationMessagesExist(array $messages, string $prefix): void
    {
        foreach ($messages as $key => $value) {
            if ($prefix === 'validation' && in_array($key, ['custom', 'attributes'], true)) {
                continue;
            }

            $fullKey = $prefix.'.'.$key;

            if (is_array($value)) {
                $this->assertValidationMessagesExist($value, $fullKey);

                continue;
            }

            $translated = __($fullKey);

            $this->assertNotSame($fullKey, $translated);
            $this->assertFalse(str_starts_with($translated, 'validation.'), $fullKey.' leaked as '.$translated);
        }
    }
}
