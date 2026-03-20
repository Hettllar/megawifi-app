<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'parent_id',
        'commission_rate',
        'balance',
        'max_subscribers',
        'company_name',
        'address',
        'is_active',
        'phone',
        'last_login_at',
        'last_login_ip',
        'can_view_hotspot_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'can_view_hotspot_password' => 'boolean',
            'commission_rate' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    // Relationships
    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(Router::class, 'router_admins')
            ->withPivot(['role', 'can_add_users', 'can_delete_users', 'can_edit_users', 'can_view_reports', 'can_manage_hotspot', 'can_manage_ppp'])
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function createdSubscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class, 'created_by');
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    // Reseller Relationships
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function resellerSubscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class, 'reseller_id');
    }

    public function resellerPermissions(): HasMany
    {
        return $this->hasMany(ResellerRouterPermission::class, 'reseller_id');
    }

    public function resellerTransactions(): HasMany
    {
        return $this->hasMany(ResellerTransaction::class, 'reseller_id');
    }

    public function iptvSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(IptvSubscription::class);
    }

    // Get reseller permission for specific router
    public function getResellerPermission(Router $router): ?ResellerRouterPermission
    {
        return $this->resellerPermissions()->where('router_id', $router->id)->first();
    }

    // Check specific permission for reseller
    public function hasResellerPermission(Router $router, string $permission): bool
    {
        if (!$this->isReseller()) {
            return false;
        }

        $perm = $this->getResellerPermission($router);
        return $perm && $perm->$permission;
    }

    // Role Checks
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'operator']);
    }

    // Router Access Checks
    public function hasAccessToRouter(Router $router): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->routers()->where('routers.id', $router->id)->exists();
    }

    public function canManageRouter(Router $router, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $pivot = $this->routers()->where('routers.id', $router->id)->first()?->pivot;
        
        if (!$pivot) {
            return false;
        }

        return $pivot->$permission ?? false;
    }

    // Get accessible routers
    public function getAccessibleRouters()
    {
        if ($this->isSuperAdmin()) {
            return Router::active()->get();
        }

        return $this->routers()->where('is_active', true)->get();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['super_admin', 'admin']);
    }

    public function scopeResellers($query)
    {
        return $query->where('role', 'reseller');
    }

    // Reseller Methods
    public function canAddMoreSubscribers(): bool
    {
        if (!$this->isReseller()) {
            return true;
        }

        if (is_null($this->max_subscribers)) {
            return true;
        }

        return $this->resellerSubscribers()->count() < $this->max_subscribers;
    }

    public function getRemainingSubscribersSlots(): ?int
    {
        if (!$this->isReseller() || is_null($this->max_subscribers)) {
            return null;
        }

        return max(0, $this->max_subscribers - $this->resellerSubscribers()->count());
    }

    public function addBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function deductBalance(float $amount): bool
    {
        if ($this->balance < $amount) {
            return false;
        }
        
        $this->decrement('balance', $amount);
        return true;
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'super_admin' => 'مدير عام',
            'admin' => 'مدير',
            'reseller' => 'وكيل/بائع',
            'operator' => 'مشغّل',
            'viewer' => 'مشاهد',
            default => 'مستخدم',
        };
    }
}
