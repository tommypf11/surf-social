# Surf Social WebSocket Server

A simple WebSocket server for the Surf Social WordPress plugin. This provides a fallback real-time communication option when Pusher is not available.

## Installation

1. Install Node.js (v14 or higher)
2. Navigate to the websocket-server directory
3. Install dependencies:

```bash
npm install
```

## Running the Server

### Development Mode

```bash
npm run dev
```

This will start the server with auto-restart on file changes using nodemon.

### Production Mode

```bash
npm start
```

Or directly:

```bash
node server.js
```

The server will start on port 8080 by default. You can change this by setting the `PORT` environment variable:

```bash
PORT=3000 node server.js
```

## Configuration

In your WordPress admin panel:

1. Go to **Settings > Surf Social**
2. Uncheck "Use Pusher"
3. Enter your WebSocket URL: `ws://your-domain.com:8080`
4. Save changes

For production use, you'll want to:

- Use a reverse proxy (nginx/Apache) with SSL
- Set up a process manager like PM2
- Configure firewall rules
- Use `wss://` (secure WebSocket) instead of `ws://`

## Production Deployment

### Using PM2

```bash
# Install PM2 globally
npm install -g pm2

# Start the server
pm2 start server.js --name surf-social-ws

# Save PM2 configuration
pm2 save

# Setup PM2 to start on boot
pm2 startup
```

### Using Docker

Create a `Dockerfile`:

```dockerfile
FROM node:18-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install --production

COPY server.js .

EXPOSE 8080

CMD ["node", "server.js"]
```

Build and run:

```bash
docker build -t surf-social-ws .
docker run -p 8080:8080 surf-social-ws
```

## Features

- Real-time bidirectional communication
- Automatic client management
- Broadcast to all connected clients
- Ping/pong heartbeat support
- Graceful shutdown handling
- Simple and lightweight

## Security Notes

This is a basic WebSocket server. For production use, consider adding:

- Authentication/authorization
- Rate limiting
- Message validation
- CORS configuration
- SSL/TLS encryption
- Message persistence (optional)

## Troubleshooting

### Port Already in Use

If port 8080 is already in use, change it:

```bash
PORT=3000 node server.js
```

### Connection Refused

- Check firewall settings
- Ensure the server is running
- Verify the WebSocket URL in WordPress settings
- Check browser console for connection errors

### High Memory Usage

The server stores all connected clients in memory. For high-traffic sites, consider:

- Using a message queue (Redis/RabbitMQ)
- Implementing connection limits
- Using a load balancer with multiple instances

## License

MIT

