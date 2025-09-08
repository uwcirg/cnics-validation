# Testing Authentication Levels - Complete Guide

## 🧪 Overview

This guide explains how to test different authentication levels in the CNICS Validation frontend, including the new popup notifications and development testing tools.

## 🚀 Quick Setup for Testing

### 1. Enable Development Authentication

Set the environment variable in your backend:
```bash
export ALLOW_DEV_HEADER=1
```

Or add to your `.env` file:
```
ALLOW_DEV_HEADER=1
```

### 2. Start the Development Environment

```bash
# Backend
cd flask_backend
python app.py

# Frontend (in another terminal)
cd frontend
npm run dev
```

## 🧩 Development Auth Tester Component

When running in development mode, you'll see a **"🧪 Dev Auth Tester"** button in the top-left corner of the application.

### Features:
- **Click to expand** - Shows all available test users
- **One-click switching** - Instantly switch between different auth levels
- **Current status display** - Shows your current username and roles
- **Reset functionality** - Return to original authentication
- **Automatic page refresh** - Ensures backend picks up auth changes

### Test Users Available:

| User Type | Username | Roles | Description |
|-----------|----------|-------|-------------|
| **Admin User** | `admin_test` | Admin, Uploader, Reviewer | Full system access |
| **Uploader Only** | `uploader_test` | Uploader | Can upload packets only |
| **Reviewer Only** | `reviewer_test` | Reviewer | Can review events only |
| **Third Reviewer** | `third_reviewer_test` | Reviewer, Third Reviewer | Review + third review privileges |
| **Multi-Role** | `multi_role_test` | Uploader, Reviewer | Can upload and review |
| **Basic User** | `basic_test` | None | No special privileges |
| **Logged Out** | (none) | None | Unauthenticated state |

## 🎯 Testing Scenarios

### Scenario 1: Admin Access Testing

1. **Switch to Admin User**
   - Click Dev Auth Tester → "Admin User"
   - ✅ Should see all menu items in navigation
   - ✅ Should see admin tools section on home page
   - ✅ Should access all admin routes: `/users/add`, `/events/addMany`, etc.

2. **Test Admin-Only Features**
   - Navigate to `/users/viewAll` - Should work
   - Navigate to `/events/export` - Should work
   - Check home page shows "🔧 Administrative Tools" section

### Scenario 2: Regular User Access Testing

1. **Switch to Basic User**
   - Click Dev Auth Tester → "No Special Roles"
   - ❌ Should NOT see admin menu items
   - ❌ Should NOT see admin tools on home page

2. **Test Access Denials**
   - Try to navigate to `/users/add`
   - 🔴 Should see **error toast popup**: "Access denied: admin privileges required"
   - 📄 Should see access denied page with clear messaging

### Scenario 3: Uploader Testing

1. **Switch to Uploader**
   - Click Dev Auth Tester → "Uploader Only"
   - ✅ Should see upload menu items
   - ✅ Should see "📤 Upload Tools" section on home page
   - ❌ Should NOT see admin or review tools

2. **Test Uploader Access**
   - Navigate to `/events/upload` - Should work
   - Navigate to `/events/reupload` - Should work
   - Try `/users/add` - Should show access denied with toast

### Scenario 4: Reviewer Testing

1. **Switch to Reviewer**
   - Click Dev Auth Tester → "Reviewer Only"
   - ✅ Should see review menu items
   - ✅ Should see "📋 Review Tools" section on home page
   - ❌ Should NOT see upload or admin tools

2. **Test Reviewer Access**
   - Navigate to `/events/review` - Should work
   - Navigate to `/events/screen` - Should work
   - Try `/events/upload` - Should show access denied with toast

### Scenario 5: Unauthenticated Testing

1. **Switch to Logged Out**
   - Click Dev Auth Tester → "Logged Out"
   - ❌ Should NOT see any role-specific menu items
   - ❌ Should NOT see role-based sections on home page

2. **Test Unauthenticated Access**
   - Try any protected route
   - 🔴 Should see access denied page and toast
   - Should see "Not authenticated" status

## 🔴 Popup Notifications

### When Access is Denied:
- **Red error toast** appears in bottom-right corner
- **4-second duration** for visibility
- **Clear messaging** showing required privileges
- **Automatic dismissal** after timeout

### Toast Examples:
- "Access denied: admin privileges required"
- "Access denied: uploader or admin privileges required"
- "Access denied: reviewer or admin privileges required"

## 🛠 Manual Testing Without Dev Tester

If you prefer manual testing, you can set cookies directly in browser dev tools:

```javascript
// Set test user cookie
document.cookie = 'dev_user=admin_test; path=/';

// Clear cookie (logout)
document.cookie = 'dev_user=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';

// Then refresh the page
window.location.reload();
```

## 📋 Complete Testing Checklist

### Admin User Testing
- [ ] Can access user management pages
- [ ] Can access bulk import/export features
- [ ] Can access all event management features
- [ ] Sees admin tools section on home page
- [ ] Has all menu items visible

### Uploader User Testing
- [ ] Can access upload pages
- [ ] Cannot access admin pages (shows toast + access denied)
- [ ] Cannot access review-only pages
- [ ] Sees upload tools section on home page
- [ ] Has appropriate menu items

### Reviewer User Testing
- [ ] Can access review pages
- [ ] Cannot access admin pages (shows toast + access denied)
- [ ] Cannot access upload-only pages
- [ ] Sees review tools section on home page
- [ ] Has appropriate menu items

### Basic User Testing
- [ ] Cannot access any protected pages
- [ ] Shows access denied for all restricted routes
- [ ] Shows appropriate toast notifications
- [ ] No role-specific sections on home page
- [ ] Minimal menu items

### Unauthenticated Testing
- [ ] Cannot access any protected pages
- [ ] Shows "Not authenticated" status
- [ ] Access denied pages work correctly
- [ ] No user-specific content visible

## 🎨 Visual Indicators

### Home Page Sections:
- **🔧 Administrative Tools** (gray background) - Admin only
- **📤 Upload Tools** (yellow background) - Uploader/Admin
- **📋 Review Tools** (blue background) - Reviewer/Admin

### User Status Widget:
- **Green background** - Admin users
- **Blue background** - Regular users with roles
- **Yellow background** - Users with no roles
- **Red background** - Not authenticated

### Navigation Menu:
- **Role-specific items** only show for appropriate users
- **Common items** (Events, Logout) show for all authenticated users

## 🚨 Troubleshooting

### Dev Tester Not Showing:
- Ensure you're in development mode (`npm run dev`)
- Check that `ALLOW_DEV_HEADER=1` is set on backend
- Verify backend is running and accessible

### Auth Changes Not Taking Effect:
- Wait for automatic page refresh (1 second delay)
- Manually refresh if needed
- Check browser dev tools for cookie changes
- Verify backend logs for auth headers

### Toasts Not Appearing:
- Check that `toast-root` div exists in DOM
- Verify no JavaScript errors in console
- Test with manual `showToast()` call in console

## 🔄 Reset Instructions

To return to normal authentication:
1. Click "🔄 Reset to Original Auth" in Dev Auth Tester
2. Or clear the `dev_user` cookie manually
3. Refresh the page
4. Should return to your actual authentication state

---

**Note:** The dev authentication system only works in development mode and when `ALLOW_DEV_HEADER=1` is enabled on the backend. In production, this component is automatically hidden and the dev authentication is disabled.
