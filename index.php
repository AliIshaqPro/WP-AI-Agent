<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'api/chat-handler.php';
require_once plugin_dir_path(__FILE__) . 'api/sql-handler.php';
require_once plugin_dir_path(__FILE__) . 'core/chatbot.php';
require_once plugin_dir_path(__FILE__) . 'core/database.php';
require_once plugin_dir_path(__FILE__) . 'core/security.php';
require_once plugin_dir_path(__FILE__) . 'helpers/utils.php';
?>