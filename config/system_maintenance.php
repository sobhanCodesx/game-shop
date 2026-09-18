<?php

return [
    // Optional absolute path to the PHP CLI binary used by generated cron lines.
    // Leave empty to auto-detect it from PATH / PHP_BINARY.
    'php_binary' => env('SCHEDULER_PHP_BINARY'),
];
