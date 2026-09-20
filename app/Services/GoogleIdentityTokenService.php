<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\Http;

class GoogleIdentityTokenService
{
    /**
     * @return array{id:string,email:string,name:?string,picture:?string}
     */
    public function identity(string $idToken): array
    {
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if (! $response->successful()) {
            throw new DomainException('توکن ورود Google معتبر نیست یا منقضی شده است.');
        }

        $allowedAudiences = collect([
            config('services.google.client_id'),
            config('services.google.android_client_id'),
            config('services.google.ios_client_id'),
        ])->filter()->map(fn ($value) => trim((string) $value))->values();

        $audience = trim((string) $response->json('aud', ''));
        if ($allowedAudiences->isEmpty() || ! $allowedAudiences->contains($audience)) {
            throw new DomainException('توکن Google برای برنامه PlayNexus صادر نشده است.');
        }

        $id = trim((string) $response->json('sub', ''));
        $email = mb_strtolower(trim((string) $response->json('email', '')));
        $emailVerified = filter_var($response->json('email_verified'), FILTER_VALIDATE_BOOLEAN);

        if ($id === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $emailVerified) {
            throw new DomainException('حساب Google باید ایمیل معتبر و تأییدشده داشته باشد.');
        }

        $name = trim((string) $response->json('name', ''));
        $picture = trim((string) $response->json('picture', ''));

        return [
            'id' => $id,
            'email' => $email,
            'name' => $name !== '' ? $name : null,
            'picture' => $picture !== '' ? $picture : null,
        ];
    }
}
