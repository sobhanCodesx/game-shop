# نصب اولیه ماژول Deployment روی هاست

این راهنما فقط برای اولین نصب قابلیت Deployment است. پیش از شروع، از تمام فایل‌های سایت و دیتابیس بکاپ مستقل و قابل بازیابی بگیرید.

## فایل‌های لازم

فایل‌ها و پوشه‌های زیر را با File Manager هاست و با حفظ مسیرشان آپلود کنید:

- `app/Console/Commands/BuildDeploymentPackage.php`
- `app/Http/Controllers/Admin/DeploymentController.php`
- `app/Http/Middleware/RejectImpersonatedDeployment.php`
- `app/Http/Requests/Admin/DeploymentActionRequest.php`
- `app/Http/Requests/Admin/DeploymentChunkRequest.php`
- `app/Services/Deployment/`
- `config/deployment.php`
- `resources/js/Pages/Admin/Deployments/Index.tsx` و خروجی تازه `public/build/`
- تغییرات `bootstrap/app.php`، `routes/web.php` و `resources/js/config/admin-navigation.ts`

هیچ migration یا seeder برای نصب اولیه لازم نیست.

> هاست Production به Node.js، npm یا Composer نیاز ندارد. فایل‌های React/Vite باید روی سیستم توسعه build شوند و Export فقط خروجی آماده `public/build` را داخل بسته قرار می‌دهد.

در Local، پس از تغییر `composer.lock` فقط یک‌بار `php artisan deployment:prepare` را اجرا کنید. Exportهای پنل پس از آن از vendor Production کش‌شده استفاده می‌کنند و Composer یا شبکه را داخل درخواست وب اجرا نمی‌کنند.

## ترتیب نصب

1. بکاپ فایل‌ها و دیتابیس فعلی را خارج از مسیر public نگه دارید.
2. فایل‌های بالا و `public/build` ساخته‌شده در Local را آپلود کنید.
3. در `.env` هاست مقادیر زیر را قرار دهید. کلید امضا باید تصادفی، حداقل ۳۲ کاراکتر و دقیقاً همسان با Local باشد:

   ```dotenv
   DEPLOY_IMPORT_ENABLED=true
   DEPLOY_EXPORT_ENABLED=false
   DEPLOYMENT_APP_ID=your-stable-application-id
   DEPLOYMENT_SIGNING_KEY=your-long-random-secret
   ```

4. مطمئن شوید PHP افزونه `zip` دارد و `storage` و `bootstrap/cache` قابل نوشتن‌اند.
5. وارد `/admin/deployments` شوید و بسته را Upload کنید. مرحله Verify/Preflight هیچ فایل فعالی را تغییر نمی‌دهد.
6. بعد از مشاهده نتیجه سالم، رمز مدیر را وارد و مراحل را یکی‌یکی اجرا کنید.

`.env`، دیتابیس، `storage`، `public/storage` و رسانه‌ها هرگز جزو بسته نیستند. قابلیت Export در Production خاموش بماند. اگر برای نصب اولیه فایل bootstrap موقتی ساخته‌اید، پس از تأیید صفحه آن را حذف کنید.
