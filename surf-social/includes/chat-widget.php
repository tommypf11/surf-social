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
        <div class="surf-chat-header">
            <span class="surf-chat-title">Web Chat</span>
            <button class="surf-chat-close" aria-label="Close chat">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        
        <div class="surf-chat-tabs">
            <button class="surf-chat-tab active" data-tab="web">Web Chat</button>
            <button class="surf-chat-tab" data-tab="friend">Friend Chat</button>
            <button class="surf-chat-tab" data-tab="support">Support</button>
        </div>
        
        <div class="surf-chat-messages" id="surf-chat-messages">
            <!-- Messages will be loaded here -->
        </div>
        
        <div class="surf-chat-pagination">
            <button class="surf-chat-load-more" id="surf-chat-load-more">Load More</button>
        </div>
        
        <div class="surf-chat-input-container">
            <button class="surf-chat-attach" aria-label="Attach file">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M15.5 4L9 10.5L8 9.5L14.5 3M15.5 4L11 8.5M15.5 4L11 8.5M15.5 4L11 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <input type="text" class="surf-chat-input" placeholder="Write a message..." id="surf-chat-input">
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
    
    <!-- Chat Toggle Button -->
    <button class="surf-chat-toggle" id="surf-chat-toggle" aria-label="Open chat">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="surf-unread-badge" id="surf-unread-badge" style="display: none;">0</span>
    </button>
    
    <!-- Avatar Chips Dock -->
    <div class="surf-avatar-dock" id="surf-avatar-dock">
        <div class="surf-avatar-chip surf-avatar-more">+5</div>
        <!-- Avatar chips will be dynamically added here -->
    </div>
    
    <!-- Branding -->
    <div class="surf-branding">
        Powered by <strong>Surf Social</strong>
    </div>
</div>

