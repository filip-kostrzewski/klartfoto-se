<?php

namespace App\Models;

use App\PhotoPhase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_photo_pair_id', 'phase', 'path', 'sort_order', 'uploaded_by_user_id', 'client_upload_id', 'upload_sha256'])]
#[Hidden(['client_upload_id', 'upload_sha256'])]
class AssignmentPhoto extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => PhotoPhase::class,
            'sort_order' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function photoPair(): BelongsTo
    {
        return $this->belongsTo(AssignmentPhotoPair::class, 'assignment_photo_pair_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
