<?php

return [
    // Only private dedicated disks are permitted. S3 requires an installed Flysystem
    // adapter and a separately tested private bucket, encryption and lifecycle policy.
    'disk' => env('ERP_VAULT_DISK', 'erp_vault'),
    'workspace_limit_bytes' => 100 * 1024 * 1024,
    'chunk_bytes' => 4 * 1024 * 1024,
    'max_file_bytes' => 48 * 1024 * 1024,
    'session_hours' => 24,
];
