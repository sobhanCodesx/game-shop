<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TemporaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class TemporaryUploadController extends Controller
{
    public function chunk(Request $request, TemporaryUploadService $uploads): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['required', Rule::in(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime'])],
            'size' => ['required', 'integer', 'min:1', 'max:2147483648'],
            'chunk' => ['required', 'file', 'max:5120'],
        ]);
        $directory = $uploads->directory($request->user()->id, $data['upload_id']);
        File::ensureDirectoryExists($directory.'/chunks');
        $request->file('chunk')->move($directory.'/chunks', (string) $data['chunk_index']);
        File::put($directory.'/upload.json', json_encode(collect($data)->except('chunk')->all(), JSON_UNESCAPED_UNICODE));

        return response()->json(['received' => $data['chunk_index']]);
    }

    public function complete(Request $request, TemporaryUploadService $uploads): JsonResponse
    {
        $data = $request->validate(['upload_id' => ['required', 'uuid']]);
        $directory = $uploads->directory($request->user()->id, $data['upload_id']);
        abort_unless(File::isFile($directory.'/upload.json'), 422, 'اطلاعات آپلود پیدا نشد.');
        $metadata = json_decode((string) File::get($directory.'/upload.json'), true, flags: JSON_THROW_ON_ERROR);
        $target = fopen($directory.'/assembled', 'wb');
        abort_unless($target, 500, 'امکان ساخت فایل نهایی وجود ندارد.');
        try {
            for ($index = 0; $index < $metadata['total_chunks']; $index++) {
                $chunk = $directory.'/chunks/'.$index;
                abort_unless(File::isFile($chunk), 422, "قطعه {$index} هنوز دریافت نشده است.");
                $source = fopen($chunk, 'rb');
                stream_copy_to_stream($source, $target);
                fclose($source);
            }
        } finally {
            fclose($target);
        }
        abort_unless(File::size($directory.'/assembled') === (int) $metadata['size'], 422, 'اندازه فایل نهایی معتبر نیست.');
        File::put($directory.'/metadata.json', json_encode([
            'name' => $metadata['name'], 'mime' => $metadata['mime'], 'size' => $metadata['size'],
        ], JSON_UNESCAPED_UNICODE));
        File::deleteDirectory($directory.'/chunks');

        return response()->json(['token' => $data['upload_id']]);
    }
}
