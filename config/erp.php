<?php

return [
    // Only the legacy domain test suite bypasses the new MFA gate.
    // Production cannot disable MFA through environment configuration.
    // Dedicated MFA feature tests explicitly set this to false.
    'mfa_test_bypass' => env('ERP_MFA_TEST_BYPASS', false),
];
