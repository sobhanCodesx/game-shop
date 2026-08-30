<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'image' => [
        'max_width' => 1600,
        'max_height' => 1600,
        'webp_quality' => 82,
    ],
    'video' => [
        'max_width' => 1280,
        'max_height' => 720,
        'crf' => 24,
        'preset' => env('FFMPEG_PRESET', 'veryfast'),
        'copy_max_bitrate_kbps' => 5000,
        'audio_bitrate' => '128k',
        'ffmpeg_binary' => env('FFMPEG_BINARY') ?: (PHP_OS_FAMILY === 'Windows'
            ? base_path('node_modules/ffmpeg-static/ffmpeg.exe')
            : base_path('node_modules/ffmpeg-static/ffmpeg')),
    ],
];
