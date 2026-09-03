<?php

return [
    'dispatch_batch_size' => 2,
    'max_attempts' => 4,
    'retry_after_minutes' => [1, 5, 15],
    'processing_timeout_minutes' => 10,

    'patterns' => [
        // Body IDs are account-specific. Put the IDs issued by Payamak Panel here.
        'otp_verify_mobile' => '',
        'otp_passwordless_login' => '',
        'otp_reset_password' => '',
        'order_activity' => '',
        'order_cashback' => '',
        'ticket_activity' => '',
    ],
];
