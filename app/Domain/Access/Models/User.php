<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property string|null $phone_e164
 * @property string $locale
 * @property string|null $timezone
 * @property string $digits
 * @property bool $show_hijri
 * @property string $status
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[UseFactory(UserFactory::class)]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasPublicUlid, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Mirrors the column defaults so freshly created models diff correctly in the audit log.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone_e164' => null,
        'timezone' => null,
        'last_login_at' => null,
        'locale' => 'en',
        'digits' => 'latn',
        'show_hijri' => false,
        'status' => 'active',
    ];

    /**
     * Login bookkeeping is recorded as auth events, not model diffs.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['last_login_at'];
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
            'last_login_at' => 'datetime',
            'show_hijri' => 'boolean',
        ];
    }
}
