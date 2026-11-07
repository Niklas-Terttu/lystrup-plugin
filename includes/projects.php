<?php
/**
 * Public association projects. Editing is restricted to site administrators.
 */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('lystrup_project', [
        'labels' => [
            'name' => 'Projekter', 'singular_name' => 'Projekt',
            'add_new_item' => 'Opret projekt', 'edit_item' => 'Rediger projekt',
            'all_items' => 'Alle projekter', 'menu_name' => 'Foreningens projekter',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'projekt'],
        'menu_icon' => 'dashicons-hammer',
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
        'capability_type' => 'post',
        'capabilities' => [
            'edit_post' => 'manage_options',
            'read_post' => 'read',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
            'create_posts' => 'manage_options',
        ],
        'map_meta_cap' => false,
    ]);
});

add_action('add_meta_boxes_lystrup_project', function () {
    add_meta_box('lystrup_project_details', 'Projektstatus', function ($post) {
        wp_nonce_field('lystrup_project_save', 'lystrup_project_nonce');
        $status = get_post_meta($post->ID, '_lystrup_project_status', true) ?: 'planning';
        $progress = (int) get_post_meta($post->ID, '_lystrup_project_progress', true);
        $statuses = ['planning' => 'Planlægning', 'fundraising' => 'Indsamling', 'ongoing' => 'I gang', 'paused' => 'På pause', 'completed' => 'Afsluttet'];
        echo '<p><label for="lystrup_project_status"><strong>Status</strong></label><br><select id="lystrup_project_status" name="lystrup_project_status">';
        foreach ($statuses as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p><p><label for="lystrup_project_progress"><strong>Fremdrift (0–100 %)</strong></label><br>';
        echo '<input type="number" id="lystrup_project_progress" name="lystrup_project_progress" min="0" max="100" step="1" value="' . esc_attr($progress) . '"></p>';
        echo '<p>Brug titel og indholdsfelt til projektets navn og beskrivelse. Vælg et fremhævet billede til kortet. Kun udgivne projekter vises offentligt.</p>';
    }, 'lystrup_project', 'side', 'default');
});

add_action('save_post_lystrup_project', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['lystrup_project_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lystrup_project_nonce'])), 'lystrup_project_save')) return;
    $statuses = ['planning', 'fundraising', 'ongoing', 'paused', 'completed'];
    $status = sanitize_key(wp_unslash($_POST['lystrup_project_status'] ?? 'planning'));
    if (!in_array($status, $statuses, true)) $status = 'planning';
    $progress = max(0, min(100, (int) ($_POST['lystrup_project_progress'] ?? 0)));
    update_post_meta($post_id, '_lystrup_project_status', $status);
    update_post_meta($post_id, '_lystrup_project_progress', $progress);
});

add_shortcode('lystrup_projects', function ($atts) {
    $atts = shortcode_atts(['limit' => 100], $atts, 'lystrup_projects');
    $limit = max(1, min(100, (int) $atts['limit']));
    $projects = new WP_Query([
        'post_type' => 'lystrup_project', 'post_status' => 'publish',
        'posts_per_page' => $limit, 'orderby' => 'date', 'order' => 'DESC',
        'no_found_rows' => true,
    ]);
    $statuses = ['planning' => 'Planlægning', 'fundraising' => 'Indsamling', 'ongoing' => 'I gang', 'paused' => 'På pause', 'completed' => 'Afsluttet'];
    ob_start();
    echo '<div class="lystrup-project-grid">';
    if (!$projects->have_posts()) echo '<p>Der er endnu ingen offentliggjorte projekter.</p>';
    while ($projects->have_posts()) {
        $projects->the_post();
        $id = get_the_ID();
        $status = get_post_meta($id, '_lystrup_project_status', true);
        $progress = max(0, min(100, (int) get_post_meta($id, '_lystrup_project_progress', true)));
        $description = wp_trim_words(wp_strip_all_tags(get_the_content()), 24, '…');
        echo '<article class="lystrup-project-card">';
        echo '<a class="lystrup-project-image" href="' . esc_url(get_permalink()) . '" aria-label="' . esc_attr(get_the_title()) . '">';
        if (has_post_thumbnail()) {
            echo get_the_post_thumbnail($id, 'large', ['loading' => 'lazy']);
        } else {
            echo '<span class="lystrup-project-placeholder" aria-hidden="true">✳</span>';
        }
        echo '</a><div class="lystrup-project-content">';
        echo '<span class="lystrup-project-status">' . esc_html($statuses[$status] ?? 'Planlægning') . '</span>';
        echo '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
        if ($description) echo '<p>' . esc_html($description) . '</p>';
        echo '<div class="lystrup-project-progress" role="progressbar" aria-label="Projektets fremdrift" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($progress) . '"><span style="width:' . esc_attr($progress) . '%"></span></div>';
        echo '<strong class="lystrup-project-percent">' . esc_html($progress) . '%</strong>';
        echo '</div></article>';
    }
    wp_reset_postdata();
    echo '</div>';
    return ob_get_clean();
});

add_action('wp_enqueue_scripts', function () {
    wp_register_style('lystrup-project-cards', false, [], '0.3.1');
    wp_enqueue_style('lystrup-project-cards');
    wp_add_inline_style('lystrup-project-cards', '
      .lystrup-project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px;margin:24px 0}
      @media(max-width:900px){.lystrup-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}\n      @media(max-width:600px){.lystrup-project-grid{grid-template-columns:minmax(0,1fr)}}\n      .lystrup-project-card{overflow:hidden;border:1px solid #dbe5e7;border-radius:17px;background:#fff;box-shadow:0 2px 5px #193b3410;display:flex;flex-direction:column}
      .lystrup-project-image{height:193px;background:#e5eeee;display:flex;align-items:center;justify-content:center;overflow:hidden}
      .lystrup-project-image img{width:100%;height:100%;object-fit:cover}
      .lystrup-project-placeholder{font-size:58px;color:#006c5b}
      .lystrup-project-content{padding:24px;flex:1}
      .lystrup-project-status{display:inline-block;padding:5px 12px;border-radius:99px;background:#e4edff;color:#1647a3;font-size:13px;font-weight:700}
      .lystrup-project-content h3{font-size:21px;line-height:1.35;margin:15px 0 10px}
      .lystrup-project-content h3 a{color:#14233b;text-decoration:none}
      .lystrup-project-content h3 a:hover{text-decoration:underline}
      .lystrup-project-content p{margin:0 0 22px;color:#43516b;line-height:1.6}
      .lystrup-project-progress{height:8px;border-radius:99px;background:#edf1f5;overflow:hidden}
      .lystrup-project-progress span{display:block;height:100%;background:#07946d;border-radius:99px}
      .lystrup-project-percent{display:block;margin-top:8px;font-size:13px;color:#14233b}
    ');
});
