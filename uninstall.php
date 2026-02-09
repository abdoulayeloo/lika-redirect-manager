<?php
/**
 * Désinstallation : nettoyage des options créées par le plugin.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

delete_option('lrm_rules');
