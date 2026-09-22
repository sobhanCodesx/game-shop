<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NexusAiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NexusAiSettingsController extends Controller
{
    public function edit(NexusAiSettings $settings): Response
    {
        return Inertia::render('Admin/Settings/NexusAI', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(Request $request, NexusAiSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'nexus_ai_enabled' => ['required', 'boolean'],
            'nexus_ai_show_in_nav' => ['required', 'boolean'],
            'nexus_ai_title' => ['required', 'string', 'max:100'],
            'nexus_ai_description' => ['nullable', 'string', 'max:500'],
            'nexus_ai_nav_label' => ['required', 'string', 'max:40'],
            'nexus_ai_worker_url' => ['required', 'url:http,https', 'max:500'],
            'nexus_ai_launcher_label' => ['required', 'string', 'max:60'],
            'nexus_ai_welcome_title' => ['required', 'string', 'max:100'],
            'nexus_ai_welcome_text' => ['required', 'string', 'max:500'],
            'nexus_ai_status_text' => ['nullable', 'string', 'max:40'],
        ]);

        $data['nexus_ai_title'] = trim($data['nexus_ai_title']);
        $data['nexus_ai_description'] = trim((string) ($data['nexus_ai_description'] ?? ''));
        $data['nexus_ai_nav_label'] = trim($data['nexus_ai_nav_label']);
        $data['nexus_ai_worker_url'] = rtrim(trim($data['nexus_ai_worker_url']), '/');
        $data['nexus_ai_launcher_label'] = trim($data['nexus_ai_launcher_label']);
        $data['nexus_ai_welcome_title'] = trim($data['nexus_ai_welcome_title']);
        $data['nexus_ai_welcome_text'] = trim($data['nexus_ai_welcome_text']);
        $data['nexus_ai_status_text'] = trim((string) ($data['nexus_ai_status_text'] ?? ''));

        $settings->update($data);

        return back()->with('success', 'تنظیمات Nexus AI ذخیره شد.');
    }
}
