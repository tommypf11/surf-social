# Surf Social - Quick Start Guide

Get Surf Social up and running in 5 minutes!

## 🚀 Fastest Setup (Using Pusher)

### 1. Install Plugin (1 minute)
- Download `surf-social.zip`
- WordPress Admin → **Plugins** → **Add New** → **Upload Plugin**
- Upload zip file → **Install Now** → **Activate**

### 2. Get Pusher Account (2 minutes)
- Go to [pusher.com](https://pusher.com)
- Sign up (free tier available)
- Click **Create app**
- Choose cluster (e.g., us2)
- Copy your **App Key**

### 3. Configure Plugin (1 minute)
- WordPress Admin → **Settings** → **Surf Social**
- Paste Pusher Key
- Enter Cluster (e.g., us2)
- Check "Use Pusher"
- Click **Save Changes**

### 4. Test It! (1 minute)
- Open your site in two browsers
- See cursors moving in real-time
- Click chat button → send messages
- ✨ Done!

---

## 🔧 Alternative Setup (WebSocket)

### 1. Install Plugin
Same as above

### 2. Start WebSocket Server
```bash
cd surf-social/websocket-server
npm install
npm start
```

### 3. Configure Plugin
- WordPress Admin → **Settings** → **Surf Social**
- Uncheck "Use Pusher"
- Enter: `ws://localhost:8080`
- Click **Save Changes**

### 4. Test It!
Same as above

---

## 📝 What You Get

✅ **Real-time cursors** - See who's on your site  
✅ **Live chat** - Talk to visitors instantly  
✅ **Three channels** - Web Chat, Friend Chat, Support  
✅ **Message history** - Never lose a conversation  
✅ **Unread badges** - Know when you have messages  
✅ **Beautiful UI** - Modern, dark-themed interface  
✅ **Mobile ready** - Works on all devices  

---

## 🎨 Customization

### Change User Colors
Edit `assets/js/surf-social.js`:
```javascript
const colors = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', 
    // Add your colors here
];
```

### Change Theme Colors
Edit `assets/css/surf-social.css`:
```css
:root {
    --surf-primary: #2C3E50;
    --surf-accent: #3498DB;
    /* Change these */
}
```

---

## 🐛 Troubleshooting

**Chat not showing?**
- Check plugin is enabled in Settings
- Clear browser cache (Ctrl+F5)
- Check browser console for errors

**Cursors not moving?**
- Verify Pusher/WebSocket is configured
- Check both browsers are on same page
- Look for connection errors in console

**Messages not sending?**
- Check REST API is enabled
- Try logging out and back in
- Verify database table exists

---

## 📚 More Info

- **Full Documentation**: See [README.md](README.md)
- **Installation Guide**: See [INSTALLATION.md](INSTALLATION.md)
- **WebSocket Server**: See [websocket-server/README.md](websocket-server/README.md)
- **Support**: support@surfsocial.com

---

## 💡 Pro Tips

1. **Use Pusher for production** - It's optimized and scales automatically
2. **Test in incognito mode** - Easy way to test with multiple users
3. **Customize colors** - Match your brand
4. **Enable on specific pages** - Use conditional logic in your theme
5. **Monitor performance** - Check browser DevTools

---

## 🎯 Next Steps

- [ ] Test with multiple users
- [ ] Customize colors to match your brand
- [ ] Add to your homepage
- [ ] Share with your team
- [ ] Monitor usage and engagement

---

**Need help?** Check the full [README.md](README.md) or [INSTALLATION.md](INSTALLATION.md)

**Enjoying Surf Social?** ⭐ Star us on GitHub!

---

*Made with ❤️ by the Surf Social team*

