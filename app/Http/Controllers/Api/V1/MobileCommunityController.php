<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoCommentRequest;
use App\Http\Requests\VideoReactionRequest;
use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Services\VideoCommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileCommunityController extends Controller
{
    public function react(
        VideoReactionRequest $request,
        SocialContent $content,
        VideoCommunityService $community,
    ): JsonResponse {
        $this->ensureVisible($content);

        if ($content->type === 'post' && $request->string('type')->toString() !== 'like') {
            abort(422, 'برای پست‌ها فقط واکنش پسندیدن پشتیبانی می‌شود.');
        }

        $reaction = $community->toggleReaction(
            $content,
            $request->user(),
            $request->string('type')->toString(),
        );

        return response()->json([
            'reaction' => $reaction,
            'liked' => $reaction === 'like',
        ]);
    }

    public function save(Request $request, SocialContent $content): JsonResponse
    {
        $this->ensureVisible($content);
        $user = $request->user();

        $saved = DB::transaction(function () use ($user, $content): bool {
            $exists = DB::table('social_content_saves')
                ->where('social_content_id', $content->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();

            $exists
                ? $user->savedContent()->detach($content->id)
                : $user->savedContent()->attach($content->id);

            return ! $exists;
        });

        return response()->json(['saved' => $saved]);
    }

    public function comment(
        StoreVideoCommentRequest $request,
        SocialContent $content,
        VideoCommunityService $community,
    ): JsonResponse {
        $this->ensureVisible($content);
        abort_unless($content->allow_comments, 403);

        $data = $request->validated();
        $comment = $community->createComment(
            $content,
            $request->user(),
            $data['body'],
            $data['parent_id'] ?? null,
        );

        return response()->json(['comment_id' => $comment->id], 201);
    }

    public function likeComment(
        Request $request,
        SocialComment $comment,
        VideoCommunityService $community,
    ): JsonResponse {
        abort_unless($comment->status === 'published', 404);

        return response()->json([
            'liked' => $community->toggleCommentLike($comment, $request->user()),
        ]);
    }

    public function destroyComment(Request $request, SocialComment $comment): JsonResponse
    {
        abort_unless(
            $request->user()->is_admin || $comment->user_id === $request->user()->id,
            403,
        );

        $comment->delete();

        return response()->json(['deleted' => true]);
    }

    public function subscribe(
        Request $request,
        Game $game,
        VideoCommunityService $community,
    ): JsonResponse {
        abort_unless(in_array($game->status, ['active', 'published'], true), 404);

        $subscribed = $community->toggleSubscription($game, $request->user());

        return response()->json([
            'subscribed' => $subscribed,
            'subscribers_count' => $game->subscribers()->count(),
        ]);
    }

    private function ensureVisible(SocialContent $content): void
    {
        abort_unless(
            in_array($content->type, ['post', 'video', 'short'], true)
                && $content->status === 'published'
                && $content->published_at?->isPast(),
            404,
        );
    }
}
