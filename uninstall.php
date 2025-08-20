<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to run on plugin uninstall
function wp_ai_sql_explorer_pro_uninstall() {
    // Remove options from the database
    delete_option('wp_ai_sql_explorer_pro_options');
    
    // Additional cleanup can be added here
}

// Hook the uninstall function
register_uninstall_hook(__FILE__, 'wp_ai_sql_explorer_pro_uninstall');