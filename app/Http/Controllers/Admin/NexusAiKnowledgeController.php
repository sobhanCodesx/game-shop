<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NexusAiKnowledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NexusAiKnowledgeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:fact,rule,brand'],
            'content' => ['required', 'string', 'max:12000'],
            'priority' => ['nullable', 'integer', 'between:1,999'],
        ]);

        NexusAiKnowledge::query()->create([
            'title' => trim($data['title']),
            'type' => $data['type'],
            'content' => trim($data['content']),
            'enabled' => true,
            'priority' => (int) ($data['priority'] ?? 100),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'دانش جدید به Nexus AI اضافه شد.');
    }

    public function update(Request $request, NexusAiKnowledge $knowledge): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'type' => ['sometimes', 'required', 'in:fact,rule,brand'],
            'content' => ['sometimes', 'required', 'string', 'max:12000'],
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:1,999'],
        ]);

        foreach (['title', 'content'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = trim((string) $data[$key]);
            }
        }

        $knowledge->update($data);

        return back()->with('success', 'دانش Nexus AI بروزرسانی شد.');
    }

    public function destroy(NexusAiKnowledge $knowledge): RedirectResponse
    {
        $knowledge->delete();

        return back()->with('success', 'آیتم دانش حذف شد.');
    }
}
