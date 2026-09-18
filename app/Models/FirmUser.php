<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $firm_id
 * @property int $user_id
 * @property string $role
 * @property int|null $hourly_rate_cents
 */
final class FirmUser extends Pivot
{
    use HasUuids;

    protected $table = 'firm_user';

    public $incrementing = false;

    protected $keyType = 'string';
}
