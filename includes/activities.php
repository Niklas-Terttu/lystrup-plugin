<?php
/**
 * Activities/events for Lystrup Borgerforening.
 */
defined('ABSPATH') || exit;

add_action('init', static function () {
    register_post_type('lystrup_activity', [
        'labels' => [
            'name' => 'Aktiviteter', 'singular_name' => 'Aktivitet',
            'menu_name' => 'Foreningens aktiviteter',
            'add_new_item' => 'Opret aktivitet', 'edit_item' => 'Rediger aktivitet',
            'all_items' => 'Alle aktiviteter',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'aktivitet'],
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'revisions'],
        'capability_type' => 'post',
        'capabilities' => [
            'edit_post' => 'manage_options', 'read_post' => 'read',
            'delete_post' => 'manage_options', 'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options', 'create_posts' => 'manage_options',
        ],
        'map_meta_cap' => false,
    ]);
});

add_action('add_meta_boxes_lystrup_activity', static function () {
    add_meta_box('lystrup_activity_info', 'Arrangementets oplysninger', static function ($post) {
        wp_nonce_field('lystrup_activity_save', 'lystrup_activity_nonce');
        $fields = [
            'date' => ['Dato', 'date'],
            'time' => ['Klokkeslæt', 'time'],
            'location' => ['Sted', 'text'],
            'notice' => ['Praktisk besked (f.eks. Tag gerne nissehuer på!)', 'text'],
            'registration' => ['Tilmelding (f.eks. Tilmelding ikke nødvendig)', 'text'],
        ];
        foreach ($fields as $key => $spec) {
            $value = get_post_meta($post->ID, '_lystrup_activity_' . $key, true);
            echo '<p><label for="lystrup_activity_' . esc_attr($key) . '"><strong>' . esc_html($spec[0]) . '</strong></label><br>';
            echo '<input style="width:100%" id="lystrup_activity_' . esc_attr($key) . '" type="' . esc_attr($spec[1]) . '" name="lystrup_activity_' . esc_attr($key) . '" value="' . esc_attr($value) . '"' . ($key === 'date' ? ' required' : '') . '></p>';
        }
        echo '<p>Beskriv arrangementet i det almindelige indholdsfelt. Udgiv aktiviteten for at vise den på hjemmesiden.</p>';
    }, 'lystrup_activity', 'normal', 'high');
});

add_action('save_post_lystrup_activity', static function ($post_id) {
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('manage_options')) return;
    if (!isset($_POST['lystrup_activity_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lystrup_activity_nonce'])), 'lystrup_activity_save')) return;
    foreach (['date', 'time', 'location', 'notice', 'registration'] as $key) {
        $value = sanitize_text_field(wp_unslash($_POST['lystrup_activity_' . $key] ?? ''));
        if ($key === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
            if (!$date || $date->format('Y-m-d') !== $value) $value = '';
        }
        if ($key === 'time' && $value !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) $value = '';
        if ($value === '') delete_post_meta($post_id, '_lystrup_activity_' . $key);
        else update_post_meta($post_id, '_lystrup_activity_' . $key, $value);
    }
});

