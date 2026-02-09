<?php

/**
 * Plugin Name: Lika Redirect Manager
 * Plugin URI:  https://likagroupe.com/
 * Description: Gestion simple de redirections (Ancienne URL → Nouvelle URL)
 * Version:     1.2.0
 * Author:      Laye
 * License:     GPL-2.0-or-later
 * Text Domain: lika-redirect-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LRM_PATH', plugin_dir_path(__FILE__));
define('LRM_URL', plugin_dir_url(__FILE__));
define('LRM_BASENAME', plugin_basename(__FILE__));

require_once LRM_PATH . 'includes/Plugin.php';

add_action('plugins_loaded', function () {
    // Chargement i18n
    load_plugin_textdomain('lika-redirect-manager', false, dirname(LRM_BASENAME) . '/languages/');
    // Bootstrap
    \Lika\RedirectManager\Plugin::instance()->init();
});

/**
 * Activation: initialise l’option si absente.
 */
register_activation_hook(__FILE__, function () {
    \Lika\RedirectManager\Plugin::on_activate();
});
