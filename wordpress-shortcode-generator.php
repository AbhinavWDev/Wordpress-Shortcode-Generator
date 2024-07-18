<?php
/*
Plugin Name: Shortcode Generator
Description: A plugin that provides an interface to generate custom shortcodes for frequently used HTML or other content snippets.
Version: 1.3
Author: Abhinav Saxena
*/

// Hook into WordPress to initialize our plugin
add_action('admin_menu', 'scg_admin_menu');
add_action('admin_init', 'scg_register_settings');
add_action('admin_enqueue_scripts', 'scg_admin_enqueue_scripts');

// Add admin menu
function scg_admin_menu() {
    add_menu_page(
        'Shortcode Generator',
        'Shortcode Generator',
        'manage_options',
        'scg_admin_page',
        'scg_admin_page_callback',
        'dashicons-editor-code',
        100
    );
}

// Register settings
function scg_register_settings() {
    register_setting('scg_settings_group', 'scg_shortcodes', 'scg_validate_shortcodes');
}

// Enqueue admin scripts and styles
function scg_admin_enqueue_scripts($hook) {
    if ($hook == 'toplevel_page_scg_admin_page') {
        wp_enqueue_style('scg-admin-style', plugins_url('css/admin-style.css', __FILE__));
    }
}

// Admin page callback
function scg_admin_page_callback() {
    ?>
    <div class="wrap">
        <h1>Shortcode Generator</h1>
        <form method="post" action="options.php">
            <?php settings_fields('scg_settings_group'); ?>
            <?php do_settings_sections('scg_admin_page'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Shortcodes</th>
                    <td>
                        <textarea id="scg_shortcodes" name="scg_shortcodes" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(get_option('scg_shortcodes', '')); ?></textarea>
                        <p class="description">Enter your shortcodes here in the format: <code>[shortcode] => HTML content</code></p>
                        <?php if (get_transient('scg_validation_error')) : ?>
                            <p style="color: red;"><?php echo esc_html(get_transient('scg_validation_error')); ?></p>
                            <?php delete_transient('scg_validation_error'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Preview</h2>
        <div id="scg_preview">
            <?php echo scg_generate_preview(get_option('scg_shortcodes', '')); ?>
        </div>
    </div>
    <script type="text/javascript">
        document.getElementById('scg_shortcodes').addEventListener('input', function() {
            var data = new FormData();
            data.append('action', 'scg_generate_preview');
            data.append('shortcodes', this.value);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: data,
            })
            .then(response => response.text())
            .then(response => {
                document.getElementById('scg_preview').innerHTML = response;
            });
        });
    </script>
    <?php
}

// Validate shortcodes
function scg_validate_shortcodes($input) {
    $shortcodes = explode("\n", $input);
    $valid_shortcodes = [];

    foreach ($shortcodes as $shortcode) {
        $shortcode = trim($shortcode);
        if ($shortcode) {
            if (strpos($shortcode, '=>') === false) {
                set_transient('scg_validation_error', 'Invalid format. Each line should be in the format: [shortcode] => HTML content', 10);
                return get_option('scg_shortcodes');
            }
            list($tag, $content) = explode('=>', $shortcode, 2);
            $tag = trim($tag);
            $content = trim($content);

            if (!preg_match('/^\[[a-zA-Z0-9_-]+\]$/', $tag)) {
                set_transient('scg_validation_error', 'Invalid shortcode tag. Only alphanumeric characters, dashes, and underscores are allowed in the shortcode name.', 10);
                return get_option('scg_shortcodes');
            }

            $valid_shortcodes[] = $tag . ' => ' . wp_kses_post($content);
        }
    }

    return implode("\n", $valid_shortcodes);
}

// Function to generate shortcodes from saved options
function scg_generate_shortcodes() {
    $shortcodes = get_option('scg_shortcodes', '');
    $shortcodes = explode("\n", $shortcodes);
    foreach ($shortcodes as $shortcode) {
        $shortcode = trim($shortcode);
        if ($shortcode) {
            list($tag, $content) = explode('=>', $shortcode, 2);
            $tag = trim($tag, '[] ');
            $content = trim($content);
            add_shortcode($tag, function() use ($content) {
                return do_shortcode($content);
            });
        }
    }
}
add_action('init', 'scg_generate_shortcodes');

// Generate preview for shortcodes
function scg_generate_preview($shortcodes) {
    $shortcodes = explode("\n", $shortcodes);
    $preview = '';

    foreach ($shortcodes as $shortcode) {
        $shortcode = trim($shortcode);
        if ($shortcode) {
            list($tag, $content) = explode('=>', $shortcode, 2);
            $tag = trim($tag, '[] ');
            $content = trim($content);
            $preview .= '<h3>' . esc_html($tag) . '</h3>';
            $preview .= '<div>' . do_shortcode($content) . '</div>';
        }
    }

    return $preview;
}

// Handle AJAX request for preview
function scg_ajax_generate_preview() {
    if (isset($_POST['shortcodes'])) {
        echo scg_generate_preview(sanitize_textarea_field($_POST['shortcodes']));
    }
    wp_die();
}
add_action('wp_ajax_scg_generate_preview', 'scg_ajax_generate_preview');

?>
