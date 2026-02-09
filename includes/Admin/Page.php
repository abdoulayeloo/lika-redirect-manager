<?php
namespace Lika\RedirectManager\Admin;

use Lika\RedirectManager\RulesStore;

if (!defined('ABSPATH')) {
    exit;
}

class Page
{
    public static function register_menu(): void
    {
        add_options_page(
            __('Redirections', 'lika-redirect-manager'),
            __('Redirections', 'lika-redirect-manager'),
            'manage_options',
            'lika-redirect-manager',
            [self::class, 'render']
        );
    }

    public static function handle_request(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Ajout d'une règle
        if (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'add') {
            check_admin_referer('lrm_nonce');
            $from = isset($_POST['lrm_from']) ? trim(wp_unslash($_POST['lrm_from'])) : '';
            $to = isset($_POST['lrm_to']) ? trim(wp_unslash($_POST['lrm_to'])) : '';
            $code = isset($_POST['lrm_code']) ? (int) $_POST['lrm_code'] : 301;

            if ($from !== '' && $to !== '' && in_array($code, [301, 302, 307, 308], true)) {
                RulesStore::add($from, $to, $code);
                add_settings_error('lrm_notices', 'lrm_added', __('Règle ajoutée.', 'lika-redirect-manager'), 'updated');
            } else {
                add_settings_error('lrm_notices', 'lrm_invalid', __('Entrées invalides.', 'lika-redirect-manager'), 'error');
            }
            return;
        }

        // Redirection depuis erreur 404
        if (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'redirect_404') {
            check_admin_referer('lrm_redirect_404');
            $from = isset($_POST['lrm_404_url']) ? trim(wp_unslash($_POST['lrm_404_url'])) : '';
            $to = isset($_POST['lrm_redirect_to']) ? trim(wp_unslash($_POST['lrm_redirect_to'])) : '';

            if ($from !== '' && $to !== '') {
                RulesStore::add($from, $to, 301);
                RulesStore::delete_404($from);
                add_settings_error('lrm_notices', 'lrm_redirected', __('Redirection créée et 404 supprimée.', 'lika-redirect-manager'), 'updated');
            }
            return;
        }

        // Suppression d'une règle
        if (isset($_GET['lrm_action']) && $_GET['lrm_action'] === 'delete' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            check_admin_referer('lrm_delete_' . $id);
            RulesStore::delete($id);
            add_settings_error('lrm_notices', 'lrm_deleted', __('Règle supprimée.', 'lika-redirect-manager'), 'updated');
            return;
        }

        // Suppression d'une entrée 404
        if (isset($_GET['lrm_action']) && $_GET['lrm_action'] === 'delete_404' && isset($_GET['url'])) {
            $url = sanitize_text_field(wp_unslash($_GET['url']));
            check_admin_referer('lrm_delete_404_' . md5($url));
            RulesStore::delete_404($url);
            add_settings_error('lrm_notices', 'lrm_404_deleted', __('Entrée 404 supprimée.', 'lika-redirect-manager'), 'updated');
            return;
        }

        // Vider toutes les 404
        if (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'clear_all_404') {
            check_admin_referer('lrm_clear_all_404');
            RulesStore::clear_all_404s();
            add_settings_error('lrm_notices', 'lrm_404_cleared', __('Toutes les erreurs 404 ont été supprimées.', 'lika-redirect-manager'), 'updated');
            return;
        }
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'redirections';
        settings_errors('lrm_notices');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Lika Redirect Manager', 'lika-redirect-manager'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=lika-redirect-manager&tab=redirections')); ?>"
                    class="nav-tab <?php echo $tab === 'redirections' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Redirections', 'lika-redirect-manager'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=lika-redirect-manager&tab=erreurs-404')); ?>"
                    class="nav-tab <?php echo $tab === 'erreurs-404' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Erreurs 404', 'lika-redirect-manager'); ?>
                </a>
            </nav>

            <div class="tab-content" style="margin-top: 20px;">
                <?php
                if ($tab === 'redirections') {
                    self::render_redirections_tab();
                } elseif ($tab === 'erreurs-404') {
                    self::render_404_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_redirections_tab(): void
    {
        $rules = RulesStore::all();
        ?>
        <h2><?php esc_html_e('Ajouter une redirection', 'lika-redirect-manager'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('lrm_nonce'); ?>
            <input type="hidden" name="lrm_action" value="add" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="lrm_from"><?php esc_html_e('Ancienne URL', 'lika-redirect-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="lrm_from" name="lrm_from" class="regular-text" required
                            placeholder="/ancienne-page">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lrm_to"><?php esc_html_e('Nouvelle URL', 'lika-redirect-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="lrm_to" name="lrm_to" class="regular-text" required placeholder="/nouvelle-page">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lrm_code"><?php esc_html_e('Code', 'lika-redirect-manager'); ?></label></th>
                    <td>
                        <select id="lrm_code" name="lrm_code">
                            <option value="301" selected>301</option>
                            <option value="302">302</option>
                            <option value="307">307</option>
                            <option value="308">308</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Ajouter', 'lika-redirect-manager')); ?>
        </form>

        <hr>

        <h2><?php esc_html_e('Règles existantes', 'lika-redirect-manager'); ?></h2>
        <?php if (empty($rules)): ?>
            <p><?php esc_html_e('Aucune règle pour le moment.', 'lika-redirect-manager'); ?></p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ancienne URL', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Nouvelle URL', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Code', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Actions', 'lika-redirect-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $r): ?>
                        <tr>
                            <td><code><?php echo esc_html($r['from']); ?></code></td>
                            <td><code><?php echo esc_html($r['to']); ?></code></td>
                            <td><?php echo (int) $r['code']; ?></td>
                            <td>
                                <?php
                                $del_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'lika-redirect-manager',
                                        'lrm_action' => 'delete',
                                        'id' => $r['id'],
                                    ], admin_url('options-general.php')),
                                    'lrm_delete_' . $r['id']
                                );
                                ?>
                                <a class="button-link delete" href="<?php echo esc_url($del_url); ?>"
                                    onclick="return confirm('<?php echo esc_js(__('Supprimer ?', 'lika-redirect-manager')); ?>');">
                                    <?php esc_html_e('Supprimer', 'lika-redirect-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php
    }

    private static function render_404_tab(): void
    {
        $errors = RulesStore::get_404s();
        ?>
        <h2><?php esc_html_e('Erreurs 404 détectées', 'lika-redirect-manager'); ?></h2>
        <p class="description">
            <?php esc_html_e('Les URLs ci-dessous ont généré des erreurs 404. Vous pouvez créer une redirection pour chacune.', 'lika-redirect-manager'); ?>
        </p>

        <?php if (!empty($errors)): ?>
            <form method="post" action="" style="margin-bottom: 20px;">
                <?php wp_nonce_field('lrm_clear_all_404'); ?>
                <input type="hidden" name="lrm_action" value="clear_all_404" />
                <?php submit_button(__('Vider la liste', 'lika-redirect-manager'), 'secondary', 'submit', false); ?>
            </form>
        <?php endif; ?>

        <?php if (empty($errors)): ?>
            <p><?php esc_html_e('Aucune erreur 404 enregistrée.', 'lika-redirect-manager'); ?></p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('URL', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Visites', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Dernière vue', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Rediriger vers', 'lika-redirect-manager'); ?></th>
                        <th><?php esc_html_e('Actions', 'lika-redirect-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $e): ?>
                        <tr>
                            <td><code><?php echo esc_html($e['url']); ?></code></td>
                            <td><?php echo (int) $e['hit_count']; ?></td>
                            <td><?php echo esc_html($e['last_seen']); ?></td>
                            <td>
                                <form method="post" action="" style="display: flex; gap: 5px; align-items: center;">
                                    <?php wp_nonce_field('lrm_redirect_404'); ?>
                                    <input type="hidden" name="lrm_action" value="redirect_404" />
                                    <input type="hidden" name="lrm_404_url" value="<?php echo esc_attr($e['url']); ?>" />
                                    <input type="text" name="lrm_redirect_to" class="regular-text" placeholder="/nouvelle-url" required
                                        style="width: 200px;" />
                                    <button type="submit"
                                        class="button button-primary"><?php esc_html_e('Rediriger', 'lika-redirect-manager'); ?></button>
                                </form>
                            </td>
                            <td>
                                <?php
                                $del_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'lika-redirect-manager',
                                        'tab' => 'erreurs-404',
                                        'lrm_action' => 'delete_404',
                                        'url' => $e['url'],
                                    ], admin_url('options-general.php')),
                                    'lrm_delete_404_' . md5($e['url'])
                                );
                                ?>
                                <a class="button-link delete" href="<?php echo esc_url($del_url); ?>"
                                    onclick="return confirm('<?php echo esc_js(__('Ignorer cette erreur ?', 'lika-redirect-manager')); ?>');">
                                    <?php esc_html_e('Ignorer', 'lika-redirect-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php
    }
}
