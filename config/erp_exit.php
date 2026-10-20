<?php

return [
    // Conservative on-disk encrypted package pilot. Never infer that this
    // capacity is sufficient for a 200 GB customer or a production restore.
    'disk' => 'erp_exit',
    'max_plaintext_bytes' => 16 * 1024 * 1024,
    'segment_bytes' => 256 * 1024,
    // Contract/legal review can raise this, but cannot be bypassed by tenant UI.
    'minimum_retention_days' => 30,
];
