<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroPublisher;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Ops escape hatch for the two-person publish gate on a fresh install or a
 * single-tester environment. The admin UI still enforces reviewer ≠ publisher.
 */
final class AroPublishCommand extends Command
{
    protected $signature = 'aro:publish {code : ARO version code, e.g. LN221-2023}
                            {--reviewed-by= : email of the reviewing user}
                            {--published-by= : email of the publishing user}
                            {--force : publish without a reviewer/publisher pair (local development only)}';

    protected $description = 'Mark an ARO version reviewed and published so bills can be drawn on it';

    public function handle(AroPublisher $publisher): int
    {
        $version = AroVersion::query()->where('code', $this->argument('code'))->first();
        if ($version === null) {
            $this->error("No ARO version with code {$this->argument('code')}");

            return self::FAILURE;
        }

        if ($version->isPublished()) {
            $this->info("{$version->code} is already published");

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            if (app()->isProduction()) {
                $this->error('--force is not allowed in production; use the admin console two-person gate');

                return self::FAILURE;
            }

            $version->forceFill(['status' => AroVersion::STATUS_PUBLISHED, 'published_at' => now()])->save();
            $this->warn("{$version->code} force-published without review (non-production only)");

            return self::SUCCESS;
        }

        $reviewer = $this->userByEmail((string) $this->option('reviewed-by'), 'reviewed-by');
        $publisherUser = $this->userByEmail((string) $this->option('published-by'), 'published-by');
        if ($reviewer === null || $publisherUser === null) {
            return self::FAILURE;
        }

        try {
            $publisher->markReviewed($version, $reviewer);
            $publisher->publish($version, $publisherUser);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$version->code} reviewed by {$reviewer->email}, published by {$publisherUser->email}");

        return self::SUCCESS;
    }

    private function userByEmail(string $email, string $option): ?User
    {
        if ($email === '') {
            $this->error("--{$option} is required (or pass --force outside production)");

            return null;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user with email {$email} for --{$option}");
        }

        return $user;
    }
}
