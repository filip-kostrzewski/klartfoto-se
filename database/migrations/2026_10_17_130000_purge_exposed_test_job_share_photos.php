<?php

use App\Actions\PurgeExposedTestJobPhotos;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Revoke public share links and delete stored photos for one identified test job.
     *
     * Forge deploys run `php artisan migrate --force`, which is what executes this.
     * Deleted files and revoked tokens cannot be restored.
     */
    public function up(): void
    {
        app(PurgeExposedTestJobPhotos::class)->handle();
    }

    public function down(): void
    {
        // Private files and share tokens removed by up() are not restored.
    }
};
