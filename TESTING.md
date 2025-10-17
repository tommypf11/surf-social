# tester Surf Social - Testing Guide - test test

This guide helps you test all features of Surf Social to ensure everything works correctly.

## 🧪 Pre-Testing Checklist

Before testing, ensure:
- [ ] WordPress 5.0+ installed
- [ ] PHP 7.4+ installed
- [ ] Plugin activated
- [ ] Settings configured (Pusher or WebSocket)
- [ ] Two browsers available (or incognito mode)
- [ ] Browser console open (F12)

## 🔍 Test Scenarios

### Test 1: Plugin Installation

**Objective**: Verify plugin installs correctly

**Steps**:
1. Upload plugin via WordPress admin
2. Click "Activate"
3. Check for activation message

**Expected Results**:
- ✅ Plugin activates without errors
- ✅ Database table created
- ✅ Settings page accessible

**Verify**:
- Go to **Plugins** - should show "Active"
- Check database for `wp_surf_social_messages` table
- Go to **Settings > Surf Social** - page should load

---

### Test 2: Settings Configuration

**Objective**: Verify settings save correctly

**Steps**:
1. Go to **Settings > Surf Social**
2. Enter Pusher credentials (or WebSocket URL)
3. Click "Save Changes"

**Expected Results**:
- ✅ Settings saved successfully
- ✅ Success message displayed
- ✅ Values persist after page reload

**Verify**:
- Reload settings page
- Check values are still there
- Check browser console for no errors

---

### Test 3: Chat Widget Display

**Objective**: Verify chat UI appears on frontend

**Steps**:
1. Open your site in a browser
2. Look for chat button in bottom-right
3. Click the chat button

**Expected Results**:
- ✅ Chat toggle button visible
- ✅ Button has chat icon
- ✅ Chat drawer slides in from right
- ✅ Drawer has dark gray background
- ✅ Three tabs visible (Web Chat, Friend Chat, Support)

**Verify**:
- Check console for no errors
- Verify drawer animation is smooth
- Check responsive on mobile

---

### Test 4: Real-Time Connection

**Objective**: Verify Pusher/WebSocket connects

**Steps**:
1. Open site in two browsers
2. Open browser console (F12)
3. Look for connection messages

**Expected Results**:
- ✅ Console shows "Surf Social initialized"
- ✅ Console shows "Pusher connected" or "WebSocket connected"
- ✅ No connection errors

**Verify**:
- Check for connection errors
- Verify heartbeat/ping messages
- Check connection is stable

---

### Test 5: Cursor Tracking

**Objective**: Verify cursors display and move

**Steps**:
1. Open site in Browser A
2. Open site in Browser B (same page)
3. Move mouse in Browser A
4. Watch Browser B

**Expected Results**:
- ✅ Cursor appears in Browser B
- ✅ Name pill shows user's name
- ✅ Cursor color is unique
- ✅ Cursor moves smoothly
- ✅ Cursor has soft glow effect

**Verify**:
- Check cursor updates are smooth (not laggy)
- Verify name pill color matches user
- Check cursor disappears when user leaves

---

### Test 6: Avatar Chips Dock

**Objective**: Verify user list displays correctly

**Steps**:
1. Open site with multiple users
2. Look below chat drawer
3. Check avatar chips dock

**Expected Results**:
- ✅ Dock appears with user avatars
- ✅ Each avatar has user's initial
- ✅ Avatars have unique colors
- ✅ "+N" indicator shows if more users
- ✅ Unread dot appears on first avatar
- ✅ Hide button works

**Verify**:
- Click hide button - dock should slide away
- Refresh page - dock should return
- Check avatar colors match cursor colors

---

### Test 7: Sending Messages

**Objective**: Verify messages send successfully

**Steps**:
1. Open chat in Browser A
2. Type a message
3. Press Enter or click Send
4. Check message appears

**Expected Results**:
- ✅ Message appears in chat
- ✅ Message is right-aligned (own message)
- ✅ Avatar shows user's initial
- ✅ Timestamp displays
- ✅ Input field clears

**Verify**:
- Check console for no errors
- Verify message is saved to database
- Check message formatting is correct

---

### Test 8: Receiving Messages

**Objective**: Verify messages appear in real-time

**Steps**:
1. Open chat in Browser A
2. Open chat in Browser B
3. Send message from Browser A
4. Watch Browser B

**Expected Results**:
- ✅ Message appears in Browser B instantly
- ✅ Message is left-aligned (other user)
- ✅ Avatar shows sender's initial
- ✅ Timestamp displays
- ✅ Smooth animation (slide in)

**Verify**:
- Check no delay in message delivery
- Verify message order is correct
- Check multiple messages work

---

### Test 9: Message History

**Objective**: Verify pagination works

**Steps**:
1. Send 25+ messages
2. Scroll to top of chat
3. Click "Load More"
4. Check older messages appear

**Expected Results**:
- ✅ Older messages load
- ✅ Messages appear at top
- ✅ "Load More" button still visible if more exist
- ✅ Button disappears when all loaded

**Verify**:
- Check message order (oldest at top)
- Verify no duplicate messages
- Check loading state displays

---

### Test 10: Tab Switching

**Objective**: Verify chat channels work

**Steps**:
1. Click "Friend Chat" tab
2. Send a message
3. Click "Web Chat" tab
4. Check messages

**Expected Results**:
- ✅ Tab switches smoothly
- ✅ Active tab is highlighted
- ✅ Title updates
- ✅ Messages load for selected channel
- ✅ Messages are channel-specific

**Verify**:
- Check all three tabs work
- Verify messages don't mix between channels
- Check tab styling (active state)

---

