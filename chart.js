const ChatApp = {
    config: {
        serverUrl: window.location.hostname === 'localhost' 
            ? 'ws://localhost:8080'
            : window.location.protocol === 'https:' 
                ? 'wss://' + window.location.host 
                : 'ws://' + window.location.host,
        reconnectInterval: 3000,
        maxReconnectAttempts: 5,
        heartbeatInterval: 15000
    },

    state: {
        connected: false,
        activeUserId: null,
        users: {},
        userChats: JSON.parse(sessionStorage.getItem('userChats')) || {},
        isAdmin: true,
        userProfiles: {},
        reconnectAttempts: 0,
        heartbeatTimer: null,
        lastActivity: null
    },

    elements: {
        userList: document.getElementById('user-list'),
        chatMessages: document.getElementById('chat-messages'),
        messageInput: document.getElementById('message-input'),
        sendBtn: document.getElementById('send-btn'),
        chatHeader: document.getElementById('current-user-name'),
        userStatus: document.getElementById('user-status'),
        userCount: document.getElementById('user-count'),
        notification: document.getElementById('notification'),
        notificationText: document.getElementById('notification-text'),
        userDetailsContent: document.getElementById('user-details-content'),
        clearChat: document.getElementById('clear-chat'),
        userDetails: document.getElementById('user-details'),
        closeChat: document.getElementById('close-chat')
    },

    init: function() {
        if (Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
        
        this.setupWebSocket();
        this.setupEventListeners();
        this.loadInitialUsers();
        
        document.addEventListener('mousemove', this.updateActivity.bind(this));
        document.addEventListener('keypress', this.updateActivity.bind(this));
        this.updateActivity();
    },

    setupEventListeners: function() {
        if (this.elements.sendBtn) {
            this.elements.sendBtn.addEventListener('click', () => this.sendMessage());
        }
        if (this.elements.messageInput) {
            this.elements.messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.sendMessage();
            });
        }
        if (this.elements.clearChat) {
            this.elements.clearChat.addEventListener('click', () => {
                if (this.state.activeUserId) {
                    this.state.userChats[this.state.activeUserId] = [];
                    sessionStorage.setItem('userChats', JSON.stringify(this.state.userChats));
                    this.elements.chatMessages.innerHTML = '<div class="no-messages"><p>Conversation cleared</p></div>';
                }
            });
        }
        if (this.elements.closeChat) {
            this.elements.closeChat.addEventListener('click', () => {
                if (this.state.activeUserId) {
                    this.state.activeUserId = null;
                    this.updateUI();
                    this.elements.chatMessages.innerHTML = '<div class="no-messages"><p>No conversation selected</p></div>';
                    this.updateUserDetails(null);
                }
            });
        }
        if (this.elements.userDetails) {
            this.elements.userDetails.addEventListener('click', () => {
                if (this.state.activeUserId) {
                    this.updateUserDetails(this.state.activeUserId);
                }
            });
        }
    },

    updateStatus: function(message, status) {
        if (this.elements.notificationText) {
            this.elements.notificationText.textContent = message;
            this.elements.notification.classList.add('show');
            setTimeout(() => this.elements.notification.classList.remove('show'), 3000);
        }
        if (this.elements.chatHeader) {
            this.elements.chatHeader.textContent = this.state.activeUserId 
                ? this.state.users[this.state.activeUserId]?.name || 'User ' + this.state.activeUserId.substring(0, 6)
                : 'Select a user to start chatting';
        }
        if (this.elements.userStatus) {
            this.elements.userStatus.textContent = this.state.activeUserId && this.state.users[this.state.activeUserId]?.online 
                ? 'Online' : '';
        }
    },

    updateActivity: function() {
        this.state.lastActivity = new Date();
    },

    setupWebSocket: function() {
        console.log('Connecting to WebSocket at:', this.config.serverUrl);
        
        if (this.chatSocket && this.chatSocket.readyState === WebSocket.OPEN) {
            this.chatSocket.close();
        }
        
        this.chatSocket = new WebSocket(this.config.serverUrl);

        this.chatSocket.onopen = () => {
            console.log('Connected to server');
            this.state.connected = true;
            this.state.reconnectAttempts = 0;
            this.updateStatus('Connected to chat server', 'connected');

            this.sendSocketMessage({
                type: 'set_user_type',
                userType: 'admin'
            });

            this.broadcastAdminStatus(true);
            this.updateUI();
            this.startHeartbeat();
        };

        this.chatSocket.onmessage = (event) => {
            console.log('Admin received raw message:', event.data);
            this.state.reconnectAttempts = 0;
            this.updateActivity();
            
            if (event.data === 'pong') return;
            
            try {
                let jsonString = event.data.substring(event.data.indexOf('{'));
                const data = JSON.parse(jsonString);
                console.log('Parsed message:', data);

                if (data.type === 'status') {
                    this.updateStatus(`Server status: ${data.status} (${data.adminCount} admin(s))`, 'connected');
                } else if (data.type === 'set_user_type' && data.userType === 'user') {
                    this.handleNewUser(data);
                } else if (data.type === 'message' && data.sender === 'user') {
                    this.handleIncomingMessage(data);
                } else if (data.type === 'get_admin_status') {
                    this.sendSocketMessage({
                        type: 'status',
                        status: 'online',
                        adminCount: 1
                    });
                }
            } catch (e) {
                console.error('Error parsing message:', e, event.data);
            }
        };

        this.chatSocket.onclose = (event) => {
            console.log('Connection closed:', event.code, event.reason);
            this.updateStatus('Disconnected. Reconnecting...', 'disconnected');
            this.state.connected = false;
            this.broadcastAdminStatus(false);
            this.updateUI();
            this.stopHeartbeat();

            if (this.state.reconnectAttempts < this.config.maxReconnectAttempts) {
                const delay = Math.min(this.config.reconnectInterval * Math.pow(2, this.state.reconnectAttempts), 30000);
                setTimeout(() => {
                    this.state.reconnectAttempts++;
                    console.log(`Reconnect attempt ${this.state.reconnectAttempts}`);
                    this.setupWebSocket();
                }, delay);
            } else {
                console.log('Max reconnect attempts reached');
                this.updateStatus('Disconnected. Please refresh the page.', 'disconnected');
            }
        };

        this.chatSocket.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.updateStatus('Connection error', 'disconnected');
            this.state.connected = false;
            this.updateUI();
        };
    },

    startHeartbeat: function() {
        this.stopHeartbeat();
        this.state.heartbeatTimer = setInterval(() => {
            if (this.chatSocket.readyState === WebSocket.OPEN) {
                this.sendSocketMessage({ type: 'ping' });
                if (new Date() - this.state.lastActivity > 120000) {
                    console.log('Tab inactive - reducing heartbeat frequency');
                    this.stopHeartbeat();
                    this.state.heartbeatTimer = setInterval(() => {
                        if (this.chatSocket.readyState === WebSocket.OPEN) {
                            this.sendSocketMessage({ type: 'ping' });
                        }
                    }, 60000);
                }
            }
        }, this.config.heartbeatInterval);
    },

    stopHeartbeat: function() {
        if (this.state.heartbeatTimer) {
            clearInterval(this.state.heartbeatTimer);
            this.state.heartbeatTimer = null;
        }
    },

    sendSocketMessage: function(message) {
        if (this.chatSocket && this.chatSocket.readyState === WebSocket.OPEN) {
            try {
                if (message.userId) message.userId = parseInt(message.userId);
                if (message.target) message.target = parseInt(message.target);
                this.chatSocket.send(JSON.stringify(message));
                return true;
            } catch (e) {
                console.error('Error sending message:', e);
                return false;
            }
        } else {
            console.warn('Cannot send message - WebSocket not connected');
            return false;
        }
    },

    handleNewUser: function(data) {
        if (data.userId && !this.state.users[data.userId]) {
            this.state.users[data.userId] = {
                id: data.userId,
                name: `User ${data.userId.substring(0, 6)}`,
                lastMessage: '',
                unread: 0,
                online: true,
                lastActive: new Date().toLocaleString()
            };
            this.updateUserList();
        }
    },

    handleIncomingMessage: function(data) {
        const userId = data.userId;
        if (!this.state.users[userId]) {
            this.state.users[userId] = {
                id: userId,
                name: `User ${userId.substring(0, 6)}`,
                lastMessage: data.content,
                unread: 0,
                online: true,
                lastActive: new Date().toLocaleString()
            };
        }
        this.processIncomingMessage(data, userId);
    },

    processIncomingMessage: function(data, userId) {
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        if (!this.state.userChats[userId]) {
            this.state.userChats[userId] = [];
        }

        this.state.userChats[userId].push({
            sender: 'user',
            text: data.content,
            time: timestamp
        });

        sessionStorage.setItem('userChats', JSON.stringify(this.state.userChats));

        this.state.users[userId].lastMessage = data.content;
        this.state.users[userId].lastActive = new Date().toLocaleString();

        if (userId !== this.state.activeUserId) {
            this.state.users[userId].unread += 1;
            this.showNotification(`User ${this.state.users[userId].name}: ${data.content}`);
        }

        if (!this.state.activeUserId) {
            this.state.activeUserId = userId;
            this.loadChatHistory(userId);
            this.updateUserDetails(userId);
        } else if (userId === this.state.activeUserId) {
            this.addMessageToUI(data.content, 'user', timestamp);
        }

        this.updateUserList();
        this.updateUI();
    },

    loadChatHistory: function(userId) {
        if (!this.elements.chatMessages) return;
        
        if (!userId) {
            this.elements.chatMessages.innerHTML = `
                <div class="no-messages">
                    <p>No conversation selected</p>
                </div>
            `;
            return;
        }

        this.elements.chatMessages.innerHTML = '';
        const messages = this.state.userChats[userId] || [];
        
        if (messages.length === 0) {
            this.elements.chatMessages.innerHTML = `
                <div class="no-messages">
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }

        messages.forEach(msg => {
            this.addMessageToUI(msg.text, msg.sender, msg.time);
        });

        this.elements.chatMessages.scrollTop = this.elements.chatMessages.scrollHeight;
    },

    sendMessage: function() {
        if (!this.elements.messageInput) return;
        
        let message = this.elements.messageInput.value.trim();
        message = message.replace(/<[^>]*>/g, '');
        
        if (!message || !this.state.activeUserId || !this.state.connected) {
            return;
        }

        const userId = this.state.activeUserId;
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (!this.state.userChats[userId]) {
            this.state.userChats[userId] = [];
        }

        this.state.userChats[userId].push({
            sender: 'admin',
            text: message,
            time: timestamp
        });

        sessionStorage.setItem('userChats', JSON.stringify(this.state.userChats));

        this.state.users[userId].lastMessage = message;

        const sendSuccess = this.sendSocketMessage({
            type: 'message',
            content: message,
            sender: 'admin',
            target: parseInt(userId)
        });

        if (sendSuccess) {
            this.addMessageToUI(message, 'admin', timestamp);
            this.elements.messageInput.value = '';
            this.elements.messageInput.focus();
            this.updateUserList();
        } else {
            this.addMessageToUI('Failed to send message. Trying to reconnect...', 'system', timestamp);
            this.setupWebSocket();
        }
    },

    fetchUserProfile: function(userId) {
        // Simplified user profile (no backend call)
        this.state.userProfiles[userId] = {
            name: `User ${userId.substring(0, 6)}`,
            email: 'Unknown',
            phone: 'Unknown',
            messageCount: this.state.userChats[userId]?.length || 0,
            lastActive: new Date().toLocaleString(),
            createdAt: new Date().toLocaleString()
        };
        this.state.users[userId].name = this.state.userProfiles[userId].name;
        this.updateUserList();
        if (userId === this.state.activeUserId) {
            this.updateUserDetails(userId);
        }
    },

    updateUserDetails: function(userId) {
        if (!this.elements.userDetailsContent) return;
        
        if (!userId) {
            this.elements.userDetailsContent.innerHTML = 
                '<div class="no-user-selected">Select a user to view details</div>';
            return;
        }

        const user = this.state.userProfiles[userId] || {};
        const userObj = this.state.users[userId] || {};
        
        this.elements.userDetailsContent.innerHTML = `
            <div class="user-info">
                <div class="user-info-item">
                    <div class="user-info-label">ID</div>
                    <div class="user-info-value">${userId}</div>
                </div>
                <div class="user-info-item">
                    <div class="user-info-label">Name</div>
                    <div class="user-info-value">${user.name || 'User ' + userId}</div>
                </div>
                <div class="user-info-item">
                    <div class="user-info-label">Email</div>
                    <div class="user-info-value">${user.email || 'Unknown'}</div>
                </div>
                <div class="user-info-item">
                    <div class="user-info-label">Status</div>
                    <div class="user-info-value">
                        <span style="color:${userObj.online ? 'var(--success-color)' : 'var(--danger-color)'}">
                            ${userObj.online ? 'Online' : 'Offline'}
                        </span>
                    </div>
                </div>
                <div class="user-info-item">
                    <div class="user-info-label">Last Active</div>
                    <div class="user-info-value">${user.lastActive || 'Unknown'}</div>
                </div>
                <div class="user-info-item">
                    <div class="user-info-label">Message Count</div>
                    <div class="user-info-value">${user.messageCount || 0}</div>
                </div>
            </div>
        `;
    },

    addMessageToUI: function(message, sender, timestamp) {
        if (!this.elements.chatMessages) return;
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.innerHTML = `
            <div class="sender">${sender === 'admin' ? 'Admin' : 'User'}</div>
            <div>${message}</div>
            <div class="timestamp">${timestamp}</div>
        `;
        this.elements.chatMessages.appendChild(messageDiv);
        this.elements.chatMessages.scrollTop = this.elements.chatMessages.scrollHeight;
    },

    showNotification: function(message) {
        if (this.elements.notification && this.elements.notificationText) {
            this.elements.notificationText.textContent = message;
            this.elements.notification.classList.add('show');
            setTimeout(() => this.elements.notification.classList.remove('show'), 3000);
            if (Notification.permission === 'granted') {
                new Notification('New Message', { body: message });
            }
        }
    },

    updateUI: function() {
        if (this.elements.messageInput && this.elements.sendBtn) {
            this.elements.messageInput.disabled = !this.state.connected || !this.state.activeUserId;
            this.elements.sendBtn.disabled = !this.state.connected || !this.state.activeUserId;
        }
        this.updateUserList();
        this.updateStatus(
            this.state.connected ? 'Connected to chat server' : 'Disconnected',
            this.state.connected ? 'connected' : 'disconnected'
        );
    },

    loadInitialUsers: function() {
        // Load any existing users from sessionStorage or WebSocket
        Object.keys(this.state.userChats).forEach(userId => {
            if (!this.state.users[userId]) {
                this.state.users[userId] = {
                    id: userId,
                    name: `User ${userId.substring(0, 6)}`,
                    lastMessage: this.state.userChats[userId]?.slice(-1)[0]?.text || '',
                    unread: 0,
                    online: false,
                    lastActive: new Date().toLocaleString()
                };
                this.fetchUserProfile(userId);
            }
        });
        this.updateUserList();
    },

    updateUserList: function() {
        const users = Object.values(this.state.users);
        if (this.elements.userCount) {
            this.elements.userCount.textContent = `(${users.length})`;
        }

        if (users.length === 0) {
            if (this.elements.userList) {
                this.elements.userList.innerHTML = '<div class="no-users">No active users</div>';
            }
            return;
        }

        if (this.elements.userList) {
            this.elements.userList.innerHTML = '';
            users.forEach(user => {
                const userItem = document.createElement('div');
                userItem.className = 'chat-user-item';
                if (user.id === this.state.activeUserId) {
                    userItem.classList.add('active');
                }
                userItem.dataset.userId = user.id;

                userItem.innerHTML = `
                    <div class="user-id">
                        <i class="fas fa-user"></i> 
                        ${user.name}
                        ${user.online ? '<span class="user-status">Online</span>' : ''}
                    </div>
                    <div class="last-message">${user.lastMessage || 'No messages yet'}</div>
                    ${user.unread > 0 ? `<div class="unread-count">${user.unread}</div>` : ''}
                `;

                userItem.addEventListener('click', () => {
                    this.state.activeUserId = user.id;
                    user.unread = 0;
                    this.updateUserList();
                    this.loadChatHistory(user.id);
                    this.updateUserDetails(user.id);
                    this.updateUI();
                });

                this.elements.userList.appendChild(userItem);
            });
        }
    },

    broadcastAdminStatus: function(online) {
        this.sendSocketMessage({
            type: 'status',
            status: online ? 'online' : 'offline',
            adminCount: 1
        });
    }
};

// Initialize chat only on the chat page
document.addEventListener('DOMContentLoaded', () => {
    ChatApp.init();
});