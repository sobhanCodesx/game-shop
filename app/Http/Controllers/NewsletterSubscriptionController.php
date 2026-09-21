<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ], [
            'email.required' => 'ایمیل را وارد کنید.',
            'email.email' => 'ایمیل واردشده معتبر نیست.',
            'email.max' => 'ایمیل واردشده بیش از حد طولانی است.',
        ]);

        $email = mb_strtolower(trim($data['email']));

        NewsletterSubscription::query()->updateOrCreate(
            ['email' => $email],
            ['subscribed_at' => now()],
        );

        return back()->with('success', 'عضویت در خبرنامه با موفقیت انجام شد.');
    }
}
