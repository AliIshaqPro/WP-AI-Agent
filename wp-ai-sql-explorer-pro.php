<?php
/*
Plugin Name: WP AI SQL Explorer Pro - Friendly Chatbot
Description: Friendly AI chatbot that can talk with users and handle database queries with safety features
Version: 3.3
Author: Enhanced by Claude
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add menu in WP Admin
add_action('admin_menu', function() {
    add_menu_page(
        'AI SQL Explorer Pro',
        'AI SQL Explorer Pro',
        'manage_options',
        'wp-ai-sql-explorer-pro',
        'wp_ai_sql_explorer_pro_page',
        'dashicons-analytics',
        81
    );
});

// Enqueue CSS and JavaScript
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wp-ai-sql-explorer-pro') {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'wp-ai-sql-explorer-pro-style',
        plugin_dir_url(__FILE__) . 'assets/css/ai-sql-explorer.css',
        [],
        '3.3'
    );

    // Enqueue JavaScript
    wp_enqueue_script(
        'wp-ai-sql-explorer-pro-script',
        plugin_dir_url(__FILE__) . 'assets/js/ai-sql-explorer.js',
        ['jquery'],
        '3.3',
        true
    );

    // Localize script for AJAX
    wp_localize_script(
        'wp-ai-sql-explorer-pro-script',
        'wpAiSqlExplorer',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_ai_sql_explorer_nonce'),
            'api_key' => 'AIzaSyDUDc7jyAqHEyZ_UKoxMPByIXXZboYZewI'
        ]
    );
});

// AJAX handler for processing query/chat
add_action('wp_ajax_process_ai_query', 'wp_ai_sql_process_query');

function wp_ai_sql_process_query() {
    check_ajax_referer('wp_ai_sql_explorer_nonce', 'nonce');
    
    $user_input = sanitize_text_field($_POST['user_query']);
    $confirmation = isset($_POST['confirmation']) ? sanitize_text_field($_POST['confirmation']) : '';
    $pending_query = isset($_POST['pending_query']) ? sanitize_text_field($_POST['pending_query']) : '';
    
    // Check if this is a confirmation response
    if ($pending_query && $confirmation === "I am 100% sure") {
        $result = execute_confirmed_query($pending_query);
        wp_send_json_success($result);
        return;
    }
    
    // First, determine if this is a database query or just conversation
    $query_intent = analyze_user_intent($user_input);
    
    if ($query_intent['is_database_query']) {
        // Check if it's a dangerous operation
        if ($query_intent['needs_confirmation']) {
            wp_send_json_success([
                'type' => 'confirmation_needed',
                'message' => "⚠️ Hey there! I noticed you want to {$query_intent['operation']} some data. This action cannot be undone. \n\nAre you absolutely sure you want to proceed? If yes, please type exactly: **I am 100% sure**",
                'pending_query' => $user_input,
                'operation' => $query_intent['operation']
            ]);
            return;
        }
        
        // Process as database query
        $result = process_database_query($user_input);
        wp_send_json_success($result);
    } else {
        // Process as conversation
        $result = process_conversation($user_input);
        wp_send_json_success($result);
    }
}

function analyze_user_intent($user_input) {
    $api_key = "AIzaSyDUDc7jyAqHEyZ_UKoxMPByIXXZboYZewI";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

    $prompt = "Analyze this user input: '$user_input'\n\n";
    $prompt .= "Determine:\n";
    $prompt .= "1. Is this a database query request? (true/false)\n";
    $prompt .= "2. If it's a database query, does it involve UPDATE, DELETE, INSERT, DROP, ALTER, or TRUNCATE operations? (true/false)\n";
    $prompt .= "3. What type of operation is it? (select, update, delete, insert, conversation, etc.)\n\n";
    $prompt .= "Return ONLY a JSON object like: {\"is_database_query\": true/false, \"needs_confirmation\": true/false, \"operation\": \"operation_type\"}";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return ['is_database_query' => false, 'needs_confirmation' => false, 'operation' => 'conversation'];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $text = trim($body['candidates'][0]['content']['parts'][0]['text']);
        $text = preg_replace('/```json?/i', '', $text);
        $text = str_replace('```', '', $text);
        $intent = json_decode(trim($text), true);
        if (is_array($intent)) {
            return $intent;
        }
    }
    
    return ['is_database_query' => false, 'needs_confirmation' => false, 'operation' => 'conversation'];
}

function process_conversation($user_input) {
    $api_key = "AIzaSyDUDc7jyAqHEyZ_UKoxMPByIXXZboYZewI";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

    $prompt = "You are a friendly and helpful AI assistant for a WordPress database explorer tool. ";
    $prompt .= "The user said: '$user_input'\n\n";
    $prompt .= "Respond in a friendly, conversational manner. You can:\n";
    $prompt .= "- Answer questions about WordPress, databases, or general topics\n";
    $prompt .= "- Help users understand how to use the database explorer\n";
    $prompt .= "- Provide friendly conversation\n";
    $prompt .= "- Guide users on how to ask database questions\n\n";
    $prompt .= "Keep your response warm, helpful, and conversational. Use emojis occasionally to be friendly.";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return [
            'type' => 'conversation',
            'message' => "Hi there! 😊 I'm here to help you with your database queries and chat with you. How can I assist you today?"
        ];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_response = trim($body['candidates'][0]['content']['parts'][0]['text']);
        return [
            'type' => 'conversation',
            'message' => $ai_response
        ];
    }
    
    return [
        'type' => 'conversation',
        'message' => "Hi there! 😊 I'm your friendly database assistant. Feel free to ask me about your database or just chat!"
    ];
}

function process_database_query($user_query) {
    // Step 1: Get database structure
    global $wpdb;
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    $table_info = [];
    
    foreach ($tables as $table) {
        $table_name = $table[0];
        $columns = $wpdb->get_results("DESCRIBE `$table_name`", ARRAY_A);
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        
        $table_info[] = [
            'name' => $table_name,
            'columns' => array_column($columns, 'Field'),
            'row_count' => $row_count
        ];
    }
    
    // Step 2: Get relevant tables from AI
    $table_summary = "";
    foreach ($table_info as $table) {
        $table_summary .= "Table: {$table['name']} ({$table['row_count']} rows) - Columns: " . implode(', ', $table['columns']) . "\n";
    }
    
    $relevant_tables = wp_ai_get_relevant_tables($user_query, $table_summary);
    
    if (!$relevant_tables) {
        return [
            'type' => 'error',
            'message' => "Hmm, I'm having trouble understanding your database request. 🤔 Could you try rephrasing it? For example, you could ask 'Show me all posts' or 'Find users who registered last month'."
        ];
    }
    
    // Step 3: Get detailed structure for relevant tables
    $detailed_structure = "";
    foreach ($relevant_tables as $table_name) {
        $columns = $wpdb->get_results("DESCRIBE `$table_name`", ARRAY_A);
        $sample_data = $wpdb->get_results("SELECT * FROM `$table_name` LIMIT 2", ARRAY_A);
        
        $detailed_structure .= "Table: $table_name\n";
        foreach ($columns as $col) {
            $detailed_structure .= "- {$col['Field']} ({$col['Type']})\n";
        }
        if ($sample_data) {
            $detailed_structure .= "Sample: " . json_encode($sample_data[0]) . "\n";
        }
        $detailed_structure .= "\n";
    }
    
    // Step 4: Generate and execute SQL
    $sql = wp_ai_generate_sql($user_query, $detailed_structure);
    
    if (!$sql) {
        return [
            'type' => 'error',
            'message' => "I couldn't generate a query for your request. 😅 Could you try asking in a different way? I'm here to help!"
        ];
    }
    
    return execute_sql_query($sql);
}

function execute_confirmed_query($user_query) {
    return process_database_query($user_query);
}

function execute_sql_query($sql) {
    global $wpdb;
    
    try {
        // Check query type
        $query_type = strtoupper(trim(strtok($sql, ' ')));
        
        if (in_array($query_type, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'])) {
            // Queries that return results
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            $friendly_message = "";
            $count = count($results);
            
            if ($count > 0) {
                $friendly_message = "Great! 🎉 I found $count " . ($count === 1 ? 'record' : 'records') . " for you. Here's what I discovered:";
            } else {
                $friendly_message = "I searched through your database, but didn't find any records matching your criteria. 🤷‍♀️ The query ran successfully though!";
            }
            
            return [
                'type' => 'select',
                'results' => $results,
                'count' => $count,
                'sql' => $sql,
                'message' => $friendly_message
            ];
        } else {
            // Queries that modify data (INSERT, UPDATE, DELETE, etc.)
            $wpdb->query($sql);
            
            $affected_rows = $wpdb->rows_affected;
            $message = '';
            
            switch ($query_type) {
                case 'INSERT':
                    if ($affected_rows > 0) {
                        $message = "Perfect! ✨ I successfully inserted $affected_rows " . ($affected_rows === 1 ? 'record' : 'records') . " into your database. The data has been added safely!";
                    } else {
                        $message = "The insert query ran, but no records were actually inserted. This might be due to duplicate constraints or other database rules. 🤔";
                    }
                    break;
                case 'UPDATE':
                    if ($affected_rows > 0) {
                        $message = "Excellent! 🔄 I successfully updated $affected_rows " . ($affected_rows === 1 ? 'record' : 'records') . ". Your database has been updated with the new information!";
                    } else {
                        $message = "The update query ran successfully, but no records were actually changed. This might mean the data was already in the desired state! 😊";
                    }
                    break;
                case 'DELETE':
                    if ($affected_rows > 0) {
                        $message = "Done! 🗑️ I successfully deleted $affected_rows " . ($affected_rows === 1 ? 'record' : 'records') . " from your database. The data has been permanently removed.";
                    } else {
                        $message = "The delete query ran, but no records were actually deleted. This might mean the records you wanted to delete didn't exist! 🤷‍♀️";
                    }
                    break;
                default:
                    if ($affected_rows > 0) {
                        $message = "Success! ✅ Your query executed perfectly and affected $affected_rows " . ($affected_rows === 1 ? 'row' : 'rows') . ". The operation completed successfully!";
                    } else {
                        $message = "Your query executed successfully! 👍 Everything went smoothly.";
                    }
            }
            
            return [
                'type' => 'modify',
                'message' => $message,
                'affected_rows' => $affected_rows,
                'query_type' => $query_type,
                'sql' => $sql
            ];
        }
    } catch (Exception $e) {
        return [
            'type' => 'error',
            'message' => "Oops! 😬 Something went wrong while executing your query. Here's what happened: " . $e->getMessage() . "\n\nDon't worry, your database is safe! Try rephrasing your request and I'll help you fix it."
        ];
    }
}

function wp_ai_get_relevant_tables($user_query, $table_summary) {
    $api_key = "AIzaSyDUDc7jyAqHEyZ_UKoxMPByIXXZboYZewI";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

    $prompt = "User query: '$user_query'\n\nAvailable tables:\n$table_summary\n";
    $prompt .= "Return ONLY a JSON array of table names needed to answer this query. Example: [\"wp_posts\", \"wp_users\"]";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $text = trim($body['candidates'][0]['content']['parts'][0]['text']);
        $text = preg_replace('/```json?/i', '', $text);
        $text = str_replace('```', '', $text);
        $tables = json_decode(trim($text), true);
        return is_array($tables) ? $tables : false;
    }
    return false;
}

function wp_ai_generate_sql($user_query, $detailed_structure) {
    $api_key = "AIzaSyDUDc7jyAqHEyZ_UKoxMPByIXXZboYZewI";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

    $prompt = "Generate a MySQL query for: '$user_query'\n\n";
    $prompt .= "Database structure:\n$detailed_structure\n";
    $prompt .= "Return ONLY the SQL query, no explanations or formatting.";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $sql = trim($body['candidates'][0]['content']['parts'][0]['text']);
        $sql = preg_replace('/```sql?/i', '', $sql);
        $sql = str_replace('```', '', $sql);
        return trim($sql);
    }
    return false;
}

function wp_ai_sql_explorer_pro_page() {
    ?>
    <div class="ai-sql-explorer">
        <div class="container">
            <div class="header">
                <h1>🤖 AI Database Chatbot</h1>
                <p>Your friendly AI assistant for database queries and conversations</p>
            </div>

            <div class="chat-container" id="chat-container">
                <div class="welcome-message">
                    <div class="avatar ai-avatar">🤖</div>
                    <div class="message-content">
                        <p>Hi there! 👋 I'm your friendly AI database assistant. I can help you.</p>
                        <p>Just ask me anything! For example: "Show me all my posts" or "How many users do I have?"</p>
                    </div>
                </div>
            </div>

            <div class="query-box">
                <div class="query-input-container">
                    <textarea id="user-query" placeholder="Type your message here... Ask me about your database or just say hello! 😊"></textarea>
                    <button id="voice-btn" class="voice-btn" title="Voice Input">
                        <span class="mic-icon">🎤</span>
                    </button>
                </div>
                <button id="send-btn" class="send-btn">
                    <span class="btn-text">Send</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        Thinking...
                    </span>
                </button>
            </div>

            <div class="voice-status" id="voice-status" style="display: none;">
                <div class="voice-status-content">
                    <span class="voice-status-icon">🎤</span>
                    <span class="voice-status-text">Listening...</span>
                    <button id="voice-stop-btn" class="voice-stop-btn">Stop</button>
                </div>
            </div>

            <div class="confirmation-box" id="confirmation-box" style="display: none;">
                <div class="confirmation-content">
                    <p id="confirmation-message"></p>
                    <div class="confirmation-buttons">
                        <input type="text" id="confirmation-input" placeholder="Type your confirmation here..." />
                        <button id="confirm-btn" class="confirm-btn">Confirm</button>
                        <button id="cancel-btn" class="cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>