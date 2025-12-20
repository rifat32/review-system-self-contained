# Google Business Profile API Integration Guide

This guide will walk you through integrating Google Business Profile reviews into your Laravel application.

## Prerequisites

1. ✅ A verified Google Business Profile (your business must be verified on Google)
2. ✅ Owner or Manager access to the business profile
3. ✅ A Google Cloud account
4. ✅ Your Laravel application (already set up)

---

## Step 1: Google Cloud Console Setup

### 1.1 Create/Select a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click on the project dropdown at the top
3. Click "New Project" or select an existing one
4. Name it something like "Review System API"

### 1.2 Enable the Business Profile API

1. In the Google Cloud Console, go to **APIs & Services** > **Library**
2. Search for "**Business Profile API**" (formerly Google My Business API)
3. Click on it and press **"Enable"**

### 1.3 Create OAuth 2.0 Credentials

1. Go to **APIs & Services** > **Credentials**
2. Click **"Create Credentials"** > **"OAuth client ID"**
3. If prompted, configure the OAuth consent screen:
   - Choose **"External"** (unless you have a Google Workspace)
   - Fill in:
     - App name: "Review System"
     - User support email: your email
     - Developer contact: your email
   - Add scopes: `https://www.googleapis.com/auth/business.manage`
   - Add test users (your Google account email)
4. Create OAuth Client ID:
   - Application type: **"Web application"**
   - Name: "Review System Web Client"
   - Authorized redirect URIs: 
     - `http://localhost:8000/api/google/business/callback` (for local testing)
     - `https://yourdomain.com/api/google/business/callback` (for production)
5. **Save the Client ID and Client Secret** - you'll need these!

---

## Step 2: Update Your Laravel Environment

### 2.1 Add Credentials to `.env`

```env
# Google Business Profile API
GOOGLE_BUSINESS_CLIENT_ID=your-client-id-here
GOOGLE_BUSINESS_CLIENT_SECRET=your-client-secret-here
GOOGLE_BUSINESS_REDIRECT_URL=http://localhost:8000/api/google/business/callback
```

### 2.2 Update `config/services.php`

Add this configuration:

```php
'google_business' => [
    'client_id' => env('GOOGLE_BUSINESS_CLIENT_ID'),
    'client_secret' => env('GOOGLE_BUSINESS_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_BUSINESS_REDIRECT_URL'),
],
```

---

## Step 3: Install Required Packages

You'll need the Google API PHP Client:

```bash
composer require google/apiclient:"^2.0"
```

---

## Step 4: Database Setup

You'll need to store:
- Business account information
- Location IDs
- Access tokens (for API calls)
- Synced reviews

### 4.1 Create Migrations

```bash
php artisan make:migration create_google_business_accounts_table
php artisan make:migration create_google_business_locations_table
php artisan make:migration create_google_business_reviews_table
```

---

## Step 5: Implementation Flow

### The Complete Flow:

1. **Authenticate** → User authorizes your app to access their Google Business Profile
2. **Get Accounts** → Fetch all business accounts the user has access to
3. **Get Locations** → Fetch all locations for a selected account
4. **Fetch Reviews** → Get reviews for a specific location
5. **Sync Reviews** → Store reviews in your database
6. **Reply to Reviews** (Optional) → Post replies programmatically

---

## Step 6: Key API Endpoints You'll Use

### Authentication
- **Scope**: `https://www.googleapis.com/auth/business.manage`

### Get Accounts
```
GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts
```

### Get Locations
```
GET https://mybusinessbusinessinformation.googleapis.com/v1/{parent}/locations
```
Example: `accounts/{accountId}/locations`

### List Reviews
```
GET https://mybusiness.googleapis.com/v4/{parent}/reviews
```
Example: `accounts/{accountId}/locations/{locationId}/reviews`

### Get Specific Review
```
GET https://mybusiness.googleapis.com/v4/{name}
```
Example: `accounts/{accountId}/locations/{locationId}/reviews/{reviewId}`

### Reply to Review
```
PUT https://mybusiness.googleapis.com/v4/{parent}/reply
```

---

## Step 7: Important Notes

### Rate Limits
- Be mindful of API quotas (check Google Cloud Console)
- Implement caching and periodic syncing (e.g., every 15 minutes)

### Token Management
- Access tokens expire after 1 hour
- Store refresh tokens to get new access tokens
- Implement automatic token refresh logic

### Review Data Structure
A typical review includes:
- `reviewId`: Unique identifier
- `reviewer`: Name and profile photo
- `starRating`: 1-5 stars (enum: ONE, TWO, THREE, FOUR, FIVE)
- `comment`: Review text
- `createTime`: When the review was posted
- `updateTime`: Last update time
- `reviewReply`: Your reply (if any)

---

## Next Steps

Would you like me to:
1. ✅ Create the database migrations for storing business accounts, locations, and reviews?
2. ✅ Build a controller to handle the OAuth flow and API calls?
3. ✅ Create a service class to interact with the Google Business Profile API?
4. ✅ Set up a command to sync reviews periodically?
5. ✅ Create API endpoints for your frontend to fetch and display reviews?

Let me know which parts you'd like me to implement first!
