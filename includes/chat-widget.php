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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
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
        
        <!-- Notes Toggle Button -->
        <button class="surf-notes-toggle" id="surf-notes-toggle" aria-label="Toggle notes mode">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14,2 14,8 20,8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10,9 9,9 8,9"></polyline>
            </svg>
        </button>
        
        <!-- Chat Toggle Button -->
        <button class="surf-chat-toggle" id="surf-chat-toggle" aria-label="Open chat">
            <img src="<?php echo SURF_SOCIAL_PLUGIN_URL; ?>assets/css/surficon.svg" width="32" height="32" alt="Chat Icon" />
            <span class="surf-unread-badge" id="surf-unread-badge" style="display: none;">0</span>
        </button>
    </div>
    
    <!-- Sticky Notes Container -->
    <div id="surf-sticky-notes-container" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 999997;">
        <!-- Notes will be dynamically added here -->
    </div>
    
    <!-- Note Creation Modal -->
    <div id="surf-note-modal" class="surf-note-modal" style="display: none;">
        <div class="surf-note-modal-content">
            <h3>Add a Note</h3>
            <textarea id="surf-note-message" placeholder="What's on your mind?"></textarea>
            <div class="surf-note-actions">
                <button id="surf-note-cancel">Cancel</button>
                <button id="surf-note-save">Save Note</button>
            </div>
        </div>
    </div>
    
    <!-- Branding -->
    <div class="surf-branding">
        Powered by <strong><a href="https://surfsocial.com" target="_blank" rel="noopener noreferrer">Surf Social</a></strong>
    </div>
</div>

