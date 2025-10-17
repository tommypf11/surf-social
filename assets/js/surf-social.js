/**
 * Surf Social Main JavaScript
 */

(function() {
    'use strict';
    
    // Configuration
    const config = window.surfSocial || {};
    const colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
        '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B739', '#52B788'
    ];
    
    // State
    let pusher = null;
    let channel = null;
    let websocket = null;
    let currentUsers = new Map();
    let currentPage = 1;
    let isLoadingMessages = false;
    let hasMoreMessages = true;
    let unreadCount = 0;
    let currentTab = 'web';
    let currentChatUser = null;
    let individualChats = new Map(); // Store individual chat messages
    let adminUser = { id: 'admin', name: 'Admin', color: '#E74C3C' };
    
    // DOM Elements
    const chatDrawer = document.getElementById('surf-chat-drawer');
    const chatToggle = document.getElementById('surf-chat-toggle');
    const chatClose = document.querySelector('.surf-chat-close');
    const chatMessages = document.getElementById('surf-chat-messages');
    const chatInput = document.getElementById('surf-chat-input');
    const chatSend = document.querySelector('.surf-chat-send');
    const chatTabs = document.querySelectorAll('.surf-chat-tab');
    const avatarDock = document.getElementById('surf-avatar-dock');
    const cursorsContainer = document.getElementById('surf-cursors-container');
    const unreadBadge = document.getElementById('surf-unread-badge');
    const loadMoreBtn = document.getElementById('surf-chat-load-more');
    
    /**
     * Initialize the plugin
     */
    function init() {
        if (!config.currentUser || !config.currentUser.name) {
            return;
        }
        
        setupEventListeners();
        initRealtime();
        loadInitialMessages();
        startCursorTracking();
        
        // Initialize avatar dock state
        updateAvatarDock();
    }
    
    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Chat toggle
        chatToggle.addEventListener('click', toggleChat);
        chatClose.addEventListener('click', toggleChat);
        
        // Chat input
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        chatSend.addEventListener('click', sendMessage);
        
        // Tab switching
        chatTabs.forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        });
        
        // Avatar dock is now auto-expanded, no expand button needed
        
        // Make avatar chips clickable for individual chat
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('surf-avatar-chip') && !e.target.classList.contains('surf-avatar-more')) {
                e.preventDefault();
                e.stopPropagation();
                
                const userId = e.target.dataset.userId;
                if (userId) {
                    const user = Array.from(currentUsers.values()).find(cursor => cursor.user.id.toString() === userId.toString());
                    if (user) {
                        // Open chat drawer first if it's closed
                        if (!chatDrawer.classList.contains('open')) {
                            toggleChat();
                        }
                        
                        // Switch to friend tab and open individual chat
                        switchTab('friend');
                        setTimeout(() => {
                            openIndividualChat(user.user);
                        }, 300);
                    }
                }
            } else if (e.target.classList.contains('surf-avatar-more')) {
                e.preventDefault();
                e.stopPropagation();
                // Show all users when clicking +N chip
                showAllUsersInAvatarDock();
            }
        });
        
        // Load more messages
        loadMoreBtn.addEventListener('click', loadMoreMessages);
        
        // Click outside to close chat
        document.addEventListener('click', (e) => {
            if (chatDrawer.classList.contains('open') && 
                !chatDrawer.contains(e.target) && 
                !chatToggle.contains(e.target) &&
                !avatarDock.contains(e.target)) {
                toggleChat();
            }
        });
    }
    
    /**
     * Initialize real-time connection
     */
    function initRealtime() {
        if (config.usePusher && config.pusherKey) {
            initPusher();
        } else if (config.websocketUrl) {
            initWebSocket();
        } else {
            console.warn('Surf Social: No real-time transport configured');
        }
    }
    
    /**
     * Initialize Pusher
     */
    function initPusher() {
        // Load Pusher library dynamically
        if (typeof Pusher === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
            script.onload = () => setupPusher();
            document.head.appendChild(script);
        } else {
            setupPusher();
        }
    }
    
    /**
     * Setup Pusher connection
     */
    function setupPusher() {
        pusher = new Pusher(config.pusherKey, {
            cluster: config.pusherCluster,
            debug: false,
            authEndpoint: config.apiUrl + 'pusher/auth'
        });
        
        channel = pusher.subscribe('private-surf-social');
        
        channel.bind('pusher:subscription_succeeded', function() {
            // Client events binding after successful subscription
            channel.bind('client-cursor-move', handleCursorMove);
            channel.bind('client-cursor-leave', handleCursorLeave);
            channel.bind('client-new-message', handleNewMessage);
            channel.bind('client-user-joined', handleUserJoined);
            channel.bind('client-user-left', handleUserLeft);
            channel.bind('client-individual-message', handleIndividualMessage);
            channel.bind('client-support-message', handleSupportMessage);
        });
        
        channel.bind('pusher:subscription_error', function(error) {
            // Handle subscription error silently
        });
        
        // Bind to server events (for future server-side broadcasting)
        channel.bind('cursor-move', handleCursorMove);
        channel.bind('cursor-leave', handleCursorLeave);
        channel.bind('new-message', handleNewMessage);
        channel.bind('user-joined', handleUserJoined);
        channel.bind('user-left', handleUserLeft);
        
        // Broadcast our presence
        broadcastPresence();
    }
    
    /**
     * Initialize WebSocket fallback with connection management
     */
    function initWebSocket() {
        try {
            websocket = new WebSocket(config.websocketUrl);
            let reconnectAttempts = 0;
            const maxReconnectAttempts = 5;
            let reconnectTimeout = null;
            let heartbeatInterval = null;
            
            websocket.onopen = () => {
                reconnectAttempts = 0;
                broadcastPresence();
                
                // Send heartbeat every 30 seconds
                heartbeatInterval = setInterval(() => {
                    if (websocket.readyState === WebSocket.OPEN) {
                        websocket.send(JSON.stringify({ type: 'ping' }));
                    }
                }, 30000);
            };
            
            websocket.onmessage = (event) => {
                const data = JSON.parse(event.data);
                if (data.type !== 'pong') {
                    handleWebSocketMessage(data);
                }
            };
            
            websocket.onerror = (error) => {
                if (heartbeatInterval) {
                    clearInterval(heartbeatInterval);
                    heartbeatInterval = null;
                }
            };
            
            websocket.onclose = () => {
                if (heartbeatInterval) {
                    clearInterval(heartbeatInterval);
                    heartbeatInterval = null;
                }
                
                if (reconnectAttempts < maxReconnectAttempts) {
                    reconnectAttempts++;
                    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
                    reconnectTimeout = setTimeout(() => {
                        initWebSocket();
                    }, delay);
                }
            };
        } catch (error) {
            // Handle connection errors gracefully
        }
    }
    
    /**
     * Handle WebSocket messages
     */
    function handleWebSocketMessage(data) {
        switch (data.type) {
            case 'cursor-move':
                handleCursorMove(data);
                break;
            case 'cursor-leave':
                handleCursorLeave(data);
                break;
            case 'new-message':
                handleNewMessage(data);
                break;
            case 'user-joined':
                handleUserJoined(data);
                break;
            case 'user-left':
                handleUserLeft(data);
                break;
        }
    }
    
    /**
     * Broadcast user presence
     */
    function broadcastPresence() {
        const data = {
            user: config.currentUser,
            page: window.location.pathname
        };
        
        console.log('Broadcasting presence for:', config.currentUser.name);
        
        if (pusher) {
            try {
                channel.trigger('client-user-joined', data);
                console.log('✅ Sent user-joined event');
            } catch (error) {
                console.error('❌ Failed to trigger user-joined event:', error);
            }
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify({ type: 'user-joined', ...data }));
        }
    }
    
    /**
     * Start cursor tracking with optimized performance
     */
    function startCursorTracking() {
        let lastSentPosition = { x: 0, y: 0, time: 0 };
        const THROTTLE_INTERVAL = 500; // 500ms to reduce Pusher message usage
        let batchTimeout = null;
        let pendingUpdates = [];
        
        document.addEventListener('mousemove', (e) => {
            const now = Date.now();
            
            // Throttling for performance
            if (now - lastSentPosition.time < THROTTLE_INTERVAL) {
                // Store latest position for batching
                pendingUpdates.push({
                    x: e.clientX,
                    y: e.clientY,
                    time: now
                });
                return;
            }
            
            // Clear any pending batch
            if (batchTimeout) {
                clearTimeout(batchTimeout);
                batchTimeout = null;
            }
            
            const coords = getPreciseCursorCoordinates(e);
            
            lastSentPosition = {
                x: coords.x,
                y: coords.y,
                time: now
            };
            
            broadcastCursorPosition(coords.x, coords.y);
        });
        
        // Batch pending updates
        if (pendingUpdates.length > 0) {
            batchTimeout = setTimeout(() => {
                const latestUpdate = pendingUpdates[pendingUpdates.length - 1];
                if (latestUpdate) {
                    broadcastCursorPosition(latestUpdate.x, latestUpdate.y);
                    lastSentPosition = latestUpdate;
                }
                pendingUpdates = [];
            }, THROTTLE_INTERVAL);
        }
        
        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                broadcastCursorLeave();
            } else {
                broadcastPresence();
            }
        });
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            broadcastCursorLeave();
        });
    }
    
    /**
     * Apply simple, accurate cursor positioning
     */
    function applyPreciseCursorPosition(element, x, y) {
        // Simple, direct positioning - most reliable across all browsers
        element.style.left = Math.round(x) + 'px';
        element.style.top = Math.round(y) + 'px';
        element.style.transform = 'none'; // Reset any transforms
    }

    /**
     * Get simple cursor coordinates
     */
    function getPreciseCursorCoordinates(event) {
        // Use clientX/Y directly - these are the most reliable coordinates
        return {
            x: Math.round(event.clientX),
            y: Math.round(event.clientY)
        };
    }

    /**
     * Broadcast cursor position
     */
    function broadcastCursorPosition(x, y) {
        const data = {
            user: config.currentUser,
            x: x,
            y: y,
            page: window.location.pathname
        };
        
        if (pusher) {
            try {
                channel.trigger('client-cursor-move', data);
            } catch (error) {
                console.error('❌ Failed to trigger cursor-move event:', error);
            }
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify({ type: 'cursor-move', ...data }));
        }
    }
    
    /**
     * Broadcast cursor leave
     */
    function broadcastCursorLeave() {
        const data = {
            user: config.currentUser,
            page: window.location.pathname
        };
        
        if (pusher) {
            channel.trigger('client-cursor-leave', data);
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify({ type: 'cursor-leave', ...data }));
        }
    }
    
    /**
     * Handle cursor move from other users
     */
    function handleCursorMove(data) {
        if (data.user.id === config.currentUser.id) {
            return;
        }
        
        let cursor = currentUsers.get(data.user.id);
        
        if (!cursor) {
            cursor = createCursor(data.user);
            currentUsers.set(data.user.id, cursor);
            updateAvatarDock();
        }
        
        // Show cursor if it was hidden
        cursor.element.style.display = 'block';
        
        applyPreciseCursorPosition(cursor.element, data.x, data.y);
        cursor.lastSeen = Date.now();
    }
    
    /**
     * Handle cursor leave
     */
    function handleCursorLeave(data) {
        if (data.user.id === config.currentUser.id) return;
        
        const cursor = currentUsers.get(data.user.id);
        if (cursor) {
            cursor.element.remove();
            currentUsers.delete(data.user.id);
            updateAvatarDock();
        }
    }
    
    /**
     * Handle user joined
     */
    function handleUserJoined(data) {
        if (data.user.id === config.currentUser.id) return;
        // User joined - cursor will appear when they start moving
    }
    
    /**
     * Handle user left
     */
    function handleUserLeft(data) {
        if (data.user.id === config.currentUser.id) return;
        handleCursorLeave(data);
    }
    
    /**
     * Create cursor element
     */
    function createCursor(user) {
        const cursor = document.createElement('div');
        cursor.className = 'surf-cursor';
        cursor.style.color = user.color || colors[user.id % colors.length];
        
        const pointer = document.createElement('div');
        pointer.className = 'surf-cursor-pointer';
        
        const namePill = document.createElement('div');
        namePill.className = 'surf-cursor-name';
        namePill.textContent = user.name || 'Unknown User';
        namePill.style.color = 'white';
        namePill.style.backgroundColor = user.color || colors[user.id % colors.length];
        namePill.style.display = 'block';
        namePill.style.opacity = '1';
        namePill.style.visibility = 'visible';
        
        cursor.appendChild(pointer);
        cursor.appendChild(namePill);
        cursorsContainer.appendChild(cursor);
        
        return {
            element: cursor,
            user: user,
            lastSeen: Date.now()
        };
    }
    
    /**
     * Update avatar dock - shows all active users with expandable layout
     */
    function updateAvatarDock() {
        const existingChips = avatarDock.querySelectorAll('.surf-avatar-chip:not(.surf-avatar-more)');
        existingChips.forEach(chip => chip.remove());
        
        const users = Array.from(currentUsers.values());
        
        // Hide avatar dock if no users are active
        if (users.length === 0) {
            avatarDock.style.display = 'none';
            return;
        }
        
        // Show avatar dock
        avatarDock.style.display = 'flex';
        
        // Sort users by join time (most recent first for left-to-right display)
        const sortedUsers = users.sort((a, b) => b.lastSeen - a.lastSeen);
        
        // Add avatar chips for all users (limit to 5 visible avatars)
        const maxVisible = 5;
        const visibleUsers = sortedUsers.slice(0, maxVisible);
        const remainingCount = Math.max(0, sortedUsers.length - maxVisible);
        
        // Add user avatars (newest users on the left)
        visibleUsers.forEach((cursor, index) => {
            const chip = document.createElement('div');
            chip.className = 'surf-avatar-chip';
            chip.style.backgroundColor = cursor.user.color;
            chip.style.zIndex = maxVisible - index; // Stack with newest on top
            chip.textContent = cursor.user.name.charAt(0).toUpperCase();
            chip.title = `${cursor.user.name} (${users.length} online)`;
            chip.dataset.userId = cursor.user.id;
            
            // Add unread indicator if there are unread messages
            if (unreadCount > 0) {
                const unreadDot = document.createElement('div');
                unreadDot.className = 'surf-avatar-unread';
                chip.appendChild(unreadDot);
            }
            
            avatarDock.appendChild(chip);
        });
        
        // Update the +N chip to show remaining count
        const moreChip = avatarDock.querySelector('.surf-avatar-more');
        if (moreChip) {
            if (remainingCount > 0) {
                moreChip.textContent = `+${remainingCount}`;
                moreChip.style.display = 'flex';
                moreChip.style.zIndex = 0; // Behind user avatars
            } else {
                moreChip.style.display = 'none';
            }
        }
    }
    
    /**
     * Show all users in avatar dock (for +N chip click)
     */
    function showAllUsersInAvatarDock() {
        // This function is now redundant since we always show all users
        // But keeping it for potential future use
        updateAvatarDock();
    }
    
    /**
     * Toggle chat drawer
     */
    function toggleChat() {
        chatDrawer.classList.toggle('open');
        
        if (chatDrawer.classList.contains('open')) {
            chatInput.focus();
            unreadCount = 0;
            updateUnreadBadge();
        }
    }
    
    /**
     * Switch chat tab
     */
    function switchTab(tabName) {
        currentTab = tabName;
        chatTabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });
        
        const title = document.querySelector('.surf-chat-title');
        title.textContent = tabName === 'web' ? 'Web Chat' : 
                          tabName === 'friend' ? 'Friend Chat' : 'Support';
        
        // Clear current chat user when switching tabs
        currentChatUser = null;
        
        // Show appropriate content based on tab
        if (tabName === 'friend') {
            showFriendChatList();
        } else if (tabName === 'support') {
            showSupportChat();
        } else {
            // Web chat
            currentPage = 1;
            hasMoreMessages = true;
            loadInitialMessages();
        }
    }
    
    /**
     * Show Friend Chat list
     */
    function showFriendChatList() {
        chatMessages.innerHTML = '';
        
        if (currentUsers.size === 0) {
            showEmptyState('No other users online');
            return;
        }
        
        const users = Array.from(currentUsers.values());
        
        // Sort users by last seen time (most recent first)
        users.sort((a, b) => (b.lastSeen || 0) - (a.lastSeen || 0));
        
        users.forEach(cursor => {
            const userEl = createFriendChatUser(cursor.user, cursor);
            chatMessages.appendChild(userEl);
        });
    }
    
    /**
     * Create Friend Chat user element
     */
    function createFriendChatUser(user, cursor) {
        const userEl = document.createElement('div');
        userEl.className = 'surf-friend-user';
        userEl.dataset.userId = user.id;
        
        const avatar = document.createElement('div');
        avatar.className = 'surf-friend-avatar';
        avatar.style.backgroundColor = user.color;
        avatar.textContent = user.name.charAt(0).toUpperCase();
        
        const content = document.createElement('div');
        content.className = 'surf-friend-content';
        
        const name = document.createElement('div');
        name.className = 'surf-friend-name';
        name.textContent = user.name;
        
        const lastMessage = document.createElement('div');
        lastMessage.className = 'surf-friend-last-message';
        
        // Get last message for this user
        const userMessages = individualChats.get(user.id) || [];
        if (userMessages.length > 0) {
            const lastMsg = userMessages[userMessages.length - 1];
            lastMessage.textContent = lastMsg.message;
        } else {
            lastMessage.textContent = 'Click to start chatting...';
        }
        
        const time = document.createElement('div');
        time.className = 'surf-friend-time';
        
        // Show last seen time or last message time
        if (userMessages.length > 0) {
            const lastMsg = userMessages[userMessages.length - 1];
            time.textContent = formatTime(lastMsg.created_at);
        } else if (cursor.lastSeen) {
            time.textContent = formatTime(new Date(cursor.lastSeen).toISOString());
        } else {
            time.textContent = 'Online';
        }
        
        content.appendChild(name);
        content.appendChild(lastMessage);
        
        userEl.appendChild(avatar);
        userEl.appendChild(content);
        userEl.appendChild(time);
        
        // Add click handler for individual chat
        userEl.addEventListener('click', () => {
            openIndividualChat(user);
        });
        
        return userEl;
    }
    
    /**
     * Open individual chat with a user
     */
    function openIndividualChat(user) {
        currentChatUser = user;
        const title = document.querySelector('.surf-chat-title');
        title.innerHTML = `
            <button class="surf-back-button" id="surf-back-button" aria-label="Back to friends">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            Chat with ${user.name}
        `;
        
        // Add back button event listener
        const backButton = document.getElementById('surf-back-button');
        if (backButton) {
            backButton.addEventListener('click', (e) => {
                e.stopPropagation();
                showFriendChatList();
                currentChatUser = null;
                const title = document.querySelector('.surf-chat-title');
                title.textContent = 'Friend Chat';
            });
        }
        
        // Update tab to show we're in individual chat
        chatTabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === 'friend');
        });
        
        // Load messages for this user
        loadIndividualChatMessages(user);
    }
    
    /**
     * Load individual chat messages
     */
    async function loadIndividualChatMessages(user) {
        chatMessages.innerHTML = '<div class="surf-loading"></div>';
        
        try {
            const response = await fetch(`${config.apiUrl}chat/individual?user_id=${config.currentUser.id}&target_user_id=${user.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            const data = await response.json();
            
            chatMessages.innerHTML = '';
            
            if (data.messages && data.messages.length > 0) {
                // Store messages locally
                individualChats.set(user.id, data.messages);
                
                data.messages.forEach(msg => {
                    const messageEl = createMessageElement(msg);
                    chatMessages.appendChild(messageEl);
                });
                
                scrollToBottom();
            } else {
                showEmptyState(`Start a conversation with ${user.name}`);
            }
        } catch (error) {
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load messages</p></div>';
        }
    }
    
    /**
     * Show Support Chat
     */
    function showSupportChat() {
        chatMessages.innerHTML = '';
        currentChatUser = adminUser;
        
        const title = document.querySelector('.surf-chat-title');
        title.textContent = 'Support Chat';
        
        // Load support messages
        loadSupportMessages();
    }
    
    /**
     * Load support messages
     */
    async function loadSupportMessages() {
        chatMessages.innerHTML = '<div class="surf-loading"></div>';
        
        try {
            const response = await fetch(`${config.apiUrl}chat/support?user_id=${config.currentUser.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            const data = await response.json();
            
            chatMessages.innerHTML = '';
            
            if (data.messages && data.messages.length > 0) {
                // Store messages locally
                individualChats.set('admin', data.messages);
                
                data.messages.forEach(msg => {
                    const messageEl = createMessageElement(msg);
                    chatMessages.appendChild(messageEl);
                });
                
                scrollToBottom();
            } else {
                showEmptyState('How can we help you today?');
            }
        } catch (error) {
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load support messages</p></div>';
        }
    }
    
    /**
     * Load initial messages
     */
    async function loadInitialMessages() {
        if (isLoadingMessages) return;
        
        isLoadingMessages = true;
        chatMessages.innerHTML = '<div class="surf-loading"></div>';
        
        try {
            const response = await fetch(`${config.apiUrl}chat/messages?page=1`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                renderMessages(data.messages);
                hasMoreMessages = data.has_more;
                loadMoreBtn.style.display = hasMoreMessages ? 'block' : 'none';
            } else {
                showEmptyState();
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load messages</p></div>';
        }
        
        isLoadingMessages = false;
    }
    
    /**
     * Load more messages
     */
    async function loadMoreMessages() {
        if (isLoadingMessages || !hasMoreMessages) return;
        
        isLoadingMessages = true;
        loadMoreBtn.disabled = true;
        
        try {
            const response = await fetch(`${config.apiUrl}chat/messages?page=${currentPage + 1}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                const existingMessages = chatMessages.querySelectorAll('.surf-message');
                const firstMessage = existingMessages[0];
                
                data.messages.forEach(msg => {
                    const messageEl = createMessageElement(msg);
                    chatMessages.insertBefore(messageEl, firstMessage);
                });
                
                currentPage++;
                hasMoreMessages = data.has_more;
                loadMoreBtn.style.display = hasMoreMessages ? 'block' : 'none';
            }
        } catch (error) {
            console.error('Failed to load more messages:', error);
        }
        
        isLoadingMessages = false;
        loadMoreBtn.disabled = false;
    }
    
    /**
     * Render messages
     */
    function renderMessages(messages) {
        chatMessages.innerHTML = '';
        
        if (messages.length === 0) {
            showEmptyState();
            return;
        }
        
        messages.forEach(msg => {
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
        });
        
        scrollToBottom();
    }
    
    /**
     * Create message element
     */
    function createMessageElement(msg) {
        const message = document.createElement('div');
        message.className = 'surf-message';
        message.dataset.messageId = msg.id;
        
        if (msg.user_id == config.currentUser.id) {
            message.classList.add('own');
        }
        
        const avatar = document.createElement('div');
        avatar.className = 'surf-message-avatar';
        avatar.style.backgroundColor = msg.user_color || colors[msg.user_id % colors.length];
        avatar.textContent = msg.user_name.charAt(0).toUpperCase();
        
        const content = document.createElement('div');
        content.className = 'surf-message-content';
        
        const name = document.createElement('div');
        name.className = 'surf-message-name';
        name.textContent = msg.user_name;
        
        const bubble = document.createElement('div');
        bubble.className = 'surf-message-bubble';
        bubble.textContent = msg.message;
        
        const time = document.createElement('div');
        time.className = 'surf-message-time';
        time.textContent = formatTime(msg.created_at);
        
        content.appendChild(name);
        content.appendChild(bubble);
        content.appendChild(time);
        
        message.appendChild(avatar);
        message.appendChild(content);
        
        return message;
    }
    
    /**
     * Show empty state
     */
    function showEmptyState(message = 'No messages yet. Start the conversation!') {
        chatMessages.innerHTML = `
            <div class="surf-empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p>${message}</p>
            </div>
        `;
    }
    
    /**
     * Send message
     */
    async function sendMessage() {
        const message = chatInput.value.trim();
        
        if (!message) return;
        
        chatInput.disabled = true;
        chatSend.disabled = true;
        
        const msg = {
            user_id: config.currentUser.id,
            user_name: config.currentUser.name,
            message: message,
            created_at: new Date().toISOString(),
            user_color: config.currentUser.color
        };
        
        try {
            // Handle different chat types
            if (currentTab === 'web') {
                await sendWebChatMessage(msg);
            } else if (currentTab === 'friend' && currentChatUser) {
                await sendIndividualChatMessage(msg, currentChatUser);
            } else if (currentTab === 'support') {
                await sendSupportMessage(msg);
            }
            
            chatInput.value = '';
            
            // Add message to current view immediately
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
            
        } catch (error) {
            console.error('Failed to send message:', error);
            alert('Failed to send message. Please try again.');
        }
        
        chatInput.disabled = false;
        chatSend.disabled = false;
        chatInput.focus();
    }
    
    /**
     * Send web chat message
     */
    async function sendWebChatMessage(msg) {
        const response = await fetch(`${config.apiUrl}chat/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                message: msg.message,
                user_name: msg.user_name,
                user_id: msg.user_id,
                channel: 'web'
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to send web chat message');
        }
        
        // Broadcast via real-time
        const data = {
            user: config.currentUser,
            message: msg.message,
            created_at: msg.created_at
        };
        
        if (pusher) {
            channel.trigger('client-new-message', data);
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify({ type: 'new-message', ...data }));
        }
    }
    
    /**
     * Send individual chat message
     */
    async function sendIndividualChatMessage(msg, targetUser) {
        try {
            // Save to backend
            const response = await fetch(`${config.apiUrl}chat/individual`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    sender_id: config.currentUser.id,
                    sender_name: config.currentUser.name,
                    recipient_id: targetUser.id,
                    recipient_name: targetUser.name,
                    message: msg.message
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to send individual message');
            }
            
            // Store message locally
            if (!individualChats.has(targetUser.id)) {
                individualChats.set(targetUser.id, []);
            }
            individualChats.get(targetUser.id).push(msg);
            
            // Broadcast to specific user
            const data = {
                user: config.currentUser,
                targetUser: targetUser,
                message: msg.message,
                created_at: msg.created_at,
                type: 'individual-chat'
            };
            
            if (pusher) {
                channel.trigger('client-individual-message', data);
            } else if (websocket && websocket.readyState === WebSocket.OPEN) {
                websocket.send(JSON.stringify({ type: 'individual-message', ...data }));
            }
        } catch (error) {
            throw error;
        }
    }
    
    /**
     * Send support message
     */
    async function sendSupportMessage(msg) {
        try {
            // Save to backend
            const response = await fetch(`${config.apiUrl}chat/support`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    user_id: config.currentUser.id,
                    user_name: config.currentUser.name,
                    message: msg.message,
                    message_type: 'user'
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to send support message');
            }
            
            // Store message locally
            if (!individualChats.has('admin')) {
                individualChats.set('admin', []);
            }
            individualChats.get('admin').push(msg);
            
            // Broadcast to admin
            const data = {
                user: config.currentUser,
                message: msg.message,
                created_at: msg.created_at,
                type: 'support-chat'
            };
            
            if (pusher) {
                channel.trigger('client-support-message', data);
            } else if (websocket && websocket.readyState === WebSocket.OPEN) {
                websocket.send(JSON.stringify({ type: 'support-message', ...data }));
            }
        } catch (error) {
            throw error;
        }
    }
    
    /**
     * Handle new message
     */
    function handleNewMessage(data) {
        if (data.user.id === config.currentUser.id) {
            return;
        }
        
        const msg = {
            user_id: data.user.id,
            user_name: data.user.name,
            message: data.message,
            created_at: data.created_at,
            user_color: data.user.color
        };
        
        // Handle different message types
        if (data.type === 'individual-chat') {
            handleIndividualMessage(data);
        } else if (data.type === 'support-chat') {
            handleSupportMessage(data);
        } else {
            // Web chat message
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
        }
        
        // Update unread count if chat is closed
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        updateAvatarDock();
    }
    
    /**
     * Handle individual chat message
     */
    function handleIndividualMessage(data) {
        const msg = {
            user_id: data.user.id,
            user_name: data.user.name,
            message: data.message,
            created_at: data.created_at,
            user_color: data.user.color
        };
        
        // Store message in individual chat
        if (!individualChats.has(data.user.id)) {
            individualChats.set(data.user.id, []);
        }
        individualChats.get(data.user.id).push(msg);
        
        // If we're currently viewing this user's chat, show the message
        if (currentChatUser && currentChatUser.id === data.user.id) {
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
        }
        
        // Update friend chat list if it's visible
        if (currentTab === 'friend' && !currentChatUser) {
            showFriendChatList();
        }
    }
    
    /**
     * Handle support message
     */
    function handleSupportMessage(data) {
        const msg = {
            user_id: data.user.id,
            user_name: data.user.name,
            message: data.message,
            created_at: data.created_at,
            user_color: data.user.color
        };
        
        // Store message in support chat
        if (!individualChats.has('admin')) {
            individualChats.set('admin', []);
        }
        individualChats.get('admin').push(msg);
        
        // If we're currently viewing support chat, show the message
        if (currentTab === 'support') {
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
        }
    }
    
    /**
     * Scroll to bottom
     */
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    /**
     * Update unread badge
     */
    function updateUnreadBadge() {
        if (unreadCount > 0) {
            unreadBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            unreadBadge.style.display = 'flex';
        } else {
            unreadBadge.style.display = 'none';
        }
    }
    
    /**
     * Format time
     */
    function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) {
            return 'just now';
        } else if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return `${minutes}m ago`;
        } else {
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
    }
    
    // Separate cursor cleanup from avatar cleanup
    // Cursors are hidden after 8 seconds, but avatars remain active
    setInterval(() => {
        const now = Date.now();
        const cursorTimeoutThreshold = 8000; // 8 seconds for cursor visibility
        const usersToHideCursors = [];
        
        currentUsers.forEach((cursor, userId) => {
            if (now - cursor.lastSeen > cursorTimeoutThreshold) {
                usersToHideCursors.push(userId);
            }
        });
        
        // Hide cursors but keep users in avatar dock
        if (usersToHideCursors.length > 0) {
            usersToHideCursors.forEach(userId => {
                const cursor = currentUsers.get(userId);
                if (cursor && cursor.element) {
                    // Hide the cursor element but keep the user data
                    cursor.element.style.display = 'none';
                }
            });
        }
    }, 10000); // Check every 10 seconds
    
    // Separate cleanup for users who have completely left (longer timeout)
    setInterval(() => {
        const now = Date.now();
        const userTimeoutThreshold = 300000; // 5 minutes for complete user removal
        const usersToRemove = [];
        
        currentUsers.forEach((cursor, userId) => {
            if (now - cursor.lastSeen > userTimeoutThreshold) {
                usersToRemove.push(userId);
            }
        });
        
        // Completely remove users who have been inactive for 5 minutes
        if (usersToRemove.length > 0) {
            usersToRemove.forEach(userId => {
                const cursor = currentUsers.get(userId);
                if (cursor && cursor.element) {
                    cursor.element.remove();
                }
                currentUsers.delete(userId);
            });
            updateAvatarDock();
        }
    }, 60000); // Check every minute
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

