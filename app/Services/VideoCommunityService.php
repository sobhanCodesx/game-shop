<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\SocialContentReaction;
use App\Models\User;
use App\Notifications\SocialActivityNotification;
use Illuminate\Support\Facades\DB;

class VideoCommunityService
{
    public function toggleReaction(SocialContent $content, User $user, string $type): ?string
    {
        $result = DB::transaction(function () use ($content, $user, $type): ?string {
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

        if ($result !== null && $content->user_id && $content->user_id !== $user->id) {
            $content->user?->notify(new SocialActivityNotification($content, $user, 'reaction'));
        }

        return $result;
    }

    public function createComment(SocialContent $content, User $user, string $body, ?int $parentId): SocialComment
    {
        if ($parentId) {
            $parent = SocialComment::query()->published()->whereKey($parentId)->firstOrFail();
            abort_unless($parent->social_content_id === $content->id && $parent->parent_id === null, 422);
        }

        $comment = $content->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => trim($body),
            'status' => 'published',
        ]);

        $recipients = collect();
        if ($parentId) {
            $parent = SocialComment::query()->with('user')->findOrFail($parentId);
            if ($parent->user_id !== $user->id) {
                $recipients->put($parent->user_id, ['user' => $parent->user, 'activity' => 'reply']);
            }
        } elseif ($content->user_id && $content->user_id !== $user->id) {
            $recipients->put($content->user_id, ['user' => $content->user, 'activity' => 'comment']);
        }

        $usernames = collect(preg_split('/\s+/u', $comment->body))
            ->filter(fn ($word) => str_starts_with($word, '@'))
            ->map(fn ($word) => trim(mb_substr($word, 1), ".,:;!?،؛؟()[]{}<>\"'"))
            ->filter()->unique()->values();

        if ($usernames->isNotEmpty()) {
            User::query()->whereIn('username', $usernames)->whereKeyNot($user->id)->get()
                ->each(fn (User $mentioned) => $recipients->put($mentioned->id, ['user' => $mentioned, 'activity' => 'mention']));
        }

        $recipients->each(fn ($recipient) => $recipient['user']?->notify(
            new SocialActivityNotification($content, $user, $recipient['activity'], $comment->id),
        ));

        return $comment;
    }

    public function toggleCommentLike(SocialComment $comment, User $user): bool
    {
        $liked = DB::transaction(function () use ($comment, $user): bool {
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

        if ($liked && $comment->user_id !== $user->id) {
            $comment->loadMissing(['user', 'content']);
            $comment->user?->notify(new SocialActivityNotification($comment->content, $user, 'comment_like', $comment->id));
        }

        return $liked;
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
