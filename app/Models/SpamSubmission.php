<?php

namespace App\Models;

use Database\Factories\SpamSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['form', 'payload', 'reason', 'ip'])]
class SpamSubmission extends Model
{
    /** @use HasFactory<SpamSubmissionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
