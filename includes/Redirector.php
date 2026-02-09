<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) {
    exit;
}

class Redirector
{
    public static function maybe_redirect(): void
    {
        if (is_admin()) {
            return;
        } // pas dans l’admin

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $current = RulesStore::normalize_path($request_uri);

        $rule = RulesStore::get_by_path($current);
        if (!$rule) {
            return;
        }

        $code = in_array((int) $rule['code'], [301, 302, 307, 308], true) ? (int) $rule['code'] : 301;

        $dest_abs = RulesStore::to_absolute($rule['to']);

        // Anti-boucle
        $p = wp_parse_url($dest_abs);
        $dst = RulesStore::normalize_path(($p['path'] ?? '/') . (isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : ''));

        if ($dst === $current) {
            return;
        }

        wp_safe_redirect($dest_abs, $code);
        exit;
    }
}
