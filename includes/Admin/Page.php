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

        // Ajout d’une règle
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

        // Suppression d’une règle
        if (isset($_GET['lrm_action']) && $_GET['lrm_action'] === 'delete' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            check_admin_referer('lrm_delete_' . $id);
            RulesStore::delete($id);
            add_settings_error('lrm_notices', 'lrm_deleted', __('Règle supprimée.', 'lika-redirect-manager'), 'updated');
            return;
        }
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $rules = RulesStore::all();
        settings_errors('lrm_notices');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Redirections', 'lika-redirect-manager'); ?></h1>

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
                                placeholder="/ancienne-page ou https://exemple.com/ancienne-page?x=1">
                            <p class="description">
                                <?php esc_html_e('Chemin ou URL complète. La comparaison se fait sur chemin + requête.', 'lika-redirect-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lrm_to"><?php esc_html_e('Nouvelle URL', 'lika-redirect-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="lrm_to" name="lrm_to" class="regular-text" required
                                placeholder="/nouvelle-page ou https://exemple.com/nouvelle-page">
                            <p class="description">
                                <?php esc_html_e('Chemin interne ou URL absolue externe. Les chemins seront préfixés par le domaine du site.', 'lika-redirect-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="lrm_code"><?php esc_html_e('Code de redirection', 'lika-redirect-manager'); ?></label></th>
                        <td>
                            <select id="lrm_code" name="lrm_code">
                                <option value="301" selected>301 (permanent)</option>
                                <option value="302">302 (temporaire)</option>
                                <option value="307">307</option>
                                <option value="308">308</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Ajouter la redirection', 'lika-redirect-manager')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Règles existantes', 'lika-redirect-manager'); ?></h2>
            <?php if (empty($rules)): ?>
                <p><?php esc_html_e('Aucune règle pour le moment.', 'lika-redirect-manager'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ancienne URL (normalisée)', 'lika-redirect-manager'); ?></th>
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
                                        onclick="return confirm('<?php echo esc_js(__('Supprimer cette règle ?', 'lika-redirect-manager')); ?>');">
                                        <?php esc_html_e('Supprimer', 'lika-redirect-manager'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
