<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSetting;
use App\Services\CommerceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommerceSettingsController extends Controller
{
    public function edit(CommerceSettings $settings): Response
    {
        return Inertia::render('Admin/Settings/Commerce', ['settings' => $settings->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['delivery_fee' => ['required', 'integer', 'min:0'], 'cashback_percent' => ['required', 'numeric', 'between:0,100']]);
        $model = HomeSetting::query()->firstOrCreate(['id' => 1], ['content' => []]);
        $model->update(['content' => [...($model->content ?? []), ...$data]]);

        return back()->with('success', 'تنظیمات فروش و Cashback ذخیره شد.');
    }
}
