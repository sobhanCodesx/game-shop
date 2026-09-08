<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleAccountService
{
    /** @param array{id:string,email:string,name:?string,picture:?string} $identity */
    public function findOrCreate(array $identity): User
    {
        $created = false;
        $user = DB::transaction(function () use ($identity, &$created): User {
            $user = User::withTrashed()->where('google_id', $identity['id'])->lockForUpdate()->first();
            if (! $user) {
                $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$identity['email']])->lockForUpdate()->first();
            }
            if ($user?->trashed()) {
                throw new DomainException('این حساب در دسترس نیست. برای بررسی با پشتیبانی تماس بگیرید.');
            }
            if ($user) {
                if ($user->google_id && $user->google_id !== $identity['id']) {
                    throw new DomainException('این ایمیل قبلاً به یک حساب Google دیگر متصل شده است.');
                }
                if (! $user->google_id) {
                    $user->forceFill([
                        'email' => $identity['email'],
                        'google_id' => $identity['id'],
                        'email_verified_at' => $user->email_verified_at ?: now(),
                    ])->save();
                }

                return $user;
            }

            $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', strip_tags($identity['name'] ?? '')));
            $name = $name !== '' ? $name : Str::before($identity['email'], '@');
            $created = true;

            return User::create([
                'name' => mb_substr($name, 0, 255),
                'email' => $identity['email'],
                'google_id' => $identity['id'],
                'email_verified_at' => now(),
                'password' => null,
                'status' => 'active',
                'role' => 'user',
            ]);
        }, 3);

        if ($created && $identity['picture']) {
            $this->storeInitialAvatar($user, $identity['picture']);
        }

        return $user->refresh();
    }

    private function storeInitialAvatar(User $user, string $url): void
    {
        $parts = parse_url($url);
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || (! str_ends_with($host, '.googleusercontent.com') && ! str_ends_with($host, '.gstatic.com'))) {
            return;
        }

        try {
            $response = Http::connectTimeout(3)->timeout(8)->get($url);
            $contents = $response->body();
            if (! $response->successful() || $contents === '' || strlen($contents) > 2 * 1024 * 1024) {
                return;
            }
            $image = @getimagesizefromstring($contents);
            if ($image === false || $image[0] > 4096 || $image[1] > 4096) {
                return;
            }
            $extension = match ($image['mime'] ?? '') {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/jpeg' => 'jpg',
                default => null,
            };
            if (! $extension) {
                return;
            }
            $path = 'avatars/google/'.Str::uuid().'.'.$extension;
            MediaStorage::disk()->put($path, $contents);
            $user->forceFill(['avatar' => $path])->save();
        } catch (\Throwable) {
            // A remote avatar is optional; authentication must still succeed.
        }
    }
}