add_shortcode('lystrup_activities', static function ($atts) {
    $atts = shortcode_atts(['limit' => 30], $atts, 'lystrup_activities');
    $limit = max(1, min(100, (int) $atts['limit']));
    $today = current_datetime()->format('Y-m-d');
    $query = new WP_Query([
        'post_type' => 'lystrup_activity',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_key' => '_lystrup_activity_date',
        'meta_value' => $today,
        'meta_compare' => '>=',
        'meta_type' => 'DATE',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);
    ob_start();
    echo '<div class="lystrup-activities">';
    if (!$query->have_posts()) echo '<p>Der er ingen kommende aktiviteter lige nu.</p>';
    while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $date_string = get_post_meta($id, '_lystrup_activity_date', true);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_string, wp_timezone());
        if (!$date || $date->format('Y-m-d') !== $date_string) continue;
        $time = get_post_meta($id, '_lystrup_activity_time', true);
        $location = get_post_meta($id, '_lystrup_activity_location', true);
        $notice = get_post_meta($id, '_lystrup_activity_notice', true);
        $registration = get_post_meta($id, '_lystrup_activity_registration', true);
        $month = wp_date('M', $date->getTimestamp(), wp_timezone());
        echo '<article class="lystrup-activity-card">';
        echo '<div class="lystrup-activity-date-panel"><span class="lystrup-calendar-icon" aria-hidden="true">▣</span>';
        echo '<strong>' . esc_html(wp_date('l j. F Y', $date->getTimestamp(), wp_timezone())) . '</strong>';
        if ($time) echo '<span>Kl. ' . esc_html($time) . '</span>';
        echo '</div><div class="lystrup-activity-main"><div class="lystrup-activity-meta">';
        echo '<span class="lystrup-activity-month">' . esc_html($month) . '</span>';
        if ($location) echo '<span class="lystrup-activity-location">📍 ' . esc_html($location) . '</span>';
        echo '</div><h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
        $description = wp_trim_words(wp_strip_all_tags(get_the_content()), 45, '…');
        if ($description) echo '<p class="lystrup-activity-description">' . esc_html($description) . '</p>';
        if ($notice || $registration) {
            echo '<div class="lystrup-activity-bottom">';
            if ($notice) echo '<span class="lystrup-activity-notice">' . esc_html($notice) . '</span>';
            if ($registration) echo '<span class="lystrup-activity-registration">' . esc_html($registration) . '</span>';
            echo '</div>';
        }
        echo '</div></article>';
    }
    wp_reset_postdata();
    echo '</div>';
    return ob_get_clean();
});

add_action('wp_enqueue_scripts', static function () {
    wp_register_style('lystrup-activity-cards', false, [], '0.4.1');
    wp_enqueue_style('lystrup-activity-cards');
    wp_add_inline_style('lystrup-activity-cards', '
      .lystrup-activities{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;margin:25px auto;max-width:1120px}
      .lystrup-activity-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;background:#fff;border:1px solid #dce5ee;border-radius:17px;box-shadow:0 5px 14px #182b3b12;color:#15243b}
      .lystrup-activity-date-panel{background:#f5f8fb;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:9px;padding:22px 24px;border-bottom:1px solid #dce5ee}
      .lystrup-calendar-icon{color:#008c6c;font-size:23px;line-height:1}
      .lystrup-activity-date-panel strong{font-size:15px}
      .lystrup-activity-date-panel>span:last-child{color:#617494}
      .lystrup-activity-main{padding:25px 28px}
      .lystrup-activity-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px;color:#60728d}
      .lystrup-activity-month{padding:4px 13px;background:#fff2cb;border-radius:99px;color:#9b5100;font-weight:700}
      .lystrup-activity-main h3{margin:11px 0 8px;font-size:21px;line-height:1.3}
      .lystrup-activity-main h3 a{color:#15243b;text-decoration:none}
      .lystrup-activity-main h3 a:hover{text-decoration:underline}
      .lystrup-activity-description{color:#3c5272;line-height:1.55;margin:0}
      .lystrup-activity-bottom{border-top:1px solid #e8edf3;margin-top:18px;padding-top:15px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px}
      .lystrup-activity-notice{padding:8px 12px;border-radius:6px;background:#e7fbf2;color:#00865f;font-weight:600}
      .lystrup-activity-registration{color:#4c6283}
      @media(max-width:900px){.lystrup-activities{grid-template-columns:repeat(2,minmax(0,1fr))}}\n      @media(max-width:600px){.lystrup-activities{grid-template-columns:minmax(0,1fr)}.lystrup-activity-date-panel,.lystrup-activity-main{padding:20px 22px}}
    ');
});
