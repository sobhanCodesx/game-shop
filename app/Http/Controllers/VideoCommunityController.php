<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoCommentRequest;
use App\Http\Requests\VideoReactionRequest;
use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Services\VideoCommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VideoCommunityController extends Controller
{
    public function react(VideoReactionRequest $request, SocialContent $content, VideoCommunityService $community): JsonResponse|RedirectResponse
    {
        $this->ensureReactable($content);
        $reaction = $community->toggleReaction($content, $request->user(), $request->string('type')->toString());

        return $this->respond($request, ['reaction' => $reaction]);
    }

    public function comment(StoreVideoCommentRequest $request, SocialContent $content, VideoCommunityService $community): JsonResponse|RedirectResponse
    {
        $this->ensureVideo($content);
        abort_unless($content->allow_comments, 403);
        $comment = $community->createComment(
            $content,
            $request->user(),
            $request->string('body')->toString(),
            $request->integer('parent_id') ?: null,
        );

        return $this->respond($request, ['comment_id' => $comment->id], 201);
    }

    public function likeComment(Request $request, SocialComment $comment, VideoCommunityService $community): JsonResponse|RedirectResponse
    {
        abort_unless($comment->status === 'published', 404);
        $liked = $community->toggleCommentLike($comment, $request->user());

        return $this->respond($request, ['liked' => $liked]);
    }

    public function destroyComment(Request $request, SocialComment $comment): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->is_admin || $comment->user_id === $request->user()->id, 403);
        $comment->delete();

        return $this->respond($request, ['deleted' => true]);
    }

    public function subscribe(Request $request, Game $game, VideoCommunityService $community): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($game->status, ['active', 'published'], true), 404);
        $subscribed = $community->toggleSubscription($game, $request->user());

        return $this->respond($request, ['subscribed' => $subscribed]);
    }

    private function ensureReactable(SocialContent $content): void
    {
        abort_unless(
            in_array($content->type, ['video', 'short'], true)
            && $content->status === 'published'
            && $content->published_at?->isPast(),
            404,
        );
    }

    private function ensureVideo(SocialContent $content): void
    {
        abort_unless($content->type === 'video' && $content->status === 'published' && $content->published_at?->isPast(), 404);
    }

    private function respond(Request $request, array $data, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json($data, $status) : back();
    }
}
