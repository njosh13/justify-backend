<?php

declare(strict_types=1);

use App\Domain\Aro\Models\AroVersion;
use App\Enums\FirmRole;
use App\Models\Firm;

beforeEach(fn () => seedAro());

it('requires a different user to publish than the one who reviewed', function () {
    $firm = Firm::factory()->create();
    $version = AroVersion::firstOrFail();

    actingAsMemberOf($firm);
    $this->post(route('admin.aro.review', $version))->assertRedirect(route('admin.aro.index'))->assertSessionHasNoErrors();
    expect($version->refresh()->status)->toBe('reviewed');

    $this->post(route('admin.aro.publish', $version))
        ->assertSessionHasErrors('publish');
    expect(sessionError('publish'))->toContain('second person');

    actingAsMemberOf($firm, FirmRole::Admin);
    $this->post(route('admin.aro.publish', $version))->assertSessionHasNoErrors();
    expect($version->refresh()->isPublished())->toBeTrue();
});

it('will not publish an unreviewed version', function () {
    actingAsMemberOf(Firm::factory()->create());

    $this->post(route('admin.aro.publish', AroVersion::firstOrFail()))
        ->assertSessionHasErrors('publish');
    expect(sessionError('publish'))->toContain('reviewed before');
});

it('forbids advocates from reviewing or publishing', function () {
    actingAsMemberOf(Firm::factory()->create(), FirmRole::Advocate);
    $version = AroVersion::firstOrFail();

    $this->post(route('admin.aro.review', $version))->assertForbidden();
    $this->post(route('admin.aro.publish', $version))->assertForbidden();
    $this->get(route('admin.aro.show', $version))->assertOk();
});

it('force-publishes from the console outside production', function () {
    $this->artisan('aro:publish', ['code' => 'LN221-2023', '--force' => true])->assertSuccessful();

    expect(AroVersion::firstOrFail()->isPublished())->toBeTrue();
});

it('publishes from the console with a reviewer and a different publisher', function () {
    $firm = Firm::factory()->create();
    $reviewer = actingAsMemberOf($firm);
    $publisher = actingAsMemberOf($firm, FirmRole::Admin);

    $this->artisan('aro:publish', ['code' => 'LN221-2023', '--reviewed-by' => $reviewer->email, '--published-by' => $reviewer->email])->assertFailed();
    $this->artisan('aro:publish', ['code' => 'LN221-2023', '--reviewed-by' => $reviewer->email, '--published-by' => $publisher->email])->assertSuccessful();

    expect(AroVersion::firstOrFail()->published_by)->toBe($publisher->id);
});