### Test 11: Unread Notifications

**Objective**: Verify unread badges work

**Steps**:
1. Close chat in Browser A
2. Send message from Browser B
3. Check Browser A

**Expected Results**:
- ✅ Unread badge appears on chat button
- ✅ Badge shows count
- ✅ Badge appears on avatar dock
- ✅ Badge clears when chat opened

**Verify**:
- Check badge updates correctly
- Verify badge shows correct count
- Check badge disappears when reading

---

### Test 12: User Join/Leave

**Objective**: Verify presence updates

**Steps**:
1. User A opens site
2. User B opens site
3. User A closes browser
4. Check User B

**Expected Results**:
- ✅ User B's cursor appears for User A
- ✅ Avatar appears in dock for User A
- ✅ Cursor disappears when User B leaves
- ✅ Avatar removed from dock

**Verify**:
- Check cleanup happens automatically
- Verify no ghost cursors
- Check dock updates correctly

---

### Test 13: Responsive Design

**Objective**: Verify mobile compatibility

**Steps**:
1. Open site on mobile device
2. Or resize browser to mobile width
3. Test all features

**Expected Results**:
- ✅ Chat drawer full width on mobile
- ✅ Buttons are touch-friendly
- ✅ Text is readable
- ✅ No horizontal scrolling
- ✅ All features work

**Verify**:
- Test on actual mobile device
- Check different screen sizes
- Verify touch interactions work

---

### Test 14: Error Handling

**Objective**: Verify graceful degradation

**Steps**:
1. Disable real-time transport
2. Try to send message
3. Check behavior

**Expected Results**:
- ✅ No JavaScript errors
- ✅ User sees helpful message
- ✅ Site doesn't break
- ✅ Can still use other features

**Verify**:
- Check console for no errors
- Verify error messages are user-friendly
- Check recovery when connection restored

---

### Test 15: Performance

**Objective**: Verify performance is acceptable

**Steps**:
1. Open site
2. Monitor browser DevTools
3. Check CPU and memory usage
4. Test with 10+ users

**Expected Results**:
- ✅ Page loads quickly
- ✅ CPU usage is low
- ✅ Memory usage is reasonable
- ✅ No lag or stuttering
- ✅ Smooth animations

**Verify**:
- Check Network tab for asset sizes
- Monitor Performance tab
- Check for memory leaks
- Verify throttling works

---

### Test 16: Security

**Objective**: Verify security measures

**Steps**:
1. Try XSS attack (inject script in message)
2. Try SQL injection
3. Check nonce validation
4. Test permissions

**Expected Results**:
- ✅ Scripts are escaped
- ✅ SQL injection prevented
- ✅ Nonces are validated
- ✅ Unauthorized access blocked

**Verify**:
- Check input is sanitized
- Verify output is escaped
- Check database queries use prepared statements
- Test with different user roles

---

### Test 17: Uninstall

**Objective**: Verify clean uninstall

**Steps**:
1. Deactivate plugin
2. Delete plugin files
3. Check database

**Expected Results**:
- ✅ Database table removed
- ✅ Options removed
- ✅ No leftover files
- ✅ Site works normally

**Verify**:
- Check database for table
- Verify options are gone
- Check for no errors
- Test site functionality

---

## 🐛 Common Issues

### Issue: Chat Not Appearing

**Symptoms**: No chat button visible

**Solutions**:
1. Check plugin is enabled in Settings
2. Clear browser cache
3. Check browser console for errors
4. Verify you're on frontend (not admin)

### Issue: Cursors Not Moving

**Symptoms**: Cursors don't update

**Solutions**:
1. Check real-time transport is configured
2. Verify both users on same page
3. Check browser console for connection errors
4. Try refreshing both pages

### Issue: Messages Not Sending

**Symptoms**: Can type but can't send

**Solutions**:
1. Check REST API is enabled
2. Try logging out and back in
3. Check database table exists
4. Verify nonce is valid

### Issue: Connection Failed

**Symptoms**: "Connection failed" in console

**Solutions**:
1. Check Pusher/WebSocket credentials
2. Verify server is running (WebSocket)
3. Check firewall settings
4. Try different cluster (Pusher)

---

## 📊 Test Results Template

```
Test Date: _______________
Tester: _______________
Environment: _______________

Test # | Feature | Status | Notes
-------|---------|--------|------
1      | Installation | ⬜ | 
2      | Settings | ⬜ | 
3      | Chat Widget | ⬜ | 
4      | Connection | ⬜ | 
5      | Cursors | ⬜ | 
6      | Avatar Dock | ⬜ | 
7      | Send Message | ⬜ | 
8      | Receive Message | ⬜ | 
9      | History | ⬜ | 
10     | Tabs | ⬜ | 
11     | Notifications | ⬜ | 
12     | Join/Leave | ⬜ | 
13     | Responsive | ⬜ | 
14     | Error Handling | ⬜ | 
15     | Performance | ⬜ | 
16     | Security | ⬜ | 
17     | Uninstall | ⬜ | 

Overall Status: ⬜ Pass ⬜ Fail
```

---

## ✅ Acceptance Criteria

Plugin is ready for production when:

- [ ] All 17 tests pass
- [ ] No console errors
- [ ] No PHP errors
- [ ] Performance is acceptable
- [ ] Security measures verified
- [ ] Mobile responsive
- [ ] Works with Pusher
- [ ] Works with WebSocket
- [ ] Clean uninstall

---

## 📝 Reporting Issues

If you find issues:

1. Note the test number
2. Describe the problem
3. Include browser/device info
4. Include console errors
5. Take screenshots if needed
6. Report to support@surfsocial.com

---

**Happy Testing! 🧪**

