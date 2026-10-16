<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentPhotoPair;
use App\Models\AssignmentPlace;
use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use App\Models\Company;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAssignmentReportVersion
{
    public function create(Assignment $assignment, User $creator, bool $preserveCurrentToken = false): AssignmentReportVersion
    {
        return DB::transaction(function () use ($assignment, $creator, $preserveCurrentToken): AssignmentReportVersion {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedAssignment->company_id !== null
                    && $lockedAssignment->company_id === $creator->company_id,
                404,
            );

            $lockedCompany = Company::query()
                ->whereKey($lockedAssignment->company_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAssignment->setRelation('company', $lockedCompany);
            $lockedAssignment->loadMissing(['places.photoPairs.photos', 'photos']);

            $versionNumber = ((int) $lockedAssignment->reportVersions()->max('version_number')) + 1;
            $snapshot = $this->snapshotData($lockedAssignment);
            $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existingVersions = $versionNumber > 1;
            $token = $preserveCurrentToken && ! $existingVersions
                ? $lockedAssignment->public_token
                : $this->newToken();

            $version = $lockedAssignment->reportVersions()->create([
                'version_number' => $versionNumber,
                'token' => $token,
                'status' => AssignmentReportVersion::STATUS_READY,
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', $snapshotJson),
                'created_by_user_id' => $creator->id,
            ]);

            foreach ($snapshot['assets'] as $asset) {
                $version->assets()->create([
                    'type' => $asset['type'],
                    'source_photo_id' => $asset['source_photo_id'],
                    'phase' => $asset['phase'],
                    'place_name' => $asset['place_name'],
                    'place_sort_order' => $asset['place_sort_order'],
                    'pair_sort_order' => $asset['pair_sort_order'],
                    'path' => $asset['path'],
                ]);
            }

            $lockedAssignment->forceFill([
                'public_token' => $token,
                'status' => $lockedAssignment->photos->isNotEmpty() ? 'ready' : 'draft',
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ])->save();

            return $version;
        }, attempts: 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotData(Assignment $assignment): array
    {
        $assignment->loadMissing(['company', 'places.photoPairs.photos', 'photos']);
        $places = [];
        $assets = [];

        foreach ($assignment->places as $place) {
            $pairs = [];

            foreach ($place->photoPairs as $pair) {
                $phasePhotoIds = ['before' => null, 'after' => null];

                foreach ($pair->photos as $photo) {
                    $phase = $photo->phase->value;
                    $phasePhotoIds[$phase] = $photo->id;
                    $assets[] = $this->photoAssetData($photo, $place, $pair);
                }

                $pairs[] = [
                    'id' => (int) $pair->id,
                    'label' => $pair->label,
                    'customer_comment' => $pair->customer_comment,
                    'sort_order' => (int) $pair->sort_order,
                    'before' => $phasePhotoIds['before'],
                    'after' => $phasePhotoIds['after'],
                ];
            }

            $places[] = [
                'id' => (int) $place->id,
                'name' => $place->name,
                'sort_order' => (int) $place->sort_order,
                'pairs' => $pairs,
            ];
        }

        if ($places === [] && $assignment->photos->isNotEmpty()) {
            $places = $this->legacyPhotoPlaces($assignment, $assets);
        }

        $logoPath = $assignment->company?->allowsTeamFeatures()
            ? $assignment->company?->logo_path
            : null;
        if (filled($logoPath)) {
            $assets[] = [
                'type' => AssignmentReportVersionAsset::TYPE_LOGO,
                'source_photo_id' => null,
                'phase' => null,
                'place_name' => null,
                'place_sort_order' => null,
                'pair_sort_order' => null,
                'path' => $logoPath,
            ];
        }

        return [
            'schema_version' => 1,
            'company_name' => $assignment->company?->name ?? '',
            'company_phone' => $assignment->company?->reportPhone(),
            'company_organization_number' => $assignment->company?->reportOrganizationNumber(),
            'company_logo_path' => $logoPath,
            'customer_name' => $assignment->customer_name,
            'address' => $assignment->address,
            'performed_on' => $assignment->performed_on?->toDateString(),
            'performed_time' => $this->clockTime($assignment->performed_time),
            'performed_end_time' => $this->clockTime($assignment->performed_end_time),
            'performed_time_label' => $assignment->performedTimeLabel(),
            'report_created_at' => $assignment->created_at?->toDateTimeString(),
            'places' => $places,
            'assets' => $assets,
        ];
    }

    public function hasUnpublishedDraftChanges(Assignment $assignment, ?AssignmentReportVersion $version): bool
    {
        if ($version === null) {
            return false;
        }

        $snapshotJson = json_encode(
            $this->normalizeSnapshotForComparison($this->snapshotData($assignment)),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $versionSnapshotJson = json_encode(
            $this->normalizeSnapshotForComparison($version->snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return ! hash_equals(hash('sha256', $versionSnapshotJson), hash('sha256', $snapshotJson));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeSnapshotForComparison(array $snapshot): array
    {
        foreach ([
            'performed_time',
            'performed_end_time',
            'performed_time_label',
            'company_phone',
            'company_organization_number',
            'report_created_at',
        ] as $key) {
            $snapshot[$key] ??= null;
        }

        if (is_string($snapshot['report_created_at'])) {
            $snapshot['report_created_at'] = Carbon::parse($snapshot['report_created_at'])
                ->timezone(config('app.timezone'))
                ->toDateTimeString();
        }

        foreach ($snapshot['places'] ?? [] as $placeIndex => $place) {
            if (! is_array($place)) {
                continue;
            }

            foreach ($place['pairs'] ?? [] as $pairIndex => $pair) {
                if (is_array($pair)) {
                    $snapshot['places'][$placeIndex]['pairs'][$pairIndex]['customer_comment'] ??= null;
                }
            }
        }

        return $this->sortSnapshotObjectKeys($snapshot);
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @return array<string|int, mixed>
     */
    private function sortSnapshotObjectKeys(array $value): array
    {
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $value[$index] = $this->sortSnapshotObjectKeys($item);
                }
            }

            return $value;
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortSnapshotObjectKeys($item);
            }
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $assets
     * @return list<array<string, mixed>>
     */
    private function legacyPhotoPlaces(Assignment $assignment, array &$assets): array
    {
        $before = $assignment->photos->where('phase', PhotoPhase::Before)->values();
        $after = $assignment->photos->where('phase', PhotoPhase::After)->values();
        $pairs = [];

        for ($index = 0; $index < max($before->count(), $after->count()); $index++) {
            $beforePhoto = $before->get($index);
            $afterPhoto = $after->get($index);
            $pairs[] = [
                'id' => null,
                'label' => null,
                'customer_comment' => null,
                'sort_order' => $index + 1,
                'before' => $beforePhoto?->id,
                'after' => $afterPhoto?->id,
            ];

            foreach ([[$beforePhoto, PhotoPhase::Before], [$afterPhoto, PhotoPhase::After]] as [$photo, $phase]) {
                if ($photo instanceof AssignmentPhoto) {
                    $assets[] = [
                        ...$this->photoAssetData($photo, null, null),
                        'phase' => $phase->value,
                        'place_name' => 'Övrigt',
                        'place_sort_order' => 0,
                        'pair_sort_order' => $index + 1,
                    ];
                }
            }
        }

        return [[
            'id' => null,
            'name' => 'Övrigt',
            'sort_order' => 0,
            'pairs' => $pairs,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoAssetData(AssignmentPhoto $photo, ?AssignmentPlace $place, ?AssignmentPhotoPair $pair): array
    {
        return [
            'type' => AssignmentReportVersionAsset::TYPE_PHOTO,
            'source_photo_id' => (int) $photo->id,
            'phase' => $photo->phase->value,
            'place_name' => $place?->name ?? 'Övrigt',
            'place_sort_order' => $place?->sort_order ?? 0,
            'pair_sort_order' => $pair?->sort_order ?? $photo->sort_order,
            'path' => $photo->path,
        ];
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(48);
        } while (AssignmentReportVersion::query()->where('token', $token)->exists());

        return $token;
    }

    private function clockTime(mixed $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
    }
}
