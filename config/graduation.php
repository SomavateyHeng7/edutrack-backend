<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session TTL (Time To Live)
    |--------------------------------------------------------------------------
    | How long a student's session token is valid after PIN verification.
    | In minutes.
    */
    'session_ttl_minutes' => (int) env('GRADUATION_SESSION_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    | How many days after the portal deadline students can still submit.
    | This extends the submission window, NOT the data retention window.
    | In days.
    */
    'grace_period_days' => (int) env('GRADUATION_GRACE_PERIOD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Submission Retention
    |--------------------------------------------------------------------------
    | How many days after the portal deadline to keep submission data.
    | Submissions will be deleted (portal.deadline + retention_days).
    | In days.
    */
    'submission_retention_days' => (int) env('GRADUATION_SUBMISSION_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | IP Validation
    |--------------------------------------------------------------------------
    | Whether to validate that requests come from the same IP as session creation.
    | Disable for development or if students use VPNs.
    */
    'validate_ip' => env('GRADUATION_VALIDATE_IP', true),

    /*
    |--------------------------------------------------------------------------
    | Max File Size
    |--------------------------------------------------------------------------
    | Maximum file size in MB that students can upload.
    */
    'max_file_size_mb' => (int) env('GRADUATION_MAX_FILE_SIZE', 5),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | Which cache store to use for graduation data.
    | Recommended: redis for production, file for development.
    */
    'cache_store' => env('GRADUATION_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Maximum PIN verification attempts per portal per IP per time window.
    */
    'max_pin_attempts' => (int) env('GRADUATION_MAX_PIN_ATTEMPTS', 5),
    'pin_attempt_decay_minutes' => (int) env('GRADUATION_PIN_ATTEMPT_DECAY', 15),

    /*
    |--------------------------------------------------------------------------
    | Submission Limits
    |--------------------------------------------------------------------------
    | Maximum number of submissions that can be pending at once per portal.
    */
    'max_pending_submissions' => (int) env('GRADUATION_MAX_PENDING_SUBMISSIONS', 100),
];
