# React + Google Business Profile Integration Guide
## Complete A-Z Implementation with Axios & React Query

This guide provides a comprehensive, step-by-step implementation for integrating Google Business Profile reviews into your React application using Axios and React Query.

---

## Table of Contents

1. [Prerequisites & Setup](#prerequisites--setup)
2. [Project Structure](#project-structure)
3. [Step 1: Install Dependencies](#step-1-install-dependencies)
4. [Step 2: Configure Axios](#step-2-configure-axios)
5. [Step 3: Setup React Query](#step-3-setup-react-query)
6. [Step 4: Create API Service Layer](#step-4-create-api-service-layer)
7. [Step 5: Create Custom Hooks](#step-5-create-custom-hooks)
8. [Step 6: Build UI Components](#step-6-build-ui-components)
9. [Step 7: Implement OAuth Flow](#step-7-implement-oauth-flow)
10. [Step 8: Display & Manage Reviews](#step-8-display--manage-reviews)
11. [Step 9: Reply to Reviews](#step-9-reply-to-reviews)
12. [Step 10: Error Handling & Loading States](#step-10-error-handling--loading-states)
13. [Complete Example Application](#complete-example-application)

---

## Prerequisites & Setup

### What You Need:
- ✅ Node.js (v16 or higher)
- ✅ React application (Create React App, Vite, or Next.js)
- ✅ Your Laravel API running (from previous implementation)
- ✅ Google Business Profile credentials configured

### Technologies We'll Use:
- **React Query (TanStack Query)** - For server state management
- **Axios** - For HTTP requests
- **React Router** - For navigation (optional but recommended)

---

## Step 1: Install Dependencies

### 1.1 Install Required Packages

Open your terminal in your React project directory and run:

```bash
npm install @tanstack/react-query axios
# or
yarn add @tanstack/react-query axios
```

**What each package does:**

- **`@tanstack/react-query`**: Manages server state, caching, synchronization, and background updates
- **`axios`**: Promise-based HTTP client for making API requests

### 1.2 Install Optional but Recommended Packages

```bash
npm install react-router-dom react-hot-toast
# or
yarn add react-router-dom react-hot-toast
```

**Why these packages:**

- **`react-router-dom`**: For handling OAuth callback routes
- **`react-hot-toast`**: For beautiful toast notifications

---

## Step 2: Configure Axios

### 2.1 Create Axios Instance

Create a file: `src/lib/axios.js`

```javascript
import axios from 'axios';

// Base URL for your Laravel API
const BASE_URL = process.env.REACT_APP_API_URL || 'http://127.0.0.1:8000/api';

// Create axios instance with default config
const axiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 30000, // 30 seconds timeout
});

// Request interceptor - Add auth token to every request
axiosInstance.interceptors.request.use(
  (config) => {
    // Get token from localStorage (or your auth state management)
    const token = localStorage.getItem('auth_token');
    
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle errors globally
axiosInstance.interceptors.response.use(
  (response) => {
    // Return the data directly for successful responses
    return response.data;
  },
  (error) => {
    // Handle different error scenarios
    if (error.response) {
      // Server responded with error status
      const { status, data } = error.response;
      
      switch (status) {
        case 401:
          // Unauthorized - clear token and redirect to login
          localStorage.removeItem('auth_token');
          window.location.href = '/login';
          break;
        case 403:
          console.error('Forbidden:', data.message);
          break;
        case 404:
          console.error('Not found:', data.message);
          break;
        case 500:
          console.error('Server error:', data.message);
          break;
        default:
          console.error('Error:', data.message);
      }
      
      return Promise.reject(data);
    } else if (error.request) {
      // Request was made but no response received
      console.error('Network error:', error.message);
      return Promise.reject({ message: 'Network error. Please check your connection.' });
    } else {
      // Something else happened
      console.error('Error:', error.message);
      return Promise.reject({ message: error.message });
    }
  }
);

export default axiosInstance;
```

**Explanation:**

1. **Base URL**: Set your Laravel API URL (use environment variable for flexibility)
2. **Default Headers**: Set JSON content type for all requests
3. **Request Interceptor**: Automatically adds authentication token to every request
4. **Response Interceptor**: 
   - Unwraps response data for easier access
   - Handles errors globally (401, 403, 404, 500)
   - Provides consistent error handling

### 2.2 Create Environment File

Create `.env` in your React project root:

```env
REACT_APP_API_URL=http://127.0.0.1:8000/api
REACT_APP_FRONTEND_URL=http://localhost:3000
```

---

## Step 3: Setup React Query

### 3.1 Create Query Client Configuration

Create a file: `src/lib/queryClient.js`

```javascript
import { QueryClient } from '@tanstack/react-query';

// Create a query client with default options
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // How long data stays fresh before refetching (5 minutes)
      staleTime: 5 * 60 * 1000,
      
      // How long inactive data stays in cache (10 minutes)
      cacheTime: 10 * 60 * 1000,
      
      // Retry failed requests 3 times
      retry: 3,
      
      // Retry delay increases exponentially
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000),
      
      // Refetch on window focus
      refetchOnWindowFocus: true,
      
      // Don't refetch on mount if data is fresh
      refetchOnMount: false,
      
      // Refetch on reconnect
      refetchOnReconnect: true,
    },
    mutations: {
      // Retry mutations once on failure
      retry: 1,
    },
  },
});
```

**Explanation:**

- **`staleTime`**: How long data is considered "fresh" before refetching
- **`cacheTime`**: How long unused data stays in memory
- **`retry`**: Number of retry attempts for failed requests
- **`refetchOnWindowFocus`**: Automatically refetch when user returns to tab
- **`refetchOnReconnect`**: Refetch when internet connection is restored

### 3.2 Wrap Your App with QueryClientProvider

Update your `src/App.js` or `src/main.jsx`:

```javascript
import React from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { queryClient } from './lib/queryClient';
import { Toaster } from 'react-hot-toast';

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      {/* Your app components */}
      <YourRoutes />
      
      {/* Toast notifications */}
      <Toaster position="top-right" />
      
      {/* React Query DevTools (only in development) */}
      {process.env.NODE_ENV === 'development' && (
        <ReactQueryDevtools initialIsOpen={false} />
      )}
    </QueryClientProvider>
  );
}

export default App;
```

**Explanation:**

- **`QueryClientProvider`**: Makes React Query available throughout your app
- **`Toaster`**: Displays toast notifications
- **`ReactQueryDevtools`**: Developer tools for debugging queries (development only)

---

## Step 4: Create API Service Layer

### 4.1 Create Google Business API Service

Create a file: `src/services/googleBusinessApi.js`

```javascript
import axios from '../lib/axios';

/**
 * Google Business Profile API Service
 * All API calls related to Google Business Profile integration
 */
const googleBusinessApi = {
  
  // ============================================================================
  // OAuth & Authentication
  // ============================================================================
  
  /**
   * Get Google OAuth authorization URL
   * @returns {Promise<{success: boolean, auth_url: string}>}
   */
  getAuthUrl: async () => {
    return await axios.get('/google/business/redirect');
  },
  
  /**
   * Handle OAuth callback (usually called automatically by backend)
   * @param {string} code - Authorization code from Google
   * @param {number} userId - User ID to associate account with
   * @returns {Promise<{success: boolean, data: object}>}
   */
  handleCallback: async (code, userId) => {
    return await axios.get('/google/business/callback', {
      params: { code, user_id: userId }
    });
  },
  
  // ============================================================================
  // Account Management
  // ============================================================================
  
  /**
   * Get all connected Google Business accounts
   * @returns {Promise<{success: boolean, data: Array}>}
   */
  getAccounts: async () => {
    return await axios.get('/google/business/accounts');
  },
  
  /**
   * Disconnect a Google Business account
   * @param {number} accountId - Account ID to disconnect
   * @returns {Promise<{success: boolean, message: string}>}
   */
  disconnectAccount: async (accountId) => {
    return await axios.delete(`/google/business/accounts/${accountId}`);
  },
  
  // ============================================================================
  // Location Management
  // ============================================================================
  
  /**
   * Get all locations for a specific account
   * @param {number} accountId - Account ID
   * @returns {Promise<{success: boolean, data: Array}>}
   */
  getLocations: async (accountId) => {
    return await axios.get(`/google/business/accounts/${accountId}/locations`);
  },
  
  /**
   * Toggle review syncing for a location
   * @param {number} locationId - Location ID
   * @returns {Promise<{success: boolean, data: object}>}
   */
  toggleLocationSync: async (locationId) => {
    return await axios.patch(`/google/business/locations/${locationId}/toggle-sync`);
  },
  
  // ============================================================================
  // Review Management
  // ============================================================================
  
  /**
   * Get reviews for a specific location
   * @param {number} locationId - Location ID
   * @param {object} params - Query parameters
   * @param {number} params.rating - Filter by rating (1-5)
   * @param {number} params.days - Get reviews from last N days
   * @param {number} params.per_page - Number of reviews per page
   * @param {number} params.page - Page number
   * @returns {Promise<{success: boolean, data: object}>}
   */
  getReviews: async (locationId, params = {}) => {
    return await axios.get(`/google/business/locations/${locationId}/reviews`, {
      params
    });
  },
  
  /**
   * Manually sync reviews for a location
   * @param {number} locationId - Location ID
   * @returns {Promise<{success: boolean, message: string, data: object}>}
   */
  syncReviews: async (locationId) => {
    return await axios.post(`/google/business/locations/${locationId}/sync`);
  },
  
  /**
   * Reply to a review
   * @param {number} reviewId - Review ID
   * @param {string} replyText - Reply message
   * @returns {Promise<{success: boolean, message: string, data: object}>}
   */
  replyToReview: async (reviewId, replyText) => {
    return await axios.post(`/google/business/reviews/${reviewId}/reply`, {
      reply: replyText
    });
  },
};

export default googleBusinessApi;
```

**Explanation:**

This service layer provides:
- **Centralized API calls**: All Google Business API calls in one place
- **Type safety**: JSDoc comments for better IDE autocomplete
- **Consistent interface**: All functions return promises with consistent structure
- **Easy to test**: Can mock this service in tests
- **Reusable**: Use these functions across multiple components

---

## Step 5: Create Custom Hooks

Custom hooks encapsulate React Query logic for cleaner components.

### 5.1 Create Hooks File

Create a file: `src/hooks/useGoogleBusiness.js`

```javascript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import googleBusinessApi from '../services/googleBusinessApi';
import toast from 'react-hot-toast';

// ============================================================================
// Query Keys - Centralized for cache management
// ============================================================================

export const QUERY_KEYS = {
  accounts: ['google-business', 'accounts'],
  locations: (accountId) => ['google-business', 'locations', accountId],
  reviews: (locationId, params) => ['google-business', 'reviews', locationId, params],
};

// ============================================================================
// Account Hooks
// ============================================================================

/**
 * Hook to fetch Google Business accounts
 * @returns {object} Query result with accounts data
 */
export const useGoogleBusinessAccounts = () => {
  return useQuery({
    queryKey: QUERY_KEYS.accounts,
    queryFn: googleBusinessApi.getAccounts,
    select: (data) => data.data, // Extract data array from response
    onError: (error) => {
      toast.error(error.message || 'Failed to fetch accounts');
    },
  });
};

/**
 * Hook to disconnect a Google Business account
 * @returns {object} Mutation object
 */
export const useDisconnectAccount = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: googleBusinessApi.disconnectAccount,
    onSuccess: () => {
      // Invalidate accounts query to refetch
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.accounts });
      toast.success('Account disconnected successfully');
    },
    onError: (error) => {
      toast.error(error.message || 'Failed to disconnect account');
    },
  });
};

// ============================================================================
// Location Hooks
// ============================================================================

/**
 * Hook to fetch locations for an account
 * @param {number} accountId - Account ID
 * @param {object} options - Query options
 * @returns {object} Query result with locations data
 */
export const useGoogleBusinessLocations = (accountId, options = {}) => {
  return useQuery({
    queryKey: QUERY_KEYS.locations(accountId),
    queryFn: () => googleBusinessApi.getLocations(accountId),
    select: (data) => data.data,
    enabled: !!accountId, // Only run if accountId exists
    ...options,
    onError: (error) => {
      toast.error(error.message || 'Failed to fetch locations');
    },
  });
};

/**
 * Hook to toggle location sync status
 * @returns {object} Mutation object
 */
export const useToggleLocationSync = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: googleBusinessApi.toggleLocationSync,
    onSuccess: (data, locationId) => {
      // Update cache optimistically
      queryClient.invalidateQueries({ 
        queryKey: ['google-business', 'locations'] 
      });
      
      const status = data.data.is_active ? 'enabled' : 'disabled';
      toast.success(`Review sync ${status} for this location`);
    },
    onError: (error) => {
      toast.error(error.message || 'Failed to update location');
    },
  });
};

// ============================================================================
// Review Hooks
// ============================================================================

/**
 * Hook to fetch reviews for a location
 * @param {number} locationId - Location ID
 * @param {object} params - Query parameters (rating, days, per_page, page)
 * @param {object} options - Query options
 * @returns {object} Query result with reviews data
 */
export const useGoogleBusinessReviews = (locationId, params = {}, options = {}) => {
  return useQuery({
    queryKey: QUERY_KEYS.reviews(locationId, params),
    queryFn: () => googleBusinessApi.getReviews(locationId, params),
    select: (data) => data.data,
    enabled: !!locationId,
    ...options,
    onError: (error) => {
      toast.error(error.message || 'Failed to fetch reviews');
    },
  });
};

/**
 * Hook to manually sync reviews
 * @returns {object} Mutation object
 */
export const useSyncReviews = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: googleBusinessApi.syncReviews,
    onMutate: () => {
      // Show loading toast
      toast.loading('Syncing reviews...', { id: 'sync-reviews' });
    },
    onSuccess: (data, locationId) => {
      // Invalidate reviews query to refetch
      queryClient.invalidateQueries({ 
        queryKey: ['google-business', 'reviews', locationId] 
      });
      
      toast.success(data.message || 'Reviews synced successfully', { 
        id: 'sync-reviews' 
      });
    },
    onError: (error) => {
      toast.error(error.message || 'Failed to sync reviews', { 
        id: 'sync-reviews' 
      });
    },
  });
};

/**
 * Hook to reply to a review
 * @returns {object} Mutation object
 */
export const useReplyToReview = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ reviewId, replyText }) => 
      googleBusinessApi.replyToReview(reviewId, replyText),
    onSuccess: (data) => {
      // Invalidate all reviews queries
      queryClient.invalidateQueries({ 
        queryKey: ['google-business', 'reviews'] 
      });
      
      toast.success('Reply posted successfully');
    },
    onError: (error) => {
      toast.error(error.message || 'Failed to post reply');
    },
  });
};

/**
 * Hook to initiate Google OAuth flow
 * @returns {object} Mutation object
 */
export const useGoogleOAuth = () => {
  return useMutation({
    mutationFn: googleBusinessApi.getAuthUrl,
    onSuccess: (data) => {
      if (data.success && data.auth_url) {
        // Redirect to Google OAuth page
        window.location.href = data.auth_url;
      }
    },
    onError: (error) => {
      toast.error(error.message || 'Failed to initiate OAuth');
    },
  });
};
```

**Explanation:**

Each hook provides:
- **Automatic caching**: React Query caches responses
- **Background refetching**: Keeps data fresh automatically
- **Loading & error states**: Built-in state management
- **Optimistic updates**: UI updates before server confirms
- **Toast notifications**: User feedback for all actions
- **Cache invalidation**: Automatically refetches related data after mutations

---

## Step 6: Build UI Components

### 6.1 Connect Account Button Component

Create a file: `src/components/GoogleBusiness/ConnectAccountButton.jsx`

```javascript
import React from 'react';
import { useGoogleOAuth } from '../../hooks/useGoogleBusiness';

/**
 * Button to initiate Google Business Profile OAuth connection
 */
const ConnectAccountButton = () => {
  const { mutate: connectGoogle, isLoading } = useGoogleOAuth();

  const handleConnect = () => {
    connectGoogle();
  };

  return (
    <button
      onClick={handleConnect}
      disabled={isLoading}
      className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 
                 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
    >
      {isLoading ? (
        <>
          <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
            <circle 
              className="opacity-25" 
              cx="12" 
              cy="12" 
              r="10" 
              stroke="currentColor" 
              strokeWidth="4"
              fill="none"
            />
            <path 
              className="opacity-75" 
              fill="currentColor" 
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          Connecting...
        </>
      ) : (
        <>
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
          </svg>
          Connect Google Business
        </>
      )}
    </button>
  );
};

export default ConnectAccountButton;
```

**Explanation:**
- Uses `useGoogleOAuth` hook to initiate connection
- Shows loading spinner during OAuth redirect
- Disabled state prevents multiple clicks
- Google logo SVG for branding

### 6.2 Accounts List Component

Create a file: `src/components/GoogleBusiness/AccountsList.jsx`

```javascript
import React from 'react';
import { 
  useGoogleBusinessAccounts, 
  useDisconnectAccount 
} from '../../hooks/useGoogleBusiness';

/**
 * Display list of connected Google Business accounts
 */
const AccountsList = ({ onSelectAccount }) => {
  const { data: accounts, isLoading, error } = useGoogleBusinessAccounts();
  const { mutate: disconnectAccount, isLoading: isDisconnecting } = useDisconnectAccount();

  const handleDisconnect = (accountId) => {
    if (window.confirm('Are you sure you want to disconnect this account?')) {
      disconnectAccount(accountId);
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="flex justify-center items-center p-8">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <p className="text-red-800">Error loading accounts: {error.message}</p>
      </div>
    );
  }

  // Empty state
  if (!accounts || accounts.length === 0) {
    return (
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
        <svg 
          className="mx-auto h-12 w-12 text-gray-400" 
          fill="none" 
          viewBox="0 0 24 24" 
          stroke="currentColor"
        >
          <path 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            strokeWidth={2} 
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" 
          />
        </svg>
        <h3 className="mt-2 text-sm font-medium text-gray-900">No accounts connected</h3>
        <p className="mt-1 text-sm text-gray-500">
          Connect your Google Business account to start syncing reviews.
        </p>
      </div>
    );
  }

  // Accounts list
  return (
    <div className="space-y-4">
      <h2 className="text-xl font-semibold text-gray-900">Connected Accounts</h2>
      
      <div className="grid gap-4">
        {accounts.map((account) => (
          <div 
            key={account.id} 
            className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
          >
            <div className="flex items-center justify-between">
              <div className="flex-1">
                <h3 className="text-lg font-medium text-gray-900">
                  {account.account_name}
                </h3>
                <p className="text-sm text-gray-500">
                  Account ID: {account.account_id}
                </p>
                <p className="text-sm text-gray-500">
                  Type: {account.type}
                </p>
                <p className="text-sm text-gray-500">
                  Locations: {account.locations?.length || 0}
                </p>
              </div>
              
              <div className="flex gap-2">
                <button
                  onClick={() => onSelectAccount(account)}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                  View Locations
                </button>
                
                <button
                  onClick={() => handleDisconnect(account.id)}
                  disabled={isDisconnecting}
                  className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 
                           disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isDisconnecting ? 'Disconnecting...' : 'Disconnect'}
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default AccountsList;
```

**Explanation:**
- Displays all connected Google Business accounts
- Shows loading, error, and empty states
- Allows viewing locations and disconnecting accounts
- Confirmation dialog before disconnecting

### 6.3 Locations List Component

Create a file: `src/components/GoogleBusiness/LocationsList.jsx`

```javascript
import React from 'react';
import { 
  useGoogleBusinessLocations, 
  useToggleLocationSync 
} from '../../hooks/useGoogleBusiness';

/**
 * Display locations for a Google Business account
 */
const LocationsList = ({ accountId, onSelectLocation }) => {
  const { data: locations, isLoading } = useGoogleBusinessLocations(accountId);
  const { mutate: toggleSync, isLoading: isToggling } = useToggleLocationSync();

  const handleToggleSync = (locationId) => {
    toggleSync(locationId);
  };

  if (isLoading) {
    return <div className="text-center p-4">Loading locations...</div>;
  }

  if (!locations || locations.length === 0) {
    return (
      <div className="text-center p-8 bg-gray-50 rounded-lg">
        <p className="text-gray-600">No locations found for this account.</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-gray-900">Business Locations</h3>
      
      <div className="grid gap-4">
        {locations.map((location) => (
          <div 
            key={location.id} 
            className="bg-white border border-gray-200 rounded-lg p-4"
          >
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <h4 className="text-md font-medium text-gray-900">
                  {location.location_name}
                </h4>
                
                {location.address && (
                  <p className="text-sm text-gray-600 mt-1">
                    📍 {location.address}
                  </p>
                )}
                
                {location.phone && (
                  <p className="text-sm text-gray-600">
                    📞 {location.phone}
                  </p>
                )}
                
                {location.website && (
                  <a 
                    href={location.website} 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="text-sm text-blue-600 hover:underline"
                  >
                    🌐 {location.website}
                  </a>
                )}
                
                <div className="mt-2 flex items-center gap-2">
                  <span className={`px-2 py-1 text-xs rounded-full ${
                    location.is_active 
                      ? 'bg-green-100 text-green-800' 
                      : 'bg-gray-100 text-gray-800'
                  }`}>
                    {location.is_active ? 'Sync Enabled' : 'Sync Disabled'}
                  </span>
                  
                  {location.last_synced_at && (
                    <span className="text-xs text-gray-500">
                      Last synced: {new Date(location.last_synced_at).toLocaleString()}
                    </span>
                  )}
                </div>
              </div>
              
              <div className="flex flex-col gap-2">
                <button
                  onClick={() => onSelectLocation(location)}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                >
                  View Reviews
                </button>
                
                <button
                  onClick={() => handleToggleSync(location.id)}
                  disabled={isToggling}
                  className={`px-4 py-2 rounded-md text-sm ${
                    location.is_active
                      ? 'bg-yellow-600 hover:bg-yellow-700'
                      : 'bg-green-600 hover:bg-green-700'
                  } text-white disabled:opacity-50`}
                >
                  {isToggling ? 'Updating...' : location.is_active ? 'Disable Sync' : 'Enable Sync'}
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default LocationsList;
```

**Explanation:**
- Lists all locations for selected account
- Shows location details (address, phone, website)
- Toggle sync status for each location
- Visual indicators for sync status
- Last sync timestamp

---

*Continued in next part...*
