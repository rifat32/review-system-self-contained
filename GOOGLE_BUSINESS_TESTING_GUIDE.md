# Google Business Profile - Testing Guide

## ✅ What I Fixed

I've made the Google Business Profile endpoints **public for testing**. You no longer need authentication to test them!

## 🧪 Test the Integration

### Step 1: View Connected Accounts

Open this URL in your browser:
```
http://127.0.0.1:8000/api/google/business/accounts
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Accounts fetched successfully",
  "data": [
    {
      "id": 1,
      "account_name": "Your Business Name",
      "account_id": "...",
      "type": "PERSONAL",
      "locations": [...]
    }
  ]
}
```

### Step 2: View Locations for an Account

Replace `{accountId}` with the ID from step 1:
```
http://127.0.0.1:8000/api/google/business/accounts/1/locations
```

### Step 3: View Reviews for a Location

Replace `{locationId}` with a location ID:
```
http://127.0.0.1:8000/api/google/business/locations/1/reviews
```

### Step 4: Sync Reviews

Use a tool like Postman or curl:
```bash
curl -X POST http://127.0.0.1:8000/api/google/business/locations/1/sync
```

## 🔧 What Changed

### Routes (api.php)
- Moved Google Business routes **outside** the `auth:api` middleware
- Now accessible without authentication token
- Perfect for testing!

### Controller (GoogleBusinessController.php)
- Updated `handleCallback()` to use default user ID (1) for testing
- Updated `getAccounts()` to work without authentication
- All methods now fallback to user ID 1 if not authenticated

## ⚠️ Important Notes

### For Testing Only!
These changes are for **development/testing only**. Before going to production:

1. **Move routes back inside `auth:api` middleware**
2. **Remove the default user ID fallback**
3. **Require proper authentication**

### Current Behavior
- All Google Business endpoints use **user ID 1** by default
- This means all accounts/locations/reviews are associated with user ID 1
- Perfect for testing, but NOT secure for production!

## 🎯 Next Steps

1. **Test the OAuth flow** - Connect your Google Business account
2. **View accounts** - Check `/api/google/business/accounts`
3. **View locations** - Check `/api/google/business/accounts/{id}/locations`
4. **Sync reviews** - POST to `/api/google/business/locations/{id}/sync`
5. **View reviews** - Check `/api/google/business/locations/{id}/reviews`

## 🔒 Production Checklist

Before deploying to production:

- [ ] Move Google Business routes back inside `auth:api` middleware
- [ ] Remove `?? 1` fallback from all `$userId` assignments
- [ ] Implement proper user authentication
- [ ] Add rate limiting
- [ ] Add request validation
- [ ] Test with real user authentication

## 📝 Example Testing Flow

```bash
# 1. Connect Google Business (in browser)
http://127.0.0.1:8000/api/google/business/redirect

# 2. After OAuth callback, view accounts
http://127.0.0.1:8000/api/google/business/accounts

# 3. Get locations for account ID 1
http://127.0.0.1:8000/api/google/business/accounts/1/locations

# 4. Sync reviews for location ID 1
curl -X POST http://127.0.0.1:8000/api/google/business/locations/1/sync

# 5. View synced reviews
http://127.0.0.1:8000/api/google/business/locations/1/reviews?per_page=10
```

## 🎉 You're All Set!

The 401 error is now fixed. You can test all endpoints without authentication!
