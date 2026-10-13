<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter(array_map('trim', explode(',', env('FRONTEND_URL', 'http://localhost:4200,http://localhost:8000')))))),
    // Tunnels Cloudflare (n'importe qui peut en créer un) : autorisés seulement en développement local
    'allowed_origins_patterns' => in_array(env('APP_ENV'), ['local', 'develop', 'development']) ? ['#^https?://.*\.trycloudflare\.com$#'] : [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
