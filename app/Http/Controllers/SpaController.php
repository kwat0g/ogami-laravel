<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class SpaController extends Controller
{
    /**
     * Serve the compiled React SPA for all non-API web routes.
     * Injects window.__REVERB_CONFIG__ so the frontend WebSocket client
     * uses the correct host/port/scheme at runtime (not baked in at build time).
     */
    public function __invoke(): Response
    {
        $index = public_path('build/index.html');

        if (! file_exists($index)) {
            abort(503, 'Frontend not built.');
        }

        $config = json_encode([
            'key' => config('reverb.apps.apps.0.key', ''),
            'wsHost' => config('reverb.apps.apps.0.options.host', 'localhost'),
            'wsPort' => (int) config('reverb.apps.apps.0.options.port', 8080),
            'wssPort' => (int) config('reverb.apps.apps.0.options.port', 443),
            'scheme' => config('reverb.apps.apps.0.options.scheme', 'http'),
        ], JSON_THROW_ON_ERROR);

        // Inject as a <meta> tag — not subject to CSP script-src restrictions
        $html = str_replace(
            '</head>',
            '<meta name="reverb-config" content="'.htmlspecialchars($config, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL.'</head>',
            (string) file_get_contents($index)
        );

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
