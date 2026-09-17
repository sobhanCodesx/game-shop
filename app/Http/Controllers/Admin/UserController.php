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
        $users = User::query()->with('permissions:id,name,slug,group')
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
                'orders_count' => $user->orders()->count(),
            ]),
            'roles' => Role::query()->with('permissions:id')->orderBy('id')->get(['id', 'name', 'slug'])->map(fn (Role $role) => [...$role->toArray(), 'permission_ids' => $role->permissions->pluck('id')]),
            'permissions' => Permission::query()->orderBy('group')->orderBy('id')->get(['id', 'name', 'slug', 'group'])->groupBy('group'),
            'filters' => [...$filters, 'search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'role' => $filters['role'] ?? '', 'admin' => $filters['admin'] ?? ''],
        ]);
    }

    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();

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

        if (! $actor->isSuperAdmin()) {
            $protectedPermissionIds = Permission::query()
                ->whereIn('slug', [
                    'users.manage',
                    'users.impersonate',
                    'audit.view',
                    'system.maintenance',
                    'system.deployments',
                    'system.files.manage',
                ])
                ->pluck('id');

            if ($protectedPermissionIds->intersect($data['permissions'] ?? [])->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'permissions' => 'این دسترسی‌های سیستمی فقط توسط مدیر کل قابل واگذاری هستند.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'status' => $data['status'],
                'role' => $data['role'],
                'is_admin' => $data['is_admin'],
            ]);

            $role = Role::query()->where('slug', $data['role'])->first();
            $user->roles()->sync($role ? [$role->id] : []);

            // Role permissions are inherited. Store only explicit extras on the user
            // so changing a role later does not accidentally keep stale privileges.
            $rolePermissionIds = $role?->permissions()->pluck('permissions.id') ?? collect();
            $directPermissionIds = collect($data['permissions'] ?? [])
                ->diff($rolePermissionIds)
                ->values()
                ->all();

            $user->permissions()->sync($directPermissionIds);
        });

        return back()->with('success', 'دسترسی‌ها و وضعیت کاربر به‌روزرسانی شد.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('users.impersonate'), 403);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'شما هم‌اکنون با همین حساب وارد شده‌اید.']);
        }
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['user' => 'ورود با حساب غیرفعال یا مسدود مجاز نیست.']);
        }
        $request->session()->put('impersonator_id', $request->user()->id);
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
