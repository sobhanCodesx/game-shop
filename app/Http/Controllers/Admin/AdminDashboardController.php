<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalOrder;
use App\Models\DigitalProduct;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $actor = request()->user();

        if ($actor?->role === 'digital-seller') {
            $orders = DigitalOrder::query()->where('seller_id', $actor->id);
            $monthOrders = (clone $orders)->where('created_at', '>=', now()->startOfMonth());

            return Inertia::render('Admin/Dashboard', [
                'stats' => [
                    ['key' => 'revenue', 'label' => 'فروش دیجیتال این ماه', 'value' => (int) $monthOrders->clone()->where('order_status', 'completed')->sum('sale_price'), 'format' => 'currency', 'change' => 0],
                    ['key' => 'orders', 'label' => 'سفارش‌های دیجیتال', 'value' => (clone $orders)->count(), 'format' => 'number', 'change' => 0],
                    ['key' => 'users', 'label' => 'مشتری‌های دیجیتال', 'value' => (clone $orders)->distinct()->count('user_id'), 'format' => 'number', 'change' => 0],
                    ['key' => 'products', 'label' => 'محصولات دیجیتال فعال', 'value' => DigitalProduct::query()->where('seller_id', $actor->id)->where('status', 'published')->count(), 'format' => 'number', 'change' => 0],
                ],
                'health' => [
                    ['label' => 'در انتظار پرداخت', 'value' => (clone $orders)->where('payment_status', 'unpaid')->where('order_status', 'new')->count(), 'tone' => 'warning'],
                    ['label' => 'رسید جدید', 'value' => (clone $orders)->where('payment_status', 'receipt_sent')->count(), 'tone' => 'primary'],
                    ['label' => 'در حال آماده‌سازی', 'value' => (clone $orders)->where('delivery_status', 'preparing')->count(), 'tone' => 'secondary'],
                    ['label' => 'مشکل تحویل', 'value' => (clone $orders)->where('delivery_status', 'problem')->count(), 'tone' => 'danger'],
                ],
                'recentOrders' => [],
                'activities' => [],
            ]);
        }

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
