<?php

return [
    'admin_emails' => collect(explode(',', (string) env('ADMIN_EMAILS', '')))
        ->map(fn (string $email) => strtolower(trim($email)))
        ->filter()
        ->values()
        ->all(),
];
