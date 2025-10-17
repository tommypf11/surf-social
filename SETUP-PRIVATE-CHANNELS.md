# Private Channel Setup Guide

## 🔧 Step-by-Step Setup

### Step 1: Get Pusher Credentials

1. Go to [dashboard.pusher.com](https://dashboard.pusher.com)
2. Select your app
3. Go to **App Keys** tab
4. Copy:
   - **Key** (e.g., `c08d09b4013a00d6a626`)
   - **Secret** (e.g., `your-secret-key-here`)
   - **Cluster** (e.g., `us3`)

### Step 2: Configure WordPress

1. Go to **Settings → Surf Social** in WordPress admin
2. Fill in:
   - ✅ **Enable Surf Social**: Checked
   - ✅ **Use Pusher**: Checked
   - **Pusher Key**: `c08d09b4013a00d6a626`
   - **Pusher Secret**: `your-secret-key-here`
   - **Pusher Cluster**: `us3`
3. Click **Save Changes**

### Step 3: Enable Client Events

1. Go to [dashboard.pusher.com](https://dashboard.pusher.com)
2. Select your app
3. Go to **Settings → App Settings**
4. Under **Client Events**, check **"Enable client events"**
5. Click **Update**

### Step 4: Test

1. **Clear browser cache** (Ctrl+F5)
2. **Open your site in two browsers**
3. **Open console** (F12) and look for:
   - ✅ "Surf Social initialized"
   - ✅ "Pusher connected successfully!"
   - ✅ "Subscribed to private-surf-social channel"
   - ✅ "Client events are working!"

## 🧪 Testing Checklist

### Console Messages (Should See):
```
Surf Social initialized for user: Guest 1234 (Guest)
Pusher state changed: initialized -> connecting
Pusher state changed: connecting -> connected
✅ Pusher connected successfully!
✅ Subscribed to private-surf-social channel
✅ Client events are working!
Starting cursor tracking...
Broadcasting presence for: Guest 1234
```

### Test Messages:
1. **Send message** in Browser 1
2. **Should appear instantly** in Browser 2
3. **No refresh needed**

### Test Cursors:
1. **Move mouse** in Browser 1
2. **Should see cursor** in Browser 2
3. **Should see name pill** with user's name

## 🐛 Troubleshooting

### Error: "Client events failed"
**Solution**: Enable client events in Pusher dashboard (Step 3 above)

### Error: "Pusher secret not configured"
**Solution**: Add your Pusher secret in WordPress settings (Step 2 above)

### Error: "Cannot broadcast client event"
**Solution**: Make sure you're using private channels and authentication is working

### Messages don't appear instantly:
**Solution**: Check console for "✅ Message broadcasted via Pusher"

### Cursors don't show:
**Solution**: Check console for "Received cursor move from: [Name]"

## 🔍 Debug Mode

The plugin runs in debug mode, so you'll see detailed console logs:

- **Connection status**: Shows Pusher connection states
- **Channel subscription**: Shows when channel is subscribed
- **Client events**: Shows if client events are working
- **Message flow**: Shows message sending and receiving
- **Cursor tracking**: Shows cursor movement and updates

## ✅ Success Indicators

When everything is working correctly, you should see:

1. **Instant messaging** - Messages appear immediately without refresh
2. **Live cursors** - Mouse movements show in real-time
3. **User presence** - See who's online in avatar dock
4. **No errors** - Clean console with success messages

## 🚀 Next Steps

Once private channels are working:

1. **Test with multiple users**
2. **Customize colors** in `assets/js/surf-social.js`
3. **Add custom styling** in `assets/css/surf-social.css`
4. **Monitor performance** in browser DevTools

## 📞 Support

If you're still having issues:

1. **Check console logs** for specific error messages
2. **Verify Pusher credentials** are correct
3. **Test with simple-test.html** first
4. **Contact support** with specific error messages

---

**Private channels with authentication = True real-time messaging! 🚀**
