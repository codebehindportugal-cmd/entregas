<?php

return [
    'health_url' => env('OPERATIONS_HEALTH_URL', env('APP_URL')),
    'backup_path' => env('OPERATIONS_BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('OPERATIONS_BACKUP_KEEP_DAYS', 14),
    'nas_rsync_target' => env('OPERATIONS_NAS_RSYNC_TARGET'),
    'security_scan_path' => env('OPERATIONS_SECURITY_SCAN_PATH', storage_path('app/operations/security-scan.json')),
    'updates_scan_path' => env('OPERATIONS_UPDATES_SCAN_PATH', storage_path('app/operations/updates-scan.json')),
    'health_scan_path' => env('OPERATIONS_HEALTH_SCAN_PATH', storage_path('app/operations/health.json')),
];
