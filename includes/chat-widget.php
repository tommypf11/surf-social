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
            <button class="surf-chat-tab active" data-tab="web">
                Web Chat
                <span class="surf-tab-badge" data-tab="web" style="display: none;">0</span>
            </button>
            <button class="surf-chat-tab" data-tab="friend">
                Friend Chat
                <span class="surf-tab-badge" data-tab="friend" style="display: none;">0</span>
            </button>
            <button class="surf-chat-tab" data-tab="support">
                Support
                <span class="surf-tab-badge" data-tab="support" style="display: none;">0</span>
            </button>
        </div>
        
        <div class="surf-chat-title"></div>
        
        <div class="surf-chat-messages" id="surf-chat-messages">
            <!-- Messages will be loaded here -->
        </div>
        
        <div class="surf-chat-input-container">
            <!-- Guest Registration Form (shown initially for guests) -->
            <div class="surf-guest-registration" id="surf-guest-registration">
                <div class="surf-input-row">
                    <input type="text" class="surf-name-input" placeholder="Name" maxlength="20" id="surf-name-input">
                </div>
                <div class="surf-input-row">
                    <input type="email" class="surf-email-input" placeholder="Email (Hidden)" maxlength="50" id="surf-email-input">
                </div>
                <button class="surf-join-button" id="surf-join-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Join Chat
                </button>
            </div>
            
            <!-- Normal Chat Input (hidden initially, shown after registration) -->
            <div class="surf-normal-chat" id="surf-normal-chat" style="display: none;">
                <input type="text" class="surf-chat-input" placeholder="Type a message..." id="surf-chat-input">
                <button class="surf-chat-send" aria-label="Send message">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
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
            <img src="<?php echo SURF_SOCIAL_PLUGIN_URL; ?>assets/css/surficon.svg" width="32" height="32" alt="Chat Icon" />
            <span class="surf-unread-badge" id="surf-unread-badge" style="display: none;">0</span>
        </button>
    </div>
    
    <!-- Branding -->
    <div class="surf-branding">
        Powered by <strong><a href="https://surfsocial.com" target="_blank" rel="noopener noreferrer">Surf Social</a></strong>
    </div>
</div>

