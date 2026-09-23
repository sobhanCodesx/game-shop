<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NexusAi\NexusAiAnalyticsService;
use App\Services\NexusAi\NexusAiKnowledgeService;
use App\Services\NexusAi\NexusAiProviderSettings;
use App\Services\NexusAiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NexusAiSettingsController extends Controller
{
    public function edit(
        NexusAiSettings $settings,
        NexusAiProviderSettings $providers,
        NexusAiAnalyticsService $analytics,
        NexusAiKnowledgeService $knowledge,
    ): Response {
        return Inertia::render('Admin/Settings/NexusAI', [
            'settings' => $settings->all(),
            'providers' => $providers->adminPayload(),
            'analytics' => $analytics->dashboard(),
            'knowledge' => $knowledge->adminList(),
        ]);
    }

    public function update(
        Request $request,
        NexusAiSettings $settings,
        NexusAiProviderSettings $providers,
    ): RedirectResponse {
        $data = $request->validate([
            'nexus_ai_enabled' => ['required', 'boolean'],
            'nexus_ai_page_enabled' => ['required', 'boolean'],
            'nexus_ai_show_in_nav' => ['required', 'boolean'],
            'nexus_ai_title' => ['required', 'string', 'max:100'],
            'nexus_ai_description' => ['nullable', 'string', 'max:500'],
            'nexus_ai_nav_label' => ['required', 'string', 'max:40'],
            'nexus_ai_worker_url' => ['required', 'url:http,https', 'max:500'],
            'nexus_ai_launcher_label' => ['required', 'string', 'max:60'],
            'nexus_ai_welcome_title' => ['required', 'string', 'max:100'],
            'nexus_ai_welcome_text' => ['required', 'string', 'max:500'],
            'nexus_ai_status_text' => ['nullable', 'string', 'max:40'],
            'nexus_ai_free_first' => ['required', 'boolean'],
            'nexus_ai_autopilot' => ['required', 'boolean'],
            'nexus_ai_collect_analytics' => ['required', 'boolean'],
            'nexus_ai_guest_daily_limit' => ['required', 'integer', 'between:0,500'],
            'nexus_ai_user_daily_limit' => ['required', 'integer', 'between:0,2000'],
            'nexus_ai_spoiler_guard' => ['required', 'boolean'],
            'nexus_ai_clarify_ambiguity' => ['required', 'boolean'],
            'nexus_ai_response_style' => ['required', 'in:concise,balanced,detailed'],
            'nexus_ai_provider_timeout_seconds' => ['required', 'integer', 'between:3,60'],
            'nexus_ai_max_output_tokens' => ['required', 'integer', 'between:128,8192'],
            'nexus_ai_temperature' => ['required', 'numeric', 'between:0,2'],
            'providers' => ['nullable', 'array'],
            'providers.*.key' => ['required_with:providers', 'string', 'max:64'],
            'providers.*.enabled' => ['required_with:providers', 'boolean'],
            'providers.*.priority' => ['required_with:providers', 'integer', 'between:1,999'],
            'providers.*.settings' => ['required_with:providers', 'array'],
        ]);

        $providerData = $data['providers'] ?? null;
        unset($data['providers']);

        foreach ([
            'nexus_ai_title',
            'nexus_ai_description',
            'nexus_ai_nav_label',
            'nexus_ai_worker_url',
            'nexus_ai_launcher_label',
            'nexus_ai_welcome_title',
            'nexus_ai_welcome_text',
            'nexus_ai_status_text',
        ] as $key) {
            $data[$key] = trim((string) ($data[$key] ?? ''));
        }

        $data['nexus_ai_worker_url'] = rtrim($data['nexus_ai_worker_url'], '/');

        $settings->update($data);

        if (is_array($providerData)) {
            $providers->saveMany($providerData, $request->user()?->id);
        }

        return back()->with('success', 'تنظیمات Nexus AI ذخیره شد.');
    }
}
