# 🔐 WordPress Nonce Guide for Surf Social

## What is a WordPress Nonce?

A **nonce** (Number Used Once) is a WordPress security feature that prevents CSRF (Cross-Site Request Forgery) attacks. It's like a temporary password that WordPress gives to your browser to prove you're authorized to make API calls.

### Key Points:
- **Expires after 24 hours** for security
- **Unique per user session** and action
- **Required for all AJAX and REST API calls** in WordPress
- **Changes every time** you refresh the page

## 🔍 How to Find WordPress Nonces

### Method 1: From WordPress Admin (Recommended)
1. **Log into your WordPress admin**
2. **Go to Settings → Surf Social** (or any admin page)
3. **Open browser developer tools** (F12)
4. **Go to Console tab**
5. **Type one of these commands:**

```javascript
// For REST API calls
console.log(wpApiSettings.nonce);

// For Surf Social specific nonce
console.log(window.surfSocial);

// For AJAX calls
console.log(ajax_object.ajax_nonce);
```

### Method 2: From the Debug Script
The debug script automatically generates proper nonces:
- **File**: `debug-support-chat.php`
- **Access**: `yoursite.com/debug-support-chat.php`
- **Nonces generated**: All required nonces are automatically created

### Method 3: From Plugin Code
In your plugin's PHP code, you can generate nonces:

```php
// For REST API
$rest_nonce = wp_create_nonce('wp_rest');

// For AJAX calls
$ajax_nonce = wp_create_nonce('surf_social_action');

// For custom actions
$custom_nonce = wp_create_nonce('surf_social_debug');
```

## 🛠️ Types of Nonces in Surf Social

### 1. REST API Nonce (`wp_rest`)
- **Used for**: All REST API calls (`/wp-json/surf-social/v1/...`)
- **Header**: `X-WP-Nonce`
- **Example**: `fetch('/wp-json/surf-social/v1/chat/support', { headers: { 'X-WP-Nonce': nonce } })`

### 2. AJAX Nonce (`surf_social_stats`)
- **Used for**: Admin panel AJAX calls
- **Parameter**: `nonce` in POST data
- **Example**: `action=surf_social_get_support_tickets&nonce=abc123`

### 3. Debug Nonce (`surf_social_debug`)
- **Used for**: Debug script AJAX calls
- **Parameter**: `nonce` in POST data
- **Example**: `action=surf_social_debug_database&nonce=xyz789`

## 🚨 Common Nonce Issues

### Issue 1: "Cookie check failed" (403 Error)
**Cause**: Invalid or missing nonce
**Solution**: 
- Make sure you're using the correct nonce type
- Check that the nonce is fresh (less than 24 hours old)
- Verify the nonce matches the action

### Issue 2: "Invalid nonce" Error
**Cause**: Nonce doesn't match the expected action
**Solution**:
- Use the correct nonce for the specific action
- Don't reuse nonces across different actions

### Issue 3: Nonce Expired
**Cause**: Nonce is older than 24 hours
**Solution**:
- Refresh the page to get a new nonce
- Or generate a new nonce programmatically

## 🔧 How to Fix Nonce Issues

### For Frontend JavaScript:
```javascript
// Get nonce from WordPress
const nonce = wpApiSettings.nonce;

// Use in REST API calls
fetch('/wp-json/surf-social/v1/chat/support', {
    headers: {
        'X-WP-Nonce': nonce
    }
});

// Use in AJAX calls
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: `action=surf_social_action&nonce=${nonce}`
});
```

### For PHP Backend:
```php
// Generate nonce
$nonce = wp_create_nonce('action_name');

// Verify nonce
if (!wp_verify_nonce($_POST['nonce'], 'action_name')) {
    wp_die('Invalid nonce');
}
```

## 📋 Testing Nonce Validity

### 1. Check in Browser Console:
```javascript
// Test REST API nonce
fetch('/wp-json/surf-social/v1/chat/support', {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
}).then(r => console.log('REST API:', r.status));

// Test AJAX nonce
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: `action=surf_social_get_support_tickets&nonce=${wpApiSettings.nonce}`
}).then(r => r.json()).then(d => console.log('AJAX:', d));
```

### 2. Use the Debug Script:
- **File**: `debug-support-chat.php`
- **Features**: Automatic nonce generation and testing
- **Access**: `yoursite.com/debug-support-chat.php`

## 🎯 Quick Fix Checklist

- [ ] **Are you logged into WordPress admin?**
- [ ] **Is the nonce less than 24 hours old?**
- [ ] **Are you using the correct nonce type for the action?**
- [ ] **Is the nonce being sent in the correct format?**
- [ ] **Are you testing from the same domain?**

## 🔍 Debugging Nonce Issues

### Step 1: Check Nonce Validity
```javascript
// In browser console
console.log('REST nonce:', wpApiSettings.nonce);
console.log('Surf Social config:', window.surfSocial);
```

### Step 2: Test API Calls
```javascript
// Test REST API
fetch('/wp-json/surf-social/v1/chat/support', {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
}).then(r => console.log('Status:', r.status));

// Test AJAX
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: `action=surf_social_get_support_tickets&nonce=${wpApiSettings.nonce}`
}).then(r => r.json()).then(d => console.log('Response:', d));
```

### Step 3: Check WordPress Error Logs
Look for nonce-related errors in:
- WordPress error logs
- Server error logs
- Browser console

## 🚀 Best Practices

1. **Always use fresh nonces** - Don't hardcode them
2. **Use the correct nonce type** for each action
3. **Handle nonce expiration** gracefully
4. **Test nonce validity** before making API calls
5. **Use the debug script** for troubleshooting

## 📞 Need Help?

If you're still having nonce issues:
1. **Use the debug script** at `/debug-support-chat.php`
2. **Check the browser console** for error messages
3. **Verify you're logged into WordPress admin**
4. **Make sure the nonce is fresh** (refresh the page)
