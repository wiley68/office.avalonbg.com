<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'must_change_password', 'status', 'appearance', 'access_encryption_key'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'access_encryption_key'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if (! $user->wasChanged('status') || $user->isActive()) {
                return;
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($user->remember_token !== null) {
                $user->forceFill(['remember_token' => null])->saveQuietly();
            }
        });
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    /**
     * @return HasMany<Access, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * @return HasMany<ProfitEntry, $this>
     */
    public function profitEntries(): HasMany
    {
        return $this->hasMany(ProfitEntry::class);
    }

    /**
     * @return HasOne<UserImapAccount, $this>
     */
    public function imapAccount(): HasOne
    {
        return $this->hasOne(UserImapAccount::class);
    }

    public function primaryRole(): ?UserRole
    {
        $role = $this->getRoleNames()->first();

        return $role !== null ? UserRole::tryFrom($role) : null;
    }

    public function isProfiler(): bool
    {
        return $this->hasRole(UserRole::Profiler->value);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    public function isOfficeUser(): bool
    {
        return $this->hasRole(UserRole::User->value);
    }

    public function canManageUsers(): bool
    {
        return $this->isProfiler() || $this->isAdmin();
    }

    public function hasOfficeAccess(): bool
    {
        return $this->isAdmin() || $this->isOfficeUser();
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
            'must_change_password' => 'boolean',
            'status' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
