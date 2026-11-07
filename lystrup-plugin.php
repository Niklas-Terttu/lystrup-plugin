<?php
/**
 * Plugin Name: Lystrup Borgerforening
 * Description: Godkendt borgeradgang og bestyrelsesportal for Lystrup.
 * Version: 0.3.0
 * Update URI: https://github.com/Niklas-Terttu/lystrup-plugin
 * Requires PHP: 8.0
 * Text Domain: lystrup
 */
defined('ABSPATH') || exit;

final class Lystrup_Plugin {
    const PENDING = 'lystrup_pending';
    const CITIZEN = 'lystrup_citizen';
    const BOARD = 'lystrup_board';

    public static function boot() {
        add_action('init', [__CLASS__, 'roles']);
        add_shortcode('lystrup_registration', [__CLASS__, 'registration']);
        add_shortcode('lystrup_login', [__CLASS__, 'login']);
        add_shortcode('lystrup_portal', [__CLASS__, 'portal']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_lystrup_approve', [__CLASS__, 'approve']);
        add_action('admin_post_lystrup_reject', [__CLASS__, 'reject']);
        add_filter('authenticate', [__CLASS__, 'block_pending'], 30, 3);
        add_filter('show_admin_bar', [__CLASS__, 'admin_bar']);
        add_action('admin_init', [__CLASS__, 'restrict_dashboard']);
    }
    public static function roles() {
        add_role(self::PENDING, 'Afventer godkendelse', ['read' => true]);
        add_role(self::CITIZEN, 'Godkendt borger', ['read' => true]);
        add_role(self::BOARD, 'Bestyrelse', ['read' => true]);
    }
    public static function can_citizen() {
        $u = wp_get_current_user();
        return is_user_logged_in() && (user_can($u, 'manage_options') || in_array(self::CITIZEN, (array)$u->roles, true) || in_array(self::BOARD, (array)$u->roles, true));
    }
    public static function can_board() {
        $u = wp_get_current_user();
        return is_user_logged_in() && (user_can($u, 'manage_options') || in_array(self::BOARD, (array)$u->roles, true));
    }
    public static function block_pending($user, $username, $password) {
        if ($user instanceof WP_User && in_array(self::PENDING, (array)$user->roles, true)) {
            return new WP_Error('lystrup_pending', 'Din konto afventer bestyrelsens godkendelse.');
        }
        return $user;
    }
    public static function registration() {
        if (is_user_logged_in()) return '<p>Du er allerede logget ind.</p>';
        $message = '';
        if (isset($_POST['lystrup_register'])) {
            if (!isset($_POST['lystrup_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lystrup_nonce'])), 'lystrup_register')) {
                $message = '<p role="alert">Ugyldig forespørgsel. Prøv igen.</p>';
            } elseif (!empty($_POST['website'])) {
                $message = '<p role="alert">Registrering kunne ikke gennemføres.</p>';
            } else {
                $name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
                $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
                $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
                $password = (string) wp_unslash($_POST['password'] ?? '');
                if (mb_strlen($name) < 2 || !is_email($email) || mb_strlen($address) < 5 || strlen($password) < 12) {
                    $message = '<p role="alert">Udfyld alle felter. Adgangskoden skal være mindst 12 tegn.</p>';
                } elseif (email_exists($email)) {
                    $message = '<p role="alert">Registrering kunne ikke gennemføres. Kontakt foreningen, hvis du allerede har en konto.</p>';
                } else {
                    $username = 'lystrup_' . wp_generate_password(16, false, false);
                    $id = wp_insert_user(['user_login' => $username, 'user_email' => $email, 'display_name' => $name, 'user_pass' => $password, 'role' => self::PENDING]);
                    if (is_wp_error($id)) {
                        $message = '<p role="alert">Registrering kunne ikke gennemføres.</p>';
                    } else {
                        update_user_meta($id, 'lystrup_address', $address);
                        $message = '<p role="status">Tak! Din ansøgning afventer godkendelse.</p>';
                        wp_mail(get_option('admin_email'), 'Ny borgeransøgning', 'En ny borger har ansøgt om adgang. Gennemgå ansøgningen i WordPress.');
                    }
                }
            }
        }
        ob_start(); echo wp_kses_post($message); ?>
        <form method="post" class="lystrup-form">
            <?php wp_nonce_field('lystrup_register', 'lystrup_nonce'); ?>
            <label>Fulde navn <input name="full_name" required maxlength="120" autocomplete="name"></label>
            <label>E-mail <input type="email" name="email" required autocomplete="email"></label>
            <label>Adresse i byen <input name="address" required maxlength="200" autocomplete="street-address"></label>
            <label>Adgangskode (mindst 12 tegn) <input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="lystrup-hp">
            <button type="submit" name="lystrup_register" value="1">Ansøg om adgang</button>
        </form>
        <?php return ob_get_clean();
    }
    public static function login($atts) {
        if (is_user_logged_in()) return '<p>Du er logget ind. <a href="' . esc_url(wp_logout_url(home_url('/'))) . '">Log ud</a></p>';
        return wp_login_form(['echo' => false, 'redirect' => home_url('/borgerportal/')]);
    }
    public static function portal($atts) {
        $atts = shortcode_atts(['area' => 'citizen'], $atts);
        if (!is_user_logged_in()) return '<p>Log ind for at se denne side.</p>' . self::login([]);
        if (!self::can_citizen()) return '<p>Du har ikke adgang til borgerportalen.</p>';
        if ($atts['area'] === 'board' && !self::can_board()) return '<p>Dette område er kun for bestyrelsen.</p>';
        $html = '<section class="lystrup-private"><h2>' . ($atts['area'] === 'board' ? 'Bestyrelsesportal' : 'Borgerportal') . '</h2><p>Velkommen til det beskyttede område.</p>';
        if ($atts['area'] === 'board') $html .= '<p>Dokumentdeling og interne opslag er under udvikling. Upload ikke fortrolige filer endnu.</p>';
        return $html . '</section>';
    }
    public static function admin_menu() {
        add_users_page('Borgeransøgninger', 'Borgeransøgninger', 'manage_options', 'lystrup-applications', [__CLASS__, 'applications']);
    }
    public static function applications() {
        if (!current_user_can('manage_options')) wp_die('Ingen adgang.');
        $users = get_users(['role' => self::PENDING, 'number' => 100]);
        echo '<div class="wrap"><h1>Borgeransøgninger</h1><p>Kontrollér identitet og adresse, før du godkender en ansøgning.</p><table class="widefat striped"><thead><tr><th>Navn</th><th>E-mail</th><th>Adresse</th><th>Handling</th></tr></thead><tbody>';
        foreach ($users as $u) {
            echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . esc_html($u->user_email) . '</td><td>' . esc_html(get_user_meta($u->ID, 'lystrup_address', true)) . '</td><td>';
            foreach (['approve' => 'Godkend', 'reject' => 'Afvis'] as $action => $label) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px">';
                echo '<input type="hidden" name="action" value="lystrup_' . esc_attr($action) . '"><input type="hidden" name="user_id" value="' . esc_attr($u->ID) . '">';
                wp_nonce_field('lystrup_' . $action . '_' . $u->ID);
                echo '<button class="button" type="submit">' . esc_html($label) . '</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    private static function change_status($approve) {
        if (!current_user_can('manage_options')) wp_die('Ingen adgang.', '', ['response' => 403]);
        $id = absint($_POST['user_id'] ?? 0);
        check_admin_referer('lystrup_' . ($approve ? 'approve' : 'reject') . '_' . $id);
        $user = get_userdata($id);
        if (!$user || !in_array(self::PENDING, (array)$user->roles, true)) wp_die('Ugyldig ansøgning.', '', ['response' => 400]);
        if ($approve) {
            $user->set_role(self::CITIZEN);
            wp_mail($user->user_email, 'Din ansøgning er godkendt', 'Du kan nu logge ind på Lystrup Borgerforenings hjemmeside.');
        } else {
            $user->set_role('lystrup_rejected');
            add_role('lystrup_rejected', 'Afvist ansøgning', ['read' => true]);
        }
        wp_safe_redirect(admin_url('users.php?page=lystrup-applications')); exit;
    }
    public static function approve() { self::change_status(true); }
    public static function reject() { self::change_status(false); }
    public static function admin_bar($show) { return self::can_board() ? $show : false; }
    public static function restrict_dashboard() {
        if (!wp_doing_ajax() && is_user_logged_in() && !current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_safe_redirect(home_url('/borgerportal/')); exit;
        }
    }
}
register_activation_hook(__FILE__, ['Lystrup_Plugin', 'roles']);
Lystrup_Plugin::boot();
require_once __DIR__ . '/includes/update-check.php';

require_once __DIR__ . '/includes/github-updates.php';

require_once __DIR__ . '/includes/projects.php';
