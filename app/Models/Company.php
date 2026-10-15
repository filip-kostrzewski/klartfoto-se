<?php

namespace App\Models;

use App\CompanyPlan;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'plan', 'trial_ends_at', 'logo_path'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => CompanyPlan::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    public function allowsTeamFeatures(): bool
    {
        return $this->onTrial() || $this->plan === CompanyPlan::Team;
    }

    public function seatLimit(): int
    {
        return $this->allowsTeamFeatures()
            ? (int) config('klartfoto.team_seat_limit', 10)
            : (int) config('klartfoto.start_seat_limit', 1);
    }
}
