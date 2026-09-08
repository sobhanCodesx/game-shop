<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudioRequest;
use App\Models\Studio;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function index(): Response
    {
        $studios = Studio::query()->withCount('games')->latest('id')->paginate(18)->withQueryString();

        return Inertia::render('Admin/Studios/Index', ['studios' => [
            'data' => collect($studios->items())->map(fn (Studio $studio) => [
                ...$studio->only(['id', 'name', 'slug', 'status', 'website']),
                'logo_url' => MediaStorage::url($studio->logo),
                'background_url' => MediaStorage::url($studio->background),
                'games_count' => $studio->games_count,
            ]),
            'links' => $studios->linkCollection(),
            'current_page' => $studios->currentPage(),
            'last_page' => $studios->lastPage(),
            'total' => $studios->total(),
        ]]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Studios/Form', ['studio' => null]);
    }

    public function store(StudioRequest $request, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $this->data($request);
        if ($request->hasFile('logo')) {
            $data['logo'] = $optimizer->store($request->file('logo'), 'studios/logos')['path'];
        }
        if ($request->hasFile('background')) {
            $data['background'] = $optimizer->store($request->file('background'), 'studios/backgrounds')['path'];
        }
        Studio::query()->create($data);

        return to_route('admin.studios.index')->with('success', 'استودیو ساخته شد.');
    }

    public function edit(Studio $studio): Response
    {
        return Inertia::render('Admin/Studios/Form', ['studio' => [
            ...$studio->only(['id', 'name', 'slug', 'description', 'website', 'status']),
            'logo_url' => MediaStorage::url($studio->logo),
            'background_url' => MediaStorage::url($studio->background),
        ]]);
    }

    public function update(StudioRequest $request, Studio $studio, MediaOptimizationService $optimizer): RedirectResponse
    {
        $data = $this->data($request);
        $delete = [];
        if ($request->hasFile('logo')) {
            $delete[] = $studio->logo;
            $data['logo'] = $optimizer->store($request->file('logo'), 'studios/logos')['path'];
        } elseif ($request->boolean('remove_logo')) {
            $delete[] = $studio->logo;
            $data['logo'] = null;
        }
        if ($request->hasFile('background')) {
            $delete[] = $studio->background;
            $data['background'] = $optimizer->store($request->file('background'), 'studios/backgrounds')['path'];
        } elseif ($request->boolean('remove_background')) {
            $delete[] = $studio->background;
            $data['background'] = null;
        }
        $studio->update($data);
        MediaStorage::disk()->delete(array_filter($delete));

        return to_route('admin.studios.index')->with('success', 'استودیو ویرایش شد.');
    }

    public function destroy(Studio $studio): RedirectResponse
    {
        $paths = array_filter([$studio->logo, $studio->background]);
        $studio->delete();
        MediaStorage::disk()->delete($paths);

        return back()->with('success', 'استودیو حذف شد؛ کانال‌های آن حفظ شدند.');
    }

    private function data(StudioRequest $request): array
    {
        $data = $request->safe()->only(['name', 'slug', 'website', 'status']);
        $data['description'] = RichText::sanitize($request->string('description')->toString()) ?: null;

        return $data;
    }
}
