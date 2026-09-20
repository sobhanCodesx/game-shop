<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'google_id',
        'username',
        'phone',
        'avatar',
        'birth_date',
        'wallet_balance',
        'status',
        'role',
        'is_admin',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'wallet_balance' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        if ($this->role === 'super-admin') {
            return true;
        }

        return $this->roles()->where('slug', 'super-admin')->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $aliases = config("admin-access.permission_aliases.{$permission}", []);
        $candidates = array_values(array_unique([$permission, ...$aliases]));

        if ($this->permissions()->whereIn('slug', $candidates)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('slug', $candidates))
            ->exists();
    }

    public function canAccessAdminPanel(): bool
    {
        if (! $this->is_admin || $this->status !== 'active') {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions()->exists()
            || $this->roles()->whereHas('permissions')->exists();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function contentReactions(): HasMany
    {
        return $this->hasMany(SocialContentReaction::class);
    }

    public function savedContent(): BelongsToMany
    {
        return $this->belongsToMany(SocialContent::class, 'social_content_saves')->withTimestamps();
    }

    public function socialComments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }

    public function videoWatchProgress(): HasMany
    {
        return $this->hasMany(VideoWatchProgress::class);
    }

    public function mobileDevices(): HasMany
    {
        return $this->hasMany(MobileDevice::class);
    }

    public function mobileAccessTokens(): HasMany
    {
        return $this->hasMany(MobileAccessToken::class);
    }

    public function subscribedGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_subscriptions')->withTimestamps();
    }

    public function contentNotificationPreference(): HasOne
    {
        return $this->hasOne(ContentNotificationPreference::class);
    }
}
