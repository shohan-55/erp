<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo / test sign-in personas
    |--------------------------------------------------------------------------
    |
    | The sign-in components historically accepted seven demo personas
    | (partner@dhakawala.com, manager@dhakawala.com, …) with ANY password, so
    | the design previews could be opened without seeded users. That check only
    | looked at `app()->environment('testing')`, which means a single stray
    | `APP_ENV=testing` in a production .env — a very common copy-paste or
    | cPanel-panel mistake — would have turned the whole platform into an
    | open door where anyone could sign in as a Partner.
    |
    | Authority to do that now comes from this explicit flag instead. It is
    | off unless somebody deliberately sets ERP_ALLOW_TEST_LOGINS=true, and it
    | is only ever set in phpunit.xml. Production must leave it unset.
    |
    */

    'allow_test_logins' => (bool) env('ERP_ALLOW_TEST_LOGINS', false),

];
