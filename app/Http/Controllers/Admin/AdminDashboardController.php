<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                ['key' => 'revenue', 'label' => 'درآمد این ماه', 'value' => 0, 'format' => 'currency', 'change' => 0],
                ['key' => 'orders', 'label' => 'سفارش‌ها', 'value' => 0, 'format' => 'number', 'change' => 0],
                ['key' => 'users', 'label' => 'کاربران', 'value' => User::where('is_admin', false)->count(), 'format' => 'number', 'change' => 0],
                ['key' => 'products', 'label' => 'محصولات فعال', 'value' => 0, 'format' => 'number', 'change' => 0],
            ],
            'health' => [
                ['label' => 'موجودی کم', 'value' => 0, 'tone' => 'warning'],
                ['label' => 'درخواست معاوضه', 'value' => 0, 'tone' => 'primary'],
                ['label' => 'گزارش باز', 'value' => 0, 'tone' => 'danger'],
                ['label' => 'تیکت پاسخ‌داده‌نشده', 'value' => 0, 'tone' => 'secondary'],
            ],
            'recentOrders' => [],
            'activities' => [],
        ]);
    }
}
