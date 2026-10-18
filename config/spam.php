<?php

$patterns = env('SPAM_BLOCKED_EMAIL_DOMAINS', '*lettersmail.com');

return [
    'blocked_email_domains' => array_values(array_filter(array_map(
        static fn (string $pattern): string => trim($pattern),
        explode(',', (string) $patterns),
    ), static fn (string $pattern): bool => $pattern !== '')),
];
