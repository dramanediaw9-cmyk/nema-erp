<?php

return [
    'backups_path' => env('OPS_BACKUPS_PATH', storage_path('app/backups')),
    'backup_retention' => (int) env('OPS_BACKUP_RETENTION', 7),
    'log_tail' => (int) env('OPS_LOG_TAIL', 400),
    'failed_jobs_warning' => (int) env('OPS_FAILED_JOBS_WARNING', 1),
    'failed_jobs_fail' => (int) env('OPS_FAILED_JOBS_FAIL', 10),
    'log_warning_threshold' => (int) env('OPS_LOG_WARNING_THRESHOLD', 1),
    'log_fail_threshold' => (int) env('OPS_LOG_FAIL_THRESHOLD', 10),
];
