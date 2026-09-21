<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSetting;
use App\Services\CommerceSettings;
use App\Services\HomeExperienceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CommerceSettingsController extends Controller
{
    public function edit(CommerceSettings $settings): Response
    {
        return Inertia::render('Admin/Settings/Commerce', ['settings' => $settings->all()]);
    }

    public function update(Request $request, HomeExperienceService $homeExperience): RedirectResponse
    {
        $data = $request->validate([
            'delivery_fee' => ['required', 'integer', 'min:0'],
            'pickup_address' => ['nullable', 'string', 'max:1000'],
            'cashback_percent' => ['required', 'numeric', 'between:0,100'],
        ]);
        $data['pickup_address'] = trim((string) ($data['pickup_address'] ?? ''));

        DB::transaction(function () use ($data): void {
            HomeSetting::query()->firstOrCreate(['id' => 1], ['content' => []]);
            $model = HomeSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $model->update([
                'content' => [
                    ...($model->content ?? []),
                    ...$data,
                ],
            ]);
        });

        $homeExperience->invalidate();

        return back()->with('success', 'تنظیمات فروش، تحویل و Cashback ذخیره شد.');
    }
}
