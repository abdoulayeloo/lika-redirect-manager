<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) { exit; }

class RulesStore {
    public const OPTION_NAME = 'lrm_rules';

    public static function all() : array {
        $rules = get_option(self::OPTION_NAME, []);
        return is_array($rules) ? array_values($rules) : [];
    }

    public static function save(array $rules) : void {
        $clean = [];
        foreach ($rules as $r) {
            if (!is_array($r)) { continue; }
            $clean[] = [
                'id'   => isset($r['id'])   ? (string)$r['id']   : uniqid('redir_', true),
                'from' => isset($r['from']) ? self::normalize_path($r['from']) : '',
                'to'   => isset($r['to'])   ? esc_url_raw($r['to']) : '',
                'code' => isset($r['code']) && in_array((int)$r['code'], [301,302,307,308], true) ? (int)$r['code'] : 301,
            ];
        }
        update_option(self::OPTION_NAME, $clean, false);
    }

    public static function add(string $from, string $to, int $code = 301) : void {
        $rules = self::all();
        $rules[] = [
            'id'   => uniqid('redir_', true),
            'from' => self::normalize_path($from),
            'to'   => esc_url_raw($to),
            'code' => in_array($code, [301,302,307,308], true) ? $code : 301,
        ];
        self::save($rules);
    }

    public static function delete(string $id) : void {
        $rules = array_filter(self::all(), function($r) use ($id) {
            return isset($r['id']) && $r['id'] !== $id;
        });
        self::save($rules);
    }

    /**
     * Normalise l’URL "source" en chemin + query (sans slash final, sauf racine).
     */
    public static function normalize_path(string $url) : string {
        $url   = trim($url);
        $parts = wp_parse_url($url);

        if ($parts && isset($parts['path'])) {
            $path  = $parts['path'] ?: '/';
            $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        } else {
            $qpos = strpos($url, '?');
            if ($qpos !== false) {
                $path  = substr($url, 0, $qpos);
                $query = substr($url, $qpos);
            } else {
                $path  = $url ?: '/';
                $query = '';
            }
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') { $path = '/'; }
        }
        return $path . $query;
    }

    /**
     * Convertit une cible potentielle en URL absolue sur le site, si besoin.
     */
    public static function to_absolute(string $maybe) : string {
        if (preg_match('#^https?://#i', $maybe)) {
            return $maybe;
        }
        if (strpos($maybe, '/') === 0) {
            return home_url($maybe);
        }
        return home_url(self::normalize_path('/' . ltrim($maybe, '/')));
    }
}
