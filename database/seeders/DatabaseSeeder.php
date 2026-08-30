<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = [
            'user' => 'کاربر عادی',
            'super-admin' => 'مدیر کل',
            'admin' => 'مدیر',
            'product-manager' => 'مدیر محصول',
            'content-moderator' => 'ناظر محتوا',
            'support' => 'پشتیبانی',
            'editor' => 'ویرایشگر',
            'warehouse' => 'انباردار',
            'finance' => 'مالی',
        ];

        foreach ($roles as $slug => $name) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $permissions = [
            'dashboard.view' => ['مشاهده داشبورد', 'dashboard'],
            'user.view' => ['مشاهده کاربران', 'user'],
            'user.manage' => ['مدیریت کاربران', 'user'],
            'product.view' => ['مشاهده محصولات', 'product'],
            'product.create' => ['ایجاد محصول', 'product'],
            'product.update' => ['ویرایش محصول', 'product'],
            'product.delete' => ['حذف محصول', 'product'],
            'order.view' => ['مشاهده سفارش', 'order'],
            'order.manage' => ['مدیریت سفارش', 'order'],
            'payment.manage' => ['مدیریت پرداخت', 'payment'],
            'content.moderate' => ['نظارت محتوا', 'content'],
            'trade.review' => ['بررسی معاوضه', 'trade'],
            'support.manage' => ['مدیریت پشتیبانی', 'support'],
            'settings.manage' => ['مدیریت تنظیمات', 'settings'],
        ];

        foreach ($permissions as $slug => [$name, $group]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'email' => config('admin.seed.email'),
                'name' => 'مدیر سیستم',
                'username' => 'admin',
                'status' => 'active',
                'role' => 'super-admin',
                'is_admin' => true,
                'password' => config('admin.seed.password'),
                'email_verified_at' => now(),
            ],
        );

        $superAdminRoleId = DB::table('roles')->where('slug', 'super-admin')->value('id');

        DB::table('role_user')->updateOrInsert([
            'role_id' => $superAdminRoleId,
            'user_id' => $admin->id,
        ]);

        DB::table('permission_role')->insertOrIgnore(
            DB::table('permissions')->pluck('id')->map(fn (int $permissionId) => [
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ])->all(),
        );

        $this->call(ProductCatalogFoundationSeeder::class);

        if (app()->environment('local')) {
            $this->call(CatalogSeeder::class);
            $this->call(StorefrontCatalogSeeder::class);
            $this->call(HomeContentSeeder::class);
        }
    }
}
