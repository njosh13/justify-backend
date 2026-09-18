<?php

declare(strict_types=1);

namespace App\Domain\Aro\Services;

use App\Domain\Aro\Models\AroVersion;
use App\Models\User;
use InvalidArgumentException;

/**
 * Two-person publish gate (plan §3.2, §10): one user reviews the data entry,
 * a different user publishes. Only a published version can be used on bills.
 */
final class AroPublisher
{
    public function markReviewed(AroVersion $version, User $reviewer): AroVersion
    {
        if ($version->isPublished()) {
            throw new InvalidArgumentException("{$version->code} is already published");
        }

        $version->forceFill([
            'status' => AroVersion::STATUS_REVIEWED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        return $version;
    }

    public function publish(AroVersion $version, User $publisher): AroVersion
    {
        if ($version->isPublished()) {
            throw new InvalidArgumentException("{$version->code} is already published");
        }

        if ($version->status !== AroVersion::STATUS_REVIEWED || $version->reviewed_by === null) {
            throw new InvalidArgumentException("{$version->code} must be reviewed before it can be published");
        }

        if ($version->reviewed_by === $publisher->id) {
            throw new InvalidArgumentException('The reviewer cannot also publish; a second person must publish this version');
        }

        $version->forceFill([
            'status' => AroVersion::STATUS_PUBLISHED,
            'published_by' => $publisher->id,
            'published_at' => now(),
        ])->save();

        return $version;
    }
}
