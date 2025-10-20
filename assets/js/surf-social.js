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
    let tabUnreadCounts = { web: 0, friend: 0, support: 0 }; // Track unread messages per tab
    let adminUser = { id: 'admin', name: 'Admin', color: '#E74C3C' };
    let hasSetGuestName = false; // Track if guest has set their name
    let hasSetGuestEmail = false; // Track if guest has set their email
    let hasSetGuestInfo = false; // Track if guest has completed registration
    
    // Sticky Notes State
    let stickyNotes = new Map();
    let isNotesMode = false;
    let noteCreationPosition = { x: 0, y: 0 };
    let noteTimers = new Map();
    
    // Drawing State
    let isDrawMode = false;
    let isDrawing = false;
    let currentPath = [];
    let currentDrawingId = null;
    let drawings = new Map();
    let drawingTimers = new Map();
    
    // Message deduplication cache
    const messageCache = new Map();
    const MAX_CACHE_SIZE = 1000;
    const CACHE_CLEANUP_INTERVAL = 300000; // 5 minutes
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
    
    // Sticky Notes DOM Elements
    const notesToggle = document.getElementById('surf-notes-toggle');
    const stickyNotesContainer = document.getElementById('surf-sticky-notes-container');
    const noteModal = document.getElementById('surf-note-modal');
    const noteMessage = document.getElementById('surf-note-message');
    const noteCancel = document.getElementById('surf-note-cancel');
    const noteSend = document.getElementById('surf-note-send');
    const noteUserInitial = document.getElementById('surf-note-user-initial');
    
    // Drawing DOM Elements
    const drawToggle = document.getElementById('surf-draw-toggle');
    const drawingContainer = document.getElementById('surf-drawing-container');
    
    // Guest Registration Elements
    const guestRegistration = document.getElementById('surf-guest-registration');
    const normalChat = document.getElementById('surf-normal-chat');
    const nameInput = document.getElementById('surf-name-input');
    const emailInput = document.getElementById('surf-email-input');
    const joinButton = document.getElementById('surf-join-button');
    
    /**
     * Initialize message cache with cleanup
     */
    function initMessageCache() {
        // Clean up cache periodically
        setInterval(() => {
            if (messageCache.size > MAX_CACHE_SIZE) {
                // Remove oldest entries
                const entries = Array.from(messageCache.entries());
                const toRemove = entries.slice(0, entries.length - MAX_CACHE_SIZE);
                toRemove.forEach(([key]) => messageCache.delete(key));
            }
        }, CACHE_CLEANUP_INTERVAL);
    }
    
    /**
     * Initialize the plugin
     */
    function init() {
        if (!config.currentUser || !config.currentUser.name) {
            return;
        }
        
        // Initialize message cache
        initMessageCache();
        
        setupEventListeners();
        initRealtime();
        loadInitialMessages();
        startCursorTracking();
        initStickyNotes();
        
        // Initialize avatar dock state
        updateAvatarDock();
        
        // Initialize guest registration if user is guest
        initGuestRegistration();
        
        // Expose test functions for debugging
        window.testTabNotifications = testTabNotifications;
        window.debugTabStructure = debugTabStructure;
        window.simulateMessage = function(tabType) {
            console.log('Simulating message for tab:', tabType);
            // Always update tab badges regardless of chat state
            tabUnreadCounts[tabType]++;
            updateTabBadges();
            
            // Only update chat toggle badge when chat is closed
            if (!chatDrawer.classList.contains('open')) {
                unreadCount++;
                updateUnreadBadge();
            }
            
            console.log('Unread counts updated:', tabUnreadCounts);
        };
        
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
        
        // Sticky Notes Event Listeners
        if (notesToggle) {
            notesToggle.addEventListener('click', toggleNotesMode);
        }
        
        // Drawing Event Listeners
        if (drawToggle) {
            drawToggle.addEventListener('click', toggleDrawMode);
        }
        
        
        if (noteSend) {
            noteSend.addEventListener('click', saveStickyNote);
        }
        
        if (noteMessage) {
            noteMessage.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    saveStickyNote();
                }
            });
            
            // Auto-expand textarea
            noteMessage.addEventListener('input', function() {
                this.style.height = '20px';
                this.style.height = Math.min(this.scrollHeight, 80) + 'px';
            });
        }
        
        // Page click listener for note creation
        document.addEventListener('click', handlePageClick);
        
        // Drawing event listeners
        document.addEventListener('mousedown', handleDrawStart);
        document.addEventListener('mousemove', handleDrawMove);
        document.addEventListener('mouseup', handleDrawEnd);
        document.addEventListener('touchstart', handleDrawStart, { passive: false });
        document.addEventListener('touchmove', handleDrawMove, { passive: false });
        document.addEventListener('touchend', handleDrawEnd, { passive: false });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);
        
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
            channel.bind('client-note-created', handleStickyNoteEvent);
            channel.bind('client-note-deleted', handleStickyNoteEvent);
            channel.bind('client-drawing-created', handleDrawingEvent);
            channel.bind('client-drawing-deleted', handleDrawingEvent);
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
            channel.bind('note-created', handleStickyNoteEvent);
            channel.bind('note-deleted', handleStickyNoteEvent);
            channel.bind('drawing-created', handleDrawingEvent);
            channel.bind('drawing-deleted', handleDrawingEvent);
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
            case 'note-created':
            case 'note-deleted':
                handleStickyNoteEvent(data);
                break;
            case 'drawing-created':
            case 'drawing-deleted':
                handleDrawingEvent(data);
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
        const THROTTLE_INTERVAL = 100; // 100ms for more real-time cursor tracking
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
        
        // Only show cursors from the same page
        if (data.page && data.page !== window.location.pathname) {
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
        
        // Only process cursor leave events from the same page
        if (data.page && data.page !== window.location.pathname) {
            return;
        }
        
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
        
        // Only show users from the same page
        if (data.page && data.page !== window.location.pathname) {
            return;
        }
        
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
        
        // Only process user left events from the same page
        if (data.page && data.page !== window.location.pathname) {
            return;
        }
        
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
            
            // Avatar unread indicators removed - notifications only on chat icon
            
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
            // Clear all tab unread counts when chat is opened
            tabUnreadCounts = { web: 0, friend: 0, support: 0 };
            updateUnreadBadge();
            updateTabBadges();
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
        
        // Clear unread count for this tab when clicked
        clearTabUnread(tabName);
        
        // Update tab active states
        chatTabs.forEach(tab => {
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Clear title
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            title.textContent = '';
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
            
            // Ensure input and send button are visible and enabled for web chat and support
            if (tabName === 'web' || (tabName === 'support' && config.currentUser.isAdmin && currentChatUser)) {
                chatInput.style.display = 'flex';
                chatInput.disabled = false;
                if (chatSend) {
                    chatSend.style.display = 'flex';
                    chatSend.disabled = false;
                }
            } else if (tabName === 'support' && !config.currentUser.isAdmin) {
                // Regular user in support chat
                chatInput.style.display = 'flex';
                chatInput.disabled = false;
                if (chatSend) {
                    chatSend.style.display = 'flex';
                    chatSend.disabled = false;
                }
            }
        }
    }
    
    /**
     * Show Friend Chat list with collapsible sections
     */
    async function showFriendChatList() {
        chatMessages.innerHTML = '';
        
        // Clear title
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            title.textContent = '';
        }
        
        // Load historical conversations first
        await loadHistoricalConversations();
        
        // Create container for both sections
        const container = document.createElement('div');
        container.className = 'surf-friend-chat-container';
        
        // Create Active Users Section
        const activeUsersSection = createCollapsibleSection('Active Users', 'surf-active-users-section');
        const activeUsersContent = activeUsersSection.querySelector('.surf-collapsible-content');
        
        // Get active users (those with cursor elements)
        const activeUsers = Array.from(currentUsers.values()).filter(cursor => cursor.element !== null);
        
        if (activeUsers.length > 0) {
            // Sort active users by last seen time (most recent first)
            activeUsers.sort((a, b) => (b.lastSeen || 0) - (a.lastSeen || 0));
            
            activeUsers.forEach(cursor => {
                const userEl = createFriendChatUser(cursor.user, cursor);
                activeUsersContent.appendChild(userEl);
            });
        } else {
            activeUsersContent.innerHTML = '<div class="surf-empty-subsection">No users currently online</div>';
        }
        
        container.appendChild(activeUsersSection);
        
        // Create Historical Chats Section
        const historicalSection = createCollapsibleSection('Recent Chats', 'surf-historical-section');
        const historicalContent = historicalSection.querySelector('.surf-collapsible-content');
        
        // Get historical users (those without cursor elements but with messages)
        const historicalUsers = Array.from(currentUsers.values()).filter(cursor => cursor.element === null);
        
        if (historicalUsers.length > 0) {
            // Sort historical users by last message time (most recent first)
            historicalUsers.sort((a, b) => (b.lastSeen || 0) - (a.lastSeen || 0));
            
            historicalUsers.forEach(cursor => {
                const userEl = createFriendChatUser(cursor.user, cursor);
                historicalContent.appendChild(userEl);
            });
        } else {
            historicalContent.innerHTML = '<div class="surf-empty-subsection">No previous conversations</div>';
        }
        
        container.appendChild(historicalSection);
        
        // If no users at all, show empty state
        if (activeUsers.length === 0 && historicalUsers.length === 0) {
            showEmptyState('No other users online. Share this page with friends to start chatting!');
            return;
        }
        
        chatMessages.appendChild(container);
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
     * Create collapsible section element
     */
    function createCollapsibleSection(title, sectionId) {
        const section = document.createElement('div');
        section.className = 'surf-collapsible-section';
        section.id = sectionId;
        
        const header = document.createElement('div');
        header.className = 'surf-collapsible-header';
        header.innerHTML = `
            <span class="surf-collapsible-title">${title}</span>
            <svg class="surf-collapsible-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;
        
        const content = document.createElement('div');
        content.className = 'surf-collapsible-content';
        content.style.display = 'block'; // Start expanded by default
        
        const divider = document.createElement('div');
        divider.className = 'surf-section-divider';
        
        section.appendChild(header);
        section.appendChild(content);
        section.appendChild(divider);
        
        // Add click handler for collapse/expand
        header.addEventListener('click', () => {
            const isExpanded = content.style.display !== 'none';
            content.style.display = isExpanded ? 'none' : 'block';
            
            const icon = header.querySelector('.surf-collapsible-icon');
            icon.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';
        });
        
        return section;
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
            lastMessage.textContent = lastMsg.message || '';
        } else {
            lastMessage.textContent = '';
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
                title.textContent = '';
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
            // Clear input immediately for better UX
            chatInput.value = '';
            isFirstMessage = false;
            
            // Add message to current view immediately (optimistic UI)
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
            
            // Broadcast immediately for instant real-time updates
            broadcastMessageImmediately(msg);
            
            // Handle different chat types (save to database in background)
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
            
        } catch (error) {
            console.error('Failed to send message:', error);
            
            // Show more specific error message
            let errorMessage = 'Failed to send message. Please try again.';
            if (error.message.includes('User not properly configured')) {
                errorMessage = 'Please refresh the page and try again.';
            } else if (error.message.includes('Failed to send support message')) {
                errorMessage = error.message.replace('Failed to send support message: ', '');
            }
            
            alert(errorMessage);
        }
        
        chatInput.disabled = false;
        chatSend.disabled = false;
        chatInput.focus();
    }
    
    /**
     * Broadcast message immediately for instant real-time updates
     */
    function broadcastMessageImmediately(msg) {
        // Generate dedupe ID for this message
        const dedupeId = `immediate_${config.currentUser.id}_${msg.message}_${msg.created_at}`;
        
        const data = {
            user: config.currentUser,
            message: msg.message,
            created_at: msg.created_at,
            user_color: msg.user_color,
            dedupe_id: dedupeId
        };
        
        // Determine the event type based on current tab
        let eventType = 'client-new-message';
        let websocketType = 'new-message';
        
        if (currentTab === 'friend' && currentChatUser) {
            eventType = 'client-individual-message';
            websocketType = 'individual-message';
            data.recipient_id = currentChatUser.id;
            data.recipient_name = currentChatUser.name;
        } else if (currentTab === 'support') {
            eventType = 'client-support-message';
            websocketType = 'support-message';
        }
        
        // Broadcast via Pusher
        if (pusher && channel) {
            try {
                channel.trigger(eventType, data);
                console.log('Message broadcasted via Pusher:', eventType);
            } catch (error) {
                console.error('Pusher broadcast error:', error);
            }
        }
        
        // Broadcast via WebSocket
        if (websocket && websocket.readyState === WebSocket.OPEN) {
            try {
                websocket.send(JSON.stringify({ 
                    type: websocketType, 
                    ...data 
                }));
                console.log('Message broadcasted via WebSocket:', websocketType);
            } catch (error) {
                console.error('WebSocket broadcast error:', error);
            }
        }
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
        
        // Message already broadcasted immediately, no need to broadcast again
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
            
            // Message already broadcasted immediately, no need to broadcast again
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
            console.log('Config object:', config);
            
            // Ensure we have valid user data
            if (!config.currentUser || !config.currentUser.id) {
                throw new Error('User not properly configured. Please refresh the page and try again.');
            }
            
            const requestData = {
                user_id: config.currentUser.id,
                user_name: config.currentUser.name || 'User',
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
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    console.error('Support message error response:', errorData);
                    errorMessage = errorData.message || errorData.error || errorMessage;
                } catch (e) {
                    console.error('Could not parse error response:', e);
                }
                throw new Error(`Failed to send support message: ${errorMessage}`);
            }
            
            const responseData = await response.json();
            console.log('Support message response data:', responseData);
            
            // Store message locally
            if (!individualChats.has('admin')) {
                individualChats.set('admin', []);
            }
            individualChats.get('admin').push(msg);
            
            // Message already broadcasted immediately, no need to broadcast again
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
            
            // Display the message in admin's own view if they're viewing this conversation
            if (currentTab === 'support' && currentChatUser && currentChatUser.id === targetUserId) {
                const messageEl = createMessageElement(adminMsg);
                chatMessages.appendChild(messageEl);
                scrollToBottom();
            }
            
            // Broadcast to target user
            const data = {
                user_id: targetUserId,
                admin_name: config.currentUser.name,
                message: msg.message,
                created_at: msg.created_at,
                type: 'admin-support-reply'
            };
            
            console.log('Broadcasting admin reply to user:', targetUserId);
            console.log('Broadcast data:', data);
            console.log('Using pusher:', !!pusher);
            console.log('Using websocket:', !!(websocket && websocket.readyState === WebSocket.OPEN));
            
            if (pusher) {
                console.log('Triggering pusher event: client-admin-support-reply');
                channel.trigger('client-admin-support-reply', data);
            } else if (websocket && websocket.readyState === WebSocket.OPEN) {
                console.log('Sending websocket message');
                websocket.send(JSON.stringify({ type: 'admin-support-reply', ...data }));
            }
            
        } catch (error) {
            console.error('Admin support reply send error:', error);
            throw error;
        }
    }
    
    /**
     * Handle new message with deduplication
     */
    function handleNewMessage(data) {
        if (data.user.id === config.currentUser.id) {
            return;
        }
        
        // Check for duplicate messages
        if (data.dedupe_id && messageCache.has(data.dedupe_id)) {
            console.log('Duplicate message ignored:', data.dedupe_id);
            return;
        }
        
        // Create a simple dedupe ID if not provided
        const dedupeId = data.dedupe_id || `msg_${data.user.id}_${data.message}_${data.created_at}`;
        if (messageCache.has(dedupeId)) {
            console.log('Duplicate message ignored (generated ID):', dedupeId);
            return;
        }
        
        const msg = {
            user_id: data.user.id,
            user_name: data.user.name,
            message: data.message,
            created_at: data.created_at,
            user_color: data.user.color,
            dedupe_id: dedupeId
        };
        
        // Cache message to prevent duplicates
        messageCache.set(dedupeId, true);
        
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
        
        // Update unread count for chat toggle badge (only when chat is closed)
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        // Always update tab badges regardless of chat state
        // Increment unread count for the appropriate tab
        if (data.type === 'individual-chat') {
            tabUnreadCounts.friend++;
        } else if (data.type === 'support-chat' || data.type === 'admin-support-reply') {
            tabUnreadCounts.support++;
        } else {
            tabUnreadCounts.web++; // Web chat messages
        }
        updateTabBadges();
        
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
        
        // Update unread count for chat toggle badge (only when chat is closed)
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        // Always update tab badges regardless of chat state
        tabUnreadCounts.friend++;
        updateTabBadges();
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
        
        // If we're currently viewing a specific support conversation, show the message
        if (currentTab === 'support' && currentChatUser && currentChatUser.id !== 'admin' && currentChatUser.id !== null && currentChatUser.id !== undefined) {
            const messageEl = createMessageElement(msg);
            chatMessages.appendChild(messageEl);
            scrollToBottom();
        }
        
        // Update admin dashboard if it's visible (but don't add messages to it)
        if (currentTab === 'support' && config.currentUser.isAdmin && !currentChatUser) {
            console.log('Refreshing admin dashboard due to new support message');
            showAdminSupportDashboard();
        }
        
        // Update unread count for chat toggle badge (only when chat is closed)
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        // Always update tab badges regardless of chat state
        tabUnreadCounts.support++;
        updateTabBadges();
    }
    
    /**
     * Handle admin support reply
     */
    function handleAdminSupportReply(data) {
        console.log('Admin support reply received:', data);
        console.log('Current user ID:', config.currentUser.id);
        console.log('Target user ID:', data.user_id);
        console.log('Is target user?', data.user_id === config.currentUser.id);
        
        // Only show if we're the target user
        if (data.user_id !== config.currentUser.id) {
            console.log('Not the target user, ignoring message');
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
            console.log('Currently viewing support chat');
            console.log('Is admin user:', config.currentUser.isAdmin);
            console.log('Current chat user:', currentChatUser);
            
            // For regular users, currentChatUser is set to adminUser, so show the message
            // For admin users, only show if they're viewing a specific conversation
            if (!config.currentUser.isAdmin || (currentChatUser && currentChatUser.id !== 'admin' && currentChatUser.id !== null && currentChatUser.id !== undefined)) {
                console.log('Showing admin reply message');
                const messageEl = createMessageElement(msg);
                chatMessages.appendChild(messageEl);
                scrollToBottom();
            } else {
                console.log('Not showing message - conditions not met');
            }
        } else {
            console.log('Not viewing support chat, current tab:', currentTab);
        }
        
        // Update admin dashboard if it's visible (but don't add messages to it)
        if (currentTab === 'support' && config.currentUser.isAdmin && !currentChatUser) {
            console.log('Refreshing admin dashboard due to new admin support reply');
            showAdminSupportDashboard();
        }
        
        // Update unread count for chat toggle badge (only when chat is closed)
        if (!chatDrawer.classList.contains('open')) {
            unreadCount++;
            updateUnreadBadge();
        }
        
        // Always update tab badges regardless of chat state
        tabUnreadCounts.support++;
        updateTabBadges();
        
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
     * Update tab badges for unread messages
     */
    function updateTabBadges() {
        console.log('updateTabBadges called, tabUnreadCounts:', tabUnreadCounts);
        Object.keys(tabUnreadCounts).forEach(tabName => {
            // Try multiple selectors to find the badge
            let badge = document.querySelector(`#surf-social-widget .surf-tab-badge[data-tab="${tabName}"]`);
            if (!badge) {
                badge = document.querySelector(`.surf-tab-badge[data-tab="${tabName}"]`);
            }
            if (!badge) {
                badge = document.querySelector(`[data-tab="${tabName}"] .surf-tab-badge`);
            }
            
            console.log(`Looking for badge with data-tab="${tabName}":`, badge);
            if (badge) {
                const count = tabUnreadCounts[tabName];
                console.log(`Tab ${tabName} count: ${count}`);
                
                // Only show badge if there are unread messages AND this tab is not currently active
                if (count > 0 && tabName !== currentTab) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.setProperty('display', 'flex', 'important');
                    badge.style.setProperty('visibility', 'visible', 'important');
                    badge.style.setProperty('opacity', '1', 'important');
                    badge.classList.remove('hidden');
                    console.log(`Showing badge for ${tabName} with count ${count} (not active tab)`);
                } else {
                    badge.style.setProperty('display', 'none', 'important');
                    badge.style.setProperty('visibility', 'hidden', 'important');
                    badge.style.setProperty('opacity', '0', 'important');
                    badge.classList.add('hidden');
                    console.log(`Hiding badge for ${tabName} (active tab or no count)`);
                }
            } else {
                console.error(`Badge not found for tab: ${tabName}`);
            }
        });
    }
    
    /**
     * Clear unread count for a specific tab
     */
    function clearTabUnread(tabName) {
        tabUnreadCounts[tabName] = 0;
        updateTabBadges();
        // Don't update unread badge here - only clear specific tab
    }
    
    /**
     * Test function to manually add unread messages for testing
     */
    function testTabNotifications() {
        console.log('Testing tab notifications...');
        
        // First check if badges exist
        console.log('Checking for badges in DOM...');
        const allBadges = document.querySelectorAll('.surf-tab-badge');
        console.log('All badges found:', allBadges);
        
        // Set test counts
        tabUnreadCounts.web = 3;
        tabUnreadCounts.friend = 2;
        tabUnreadCounts.support = 1;
        
        // Update badges (works regardless of chat state now)
        updateTabBadges();
        console.log('Tab counts set for testing:', tabUnreadCounts);
        console.log('Chat drawer open:', chatDrawer.classList.contains('open'));
    }
    
    /**
     * Debug function to check DOM structure
     */
    function debugTabStructure() {
        console.log('=== DEBUGGING TAB STRUCTURE ===');
        const tabs = document.querySelectorAll('.surf-chat-tab');
        console.log('Found tabs:', tabs);
        
        tabs.forEach((tab, index) => {
            console.log(`Tab ${index}:`, tab);
            console.log(`  - data-tab:`, tab.getAttribute('data-tab'));
            console.log(`  - children:`, tab.children);
            
            const badge = tab.querySelector('.surf-tab-badge');
            console.log(`  - badge:`, badge);
            if (badge) {
                console.log(`    - data-tab:`, badge.getAttribute('data-tab'));
                console.log(`    - display:`, getComputedStyle(badge).display);
                console.log(`    - visibility:`, getComputedStyle(badge).visibility);
                console.log(`    - opacity:`, getComputedStyle(badge).opacity);
            }
        });
        console.log('=== END DEBUG ===');
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
        // Clear title
        const title = document.querySelector('.surf-chat-title');
        if (title) {
            title.textContent = '';
        }
        
        // Hide input and send button on admin dashboard
        const chatInput = document.querySelector('.surf-chat-input');
        const chatSend = document.querySelector('.surf-chat-send');
        if (chatInput) chatInput.style.display = 'none';
        if (chatSend) chatSend.style.display = 'none';
        
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
                console.log('Tickets data:', data.tickets);
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
            // Skip tickets with invalid user names
            if (!ticket.user_name || ticket.user_name.trim() === '') {
                console.log('Skipping ticket with invalid user_name:', ticket);
                return;
            }
            
            const lastMessageTime = new Date(ticket.last_message_time);
            const timeAgo = formatTimeAgo(ticket.last_message_time);
            const isUnread = !ticket.is_read_by_admin;
            
            console.log('Ticket:', ticket.user_name, 'is_read_by_admin:', ticket.is_read_by_admin, 'isUnread:', isUnread);
            
            html += `
                <div class="surf-admin-ticket-item ${isUnread ? 'unread' : ''}" data-user-id="${ticket.user_id}">
                    <div class="surf-admin-ticket-header">
                        <div class="surf-admin-ticket-user">
                            <strong>${ticket.user_name}</strong>
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
            
            // Update title with back button
            const title = document.querySelector('.surf-chat-title');
            if (title) {
                title.innerHTML = `
                    <div class="surf-admin-back-header">
                        <button class="surf-back-arrow" id="admin-back-btn">←</button>
                        <span class="surf-admin-chat-title">Support Chat - ${data.user_info.user_name}</span>
                    </div>
                `;
                
                // Add event listener for back button
                const backBtn = document.getElementById('admin-back-btn');
                if (backBtn) {
                    backBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Back button clicked, returning to admin dashboard');
                        showAdminSupportDashboard();
                    });
                }
            }
            
            // Show input and send button for conversation
            const chatInput = document.querySelector('.surf-chat-input');
            const chatSend = document.querySelector('.surf-chat-send');
            if (chatInput) chatInput.style.display = 'flex';
            if (chatSend) chatSend.style.display = 'flex';
            
            // Display conversation
            displayAdminSupportConversation(data.messages, data.user_info);
            
            // Mark as read
            await markSupportAsRead(userId);
            
            // Refresh the admin dashboard to update unread status
            // (This will be handled by real-time updates, but we can also refresh manually)
            
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
        
        // Force scroll to bottom after messages are rendered
        const scrollToBottom = () => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
            // Also try scrolling to the last message element
            const lastMessage = chatMessages.querySelector('.surf-message-item:last-child');
            if (lastMessage) {
                lastMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        };
        
        // Try multiple times to ensure scroll happens
        requestAnimationFrame(scrollToBottom);
        setTimeout(scrollToBottom, 50);
        setTimeout(scrollToBottom, 100);
        setTimeout(scrollToBottom, 200);
        setTimeout(scrollToBottom, 500);
        setTimeout(scrollToBottom, 1000);
        
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
            const response = await fetch(`${config.apiUrl}chat/support/admin/mark-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({ user_id: userId })
            });
            
            if (response.ok) {
                console.log('Successfully marked support as read for user:', userId);
                // Refresh the admin dashboard to update unread status
                if (currentTab === 'support' && config.currentUser.isAdmin && !currentChatUser) {
                    showAdminSupportDashboard();
                }
            } else {
                console.error('Failed to mark support as read:', response.status, response.statusText);
            }
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
    
    /**
     * Initialize Sticky Notes
     */
    function initStickyNotes() {
        // Load existing notes for current page
        loadStickyNotes();
    }
    
    /**
     * Toggle Notes Mode
     */
    function toggleNotesMode() {
        isNotesMode = !isNotesMode;
        
        if (isNotesMode) {
            notesToggle.classList.add('active');
            document.body.classList.add('notes-mode');
            console.log('Notes mode enabled - click anywhere to create a note');
        } else {
            notesToggle.classList.remove('active');
            document.body.classList.remove('notes-mode');
            console.log('Notes mode disabled');
        }
    }
    
    /**
     * Handle Page Click for Note Creation
     */
    function handlePageClick(e) {
        if (!isNotesMode) return;
        
        // Don't create notes if clicking on UI elements
        if (isClickOnUI(e.target)) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        showNoteCreationModal(e.clientX, e.clientY);
    }
    
    /**
     * Check if click is on UI elements
     */
    function isClickOnUI(target) {
        const uiSelectors = [
            '#surf-social-widget',
            '#surf-chat-drawer',
            '#surf-chat-toggle',
            '#surf-notes-toggle',
            '#surf-avatar-dock',
            '#surf-sticky-notes-container',
            '.surf-sticky-note',
            '#surf-note-modal'
        ];
        
        return uiSelectors.some(selector => {
            const element = document.querySelector(selector);
            return element && element.contains(target);
        });
    }
    
    /**
     * Show Note Creation Modal
     */
    function showNoteCreationModal(x, y) {
        noteCreationPosition = { x, y };
        
        if (noteModal) {
            // Set user initial
            if (noteUserInitial && config.currentUser.name) {
                noteUserInitial.textContent = config.currentUser.name.charAt(0).toUpperCase();
            }
            
            // Position modal at click location
            const modalContent = noteModal.querySelector('.surf-note-modal-content');
            if (modalContent) {
                // Adjust position to keep modal on screen
                const rect = modalContent.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                let left = x;
                let top = y;
                
                // Adjust if modal would go off screen
                if (left + 350 > viewportWidth) {
                    left = viewportWidth - 350 - 20;
                }
                if (top + 200 > viewportHeight) {
                    top = viewportHeight - 200 - 20;
                }
                if (left < 20) left = 20;
                if (top < 20) top = 20;
                
                modalContent.style.left = left + 'px';
                modalContent.style.top = top + 'px';
            }
            
            noteModal.style.display = 'flex';
            if (noteMessage) {
                noteMessage.value = '';
                noteMessage.style.height = '20px';
                noteMessage.focus();
            }
        }
    }
    
    /**
     * Close Note Modal
     */
    function closeNoteModal() {
        if (noteModal) {
            noteModal.style.display = 'none';
        }
    }
    
    /**
     * Save Sticky Note
     */
    async function saveStickyNote() {
        if (!noteMessage || !noteMessage.value.trim()) return;
        
        const message = noteMessage.value.trim();
        const data = {
            user_id: config.currentUser.id,
            user_name: config.currentUser.name,
            page_url: window.location.pathname,
            x_position: noteCreationPosition.x,
            y_position: noteCreationPosition.y,
            message: message,
            color: config.currentUser.color
        };
        
        try {
            const response = await fetch(`${config.apiUrl}sticky-notes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.note) {
                    createStickyNoteElement(result.note);
                    closeNoteModal();
                    
                    // Broadcast note creation to other users
                    broadcastStickyNoteEvent('note-created', result.note);
                }
            } else {
                console.error('Failed to save sticky note:', response.status);
            }
        } catch (error) {
            console.error('Failed to save sticky note:', error);
        }
    }
    
    /**
     * Load Sticky Notes for Current Page
     */
    async function loadStickyNotes() {
        try {
            const response = await fetch(`${config.apiUrl}sticky-notes?page_url=${encodeURIComponent(window.location.pathname)}`, {
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.notes) {
                    data.notes.forEach(note => {
                        createStickyNoteElement(note);
                    });
                }
            }
        } catch (error) {
            console.error('Failed to load sticky notes:', error);
        }
    }
    
    /**
     * Create Sticky Note Element
     */
    function createStickyNoteElement(note) {
        if (!stickyNotesContainer) return;
        
        const noteEl = document.createElement('div');
        noteEl.className = 'surf-sticky-note';
        noteEl.style.left = note.x_position + 'px';
        noteEl.style.top = note.y_position + 'px';
        noteEl.style.backgroundColor = note.color;
        noteEl.style.borderColor = note.color;
        noteEl.dataset.noteId = note.id;
        
        // Always start with 10 seconds for new notes
        const timeRemaining = 10;
        
        noteEl.innerHTML = `
            <div class="surf-sticky-note-content">
                <span class="surf-sticky-note-user">${escapeHtml(note.user_name)}:</span>
                <span class="surf-sticky-note-text">${escapeHtml(note.message)}</span>
            </div>
        `;
        
        // Make note draggable
        makeDraggable(noteEl);
        
        stickyNotesContainer.appendChild(noteEl);
        stickyNotes.set(note.id, noteEl);
        
        // Start countdown timer - always start with 10 seconds for new notes
        startNoteTimer(note.id, 10);
    }
    
    /**
     * Make Note Draggable
     */
    function makeDraggable(element) {
        let isDragging = false;
        let startX, startY, startLeft, startTop;
        
        element.addEventListener('mousedown', (e) => {
            if (e.target.tagName === 'BUTTON') return;
            
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = parseInt(element.style.left) || 0;
            startTop = parseInt(element.style.top) || 0;
            
            element.style.cursor = 'grabbing';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;
            
            element.style.left = (startLeft + deltaX) + 'px';
            element.style.top = (startTop + deltaY) + 'px';
        });
        
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                element.style.cursor = 'move';
            }
        });
    }
    
    /**
     * Start Note Timer
     */
    function startNoteTimer(noteId, timeRemaining) {
        const noteEl = stickyNotes.get(noteId);
        if (!noteEl) return;
        
        // Clear any existing timer for this note
        if (noteTimers.has(noteId)) {
            clearInterval(noteTimers.get(noteId));
        }
        
        let timeLeft = Math.max(0, timeRemaining);
        
        if (timeLeft <= 0) {
            removeStickyNote(noteId);
            return;
        }
        
        const timer = setInterval(() => {
            timeLeft--;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                noteTimers.delete(noteId);
                removeStickyNote(noteId);
            }
        }, 1000);
        
        noteTimers.set(noteId, timer);
    }
    
    /**
     * Remove Sticky Note
     */
    function removeStickyNote(noteId) {
        const noteEl = stickyNotes.get(noteId);
        if (noteEl) {
            noteEl.classList.add('fade-out');
            setTimeout(() => {
                if (noteEl.parentNode) {
                    noteEl.parentNode.removeChild(noteEl);
                }
                stickyNotes.delete(noteId);
                noteTimers.delete(noteId);
            }, 500);
        }
    }
    
    /**
     * Delete Sticky Note
     */
    async function deleteStickyNote(noteId) {
        try {
            const response = await fetch(`${config.apiUrl}sticky-notes/${noteId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({ user_id: config.currentUser.id })
            });
            
            if (response.ok) {
                const note = stickyNotes.get(noteId);
                if (note) {
                    const noteData = {
                        id: noteId,
                        page_url: window.location.pathname
                    };
                    broadcastStickyNoteEvent('note-deleted', noteData);
                }
                removeStickyNote(noteId);
            } else {
                console.error('Failed to delete sticky note:', response.status);
            }
        } catch (error) {
            console.error('Failed to delete sticky note:', error);
        }
    }
    
    /**
     * Broadcast Sticky Note Events
     */
    function broadcastStickyNoteEvent(eventType, note) {
        const data = {
            type: eventType,
            note: note,
            page: window.location.pathname
        };
        
        if (pusher) {
            try {
                channel.trigger('client-' + eventType, data);
            } catch (error) {
                console.error('Failed to trigger sticky note event:', error);
            }
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify(data));
        }
    }
    
    /**
     * Handle Sticky Note Events from Other Users
     */
    function handleStickyNoteEvent(data) {
        // Only process events from the same page
        if (data.page !== window.location.pathname) return;
        
        switch (data.type) {
            case 'note-created':
                createStickyNoteElement(data.note);
                break;
            case 'note-deleted':
                removeStickyNote(data.note.id);
                break;
        }
    }
    
    /**
     * Handle Keyboard Shortcuts
     */
    function handleKeyboardShortcuts(e) {
        // Escape to close modal
        if (e.key === 'Escape' && noteModal && noteModal.style.display !== 'none') {
            closeNoteModal();
        }
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Format Time
     */
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    /**
     * Toggle Draw Mode
     */
    function toggleDrawMode() {
        isDrawMode = !isDrawMode;
        
        if (drawToggle) {
            if (isDrawMode) {
                drawToggle.classList.add('active');
                document.body.classList.add('draw-mode');
            } else {
                drawToggle.classList.remove('active');
                document.body.classList.remove('draw-mode');
            }
        }
        
        // Disable notes mode if enabling draw mode
        if (isDrawMode && isNotesMode) {
            toggleNotesMode();
        }
    }
    
    /**
     * Handle Draw Start
     */
    function handleDrawStart(e) {
        if (!isDrawMode) return;
        
        e.preventDefault();
        isDrawing = true;
        currentPath = [];
        
        const point = getEventPoint(e);
        currentPath.push(point);
        
        // Create new drawing canvas
        const result = createDrawingCanvas();
        if (result) {
            currentDrawingId = result.drawingId;
        }
    }
    
    /**
     * Handle Draw Move
     */
    function handleDrawMove(e) {
        if (!isDrawMode || !isDrawing) return;
        
        e.preventDefault();
        const point = getEventPoint(e);
        currentPath.push(point);
        
        // Draw the current path
        drawCurrentPath();
    }
    
    /**
     * Handle Draw End
     */
    function handleDrawEnd(e) {
        if (!isDrawMode || !isDrawing) return;
        
        e.preventDefault();
        isDrawing = false;
        
        if (currentPath.length > 1) {
            // Save the drawing
            saveDrawing();
        }
        
        currentPath = [];
    }
    
    /**
     * Get Event Point
     */
    function getEventPoint(e) {
        const rect = document.body.getBoundingClientRect();
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }
    
    /**
     * Create Drawing Canvas
     */
    function createDrawingCanvas() {
        if (!drawingContainer) return;
        
        const drawingId = 'drawing_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        const canvas = document.createElement('canvas');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        canvas.style.position = 'absolute';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '999996';
        canvas.dataset.drawingId = drawingId;
        
        drawingContainer.appendChild(canvas);
        
        return { canvas, drawingId };
    }
    
    /**
     * Draw Current Path
     */
    function drawCurrentPath() {
        const canvas = drawingContainer.querySelector(`canvas[data-drawing-id="${currentDrawingId}"]`);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        ctx.strokeStyle = config.currentUser.color;
        ctx.lineWidth = 1;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        if (currentPath.length > 1) {
            ctx.beginPath();
            ctx.moveTo(currentPath[0].x, currentPath[0].y);
            
            for (let i = 1; i < currentPath.length; i++) {
                ctx.lineTo(currentPath[i].x, currentPath[i].y);
            }
            
            ctx.stroke();
        }
    }
    
    /**
     * Save Drawing
     */
    function saveDrawing() {
        const canvas = drawingContainer.querySelector(`canvas[data-drawing-id="${currentDrawingId}"]`);
        if (!canvas) return;
        
        const drawingData = {
            id: currentDrawingId,
            user_id: config.currentUser.id,
            user_name: config.currentUser.name,
            color: config.currentUser.color,
            page_url: window.location.pathname,
            created_at: new Date().toISOString(),
            data_url: canvas.toDataURL()
        };
        
        // Store drawing
        drawings.set(currentDrawingId, drawingData);
        
        // Start 5-second timer
        startDrawingTimer(currentDrawingId);
        
        // Broadcast to other users
        broadcastDrawingEvent('drawing-created', drawingData);
        
        // Reset current drawing ID
        currentDrawingId = null;
    }
    
    /**
     * Start Drawing Timer
     */
    function startDrawingTimer(drawingId) {
        const timer = setTimeout(() => {
            removeDrawing(drawingId);
        }, 5000);
        
        drawingTimers.set(drawingId, timer);
    }
    
    /**
     * Remove Drawing
     */
    function removeDrawing(drawingId) {
        const canvas = drawingContainer.querySelector(`canvas[data-drawing-id="${drawingId}"]`);
        if (canvas) {
            canvas.remove();
        }
        
        // Clear timer
        if (drawingTimers.has(drawingId)) {
            clearTimeout(drawingTimers.get(drawingId));
            drawingTimers.delete(drawingId);
        }
        
        drawings.delete(drawingId);
    }
    
    /**
     * Broadcast Drawing Event
     */
    function broadcastDrawingEvent(eventType, drawing) {
        const data = {
            type: eventType,
            drawing: drawing,
            page: window.location.pathname
        };
        
        if (pusher) {
            try {
                channel.trigger('client-' + eventType, data);
            } catch (error) {
                console.error('Failed to trigger drawing event:', error);
            }
        } else if (websocket && websocket.readyState === WebSocket.OPEN) {
            websocket.send(JSON.stringify(data));
        }
    }
    
    /**
     * Handle Drawing Event
     */
    function handleDrawingEvent(data) {
        // Only process events from the same page
        if (data.page !== window.location.pathname) return;
        
        switch (data.type) {
            case 'drawing-created':
                createDrawingFromData(data.drawing);
                break;
            case 'drawing-deleted':
                removeDrawing(data.drawing.id);
                break;
        }
    }
    
    /**
     * Create Drawing From Data
     */
    function createDrawingFromData(drawingData) {
        if (!drawingContainer) return;
        
        const canvas = document.createElement('canvas');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        canvas.style.position = 'absolute';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '999996';
        canvas.dataset.drawingId = drawingData.id;
        
        // Load the drawing data
        const img = new Image();
        img.onload = function() {
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
        };
        img.src = drawingData.data_url;
        
        drawingContainer.appendChild(canvas);
        
        // Store drawing and start timer
        drawings.set(drawingData.id, drawingData);
        startDrawingTimer(drawingData.id);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

