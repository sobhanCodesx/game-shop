<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\SocialContentReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VideoCommunityService
{
    public function toggleReaction(SocialContent $content, User $user, string $type): ?string
    {
        return DB::transaction(function () use ($content, $user, $type): ?string {
            $reaction = SocialContentReaction::query()
                ->whereBelongsTo($content, 'content')
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($reaction?->type === $type) {
                $reaction->delete();

                return null;
            }

            SocialContentReaction::query()->updateOrCreate(
                ['social_content_id' => $content->id, 'user_id' => $user->id],
                ['type' => $type],
            );

            return $type;
        });
    }

    public function createComment(SocialContent $content, User $user, string $body, ?int $parentId): SocialComment
    {
        if ($parentId) {
            $parent = SocialComment::query()->published()->whereKey($parentId)->firstOrFail();
            abort_unless($parent->social_content_id === $content->id && $parent->parent_id === null, 422);
        }

        return $content->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => trim($body),
            'status' => 'published',
        ]);
    }

    public function toggleCommentLike(SocialComment $comment, User $user): bool
    {
        return DB::transaction(function () use ($comment, $user): bool {
            $exists = DB::table('social_comment_likes')
                ->where('social_comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                $comment->likedBy()->detach($user->id);

                return false;
            }

            $comment->likedBy()->attach($user->id);

            return true;
        });
    }

    public function toggleSubscription(Game $game, User $user): bool
    {
        return DB::transaction(function () use ($game, $user): bool {
            $exists = DB::table('game_subscriptions')
                ->where('game_id', $game->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                $game->subscribers()->detach($user->id);

                return false;
            }

            $game->subscribers()->attach($user->id);

            return true;
        });
    }
}
