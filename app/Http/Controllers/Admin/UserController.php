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
        if ($request->user()->is($user) && (! $request->boolean('is_admin') || $request->string('status')->toString() !== 'active')) {
            throw ValidationException::withMessages(['is_admin' => 'نمی‌توانید دسترسی مدیریت یا وضعیت حساب خودتان را غیرفعال کنید.']);
        }
        $data = $request->validated();
        DB::transaction(function () use ($user, $data) {
            $user->update(['status' => $data['status'], 'role' => $data['role'], 'is_admin' => $data['is_admin']]);
            $roleId = Role::where('slug', $data['role'])->value('id');
            $user->roles()->sync($roleId ? [$roleId] : []);
            $user->permissions()->sync($data['permissions'] ?? []);
        });

        return back()->with('success', 'دسترسی‌ها و وضعیت کاربر به‌روزرسانی شد.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
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
        $admin = $adminId ? User::whereKey($adminId)->where('is_admin', true)->first() : null;
        abort_unless($admin, 403);
        Auth::login($admin);
        $request->session()->regenerate();

        return to_route('admin.users.index')->with('success', 'به حساب مدیریت بازگشتید.');
    }
}
