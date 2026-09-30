<?php

return [
    /*
     * Product media is intentionally isolated from the legacy general-media
     * disk. The old DIGITAL_MEDIA_DISK setting remains a compatible fallback.
     */
    'disk' => env('PRODUCT_MEDIA_DISK', env('DIGITAL_MEDIA_DISK', 'public')),
];
