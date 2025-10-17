# test Surf Social - WordPress Plugin

Real-time presence and global chat for WordPress. See who's on your site and chat with them instantly.

![Surf Social](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)

## Features

### 🎯 Real-Time Presence
- See other users' cursors on the page with colored name pills
- Visual indicators showing who's currently browsing
- Smooth cursor tracking with soft glow effects

### 💬 Global Chat
- Right-edge chat drawer with modern UI
- Three chat channels: Web Chat, Friend Chat, and Support
- Message history with pagination
- Real-time message delivery
- Unread message notifications

### ⚡ Real-Time Transport
- **Pusher Integration** (Recommended) - Easy setup with Pusher's hosted service
- **WebSocket Fallback** - Self-hosted option for complete control
- Automatic reconnection and heartbeat

### 🎨 Beautiful UI/UX
- Rounded chat drawer matching modern design standards
- Avatar chips dock with user indicators
- Pill-style name tags with soft glows
- Responsive design for mobile and desktop
- Smooth animations and transitions

## Screenshots

The plugin provides a sleek, dark-themed chat interface that docks to the right edge of your site, with real-time cursor tracking and presence indicators throughout the page.

## Installation

### Method 1: Upload via WordPress Admin

1. Download the `surf-social` folder
2. Zip the entire folder
3. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download the `surf-social` folder
2. Upload the entire folder to `/wp-content/plugins/` directory
3. Activate the plugin through the **Plugins** menu in WordPress

## Configuration

### Using Pusher (Recommended)

1. Go to **Settings > Surf Social** in your WordPress admin
2. Sign up for a free Pusher account at [pusher.com](https://pusher.com)
3. Create a new app in your Pusher dashboard
4. Copy your **App Key** and **Cluster** (e.g., us2, eu, ap1)
5. Enter your Pusher credentials in the plugin settings
6. Enable "Use Pusher"
7. Click **Save Changes**

### Using WebSocket Fallback

1. Install Node.js (v14 or higher) on your server
2. Navigate to the `websocket-server` directory in the plugin folder
3. Run:
   ```bash
   npm install
   npm start
   ```
4. In WordPress admin, go to **Settings > Surf Social**
5. Uncheck "Use Pusher"
6. Enter your WebSocket URL: `ws://your-domain.com:8080`
7. Click **Save Changes**

For production use, see the [WebSocket Server README](websocket-server/README.md) for deployment instructions.

## Usage

Once activated and configured, Surf Social will automatically:

- Display the chat toggle button in the bottom-right corner
- Show real-time cursor tracking for all visitors
- Enable global chat across your site
- Track user presence and display avatar chips

### For Site Visitors

- **Open Chat**: Click the chat button in the bottom-right
- **Send Messages**: Type in the input field and press Enter or click Send
- **Switch Channels**: Click the tabs (Web Chat, Friend Chat, Support)
- **View History**: Scroll up in the chat to see older messages
- **Load More**: Click "Load More" to see additional message history

### For Administrators

- Configure real-time transport in **Settings > Surf Social**
- Enable/disable the plugin
- View message history (stored in database)
- Customize Pusher or WebSocket settings

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Browser**: Modern browser with WebSocket support (Chrome, Firefox, Safari, Edge)

## Database

The plugin creates one database table:

- `wp_surf_social_messages` - Stores chat message history

This table is automatically created on plugin activation and removed on uninstallation.

## API Endpoints

The plugin exposes the following REST API endpoints:

- `GET /wp-json/surf-social/v1/chat/messages` - Retrieve chat messages
- `POST /wp-json/surf-social/v1/chat/messages` - Send a new message

## Customization

### Colors

User colors are automatically assigned based on user ID. You can customize the color palette by modifying the `colors` array in `assets/js/surf-social.js`:

```javascript
const colors = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
    '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B739', '#52B788'
];
```

### Styling

The plugin uses CSS variables for easy customization. Edit `assets/css/surf-social.css`:

```css
:root {
    --surf-primary: #2C3E50;
    --surf-secondary: #34495E;
    --surf-accent: #3498DB;
    /* ... more variables */
}
```

## Troubleshooting

### Chat Not Appearing

1. Check that the plugin is enabled in **Settings > Surf Social**
2. Verify your real-time transport is configured (Pusher or WebSocket)
3. Check browser console for JavaScript errors
4. Ensure you're not logged in as an admin with the plugin disabled

### Cursors Not Showing

1. Verify real-time connection is working (check browser console)
2. Make sure multiple users are on the same page
3. Check if WebSocket or Pusher is properly configured
4. Try refreshing the page

### Messages Not Sending

1. Check REST API is enabled (WordPress 4.4+)
2. Verify nonce is valid (try logging out and back in)
3. Check browser console for API errors
4. Ensure database table was created properly

### WebSocket Connection Failed

1. Verify the WebSocket server is running
2. Check firewall allows the WebSocket port
3. For production, use `wss://` (secure WebSocket) with SSL
4. Check server logs for connection errors

## Performance

- **Lightweight**: Minimal impact on page load times
- **Efficient**: Throttled cursor updates (50ms intervals)
- **Scalable**: Works with Pusher's infrastructure or your own WebSocket server
- **Optimized**: Automatic cleanup of disconnected users

## Security

- All user input is sanitized
- REST API uses WordPress nonces
- SQL queries use prepared statements
- No sensitive data exposed to frontend
- CORS handled by WordPress

## Roadmap

Future versions may include:

- [ ] Private messaging between users
- [ ] File attachments in chat
- [ ] Message reactions and emojis
- [ ] User mentions (@username)
- [ ] Chat moderation tools
- [ ] Analytics dashboard
- [ ] Customizable chat channels
- [ ] Integration with WooCommerce

## Support

For issues, questions, or contributions:

- Create an issue on GitHub
- Email: support@surfsocial.com
- Documentation: [docs.surfsocial.com](https://docs.surfsocial.com)

## Changelog

### Version 1.0.0 (2024)

- Initial release
- Real-time cursor tracking
- Global chat with history
- Pusher integration
- WebSocket fallback
- Three chat channels
- Message pagination
- Unread notifications

## Credits

Built with ❤️ by the Surf Social team.

Powered by:
- [Pusher](https://pusher.com) - Real-time infrastructure
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [WebSocket API](https://developer.mozilla.org/en-US/docs/Web/API/WebSocket)

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Surf Social

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Uninstallation

To completely remove Surf Social:

1. Deactivate the plugin in **Plugins**
2. Delete the plugin files
3. The `wp_surf_social_messages` table will be removed automatically

To manually remove the database table:

```sql
DROP TABLE IF EXISTS wp_surf_social_messages;
```

---

**Made for WordPress. Built for real-time engagement.** 🏄‍♂️

