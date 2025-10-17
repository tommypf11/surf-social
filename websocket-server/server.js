/**
 * Surf Social WebSocket Server (Fallback)
 * Simple WebSocket server for real-time communication
 */

const WebSocket = require('ws');
const http = require('http');

const PORT = process.env.PORT || 8080;

// Create HTTP server
const server = http.createServer();

// Create WebSocket server
const wss = new WebSocket.Server({ 
    server,
    perMessageDeflate: false 
});

// Store connected clients
const clients = new Map();

// Broadcast to all clients except sender
function broadcast(data, senderId) {
    const message = JSON.stringify(data);
    
    clients.forEach((client, id) => {
        if (id !== senderId && client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

// Broadcast to all clients
function broadcastAll(data) {
    const message = JSON.stringify(data);
    
    clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

// Handle new connection
wss.on('connection', (ws, req) => {
    const clientId = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    clients.set(clientId, ws);
    
    console.log(`Client connected: ${clientId} (Total: ${clients.size})`);
    
    // Send welcome message
    ws.send(JSON.stringify({
        type: 'connected',
        clientId: clientId
    }));
    
    // Handle incoming messages
    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            
            // Ignore ping messages
            if (data.type === 'ping') {
                ws.send(JSON.stringify({ type: 'pong' }));
                return;
            }
            
            // Add client ID to data
            data.clientId = clientId;
            
            // Broadcast to all other clients
            broadcast(data, clientId);
            
            console.log(`Message from ${clientId}:`, data.type);
            
        } catch (error) {
            console.error('Error parsing message:', error);
        }
    });
    
    // Handle client disconnect
    ws.on('close', () => {
        clients.delete(clientId);
        console.log(`Client disconnected: ${clientId} (Total: ${clients.size})`);
        
        // Broadcast user left event
        broadcastAll({
            type: 'user-left',
            clientId: clientId
        });
    });
    
    // Handle errors
    ws.on('error', (error) => {
        console.error(`WebSocket error for ${clientId}:`, error);
    });
});

// Start server
server.listen(PORT, () => {
    console.log(`Surf Social WebSocket Server running on ws://localhost:${PORT}`);
    console.log(`Clients connected: ${clients.size}`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('SIGTERM received, closing server...');
    wss.close(() => {
        server.close(() => {
            console.log('Server closed');
            process.exit(0);
        });
    });
});

process.on('SIGINT', () => {
    console.log('SIGINT received, closing server...');
    wss.close(() => {
        server.close(() => {
            console.log('Server closed');
            process.exit(0);
        });
    });
});

