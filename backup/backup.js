let pendingQuery = '';
let conversationHistory = [];

jQuery(document).ready(function($) {
    $('#user-query').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    $('#send-btn').on('click', function() {
        sendMessage();
    });

    $('#confirm-btn').on('click', function() {
        const confirmation = $('#confirmation-input').val().trim();
        sendConfirmation(confirmation);
    });

    $('#cancel-btn').on('click', function() {
        cancelOperation();
    });

    function sendMessage() {
        const query = $('#user-query').val().trim();
        if (!query) return;

        // Add user message to chat
        addMessageToChat(query, true);
        $('#user-query').val('');

        // Show loading state
        showLoadingState();

        $.post(wpAiSqlExplorer.ajax_url, {
            action: 'process_ai_query',
            nonce: wpAiSqlExplorer.nonce,
            user_query: query
        }, function(response) {
            hideLoadingState();
            handleResponse(response);
        }).fail(function() {
            hideLoadingState();
            addMessageToChat("Oops! 😅 I'm having trouble connecting right now. Please check your internet connection and try again!", false);
        });
    }

    function sendConfirmation(confirmation) {
        if (!pendingQuery) return;

        $('#confirmation-box').hide();
        showLoadingState();

        $.post(wpAiSqlExplorer.ajax_url, {
            action: 'process_ai_query',
            nonce: wpAiSqlExplorer.nonce,
            user_query: pendingQuery,
            confirmation: confirmation,
            pending_query: pendingQuery
        }, function(response) {
            hideLoadingState();
            pendingQuery = '';
            handleResponse(response);
        }).fail(function() {
            hideLoadingState();
            addMessageToChat("Sorry, there was a network error. Please try again! 😔", false);
        });
    }

    function cancelOperation() {
        $('#confirmation-box').hide();
        pendingQuery = '';
        addMessageToChat("No worries! Operation cancelled. Your data is safe! 😊 Is there anything else I can help you with?", false);
    }

    function handleResponse(response) {
        if (!response.success) {
            addMessageToChat(response.data || 'Something went wrong. Please try again! 😔', false);
            return;
        }

        const data = response.data;

        switch (data.type) {
            case 'conversation':
                addMessageToChat(data.message, false);
                break;
                
            case 'confirmation_needed':
                addMessageToChat(data.message, false);
                showConfirmationBox(data.message, data.pending_query);
                break;
                
            case 'select':
                addMessageToChat(data.message, false);
                if (data.results && data.results.length > 0) {
                    addResultsToChat(data.results);
                }
                break;
                
            case 'modify':
                addMessageToChat(data.message, false);
                break;
                
            case 'error':
                addMessageToChat(data.message, false);
                break;
        }
    }

    function addMessageToChat(message, isUser) {
        const avatar = isUser ? 
            '<div class="avatar user-avatar">👤</div>' : 
            '<div class="avatar ai-avatar">🤖</div>';
        
        const messageClass = isUser ? 'message user-message' : 'message';
        
        const messageHtml = `
            <div class="${messageClass}">
                ${avatar}
                <div class="message-content">
                    <p>${formatMessage(message)}</p>
                </div>
            </div>
        `;
        
        $('#chat-container').append(messageHtml);
        scrollToBottom();
    }

    function addResultsToChat(results) {
        if (!results || results.length === 0) return;
        
        let tableHtml = '<div class="table-wrapper"><table class="results-table"><thead><tr>';
        
        // Headers
        Object.keys(results[0]).forEach(key => {
            tableHtml += `<th>${escapeHtml(key)}</th>`;
        });
        tableHtml += '</tr></thead><tbody>';
        
        // Rows (limit to first 50 for performance)
        const displayResults = results.slice(0, 50);
        displayResults.forEach(row => {
            tableHtml += '<tr>';
            Object.values(row).forEach(value => {
                let displayValue = value;
                if (value === null) displayValue = '<em style="color:#888;">NULL</em>';
                else if (value === '') displayValue = '<em style="color:#888;">Empty</em>';
                else displayValue = escapeHtml(String(value));
                
                tableHtml += `<td>${displayValue}</td>`;
            });
            tableHtml += '</tr>';
        });
        tableHtml += '</tbody></table></div>';
        
        if (results.length > 50) {
            tableHtml += `<p style="margin-top: 1rem; color: #888; font-size: 0.9rem;">
                📋 Showing first 50 results out of ${results.length} total records.
            </p>`;
        }
        
        const messageHtml = `
            <div class="message">
                <div class="avatar ai-avatar">📊</div>
                <div class="message-content">
                    ${tableHtml}
                </div>
            </div>
        `;
        
        $('#chat-container').append(messageHtml);
        scrollToBottom();
    }

    function showConfirmationBox(message, query) {
        pendingQuery = query;
        $('#confirmation-message').html(formatMessage(message));
        $('#confirmation-input').val('');
        $('#confirmation-box').show();
    }

    function showLoadingState() {
        $('#send-btn').prop('disabled', true);
        $('.btn-text').hide();
        $('.btn-loading').show();
    }

    function hideLoadingState() {
        $('#send-btn').prop('disabled', false);
        $('.btn-text').show();
        $('.btn-loading').hide();
    }

    function formatMessage(message) {
        // Convert markdown-style formatting to HTML
        message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
        message = message.replace(/\n/g, '<br>');
        return message;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function scrollToBottom() {
        const chatContainer = document.getElementById('chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Auto-focus on input when page loads
    $('#user-query').focus();
});