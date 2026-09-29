<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserAccessRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'role' => ['nullable', 'string', 'max:40'],
            'admin' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'in:12,24,48'],
        ]);
        $actor = $request->user();
        $actorPermissions = collect($actor->effectivePermissionSlugs());
        $protectedPermissions = collect(config('admin-access.protected_permissions', []));

        $users = User::query()->with([
            'permissions:id,name,slug,group',
            'roles.permissions:id,slug',
        ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            })->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(isset($filters['admin']), fn ($query) => $query->where('is_admin', (bool) $filters['admin']))
            ->latest('id')->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->through(fn (User $user) => [
                ...$user->only(['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'username', 'status', 'role', 'is_admin', 'wallet_balance']),
                'email_verified' => (bool) $user->email_verified_at,
                'phone_verified' => (bool) $user->phone_verified_at,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'permission_ids' => $user->permissions->pluck('id'),
                'can_access_admin' => $user->canAccessAdminPanel(),
                'manageable' => $actor->isSuperAdmin() || (
                    ! $user->isSuperAdmin()
                    && collect($user->effectivePermissionSlugs())->diff($actorPermissions)->isEmpty()
                    && collect($user->effectivePermissionSlugs())->intersect($protectedPermissions)->isEmpty()
                ),
                'orders_count' => $user->orders()->count(),
            ]),
            'roles' => Role::query()
                ->with('permissions:id,slug')
                ->orderBy('id')
                ->get(['id', 'name', 'slug'])
                ->map(function (Role $role) use ($actor, $actorPermissions, $protectedPermissions): array {
                    $rolePermissionSlugs = $role->permissions->pluck('slug');

                    return [
                        ...$role->toArray(),
                        'permission_ids' => $role->permissions->pluck('id'),
                        'assignable' => $actor->isSuperAdmin() || (
                            $role->slug !== 'super-admin'
                            && $rolePermissionSlugs->diff($actorPermissions)->isEmpty()
                            && $rolePermissionSlugs->intersect($protectedPermissions)->isEmpty()
                        ),
                    ];
                }),
            'permissions' => Permission::query()
                ->orderBy('group')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'group'])
                ->map(fn (Permission $permission) => [
                    ...$permission->toArray(),
                    'assignable' => $actor->isSuperAdmin() || (
                        ! $protectedPermissions->contains($permission->slug)
                        && $actorPermissions->contains($permission->slug)
                    ),
                ])
                ->groupBy('group'),
            'filters' => [...$filters, 'search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'role' => $filters['role'] ?? '', 'admin' => $filters['admin'] ?? ''],
        ]);
    }

    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $actorPermissions = collect($actor->effectivePermissionSlugs());
        $protectedPermissions = collect(config('admin-access.protected_permissions', []));
        $targetPermissions = collect($user->effectivePermissionSlugs());

        if (
            ! $actor->isSuperAdmin()
            && (
                $user->isSuperAdmin()
                || $targetPermissions->diff($actorPermissions)->isNotEmpty()
                || $targetPermissions->intersect($protectedPermissions)->isNotEmpty()
            )
        ) {
            throw ValidationException::withMessages([
                'user' => 'نمی‌توانید دسترسی حسابی با سطح دسترسی بالاتر یا سیستمی را تغییر دهید.',
            ]);
        }

        if ($actor->is($user) && (! $data['is_admin'] || $data['status'] !== 'active')) {
            throw ValidationException::withMessages([
                'is_admin' => 'نمی‌توانید دسترسی مدیریت یا وضعیت حساب خودتان را غیرفعال کنید.',
            ]);
        }

        if ($actor->is($user) && $user->isSuperAdmin() && $data['role'] !== 'super-admin') {
            throw ValidationException::withMessages([
                'role' => 'برای جلوگیری از قفل‌شدن پنل، نمی‌توانید نقش مدیر کل خودتان را حذف کنید.',
            ]);
        }

        if ($data['role'] === 'super-admin' && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'فقط مدیر کل می‌تواند نقش مدیر کل را واگذار کند.',
            ]);
        }

        if ($user->isSuperAdmin() && $data['role'] !== 'super-admin') {
            $activeSuperAdmins = User::query()
                ->where('role', 'super-admin')
                ->where('is_admin', true)
                ->where('status', 'active')
                ->count();

            if ($activeSuperAdmins <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'حداقل یک مدیر کل فعال باید در سیستم باقی بماند.',
                ]);
            }
        }

        $role = Role::query()
            ->with('permissions:id,slug')
            ->where('slug', $data['role'])
            ->firstOrFail();

        $requestedPermissions = Permission::query()
            ->whereIn('id', $data['permissions'] ?? [])
            ->get(['id', 'slug']);

        $rolePermissionSlugs = $role->permissions->pluck('slug');
        $requestedPermissionSlugs = $requestedPermissions->pluck('slug');

        if (
            $data['is_admin']
            && $data['role'] !== 'super-admin'
            && $rolePermissionSlugs->merge($requestedPermissionSlugs)->unique()->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'is_admin' => 'برای ورود به پنل مدیریت باید حداقل یک دسترسی مدیریتی مؤثر وجود داشته باشد.',
            ]);
        }

        if (! $actor->isSuperAdmin()) {
            if (
                $rolePermissionSlugs->diff($actorPermissions)->isNotEmpty()
                || $rolePermissionSlugs->intersect($protectedPermissions)->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'role' => 'نمی‌توانید نقشی با دسترسی بیشتر یا سیستمی واگذار کنید.',
                ]);
            }

            if (
                $requestedPermissionSlugs->diff($actorPermissions)->isNotEmpty()
                || $requestedPermissionSlugs->intersect($protectedPermissions)->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'permissions' => 'نمی‌توانید دسترسی‌ای بالاتر از سطح خودتان یا از نوع سیستمی واگذار کنید.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $data, $role, $requestedPermissions) {
            $user->update([
                'status' => $data['status'],
                'role' => $data['role'],
                'is_admin' => $data['is_admin'],
            ]);

            $user->roles()->sync([$role->id]);

            $rolePermissionIds = $role->permissions->pluck('id');
            $directPermissionIds = $requestedPermissions
                ->pluck('id')
                ->diff($rolePermissionIds)
                ->values()
                ->all();

            $user->permissions()->sync($directPermissionIds);
        });

        return back()->with('success', 'دسترسی‌ها و وضعیت کاربر به‌روزرسانی شد.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->hasPermission('users.impersonate'), 403);

        if ($request->session()->has('impersonator_id')) {
            throw ValidationException::withMessages([
                'user' => 'برای ورود به حساب دیگری ابتدا از حالت ورود موقت فعلی خارج شوید.',
            ]);
        }

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => 'شما هم‌اکنون با همین حساب وارد شده‌اید.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['user' => 'ورود با حساب غیرفعال یا مسدود مجاز نیست.']);
        }

        if (! $actor->isSuperAdmin() && $user->canAccessAdminPanel()) {
            abort(403, 'ورود موقت به حساب مدیریتی فقط برای مدیر کل مجاز است.');
        }

        $request->session()->put('impersonator_id', $actor->id);
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('account.dashboard')->with('success', "اکنون به‌عنوان {$user->name} وارد شده‌اید.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::whereKey($adminId)->first() : null;
        abort_unless($admin?->canAccessAdminPanel(), 403);

        Auth::login($admin);
        $request->session()->regenerate();

        return to_route('admin.users.index')->with('success', 'به حساب مدیریت بازگشتید.');
    }
}
