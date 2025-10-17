# Surf Social - Installation Guide

## Quick Start (5 Minutes)

### Option 1: Using Pusher (Recommended)

1. **Install the Plugin**
   - Download the `surf-social` folder
   - Zip it and upload via WordPress admin: **Plugins > Add New > Upload Plugin**
   - Activate the plugin

2. **Get Pusher Credentials**
   - Go to [pusher.com](https://pusher.com) and sign up (free tier available)
   - Create a new app in your dashboard
   - Copy your **App Key** and **Cluster** (e.g., us2, eu, ap1)

3. **Configure in WordPress**
   - Go to **Settings > Surf Social**
   - Paste your Pusher Key and Cluster
   - Make sure "Use Pusher" is checked
   - Click **Save Changes**

4. **Test It**
   - Open your site in two different browsers (or incognito windows)
   - You should see cursors and be able to chat!

### Option 2: Using WebSocket Fallback

1. **Install the Plugin**
   - Same as above

2. **Set Up WebSocket Server**
   ```bash
   cd surf-social/websocket-server
   npm install
   npm start
   ```

3. **Configure in WordPress**
   - Go to **Settings > Surf Social**
   - Uncheck "Use Pusher"
   - Enter WebSocket URL: `ws://localhost:8080`
   - Click **Save Changes**

4. **Test It**
   - Same as above

## Detailed Installation

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Modern web browser

### Step-by-Step Installation

#### 1. Download and Install

**Method A: Via WordPress Admin (Easiest)**
1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the `surf-social.zip` file
5. Click **Install Now**
6. Click **Activate**

**Method B: Via FTP/SFTP**
1. Download and extract the plugin
2. Upload the `surf-social` folder to `/wp-content/plugins/`
3. Log in to WordPress admin
4. Go to **Plugins**
5. Find "Surf Social" and click **Activate**

#### 2. Configure Real-Time Transport

**Using Pusher (Recommended for Most Users)**

Pusher is a hosted service that handles all the real-time infrastructure for you.

1. Sign up at [pusher.com](https://pusher.com) (free tier: 100 concurrent connections, 200,000 messages/day)
2. Click "Create app" or "Channels apps"
3. Fill in:
   - **App name**: Your site name
   - **Cluster**: Choose closest to your users (e.g., us2, eu, ap1)
   - **Front-end tech**: Vanilla JS
4. Go to **App Keys** tab
5. Copy your **Key** and note your **Cluster**
6. In WordPress: **Settings > Surf Social**
7. Paste the key and cluster
8. Check "Use Pusher"
9. Click **Save Changes**

**Using WebSocket (For Self-Hosted)**

If you prefer to host your own real-time server:

1. **Install Node.js**
   - Download from [nodejs.org](https://nodejs.org/)
   - Verify: `node --version` (should be v14+)

2. **Start the WebSocket Server**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/surf-social/websocket-server
   npm install
   npm start
   ```
   You should see: `Surf Social WebSocket Server running on ws://localhost:8080`

3. **Configure in WordPress**
   - Go to **Settings > Surf Social**
   - Uncheck "Use Pusher"
   - Enter: `ws://localhost:8080` (or your domain)
   - Click **Save Changes**

4. **Production Deployment** (Optional)
   - Use PM2: `npm install -g pm2 && pm2 start server.js`
   - Or Docker: See `websocket-server/README.md`
   - For production, use `wss://` with SSL

#### 3. Enable the Plugin

1. Go to **Settings > Surf Social**
2. Make sure "Enable Surf Social" is checked
3. Click **Save Changes**

#### 4. Test the Installation

1. Open your site in two different browsers (or incognito/private windows)
2. You should see:
   - A chat button in the bottom-right corner
   - Your cursor with your name in one browser
   - The other user's cursor in the other browser
3. Click the chat button to open the chat drawer
4. Send a test message
5. Verify it appears in both browsers

## Troubleshooting

### Chat Not Appearing

**Problem**: The chat button doesn't show up

**Solutions**:
1. Check that the plugin is activated
2. Go to **Settings > Surf Social** and verify "Enable Surf Social" is checked
3. Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)
4. Check browser console for errors (F12 > Console)
5. Make sure you're not in WordPress admin (plugin only works on frontend)

### Cursors Not Showing

**Problem**: Can't see other users' cursors

**Solutions**:
1. Verify real-time transport is configured (Pusher or WebSocket)
2. Check browser console for connection errors
3. Make sure both browsers are on the same page
4. Try refreshing both pages
5. For WebSocket: verify the server is running (`npm start`)

### Pusher Connection Failed

**Problem**: "Pusher connection failed" in console

**Solutions**:
1. Verify your Pusher key and cluster are correct
2. Check Pusher dashboard - is your app active?
3. Check your firewall isn't blocking Pusher
4. Try a different cluster (us2, eu, ap1)
5. Check Pusher's status page: [status.pusher.com](https://status.pusher.com)

### WebSocket Connection Failed

**Problem**: "WebSocket connection failed"

**Solutions**:
1. Verify the WebSocket server is running: `npm start`
2. Check the URL is correct (ws://localhost:8080 or your domain)
3. Check firewall allows port 8080
4. For production, ensure you're using `wss://` with SSL
5. Check server logs for errors

### Messages Not Sending

**Problem**: Can type but messages don't send

**Solutions**:
1. Check browser console for API errors
2. Verify REST API is enabled (WordPress 4.4+)
3. Try logging out and back in
4. Check database table exists: `wp_surf_social_messages`
5. Verify file permissions on plugin directory

### High CPU Usage

**Problem**: WebSocket server using too much CPU

**Solutions**:
1. Check number of connected clients (should be manageable)
2. Reduce cursor update frequency in `assets/js/surf-social.js` (line ~150)
3. Consider using Pusher for better scalability
4. Check for console errors causing reconnection loops

## Performance Tips

1. **Use Pusher for Production**: It's optimized and scales automatically
2. **Limit Cursor Updates**: Already throttled to 50ms, but you can increase
3. **Use CDN**: Serve static assets (CSS/JS) from a CDN
4. **Enable Caching**: Use WordPress caching plugins
5. **Database Optimization**: Regularly clean old messages if needed

## Security Considerations

1. **Use HTTPS**: Always use SSL/TLS in production
2. **Use WSS**: For WebSocket, use `wss://` not `ws://`
3. **Firewall**: Only allow necessary ports
4. **Rate Limiting**: Consider adding rate limits for high-traffic sites
5. **Input Validation**: Already handled, but review if customizing

## Next Steps

- Customize colors in `assets/js/surf-social.js`
- Customize styling in `assets/css/surf-social.css`
- Add custom chat channels
- Integrate with your theme
- Set up analytics

## Getting Help

- Check the main [README.md](README.md)
- Review [WebSocket Server README](websocket-server/README.md)
- Check browser console for errors
- Verify all prerequisites are met
- Contact support: support@surfsocial.com

## Uninstallation

To completely remove Surf Social:

1. Deactivate the plugin in **Plugins**
2. Delete the plugin files
3. The database table will be removed automatically

Or manually remove the table:
```sql
DROP TABLE IF EXISTS wp_surf_social_messages;
```

---

**Need help?** Check the main README or contact support!

