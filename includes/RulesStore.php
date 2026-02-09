<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) {
    exit;
}

class RulesStore
{
    public const TABLE_NAME = 'lrm_rules';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function install(): void
    {
        global $wpdb;
        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_url varchar(191) NOT NULL,
            to_url text NOT NULL,
            status_code int(3) NOT NULL DEFAULT 301,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY from_url (from_url)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        self::migrate();
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
