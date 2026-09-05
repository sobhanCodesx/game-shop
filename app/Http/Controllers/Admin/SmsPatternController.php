<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSmsPatternsRequest;
use App\Services\Sms\SmsPatternRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SmsPatternController extends Controller
{
    public function index(SmsPatternRegistry $patterns): Response
    {
        return Inertia::render('Admin/SmsPatterns/Index', [
            'patterns' => $patterns->all(),
        ]);
    }

    public function update(UpdateSmsPatternsRequest $request, SmsPatternRegistry $patterns): RedirectResponse
    {
        $patterns->update($request->validated('patterns'), $request->user()->id);

        return back()->with('success', 'رجیستری Patternهای پیامک ذخیره شد.');
    }
}
