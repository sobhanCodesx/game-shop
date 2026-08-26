<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminResourceController extends Controller
{
    private const RESOURCES = [
        'users' => ['title' => 'کاربران', 'description' => 'مدیریت حساب‌ها، وضعیت و دسترسی کاربران', 'createLabel' => 'کاربر جدید', 'columns' => ['نام', 'راه ارتباطی', 'نقش', 'وضعیت', 'تاریخ عضویت']],
        'creators' => ['title' => 'کریتورها', 'description' => 'بررسی و مدیریت پروفایل تولیدکنندگان محتوا', 'columns' => ['کریتور', 'دنبال‌کننده', 'محتوا', 'وضعیت', 'تاریخ عضویت']],
        'products' => ['title' => 'محصولات', 'description' => 'مدیریت کاتالوگ، قیمت و انتشار محصولات', 'createLabel' => 'محصول جدید', 'columns' => ['محصول', 'SKU', 'دسته‌بندی', 'قیمت', 'موجودی', 'وضعیت']],
        'categories' => ['title' => 'دسته‌بندی‌ها', 'description' => 'ساختار سلسله‌مراتبی دسته‌بندی فروشگاه', 'createLabel' => 'دسته جدید', 'columns' => ['عنوان', 'والد', 'محصولات', 'ترتیب', 'وضعیت']],
        'brands' => ['title' => 'برندها', 'description' => 'مدیریت برندهای فروشگاه', 'createLabel' => 'برند جدید', 'columns' => ['برند', 'وب‌سایت', 'محصولات', 'وضعیت']],
        'games' => ['title' => 'بازی‌ها', 'description' => 'مدیریت اطلاعات بازی‌ها و ارتباط با فروشگاه', 'createLabel' => 'بازی جدید', 'columns' => ['بازی', 'سازنده', 'ناشر', 'تاریخ انتشار', 'محصولات']],
        'platforms' => ['title' => 'پلتفرم‌ها', 'description' => 'مدیریت پلتفرم‌های گیمینگ', 'createLabel' => 'پلتفرم جدید', 'columns' => ['پلتفرم', 'شناسه', 'بازی‌ها', 'محصولات', 'وضعیت']],
        'inventory' => ['title' => 'انبار', 'description' => 'کنترل موجودی فیزیکی و دیجیتال', 'columns' => ['محصول', 'نوع', 'موجودی کل', 'رزرو', 'قابل فروش', 'وضعیت']],
        'orders' => ['title' => 'سفارش‌ها', 'description' => 'پردازش و پیگیری سفارش‌های فروشگاه', 'columns' => ['شماره سفارش', 'مشتری', 'مبلغ', 'پرداخت', 'وضعیت', 'تاریخ']],
        'payments' => ['title' => 'پرداخت‌ها', 'description' => 'تراکنش‌ها و وضعیت درگاه‌های پرداخت', 'columns' => ['شناسه', 'سفارش', 'مبلغ', 'درگاه', 'وضعیت', 'تاریخ']],
        'coupons' => ['title' => 'کدهای تخفیف', 'description' => 'ساخت و کنترل کمپین‌های تخفیف', 'createLabel' => 'کد تخفیف جدید', 'columns' => ['کد', 'نوع', 'مقدار', 'مصرف', 'اعتبار', 'وضعیت']],
        'reviews' => ['title' => 'نقد و بررسی', 'description' => 'مدیریت دیدگاه و امتیاز محصولات', 'columns' => ['کاربر', 'محصول', 'امتیاز', 'خرید تأییدشده', 'وضعیت', 'تاریخ']],
        'trades' => ['title' => 'درخواست‌های معاوضه', 'description' => 'بررسی و مدیریت جریان معاوضه کالا', 'columns' => ['شناسه', 'درخواست‌دهنده', 'محصول', 'پیشنهاد', 'اختلاف نقدی', 'وضعیت']],
        'posts' => ['title' => 'پست‌ها', 'description' => 'مدیریت محتوای متنی و تصویری', 'columns' => ['عنوان', 'کریتور', 'بازی', 'تعامل', 'وضعیت', 'انتشار']],
        'videos' => ['title' => 'ویدیوها', 'description' => 'مدیریت ویدیوهای بلند پلتفرم', 'columns' => ['عنوان', 'کریتور', 'مدت', 'بازدید', 'وضعیت', 'انتشار']],
        'shorts' => ['title' => 'ویدیوهای کوتاه', 'description' => 'مدیریت Shorts و محتوای عمودی', 'columns' => ['عنوان', 'کریتور', 'مدت', 'بازدید', 'وضعیت', 'انتشار']],
        'comments' => ['title' => 'نظرات', 'description' => 'مدیریت نظرات، پاسخ‌ها و تعاملات', 'columns' => ['نویسنده', 'متن', 'محتوا', 'پسند', 'وضعیت', 'تاریخ']],
        'reports' => ['title' => 'گزارش‌ها', 'description' => 'صف بررسی گزارش‌های کاربران و محتوا', 'columns' => ['گزارش‌دهنده', 'هدف', 'دلیل', 'بررسی‌کننده', 'وضعیت', 'تاریخ']],
        'moderation' => ['title' => 'نظارت محتوا', 'description' => 'صف یکپارچه اقدامات نظارتی', 'columns' => ['محتوا', 'نوع', 'مالک', 'ریسک', 'گزارش‌ها', 'وضعیت']],
        'notifications' => ['title' => 'اعلان‌ها', 'description' => 'ارسال و مدیریت اعلان‌های سیستمی', 'createLabel' => 'اعلان جدید', 'columns' => ['عنوان', 'مخاطب', 'کانال', 'ارسال‌شده', 'وضعیت', 'تاریخ']],
        'banners' => ['title' => 'بنرها', 'description' => 'مدیریت بنرها و جایگاه‌های تبلیغاتی', 'createLabel' => 'بنر جدید', 'columns' => ['عنوان', 'جایگاه', 'شروع', 'پایان', 'وضعیت']],
        'pages' => ['title' => 'صفحات', 'description' => 'مدیریت صفحات ثابت و محتوای حقوقی', 'createLabel' => 'صفحه جدید', 'columns' => ['عنوان', 'آدرس', 'آخرین ویرایش', 'ویرایشگر', 'وضعیت']],
        'settings' => ['title' => 'تنظیمات', 'description' => 'پیکربندی عمومی، فروشگاه و قابلیت‌های سیستم', 'columns' => ['گروه', 'تنظیم', 'مقدار', 'آخرین تغییر']],
        'audit-logs' => ['title' => 'گزارش فعالیت مدیران', 'description' => 'ردیابی اقدامات حساس و تغییرات مدیریتی', 'columns' => ['مدیر', 'عملیات', 'بخش', 'شناسه هدف', 'IP', 'زمان']],
        'support' => ['title' => 'تیکت‌های پشتیبانی', 'description' => 'رسیدگی و پاسخ‌گویی به درخواست کاربران', 'columns' => ['شماره', 'کاربر', 'موضوع', 'اولویت', 'مسئول', 'وضعیت']],
    ];

    public function __invoke(Request $request, string $resource): Response
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        $definition = self::RESOURCES[$resource];

        return Inertia::render('Admin/Resource/Index', [
            'resource' => $resource,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'createLabel' => $definition['createLabel'] ?? null,
            'columns' => $definition['columns'],
            'items' => [],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'pagination' => [
                'currentPage' => 1,
                'lastPage' => 1,
                'perPage' => 20,
                'total' => 0,
            ],
        ]);
    }
}
