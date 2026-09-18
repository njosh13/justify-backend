<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

final class BillCannotBeIssuedException extends RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message, public readonly string $reason, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
