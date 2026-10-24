<?php

return [
    // Explicitly enabled only in an isolated staging installation.
    'checks_enabled' => (bool) env('STAGING_CHECKS_ENABLED', false),
    'mail_recipient' => env('STAGING_MAIL_RECIPIENT'),
];
