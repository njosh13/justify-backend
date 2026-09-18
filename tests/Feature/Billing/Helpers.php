<?php

declare(strict_types=1);

use App\Domain\Aro\Models\AroVersion;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\CurrentFirm;

/** Seed the catalogue and publish it so bills can be drawn on it. */
function seedPublishedAro(): AroVersion
{
    seedAro();

    $version = AroVersion::firstOrFail();
    $version->forceFill(['status' => AroVersion::STATUS_PUBLISHED, 'published_at' => now()])->save();

    return $version;
}

/** Sign in as a member of a firm and make it the request's current firm. */
function actingAsMemberOf(Firm $firm, FirmRole $role = FirmRole::Owner): User
{
    $user = User::factory()->create(['current_firm_id' => $firm->id]);
    $firm->users()->attach($user, ['role' => $role->value]);

    test()->actingAs($user);
    app(CurrentFirm::class)->set($firm);

    return $user;
}

/** First validation message flashed for a field on the last request. */
function sessionError(string $field): string
{
    return (string) (session('errors')?->get($field)[0] ?? '');
}
