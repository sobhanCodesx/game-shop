<?php

namespace App\Http\Controllers;

use App\Models\DigitalOrderMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalOrderAttachmentController extends Controller
{
    public function __invoke(Request $request, DigitalOrderMessage $message): StreamedResponse
    {
        abort_unless($message->attachment_path, 404);

        $message->loadMissing('order');
        $user = $request->user();
        $order = $message->order;

        $allowed = $user
            && (
                $order->user_id === $user->id
                || $order->seller_id === $user->id
                || $user->hasPermission('digital.delivery.manage')
            );

        abort_unless($allowed, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response(
            $message->attachment_path,
            $message->attachment_name ?: 'attachment',
            ['Cache-Control' => 'private, no-store, max-age=0'],
        );
    }
}
