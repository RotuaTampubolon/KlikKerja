<?php

return [
    'policy_version' => env('POLICY_VERSION', '1.0'),
    'otp_driver' => env('OTP_DRIVER', 'log'),
    'require_verified_to_apply' => env('REQUIRE_VERIFIED_TO_APPLY', false),
];