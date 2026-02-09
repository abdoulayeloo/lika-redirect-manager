<?php
namespace Lika\RedirectManager;

if (!defined('ABSPATH')) {
    exit;
}

require_once LRM_PATH . 'includes/RulesStore.php';
require_once LRM_PATH . 'includes/Redirector.php';
require_once LRM_PATH . 'includes/Tracker404.php';
require_once LRM_PATH . 'includes/Admin/Page.php';

final class Plugin
{
    private static $instance;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function on_activate(): void
    {
        RulesStore::install();
    }

    private static function maybe_install_tables(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'lrm_rules';
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            RulesStore::install();
        }
    }

    public function init(): void
    {
        // Auto-install tables if missing
        self::maybe_install_tables();
        // Front: exécution des redirections tôt
        add_action('template_redirect', [Redirector::class, 'maybe_redirect'], 1);
        // Front: suivi des 404
        Tracker404::init();
        // Admin: page réglages
        if (is_admin()) {
            add_action('admin_menu', [Admin\Page::class, 'register_menu']);
            add_action('admin_init', [Admin\Page::class, 'handle_request']);
        }
    }
}
