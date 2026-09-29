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
        'telegram_user_id',
        'telegram_chat_id',
        'telegram_linked_at',
        'avatar',
        'birth_date',
        'home_focus_preference',
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
            'telegram_linked_at' => 'datetime',
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

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('slug', 'super-admin');
        }

        return $this->roles()->where('slug', 'super-admin')->exists();
    }

    /**
     * @return array<int, string>
     */
    public function effectivePermissionSlugs(): array
    {
        if ($this->isSuperAdmin()) {
            return array_keys(config('admin-access.permissions', []));
        }

        $this->loadMissing([
            'permissions:id,slug',
            'roles.permissions:id,slug',
        ]);

        $granted = $this->permissions
            ->pluck('slug')
            ->merge($this->roles->flatMap(fn (Role $role) => $role->permissions->pluck('slug')))
            ->filter()
            ->unique()
            ->values();

        return collect(array_keys(config('admin-access.permissions', [])))
            ->filter(function (string $permission) use ($granted): bool {
                if ($granted->contains($permission)) {
                    return true;
                }

                $aliases = config("admin-access.permission_aliases.{$permission}", []);
                if (collect($aliases)->contains(fn (string $alias) => $granted->contains($alias))) {
                    return true;
                }

                $requiredAliases = config("admin-access.permission_all_aliases.{$permission}", []);

                return $requiredAliases !== []
                    && collect($requiredAliases)->every(fn (string $alias) => $granted->contains($alias));
            })
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissionSlugs(), true);
    }

    public function canAccessAdminPanel(): bool
    {
        if (! $this->is_admin || $this->status !== 'active') {
            return false;
        }

        return $this->isSuperAdmin() || $this->effectivePermissionSlugs() !== [];
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
