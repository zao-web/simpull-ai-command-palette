<?php
namespace AICP\UI;

class Frontend_Handler {
    public function __construct() {
        // Add keyboard shortcut handler
        add_action('wp_footer', [$this, 'add_keyboard_handler']);
        add_action('admin_footer', [$this, 'add_keyboard_handler']);

        // Add command palette container
        add_action('wp_footer', [$this, 'add_palette_container']);
        add_action('admin_footer', [$this, 'add_palette_container']);
    }

    /**
     * Add keyboard shortcut handler script
     */
    public function add_keyboard_handler() {
        if (!$this->should_load_palette()) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the command palette when the script loads
            if (window.AICP && window.AICP.init) {
                window.AICP.init();
            }
        });
        </script>
        <?php
    }

    /**
     * Add command palette container
     */
    public function add_palette_container() {
        if (!$this->should_load_palette()) {
            return;
        }
        ?>
        <div id="aicp-command-palette-root"></div>
        <?php
    }

    /**
     * Check if palette should load
     */
    private function should_load_palette() {
        // Must be logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Must have at least edit_posts capability
        if (!current_user_can('edit_posts')) {
            return false;
        }

        // Check if enabled on frontend
        if (!is_admin()) {
            $settings = get_option('aicp_settings', []);
            if (empty($settings['enable_frontend'])) {
                return false;
            }
        }

        return true;
    }
}