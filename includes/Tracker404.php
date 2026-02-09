<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) { exit; }

class Tracker404 {
    const OPTION_404 = 'lrm_404_log';
    const MAX_ROWS   = 2000; // sécurité mémoire: garde max N entrées

    public static function log_if_404() : void {
        if (is_admin()) { return; }
        if (!is_404()) { return; }

        // Chemin + query normalisés (comme pour les redirs)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = RulesStore::normalize_path($request_uri);

        // Ignore les assets évidents (affinable)
        if (preg_match('#\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|map)$#i', $path)) {
            return;
        }

        $log = get_option(self::OPTION_404, []);
        if (!is_array($log)) { $log = []; }

        if (!isset($log[$path])) {
            $log[$path] = [
                'path'      => $path,
                'count'     => 1,
                'first_seen'=> current_time('mysql'),
                'last_seen' => current_time('mysql'),
                'ref'       => self::capture_referrer(),
            ];
        } else {
            $log[$path]['count']     = isset($log[$path]['count']) ? ((int)$log[$path]['count'] + 1) : 1;
            $log[$path]['last_seen'] = current_time('mysql');
            if (empty($log[$path]['ref'])) { $log[$path]['ref'] = self::capture_referrer(); }
        }

        // Trim si trop gros
        if (count($log) > self::MAX_ROWS) {
            // Supprime les plus anciennes (par last_seen)
            uasort($log, function($a, $b){
                return strcmp($a['last_seen'] ?? '', $b['last_seen'] ?? '');
            });
            $log = array_slice($log, -self::MAX_ROWS, null, true);
        }

        update_option(self::OPTION_404, $log, false);
    }

    private static function capture_referrer() : string {
        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
        return esc_url_raw($ref);
    }

    public static function all() : array {
        $log = get_option(self::OPTION_404, []);
        return is_array($log) ? $log : [];
    }

    public static function clear_item(string $path) : void {
        $log = self::all();
        unset($log[$path]);
        update_option(self::OPTION_404, $log, false);
    }

    public static function clear_all() : void {
        delete_option(self::OPTION_404);
    }
}