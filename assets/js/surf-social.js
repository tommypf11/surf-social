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
    let hasSetGuestName = false; // Track if guest has set their name
    let hasSetGuestEmail = false; // Track if guest has set their email
    let hasSetGuestInfo = false; // Track if guest has completed registration
    let isFirstMessage = true; // Track if this is the first message attempt
    let supportRefreshInterval = null; // Auto-refresh for support chat
    
    // DOM Elements
    const chatDrawer = document.getElementById('surf-chat-drawer');
    const chatToggle = document.getElementById('surf-chat-toggle');
    const chatMessages = document.getElementById('surf-chat-messages');
    const chatInput = document.getElementById('surf-chat-input');
    const chatSend = document.querySelector('.surf-chat-send');
    const chatTabs = document.querySelectorAll('.surf-chat-tab');
    const avatarDock = document.getElementById('surf-avatar-dock');
    const cursorsContainer = document.getElementById('surf-cursors-container');
    const unreadBadge = document.getElementById('surf-unread-badge');
    
    // Guest Registration Elements
    const guestRegistration = document.getElementById('surf-guest-registration');
    const normalChat = document.getElementById('surf-normal-chat');
    const nameInput = document.getElementById('surf-name-input');
    const emailInput = document.getElementById('surf-email-input');
    const joinButton = document.getElementById('surf-join-button');
    
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
        
        // Initialize guest registration if user is guest
        initGuestRegistration();
        
        // Load friend chat list if we're on the friend tab
        if (currentTab === 'friend') {
            showFriendChatList();
        }
        
        // Pre-load all historical conversations on page load
        loadAllHistoricalConversations();
        
        // Friend list will be updated via real-time events instead of polling
        
        // Also refresh friend list when real-time connection is established
        setTimeout(() => {
            if (currentTab === 'friend' && !currentChatUser) {
                showFriendChatList();
            }
        }, 2000); // Refresh after 2 seconds to catch any users that joined
    }
    
    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Chat toggle
        chatToggle.addEventListener('click', toggleChat);
        
        // Chat input
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        chatSend.addEventListener('click', sendMessage);
        
        // Add input validation for guest registration
        if (nameInput) {
            nameInput.addEventListener('input', (e) => {
                validateGuestInputs();
            });
            nameInput.addEventListener('keyup', (e) => {
                validateGuestInputs();
            });
            nameInput.addEventListener('paste', (e) => {
                setTimeout(() => validateGuestInputs(), 10);
            });
        }
        
        if (emailInput) {
            emailInput.addEventListener('input', (e) => {
                validateGuestInputs();
            });
            emailInput.addEventListener('keyup', (e) => {
                validateGuestInputs();
            });
            emailInput.addEventListener('paste', (e) => {
                setTimeout(() => validateGuestInputs(), 10);
            });
            emailInput.addEventListener('blur', (e) => {
                validateGuestInputs();
            });
            emailInput.addEventListener('focus', (e) => {
                validateGuestInputs();
            });
        }
        
        // Add focus event to name input as well
        if (nameInput) {
            nameInput.addEventListener('focus', (e) => {
                validateGuestInputs();
            });
        }
        
        if (joinButton) {
            joinButton.addEventListener('click', handleGuestJoin);
        }
        
        // Add Enter key support for guest registration
        if (nameInput) {
            nameInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    emailInput.focus();
                }
            });
        }
        
        if (emailInput) {
            emailInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    handleGuestJoin();
                }
            });
        }
        
        // Tab switching
        chatTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const tabName = tab.dataset.tab;
                if (tabName) {
                    switchTab(tabName);
                }
            });
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
        
        // Click outside to close chat
        document.addEventListener('click', (e) => {
            if (chatDrawer.classList.contains('open') && 
                !chatDrawer.contains(e.target) && 
                !chatToggle.contains(e.target) &&
                !avatarDock.contains(e.target) &&
                !e.target.closest('.surf-friend-user') &&
                !e.target.closest('.surf-back-button')) {
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
            channel.bind('client-admin-support-reply', handleAdminSupportReply);
            channel.bind('client-message-deleted', handleMessageDeleted);
            
            // Refresh friend list after connection is established
            if (currentTab === 'friend' && !currentChatUser) {
                setTimeout(() => {
                    showFriendChatList();
                }, 1000);
            }
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
        channel.bind('admin-support-reply', handleAdminSupportReply);
        
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
            case 'message-deleted':
                handleMessageDeleted(data);
                break;
            case 'admin-support-reply':
                handleAdminSupportReply(data);
                break;
            case 'individual-message':
                handleIndividualMessage(data);
                break;
            case 'support-message':
                handleSupportMessage(data);
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
        
        // Add user to current users if not already present
        if (!currentUsers.has(data.user.id)) {
            const cursor = createCursor(data.user);
            currentUsers.set(data.user.id, cursor);
            updateAvatarDock();
            
            // Update friend chat list if it's visible
            if (currentTab === 'friend' && !currentChatUser) {
                showFriendChatList();
            }
        } else {
            // Update existing user's last seen time
            const existingCursor = currentUsers.get(data.user.id);
            if (existingCursor) {
                existingCursor.lastSeen = Date.now();
            }
        }
    }
    
    /**
     * Handle user left
     */
    function handleUserLeft(data) {
        if (data.user.id === config.currentUser.id) return;
        handleCursorLeave(data);
        
        // Update friend chat list if it's visible
        if (currentTab === 'friend' && !currentChatUser) {
            showFriendChatList();
        }
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
        
        // Add/remove 'multiple' class based on user count
        if (users.length > 1) {
            avatarDock.classList.add('multiple');
        } else {
            avatarDock.classList.remove('multiple');
        }
        
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
        // Validate tab name
        if (!tabName || !['web', 'friend', 'support'].includes(tabName)) {
            return;
        }
        
        currentTab = tabName;
        
        // Update tab active states
        chatTabs.forEach(tab => {
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Update title if it exists
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            if (tabName === 'web') {
                title.textContent = 'Web Chat';
            } else if (tabName === 'friend') {
                title.textContent = 'Friend Chat';
            } else if (tabName === 'support') {
                title.textContent = 'Support';
            }
        }
        
        // Clear current chat user when switching tabs
        currentChatUser = null;
        
        // Clear chat messages container to prevent showing wrong data
        chatMessages.innerHTML = '';
        
        // Clear any existing refresh intervals
        // (Auto-refresh has been removed in favor of real-time updates)
        
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
        
        // Clear chat input when switching tabs
        if (chatInput) {
            chatInput.value = '';
            
            // Update placeholder based on tab
            if (tabName === 'friend') {
                chatInput.placeholder = 'Select a friend to start chatting...';
            } else if (tabName === 'support') {
                if (config.currentUser.isAdmin) {
                    chatInput.placeholder = 'Select a support ticket to reply...';
                } else {
                    chatInput.placeholder = 'Type your support message...';
                }
            } else {
                chatInput.placeholder = 'Type a message...';
            }
        }
    }
    
    /**
     * Show Friend Chat list
     */
    async function showFriendChatList() {
        chatMessages.innerHTML = '';
        
        // Update title
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            title.textContent = 'Friend Chat';
        }
        
        // Load historical conversations first
        await loadHistoricalConversations();
        
        // If no users are available, show empty state
        if (currentUsers.size === 0) {
            showEmptyState('No other users online. Share this page with friends to start chatting!');
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
     * Load all historical conversations on page load
     */
    async function loadAllHistoricalConversations() {
        try {
            console.log('Loading all historical conversations on page load');
            
            const response = await fetch(`${config.apiUrl}chat/individual/load-conversations?user_id=${config.currentUser.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.conversations && data.conversations.length > 0) {
                    console.log('Found', data.conversations.length, 'historical conversations');
                    
                    // Load messages for each conversation
                    for (const conv of data.conversations) {
                        if (conv.other_user_id && conv.other_user_name) {
                            console.log('Loading conversation for user:', conv.other_user_id, 'name:', conv.other_user_name);
                            await loadConversationMessages(conv.other_user_id, conv.other_user_name);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load all historical conversations:', error);
        }
    }
    
    /**
     * Load historical conversations
     */
    async function loadHistoricalConversations() {
        try {
            const response = await fetch(`${config.apiUrl}chat/individual/load-conversations?user_id=${config.currentUser.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.conversations && data.conversations.length > 0) {
                    // Add historical conversations to the friend list
                    data.conversations.forEach(conv => {
                        // Validate conversation data
                        if (!conv.other_user_id || !conv.other_user_name) {
                            console.warn('Invalid conversation data:', conv);
                            return;
                        }
                        
                        // Check if this user is already in currentUsers
                        const existingUser = Array.from(currentUsers.values()).find(cursor => cursor.user.id === conv.other_user_id);
                        if (!existingUser) {
                            // Create a virtual user for historical conversation
                            const virtualUser = {
                                id: conv.other_user_id,
                                name: conv.other_user_name,
                                color: getColorForUserId(conv.other_user_id)
                            };
                            
                            const virtualCursor = {
                                user: virtualUser,
                                lastSeen: conv.last_message_time ? new Date(conv.last_message_time).getTime() : Date.now(),
                                element: null // No cursor element for historical users
                            };
                            
                            // Add to currentUsers temporarily for display
                            currentUsers.set(conv.other_user_id, virtualCursor);
                        }
                        
                        // Pre-load the conversation messages for this user
                        loadConversationMessages(conv.other_user_id, conv.other_user_name);
                    });
                }
            }
        } catch (error) {
            console.error('Failed to load historical conversations:', error);
        }
    }
    
    /**
     * Load conversation messages for a user (background loading)
     */
    async function loadConversationMessages(userId, userName) {
        try {
            console.log('Pre-loading conversation messages for user:', userId);
            
            const response = await fetch(`${config.apiUrl}chat/individual?user_id=${config.currentUser.id}&target_user_id=${userId}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.messages && data.messages.length > 0) {
                    // Validate and clean message data
                    const validMessages = data.messages.filter(msg => {
                        return msg && msg.user_name && msg.message && msg.created_at;
                    });
                    
                    if (validMessages.length > 0) {
                        // Store messages in individualChats for quick access
                        // Store with both string and number versions to handle type mismatches
                        individualChats.set(userId, validMessages);
                        individualChats.set(String(userId), validMessages);
                        individualChats.set(Number(userId), validMessages);
                        console.log('Pre-loaded', validMessages.length, 'messages for user:', userId);
                        console.log('individualChats now has keys:', Array.from(individualChats.keys()));
                    }
                }
            }
        } catch (error) {
            console.error('Failed to pre-load conversation messages for user', userId, ':', error);
        }
    }
    
    /**
     * Get color for user ID
     */
    function getColorForUserId(userId) {
        const colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
            '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B739', '#52B788'
        ];
        
        if (!userId) {
            return colors[0];
        }
        
        if (typeof userId === 'string') {
            const hash = userId.split('').reduce((a, b) => {
                a = ((a << 5) - a) + b.charCodeAt(0);
                return a & a;
            }, 0);
            return colors[Math.abs(hash) % colors.length];
        }
        
        return colors[Math.abs(userId) % colors.length];
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
        avatar.style.backgroundColor = user.color || '#FF6B6B';
        avatar.textContent = (user.name && user.name.charAt) ? user.name.charAt(0).toUpperCase() : '?';
        
        const content = document.createElement('div');
        content.className = 'surf-friend-content';
        
        const name = document.createElement('div');
        name.className = 'surf-friend-name';
        name.textContent = user.name || 'Unknown User';
        
        const lastMessage = document.createElement('div');
        lastMessage.className = 'surf-friend-last-message';
        
        // Get last message for this user
        const userMessages = individualChats.get(user.id) || [];
        if (userMessages.length > 0) {
            const lastMsg = userMessages[userMessages.length - 1];
            lastMessage.textContent = lastMsg.message || 'Click to start chatting...';
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
        userEl.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openIndividualChat(user);
        });
        
        return userEl;
    }
    
    /**
     * Open individual chat with a user
     */
    function openIndividualChat(user) {
        currentChatUser = user;
        
        // Ensure chat drawer is open
        if (!chatDrawer.classList.contains('open')) {
            chatDrawer.classList.add('open');
        }
        
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            title.innerHTML = `
                <button class="surf-back-button" id="surf-back-button" aria-label="Back to friends">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                Chat with ${user.name}
            `;
        }
        
        // Add back button event listener
        const backButton = document.getElementById('surf-back-button');
        if (backButton) {
            // Remove any existing event listeners
            backButton.replaceWith(backButton.cloneNode(true));
            const newBackButton = document.getElementById('surf-back-button');
            newBackButton.addEventListener('click', (e) => {
                e.stopPropagation();
                showFriendChatList();
                currentChatUser = null;
                
                // Update input placeholder
                if (chatInput) {
                    chatInput.placeholder = 'Select a friend to start chatting...';
                }
            });
        }
        
        // Update tab to show we're in individual chat
        chatTabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === 'friend');
        });
        
        // Update input placeholder
        if (chatInput) {
            chatInput.placeholder = `Message ${user.name}...`;
        }
        
        // Check if we already have messages for this user
        console.log('Checking for cached messages for user:', user.id, 'type:', typeof user.id);
        console.log('Available cached users:', Array.from(individualChats.keys()));
        console.log('individualChats.has(user.id):', individualChats.has(user.id));
        
        // Try both string and number versions of the user ID
        const userIdString = String(user.id);
        const userIdNumber = Number(user.id);
        const hasStringId = individualChats.has(userIdString);
        const hasNumberId = individualChats.has(userIdNumber);
        
        console.log('Checking string ID:', userIdString, 'has:', hasStringId);
        console.log('Checking number ID:', userIdNumber, 'has:', hasNumberId);
        
        let cachedMessages = null;
        if (hasStringId && individualChats.get(userIdString).length > 0) {
            cachedMessages = individualChats.get(userIdString);
        } else if (hasNumberId && individualChats.get(userIdNumber).length > 0) {
            cachedMessages = individualChats.get(userIdNumber);
        }
        
        if (cachedMessages) {
            console.log('Loading cached messages for user:', user.id, 'Count:', cachedMessages.length);
            renderMessages(cachedMessages);
        } else {
            console.log('No cached messages found, loading fresh messages for user:', user.id);
            // Load messages for this user
            loadIndividualChatMessages(user);
        }
    }
    
    /**
     * Load individual chat messages
     */
    async function loadIndividualChatMessages(user) {
        chatMessages.innerHTML = '<div class="surf-loading"></div>';
        
        try {
            console.log('Loading individual messages for user:', user.id, 'from user:', config.currentUser.id);
            
            const response = await fetch(`${config.apiUrl}chat/individual?user_id=${config.currentUser.id}&target_user_id=${user.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            console.log('Individual messages response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Individual messages data:', data);
            
            chatMessages.innerHTML = '';
            
            if (data.messages && data.messages.length > 0) {
                // Validate and clean message data
                const validMessages = data.messages.filter(msg => {
                    return msg && msg.user_name && msg.message && msg.created_at;
                });
                
                if (validMessages.length > 0) {
                    // Store messages locally with both string and number versions
                    individualChats.set(user.id, validMessages);
                    individualChats.set(String(user.id), validMessages);
                    individualChats.set(Number(user.id), validMessages);
                    renderMessages(validMessages);
                } else {
                    console.warn('No valid messages found in response');
                    individualChats.set(user.id, []);
                    individualChats.set(String(user.id), []);
                    individualChats.set(Number(user.id), []);
                    showEmptyState(`Start a conversation with ${user.name}`);
                }
            } else {
                // Initialize empty chat for this user
                individualChats.set(user.id, []);
                individualChats.set(String(user.id), []);
                individualChats.set(Number(user.id), []);
                showEmptyState(`Start a conversation with ${user.name}`);
            }
        } catch (error) {
            console.error('Failed to load individual messages:', error);
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load messages: ' + error.message + '</p></div>';
        }
    }
    
    /**
     * Show Support Chat
     */
    function showSupportChat() {
        chatMessages.innerHTML = '';
        
        const title = document.querySelector('.surf-chat-title');
        
        // Check if current user is admin
        if (config.currentUser.isAdmin) {
            // Show admin support dashboard
            currentChatUser = null; // No specific user selected yet
            if (title) {
                title.textContent = 'Admin Support Dashboard';
            }
            showAdminSupportDashboard();
        } else {
            // Show regular user support chat
            currentChatUser = adminUser;
            if (title) {
                title.textContent = 'Support Chat';
            }
            
            // Load support messages
            loadSupportMessages();
            
            // Support chat will be updated via real-time events
        }
    }
    
    /**
     * Load support messages
     */
    async function loadSupportMessages() {
        // Don't show loading if we're just refreshing
        if (!chatMessages.querySelector('.surf-loading')) {
            chatMessages.innerHTML = '<div class="surf-loading"></div>';
        }
        
        try {
            console.log('Loading support messages for user:', config.currentUser.id);
            console.log('API URL:', config.apiUrl);
            console.log('Nonce:', config.nonce);
            
            const response = await fetch(`${config.apiUrl}chat/support?user_id=${config.currentUser.id}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            console.log('Support messages response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Support messages data:', data);
            
            chatMessages.innerHTML = '';
            
            if (data.messages && data.messages.length > 0) {
                console.log('Found', data.messages.length, 'support messages');
                
                // Store messages locally
                individualChats.set('admin', data.messages);
                
                renderMessages(data.messages);
            } else {
                console.log('No support messages found');
                // Show existing local messages if any
                const existingMessages = individualChats.get('admin') || [];
                if (existingMessages.length > 0) {
                    renderMessages(existingMessages);
                } else {
                    showEmptyState('How can we help you today?');
                }
            }
        } catch (error) {
            console.error('Failed to load support messages:', error);
            
            // Show existing local messages if any, even on error
            const existingMessages = individualChats.get('admin') || [];
            if (existingMessages.length > 0) {
                chatMessages.innerHTML = '';
                renderMessages(existingMessages);
            } else {
                chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load support messages: ' + error.message + '</p></div>';
            }
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
     * Render messages
     */
    function renderMessages(messages) {
        chatMessages.innerHTML = '';
        
        if (messages.length === 0) {
            showEmptyState();
            return;
        }
        
        let lastDate = null;
        
        messages.forEach(msg => {
            const messageDate = new Date(msg.created_at).toDateString();
            
            // Add date divider if this is a new day
            if (lastDate !== messageDate) {
                const divider = createDateDivider(msg.created_at);
                chatMessages.appendChild(divider);
                lastDate = messageDate;
            }
            
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
        
        // Convert both IDs to strings for reliable comparison
        const messageUserId = String(msg.user_id);
        const currentUserId = String(config.currentUser.id);
        
        // Debug logging to help identify issues
        console.log('Message comparison:', {
            messageUserId,
            currentUserId,
            isOwn: messageUserId === currentUserId,
            originalMessageUserId: msg.user_id,
            originalCurrentUserId: config.currentUser.id
        });
        
        if (messageUserId === currentUserId) {
            message.classList.add('own');
        }
        
        const header = document.createElement('div');
        header.className = 'surf-message-header';
        
        const avatar = document.createElement('div');
        avatar.className = 'surf-message-avatar';
        avatar.style.backgroundColor = msg.user_color || colors[msg.user_id % colors.length];
        
        // For support chat admin messages, show "S" for Support
        if (currentTab === 'support' && (msg.user_id === 'admin' || msg.message_type === 'admin')) {
            avatar.textContent = 'S';
        } else {
            avatar.textContent = (msg.user_name && msg.user_name.charAt) ? msg.user_name.charAt(0).toUpperCase() : '?';
        }
        
        const name = document.createElement('div');
        name.className = 'surf-message-name';
        
        // For support chat, show "Support" for admin messages
        if (currentTab === 'support' && (msg.user_id === 'admin' || msg.message_type === 'admin')) {
            name.textContent = 'Support';
        } else {
            name.textContent = msg.user_name || 'Unknown User';
        }
        
        header.appendChild(avatar);
        header.appendChild(name);
        
        const content = document.createElement('div');
        content.className = 'surf-message-content';
        
        const bubble = document.createElement('div');
        bubble.className = 'surf-message-bubble';
        bubble.textContent = msg.message;
        
        content.appendChild(bubble);
        
        message.appendChild(header);
        message.appendChild(content);
        
        return message;
    }
    
    function createDateDivider(dateString) {
        const divider = document.createElement('div');
        divider.className = 'surf-date-divider';
        
        const line = document.createElement('div');
        line.className = 'surf-date-line';
        
        const date = document.createElement('div');
        date.className = 'surf-date-text';
        date.textContent = formatDate(dateString);
        
        divider.appendChild(line);
        divider.appendChild(date);
        divider.appendChild(line.cloneNode(true));
        
        return divider;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric'
        });
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
        
        // Ensure guest has completed registration before allowing messages
        if (config.currentUser.isGuest && !hasSetGuestInfo) {
            return; // Don't allow messages until registration is complete
        }
        
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
                if (config.currentUser.isAdmin && currentChatUser) {
                    // Admin sending reply to user
                    await sendAdminSupportReply(msg, currentChatUser.id);
                } else {
                    // Regular user sending support message
                    await sendSupportMessage(msg);
                }
            } else {
                // If in friend tab but no user selected, don't send message
                console.warn('No user selected for individual chat');
                return;
            }
            
            chatInput.value = '';
            isFirstMessage = false;
            
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
            console.log('Sending individual message:', {
                sender_id: config.currentUser.id,
                sender_name: config.currentUser.name,
                recipient_id: targetUser.id,
                recipient_name: targetUser.name,
                message: msg.message
            });
            
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
            
            console.log('Individual message response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Individual message error response:', errorText);
                throw new Error(`Failed to send individual message: ${response.status} ${errorText}`);
            }
            
            const responseData = await response.json();
            console.log('Individual message saved successfully:', responseData);
            
            // Store message locally
            if (!individualChats.has(targetUser.id)) {
                individualChats.set(targetUser.id, []);
            }
            
            // Create proper message object for storage
            const messageForStorage = {
                id: Date.now(), // Temporary ID
                user_id: config.currentUser.id,
                user_name: config.currentUser.name,
                message: msg.message,
                created_at: msg.created_at,
                user_color: config.currentUser.color
            };
            
            individualChats.get(targetUser.id).push(messageForStorage);
            
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
            console.log('Sending support message:', msg);
            console.log('User ID:', config.currentUser.id);
            console.log('User Name:', config.currentUser.name);
            
            const requestData = {
                user_id: config.currentUser.id,
                user_name: config.currentUser.name,
                message: msg.message,
                message_type: 'user'
            };
            
            console.log('Request data:', requestData);
            console.log('API URL:', config.apiUrl);
            console.log('Nonce:', config.nonce);
            
            // Save to backend
            const response = await fetch(`${config.apiUrl}chat/support`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('Support message response status:', response.status);
            
            if (!response.ok) {
                const errorData = await response.json();
                console.error('Support message error response:', errorData);
                throw new Error(`Failed to send support message: ${errorData.message || response.statusText}`);
            }
            
            const responseData = await response.json();
            console.log('Support message response data:', responseData);
            
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
            console.error('Support message send error:', error);
            throw error;
        }
    }
    
    /**
     * Send admin support reply
     */
    async function sendAdminSupportReply(msg, targetUserId) {
        try {
            console.log('Sending admin support reply:', msg);
            console.log('Target User ID:', targetUserId);
            console.log('Admin Name:', config.currentUser.name);
            
            const requestData = {
                user_id: targetUserId,
                message: msg.message,
                admin_id: config.currentUser.id,
                admin_name: config.currentUser.name
            };
            
            console.log('Admin reply request data:', requestData);
            
            // Save to backend
            const response = await fetch(`${config.apiUrl}chat/support/admin/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('Admin reply response status:', response.status);
            
            if (!response.ok) {
                const errorData = await response.json();
                console.error('Admin reply error response:', errorData);
                throw new Error(`Failed to send admin reply: ${errorData.message || response.statusText}`);
            }
            
            const responseData = await response.json();
            console.log('Admin reply response data:', responseData);
            
            // Create admin message object for display
            const adminMsg = {
                user_id: 'admin',
                user_name: config.currentUser.name,
                message: msg.message,
                created_at: msg.created_at,
                user_color: '#E74C3C',
                message_type: 'admin'
            };
            
            // Store message locally
            if (!individualChats.has(targetUserId)) {
                individualChats.set(targetUserId, []);
            }
            individualChats.get(targetUserId).push(adminMsg);
            
            // Broadcast to target user
            const data = {
                user_id: targetUserId,
                admin_name: config.currentUser.name,
                message: msg.message,
                created_at: msg.created_at,
                type: 'admin-support-reply'
            };
            
            if (pusher) {
                channel.trigger('client-admin-support-reply', data);
            } else if (websocket && websocket.readyState === WebSocket.OPEN) {
                websocket.send(JSON.stringify({ type: 'admin-support-reply', ...data }));
            }
            
        } catch (error) {
            console.error('Admin support reply send error:', error);
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
        } else if (data.type === 'admin-support-reply') {
            handleAdminSupportReply(data);
        } else {
            // Web chat message - only show if we're currently viewing web chat
            if (currentTab === 'web') {
                const messageEl = createMessageElement(msg);
                chatMessages.appendChild(messageEl);
                scrollToBottom();
            }
        }
        
        // Update unread count if chat is closed
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        updateAvatarDock();
    }
    
    /**
     * Handle message deletion
     */
    function handleMessageDeleted(data) {
        const messageId = data.message_id;
        
        // Find and remove the message element
        const messageElement = chatMessages.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            // Add fade out animation
            messageElement.style.transition = 'opacity 0.3s ease-out';
            messageElement.style.opacity = '0';
            
            setTimeout(() => {
                messageElement.remove();
                
                // Check if there are any messages left
                const remainingMessages = chatMessages.querySelectorAll('.surf-message');
                if (remainingMessages.length === 0) {
                    showEmptyState();
                }
            }, 300);
        }
    }
    
    /**
     * Handle individual chat message
     */
    function handleIndividualMessage(data) {
        const msg = {
            id: Date.now(), // Temporary ID
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
        
        // Update unread count if chat is closed
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
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
        
        // Update admin dashboard if it's visible
        if (currentTab === 'support' && config.currentUser.isAdmin && !currentChatUser) {
            showAdminSupportDashboard();
        }
    }
    
    /**
     * Handle admin support reply
     */
    function handleAdminSupportReply(data) {
        // Only show if we're the target user
        if (data.user_id !== config.currentUser.id) {
            return;
        }
        
        const msg = {
            user_id: 'admin',
            user_name: 'Support', // Always show "Support" instead of admin name
            message: data.message,
            created_at: data.created_at,
            user_color: '#E74C3C',
            message_type: 'admin' // Add message type for proper handling
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
        
        // Update admin dashboard if it's visible
        if (currentTab === 'support' && config.currentUser.isAdmin && !currentChatUser) {
            showAdminSupportDashboard();
        }
        
        // Update unread count if chat is closed
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        // Show notification
        showNotification('New support reply received!');
    }
    
    /**
     * Show notification
     */
    function showNotification(message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'surf-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007cba;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.parentElement.removeChild(notification);
                }
            }, 300);
        }, 3000);
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
     * Initialize guest registration
     */
    function initGuestRegistration() {
        if (config.currentUser.isGuest && !hasSetGuestInfo) {
            // Check if guest info is saved in localStorage
            const savedGuestInfo = loadGuestInfoFromStorage();
            if (savedGuestInfo) {
                // Auto-login with saved info
                autoLoginGuest(savedGuestInfo);
            } else {
                // Show guest registration form
                showGuestRegistrationForm();
            }
        } else {
            // Show normal chat
            showNormalChat();
        }
    }
    
    /**
     * Load guest info from localStorage
     */
    function loadGuestInfoFromStorage() {
        try {
            const savedData = localStorage.getItem('surf_guest_info');
            if (savedData) {
                const guestInfo = JSON.parse(savedData);
                // Validate the saved data structure
                if (guestInfo.name && guestInfo.email && guestInfo.userId) {
                    return guestInfo;
                }
            }
        } catch (error) {
            console.error('Failed to load guest info from localStorage:', error);
        }
        return null;
    }
    
    /**
     * Save guest info to localStorage
     */
    function saveGuestInfoToStorage(name, email, userId) {
        try {
            const guestInfo = {
                name: name,
                email: email,
                userId: userId,
                timestamp: Date.now()
            };
            localStorage.setItem('surf_guest_info', JSON.stringify(guestInfo));
        } catch (error) {
            console.error('Failed to save guest info to localStorage:', error);
        }
    }
    
    /**
     * Auto-login guest with saved info
     */
    function autoLoginGuest(savedGuestInfo) {
        // Use the saved user ID and color to maintain consistency
        config.currentUser.id = savedGuestInfo.userId;
        config.currentUser.name = savedGuestInfo.name;
        config.currentUser.email = savedGuestInfo.email;
        
        // Ensure we have a consistent color for this user ID
        config.currentUser.color = getColorForUserId(savedGuestInfo.userId);
        
        hasSetGuestInfo = true;
        
        // Update cursor name immediately
        updateCurrentUserCursor();
        
        // Update avatar dock
        updateAvatarDock();
        
        // Show normal chat
        showNormalChat();
        
        // Broadcast updated presence
        broadcastPresence();
        
        // Focus on chat input
        setTimeout(() => {
            if (chatInput) {
                chatInput.focus();
            }
        }, 100);
    }
    
    /**
     * Show guest registration form
     */
    function showGuestRegistrationForm() {
        if (guestRegistration) {
            guestRegistration.style.display = 'flex';
        }
        if (normalChat) {
            normalChat.style.display = 'none';
        }
        
        // Focus on name input
        if (nameInput) {
            setTimeout(() => nameInput.focus(), 100);
        }
    }
    
    /**
     * Show normal chat interface
     */
    function showNormalChat() {
        if (guestRegistration) {
            guestRegistration.style.display = 'none';
        }
        if (normalChat) {
            normalChat.style.display = 'flex';
        }
    }
    
    /**
     * Validate guest inputs and update UI
     */
    function validateGuestInputs() {
        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        
        const nameValid = validateName(name);
        const emailValid = validateEmail(email);
        
        // Update input styling immediately
        if (nameInput) {
            updateInputValidation(nameInput, nameValid, name);
        }
        if (emailInput) {
            updateInputValidation(emailInput, emailValid, email);
        }
        
        // Update join button state
        if (joinButton) {
            joinButton.disabled = !(nameValid && emailValid);
        }
        
        return nameValid && emailValid;
    }
    
    /**
     * Validate name input
     */
    function validateName(name) {
        return name.length >= 2 && 
               name.length <= 20 && 
               !name.includes(' ') && 
               /^[a-zA-Z]+$/.test(name);
    }
    
    /**
     * Validate email input
     */
    function validateEmail(email) {
        return email.length > 0 && 
               /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    /**
     * Update input validation styling
     */
    function updateInputValidation(input, isValid, value) {
        if (!input) return;
        
        // Remove existing validation classes
        input.classList.remove('valid', 'invalid');
        
        if (value.length > 0) {
            if (isValid) {
                input.classList.add('valid');
                // Force immediate style update
                input.style.borderColor = '#10B981';
                input.style.color = '#10B981';
            } else {
                input.classList.add('invalid');
                // Force immediate style update
                input.style.borderColor = '#EF4444';
                input.style.color = '#EF4444';
            }
        } else {
            // Reset to default styles
            input.style.borderColor = '';
            input.style.color = '';
        }
        
        // Force a reflow to ensure styles are applied
        input.offsetHeight;
    }
    
    /**
     * Handle guest join button click
     */
    async function handleGuestJoin() {
        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        
        if (!validateGuestInputs()) {
            return; // Validation failed
        }
        
        try {
            // Keep the original user ID from the server to maintain consistency
            const originalUserId = config.currentUser.id;
            
            // Update user info with original ID and consistent color
            config.currentUser.name = name;
            config.currentUser.email = email;
            config.currentUser.color = getColorForUserId(originalUserId);
            
            // Show success animation
            showNameSuccessAnimation();
            
            // Save guest information to localStorage
            saveGuestInfoToStorage(name, email, originalUserId);
            
            // Save guest information to backend
            await saveGuestInfo(name, email);
            
            hasSetGuestInfo = true;
            
            // Update cursor name immediately
            updateCurrentUserCursor();
            
            // Update avatar dock
            updateAvatarDock();
            
            // Hide registration form and show normal chat
            transitionToNormalChat();
            
            // Broadcast updated presence
            broadcastPresence();
            
            // Focus on chat input
            setTimeout(() => {
                if (chatInput) {
                    chatInput.focus();
                }
            }, 500);
            
        } catch (error) {
            console.error('Failed to join chat:', error);
        }
    }
    
    /**
     * Save guest information to backend
     */
    async function saveGuestInfo(name, email) {
        try {
            const response = await fetch(`${config.apiUrl}guest/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    user_id: config.currentUser.id,
                    name: name,
                    email: email
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to save guest information');
            }
            
            const data = await response.json();
            console.log('Guest registered successfully:', data);
            
        } catch (error) {
            console.error('Failed to save guest information:', error);
            // Don't throw error - allow user to continue even if backend save fails
        }
    }
    
    /**
     * Transition from registration form to normal chat
     */
    function transitionToNormalChat() {
        // Add transition classes
        if (guestRegistration) {
            guestRegistration.classList.add('hidden');
        }
        
        // Show normal chat after transition
        setTimeout(() => {
            if (guestRegistration) {
                guestRegistration.style.display = 'none';
            }
            if (normalChat) {
                normalChat.style.display = 'flex';
                normalChat.classList.remove('hidden');
            }
        }, 300);
    }
    
    /**
     * Show success animation
     */
    function showNameSuccessAnimation() {
        const successIcon = document.createElement('div');
        successIcon.className = 'surf-name-success';
        successIcon.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" 
                      stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;
        
        // Position the success icon over the join button
        const inputContainer = joinButton ? joinButton.parentElement : document.querySelector('.surf-chat-input-container');
        if (inputContainer) {
            inputContainer.style.position = 'relative';
            inputContainer.appendChild(successIcon);
            
            // Animate in
            setTimeout(() => {
                successIcon.classList.add('show');
            }, 10);
            
            // Remove after animation
            setTimeout(() => {
                successIcon.classList.add('hide');
                setTimeout(() => {
                    if (successIcon.parentElement) {
                        successIcon.parentElement.removeChild(successIcon);
                    }
                }, 300);
            }, 1500);
        }
    }
    
    /**
     * Update current user cursor (for when name changes)
     */
    function updateCurrentUserCursor() {
        // Find and update existing cursor if it exists
        const existingCursor = currentUsers.get(config.currentUser.id);
        if (existingCursor && existingCursor.element) {
            const namePill = existingCursor.element.querySelector('.surf-cursor-name');
            if (namePill) {
                namePill.textContent = config.currentUser.name;
            }
        }
    }
    
    /**
     * Format time
     */
    function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        // Show actual time and date instead of relative time
        const today = new Date();
        const isToday = date.toDateString() === today.toDateString();
        
        if (isToday) {
            // Show time for today's messages
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        } else {
            // Show date for older messages
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
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
    
    
    /**
     * Show Admin Support Dashboard
     */
    async function showAdminSupportDashboard() {
        chatMessages.innerHTML = '<div class="surf-loading">Loading support tickets...</div>';
        
        try {
            console.log('Loading admin support tickets...');
            console.log('API URL:', config.apiUrl);
            console.log('Nonce:', config.nonce);
            
            // Load all support tickets
            const response = await fetch(`${config.apiUrl}chat/support/admin`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            console.log('Admin support tickets response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Admin support tickets data:', data);
            
            if (data.tickets && data.tickets.length > 0) {
                console.log('Found', data.tickets.length, 'support tickets');
                displayAdminSupportTickets(data.tickets);
            } else {
                console.log('No support tickets found');
                chatMessages.innerHTML = '<div class="surf-empty-state"><p>No support tickets found</p></div>';
            }
            
            // Admin dashboard will be updated via real-time events
            
        } catch (error) {
            console.error('Failed to load support tickets:', error);
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load support tickets: ' + error.message + '</p></div>';
        }
    }
    
    /**
     * Display Admin Support Tickets
     */
    function displayAdminSupportTickets(tickets) {
        let html = '<div class="surf-admin-tickets-container">';
        
        tickets.forEach(ticket => {
            const lastMessageTime = new Date(ticket.last_message_time);
            const timeAgo = formatTimeAgo(ticket.last_message_time);
            const isUnread = !ticket.is_read_by_admin;
            
            html += `
                <div class="surf-admin-ticket-item ${isUnread ? 'unread' : ''}" data-user-id="${ticket.user_id}">
                    <div class="surf-admin-ticket-header">
                        <div class="surf-admin-ticket-user">
                            <strong>${ticket.user_name || 'Unknown User'}</strong>
                            ${isUnread ? '<span class="surf-unread-indicator">●</span>' : ''}
                        </div>
                        <div class="surf-admin-ticket-meta">
                            <span class="surf-ticket-time">${timeAgo}</span>
                        </div>
                    </div>
                    <div class="surf-admin-ticket-preview">
                        ${ticket.last_message ? ticket.last_message.substring(0, 100) + (ticket.last_message.length > 100 ? '...' : '') : 'No messages yet'}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        chatMessages.innerHTML = html;
        
        // Add click handlers for ticket items
        document.querySelectorAll('.surf-admin-ticket-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.dataset.userId;
                selectAdminSupportTicket(userId);
            });
        });
    }
    
    /**
     * Select Admin Support Ticket
     */
    async function selectAdminSupportTicket(userId) {
        // Update UI to show selected ticket
        document.querySelectorAll('.surf-admin-ticket-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-user-id="${userId}"]`).classList.add('active');
        
        // Load conversation for this user
        await loadAdminSupportConversation(userId);
    }
    
    /**
     * Load Admin Support Conversation
     */
    async function loadAdminSupportConversation(userId) {
        try {
            const response = await fetch(`${config.apiUrl}chat/support/admin/conversation?user_id=${userId}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // Update title
            const title = document.querySelector('.surf-chat-title');
            if (title) {
                title.textContent = `Support Chat - ${data.user_info.user_name}`;
            }
            
            // Display conversation
            displayAdminSupportConversation(data.messages, data.user_info);
            
            // Mark as read
            markSupportAsRead(userId);
            
        } catch (error) {
            console.error('Failed to load support conversation:', error);
            chatMessages.innerHTML = '<div class="surf-empty-state"><p>Failed to load conversation: ' + error.message + '</p></div>';
        }
    }
    
    /**
     * Display Admin Support Conversation
     */
    function displayAdminSupportConversation(messages, userInfo) {
        let html = '<div class="surf-admin-conversation">';
        
        if (messages.length === 0) {
            html += '<div class="surf-empty-state"><p>No messages in this conversation</p></div>';
        } else {
            messages.forEach(message => {
                const isAdmin = message.message_type === 'admin';
                const messageTime = new Date(message.created_at);
                const timeStr = messageTime.toLocaleTimeString();
                
                html += `
                    <div class="surf-message-item ${isAdmin ? 'admin' : 'user'}">
                        <div class="surf-message-bubble">
                            ${message.message}
                        </div>
                        <div class="surf-message-meta">
                            ${isAdmin ? 'Admin' : message.user_name} • ${timeStr}
                        </div>
                    </div>
                `;
            });
        }
        
        html += '</div>';
        chatMessages.innerHTML = html;
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Update current chat user for sending messages
        currentChatUser = {
            id: userInfo.user_id,
            name: userInfo.user_name,
            color: '#E74C3C'
        };
        
        // Update placeholder text
        if (chatInput) {
            chatInput.placeholder = `Reply to ${userInfo.user_name}...`;
        }
    }
    
    /**
     * Mark Support as Read
     */
    async function markSupportAsRead(userId) {
        try {
            await fetch(`${config.apiUrl}chat/support/admin/mark-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({ user_id: userId })
            });
        } catch (error) {
            console.error('Failed to mark support as read:', error);
        }
    }
    
    
    /**
     * Format Time Ago
     */
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

