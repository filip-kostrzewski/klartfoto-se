<?php

namespace App\Models;

use Database\Factories\AssignmentReportVersionAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'source_photo_id', 'phase', 'place_name', 'place_sort_order', 'pair_sort_order', 'path'])]
class AssignmentReportVersionAsset extends Model
{
    public const TYPE_PHOTO = 'photo';

    public const TYPE_LOGO = 'logo';

    /** @use HasFactory<AssignmentReportVersionAssetFactory> */
    use HasFactory;

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AssignmentReportVersion::class, 'assignment_report_version_id');
    }
}
