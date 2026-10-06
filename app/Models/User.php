<?php

namespace App\Models;

use App\Support\CompanyContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use RuntimeException;

/**
 * Users are intentionally not company-scoped. Sign-in must find the
 * account before a company context exists. Shop screens still check
 * company_id in policies and route binding.
 */
#[Fillable(['name', 'email', 'phone', 'password', 'company_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** @var array<int, string>|null */
    private ?array $permissionCodesCache = null;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->uuid) {
                $user->uuid = (string) Str::uuid();
            }

            if (! $user->company_id) {
                throw new RuntimeException('User requires a company_id.');
            }
        });

        static::saving(function (User $user): void {
            if (is_string($user->email)) {
                $user->email = Str::lower($user->email);
            }
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('company_id')) {
                throw new RuntimeException('company_id cannot be changed.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $companyId = app(CompanyContext::class)->id();

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('company_id', $companyId ?? 0)
            ->first();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Branch ids this user may access. Null means every branch of the shop.
     *
     * @return array<int, int>|null
     */
    public function restrictedBranchIds(): ?array
    {
        $ids = DB::table('branch_user')
            ->where('user_id', $this->id)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids === [] ? null : $ids;
    }

    public function hasRole(string $code): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->code === $code);
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissionCodes(), true);
    }

    /**
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        if ($this->permissionCodesCache !== null) {
            return $this->permissionCodesCache;
        }

        $this->loadMissing('roles.permissions');

        return $this->permissionCodesCache = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->values()
            ->all();
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCodesCache = null;
        $this->unsetRelation('roles');
    }
}
