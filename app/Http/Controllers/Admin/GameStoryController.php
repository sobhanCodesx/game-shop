<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameStory;
use App\Services\GameStoryService;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use App\Support\StoryRichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameStoryController extends Controller
{
    public function index(Request $request): Response
    {
        $stories = GameStory::query()->with('game:id,name,slug,cover,background')
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest('id')->paginate(18)->withQueryString()
            ->through(fn (GameStory $story) => [...$story->card(), 'status' => $story->status]);
        return Inertia::render('Admin/GameStories/Index', [
            'stories' => $stories,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(GameStory $story): Response
    {
        return $this->form($story);
    }

    private function form(?GameStory $story): Response
    {
        return Inertia::render('Admin/GameStories/Form', [
            'story' => $story ? app(GameStoryService::class)->serialize($story) : null,
            'games' => Game::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, GameStoryService $service): RedirectResponse
    {
        $story = DB::transaction(function () use ($request, $service) {
            $story = $service->save($request->all(), null, $request->user()?->id);
            if ($request->input('status') === 'published') {
                $service->setState($story, 'published');
            }
            return $story;
        });
        return to_route('admin.game-stories.edit', $story)->with('success', 'Game Story ذخیره شد.');
    }

    public function update(Request $request, GameStory $story, GameStoryService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service, $story): void {
            $service->save($request->all(), $story);
            if (in_array($request->input('status'), ['draft', 'published'], true)) {
                $service->setState($story->fresh(['game']), $request->input('status'));
            }
        });
        return back()->with('success', 'تغییرات داستان ذخیره شد.');
    }

    public function destroy(GameStory $story): RedirectResponse
    {
        $story->delete();
        app(\App\Services\SitemapCacheService::class)->invalidate();
        return to_route('admin.game-stories.index')->with('success', 'داستان حذف شد.');
    }

    public function uploadImage(Request $request, TemporaryUploadService $uploads, MediaOptimizationService $optimizer): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => ['required', 'uuid'],
        ]);
        $token = $data['upload_token'];
        $file = $uploads->claim($request->user()->id, $token);
        try {
            $mime = (string) $file->getMimeType();
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $file->getSize() > 8 * 1024 * 1024) {
                throw ValidationException::withMessages(['upload_token' => 'فقط تصویر JPG، PNG یا WebP تا ۸ مگابایت مجاز است.']);
            }
            $path = $optimizer->store($file, 'game-stories/inline')['path'];
            return response()->json(['path' => $path, 'url' => MediaStorage::url($path)]);
        } finally {
            $uploads->forget($request->user()->id, $token);
        }
    }
}
