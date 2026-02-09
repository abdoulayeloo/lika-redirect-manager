<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) {
    exit;
}

class RulesStore
{
    public const TABLE_NAME = 'lrm_rules';
    public const TABLE_404 = 'lrm_404_logs';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function table_404_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_404;
    }

    public static function install(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Rules table
        $table_rules = self::table_name();
        $sql_rules = "CREATE TABLE $table_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_url varchar(191) NOT NULL,
            to_url text NOT NULL,
            status_code int(3) NOT NULL DEFAULT 301,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY from_url (from_url)
        ) $charset_collate;";

        // 404 Logs table
        $table_404 = self::table_404_name();
        $sql_404 = "CREATE TABLE $table_404 (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(191) NOT NULL,
            hit_count int(11) NOT NULL DEFAULT 1,
            referrer text,
            first_seen datetime DEFAULT CURRENT_TIMESTAMP,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY url (url)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rules);
        dbDelta($sql_404);

        self::migrate();
    }

    // ========== 404 Logs Methods ==========

    public static function log_404(string $url, string $referrer = ''): void
    {
        global $wpdb;
        $table = self::table_404_name();
        $normalized = self::normalize_path($url);

        // Check if entry exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE url = %s", $normalized));

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET hit_count = hit_count + 1, last_seen = NOW() WHERE id = %d",
                $existing
            ));
        } else {
            $wpdb->insert(
                $table,
                [
                    'url' => $normalized,
                    'referrer' => esc_url_raw($referrer),
                ],
                ['%s', '%s']
            );
        }
    }

    public static function get_404s(): array
    {
        global $wpdb;
        $table = self::table_404_name();
        return $wpdb->get_results("SELECT * FROM $table ORDER BY hit_count DESC, last_seen DESC LIMIT 100", ARRAY_A) ?: [];
    }

    public static function delete_404(string $url): void
    {
        global $wpdb;
        $table = self::table_404_name();
        $wpdb->delete($table, ['url' => self::normalize_path($url)], ['%s']);
    }

    public static function clear_all_404s(): void
    {
        global $wpdb;
        $table = self::table_404_name();
        $wpdb->query("TRUNCATE TABLE $table");
    }

    private static function migrate(): void
    {
        $old_rules = get_option('lrm_rules', []);
        if (!empty($old_rules) && is_array($old_rules)) {
            foreach ($old_rules as $rule) {
                if (isset($rule['from'], $rule['to'], $rule['code'])) {
                    self::add($rule['from'], $rule['to'], (int) $rule['code']);
                }
            }
            delete_option('lrm_rules');
        }
    }

    public static function all(): array
    {
        global $wpdb;
        $table = self::table_name();
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);

        // Adapt format for backward compatibility with views
        return array_map(function ($row) {
            return [
                'id' => $row['id'],
                'from' => $row['from_url'],
                'to' => $row['to_url'],
                'code' => $row['status_code']
            ];
        }, $results);
    }

    public static function get_by_path(string $path): ?array
    {
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE from_url = %s", $path), ARRAY_A);

        if (!$row) {
            return null;
        }

        return [
            'id' => $row['id'],
            'from' => $row['from_url'],
            'to' => $row['to_url'],
            'code' => $row['status_code']
        ];
    }

    public static function add(string $from, string $to, int $code = 301): void
    {
        global $wpdb;
        $table = self::table_name();

        $wpdb->insert(
            $table,
            [
                'from_url' => self::normalize_path($from),
                'to_url' => esc_url_raw($to),
                'status_code' => in_array($code, [301, 302, 307, 308], true) ? $code : 301,
            ],
            ['%s', '%s', '%d']
        );
    }

    public static function delete(string $id): void
    {
        global $wpdb;
        $table = self::table_name();
        $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Normalise l’URL "source" en chemin + query (sans slash final, sauf racine).
     */
    public static function normalize_path(string $url): string
    {
        $url = trim($url);
        $parts = wp_parse_url($url);

        if ($parts && isset($parts['path'])) {
            $path = $parts['path'] ?: '/';
            $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        } else {
            $qpos = strpos($url, '?');
            if ($qpos !== false) {
                $path = substr($url, 0, $qpos);
                $query = substr($url, $qpos);
            } else {
                $path = $url ?: '/';
                $query = '';
            }
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }
        return $path . $query;
    }

    /**
     * Convertit une cible potentielle en URL absolue sur le site, si besoin.
     */
    public static function to_absolute(string $maybe): string
    {
        if (preg_match('#^https?://#i', $maybe)) {
            return $maybe;
        }
        if (strpos($maybe, '/') === 0) {
            return home_url($maybe);
        }
        return home_url(self::normalize_path('/' . ltrim($maybe, '/')));
    }
}
