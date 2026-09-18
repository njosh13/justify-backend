<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FirmRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $current_firm_id
 * @property string|null $lsk_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'lsk_number'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsToMany<Firm, $this, FirmUser> */
    public function firms(): BelongsToMany
    {
        return $this->belongsToMany(Firm::class)
            ->using(FirmUser::class)
            ->withPivot(['role', 'hourly_rate_cents'])
            ->withTimestamps();
    }

    /**
     * The firm this user acts for: their chosen firm while still a member,
     * otherwise their first membership.
     */
    public function resolveCurrentFirm(): ?Firm
    {
        $firms = $this->firms()->orderBy('firm_user.created_at')->get();

        return $firms->firstWhere('id', $this->current_firm_id) ?? $firms->first();
    }

    public function roleIn(Firm $firm): ?FirmRole
    {
        $membership = $this->firms()->whereKey($firm->id)->first();
        $role = $membership?->getRelationValue('pivot')?->getAttribute('role');

        return is_string($role) ? FirmRole::from($role) : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
