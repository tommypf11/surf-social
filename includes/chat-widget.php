<?php
/**
 * Chat Widget HTML
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="surf-social-widget">
    <!-- Chat Drawer -->
    <div id="surf-chat-drawer" class="surf-chat-drawer">
        <div class="surf-chat-tabs">
            <button class="surf-chat-tab active" data-tab="web">Web Chat</button>
            <button class="surf-chat-tab" data-tab="friend">Friend Chat</button>
            <button class="surf-chat-tab" data-tab="support">Support</button>
        </div>
        
        <div class="surf-chat-messages" id="surf-chat-messages">
            <!-- Messages will be loaded here -->
        </div>
        
        <div class="surf-chat-input-container">
            <input type="text" class="surf-chat-input" placeholder="Type a message..." id="surf-chat-input">
            <button class="surf-chat-send" aria-label="Send message">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Presence Cursors -->
    <div id="surf-cursors-container">
        <!-- Cursors will be dynamically added here -->
    </div>
    
    <!-- Bottom Widget Bar -->
    <div class="surf-widget-bar">
        <!-- Avatar Chips Dock -->
        <div class="surf-avatar-dock" id="surf-avatar-dock" style="display: none;">
            <div class="surf-avatar-chip surf-avatar-more"></div>
            <!-- Avatar chips will be dynamically added here -->
        </div>
        
        <!-- Chat Toggle Button -->
        <button class="surf-chat-toggle" id="surf-chat-toggle" aria-label="Open chat">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="surf-unread-badge" id="surf-unread-badge" style="display: none;">0</span>
        </button>
    </div>
    
    <!-- Branding -->
    <div class="surf-branding">
        Powered by <strong><a href="https://surfsocial.com" target="_blank" rel="noopener noreferrer">Surf Social</a></strong>
    </div>
</div>

