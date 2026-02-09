<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) {
    exit;
}

class Tracker404
{
    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'track_404'], 99);
    }

    public static function track_404(): void
    {
        if (is_admin()) {
            return;
        }

        if (!is_404()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        // Ignore common static assets
        if (preg_match('#\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|map)$#i', $request_uri)) {
            return;
        }

        $referrer = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';

        RulesStore::log_404($request_uri, $referrer);
    }
}
