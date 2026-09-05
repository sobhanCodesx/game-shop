<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthenticationCodeNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly string $code, private readonly string $purpose) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $isVerification = $this->purpose === 'verify_email';
        $title = $isVerification ? 'تأیید ایمیل حساب کاربری' : 'بازیابی رمز عبور';

        return (new MailMessage)
            ->subject(($isVerification ? 'کد تأیید شما: ' : 'کد بازیابی شما: ').$this->code.' | PLAY NEXUS')
            ->view(['html' => 'emails.authentication-code', 'text' => 'emails.authentication-code-text'], [
                'name' => $notifiable->name,
                'code' => $this->code,
                'title' => $title,
                'description' => $isVerification
                    ? 'برای فعال‌سازی حساب PLAY NEXUS، کد زیر را در صفحه تأیید وارد کنید.'
                    : 'برای انتخاب رمز عبور جدید، کد زیر را در صفحه بازیابی وارد کنید.',
            ]);
    }
}
