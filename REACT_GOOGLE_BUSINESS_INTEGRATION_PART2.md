# React + Google Business Profile Integration Guide (Part 2)
## Reviews Display, Reply Functionality & Complete Examples

---

## Step 7: Display & Manage Reviews

### 7.1 Reviews List Component

Create a file: `src/components/GoogleBusiness/ReviewsList.jsx`

```javascript
import React, { useState } from 'react';
import { 
  useGoogleBusinessReviews, 
  useSyncReviews 
} from '../../hooks/useGoogleBusiness';
import ReviewCard from './ReviewCard';
import ReviewFilters from './ReviewFilters';

/**
 * Display and manage reviews for a location
 */
const ReviewsList = ({ location }) => {
  const [filters, setFilters] = useState({
    rating: null,
    days: 30,
    per_page: 15,
    page: 1,
  });

  const { 
    data: reviewsData, 
    isLoading, 
    error,
    refetch 
  } = useGoogleBusinessReviews(location.id, filters);

  const { mutate: syncReviews, isLoading: isSyncing } = useSyncReviews();

  const handleSync = () => {
    syncReviews(location.id, {
      onSuccess: () => {
        refetch(); // Refetch reviews after sync
      }
    });
  };

  const handleFilterChange = (newFilters) => {
    setFilters({ ...filters, ...newFilters, page: 1 });
  };

  const handlePageChange = (newPage) => {
    setFilters({ ...filters, page: newPage });
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center p-12">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading reviews...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <p className="text-red-800">Error loading reviews: {error.message}</p>
        <button 
          onClick={() => refetch()}
          className="mt-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
        >
          Retry
        </button>
      </div>
    );
  }

  const reviews = reviewsData?.data || [];
  const pagination = reviewsData?.meta || {};

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">
            Reviews for {location.location_name}
          </h2>
          <p className="text-sm text-gray-600 mt-1">
            Total: {pagination.total || 0} reviews
          </p>
        </div>
        
        <button
          onClick={handleSync}
          disabled={isSyncing}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 
                   disabled:opacity-50 flex items-center gap-2"
        >
          {isSyncing ? (
            <>
              <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
              </svg>
              Syncing...
            </>
          ) : (
            <>
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Sync Reviews
            </>
          )}
        </button>
      </div>

      {/* Filters */}
      <ReviewFilters 
        filters={filters} 
        onFilterChange={handleFilterChange} 
      />

      {/* Reviews List */}
      {reviews.length === 0 ? (
        <div className="text-center p-12 bg-gray-50 rounded-lg">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
          </svg>
          <h3 className="mt-2 text-sm font-medium text-gray-900">No reviews found</h3>
          <p className="mt-1 text-sm text-gray-500">
            Try adjusting your filters or sync reviews from Google.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {reviews.map((review) => (
            <ReviewCard key={review.id} review={review} />
          ))}
        </div>
      )}

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between border-t border-gray-200 pt-4">
          <div className="text-sm text-gray-700">
            Showing {pagination.from} to {pagination.to} of {pagination.total} results
          </div>
          
          <div className="flex gap-2">
            <button
              onClick={() => handlePageChange(pagination.current_page - 1)}
              disabled={pagination.current_page === 1}
              className="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 
                       disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            
            <span className="px-4 py-2 border border-gray-300 rounded-md bg-blue-50">
              Page {pagination.current_page} of {pagination.last_page}
            </span>
            
            <button
              onClick={() => handlePageChange(pagination.current_page + 1)}
              disabled={pagination.current_page === pagination.last_page}
              className="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 
                       disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ReviewsList;
```

### 7.2 Review Filters Component

Create a file: `src/components/GoogleBusiness/ReviewFilters.jsx`

```javascript
import React from 'react';

/**
 * Filter controls for reviews
 */
const ReviewFilters = ({ filters, onFilterChange }) => {
  const ratingOptions = [
    { value: null, label: 'All Ratings' },
    { value: 5, label: '⭐⭐⭐⭐⭐ (5 stars)' },
    { value: 4, label: '⭐⭐⭐⭐ (4 stars)' },
    { value: 3, label: '⭐⭐⭐ (3 stars)' },
    { value: 2, label: '⭐⭐ (2 stars)' },
    { value: 1, label: '⭐ (1 star)' },
  ];

  const daysOptions = [
    { value: 7, label: 'Last 7 days' },
    { value: 30, label: 'Last 30 days' },
    { value: 90, label: 'Last 90 days' },
    { value: 365, label: 'Last year' },
    { value: null, label: 'All time' },
  ];

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Rating Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Filter by Rating
          </label>
          <select
            value={filters.rating || ''}
            onChange={(e) => onFilterChange({ 
              rating: e.target.value ? parseInt(e.target.value) : null 
            })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {ratingOptions.map((option) => (
              <option key={option.value} value={option.value || ''}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        {/* Time Period Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Time Period
          </label>
          <select
            value={filters.days || ''}
            onChange={(e) => onFilterChange({ 
              days: e.target.value ? parseInt(e.target.value) : null 
            })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {daysOptions.map((option) => (
              <option key={option.value} value={option.value || ''}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        {/* Per Page Filter */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Reviews per page
          </label>
          <select
            value={filters.per_page}
            onChange={(e) => onFilterChange({ 
              per_page: parseInt(e.target.value) 
            })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value={10}>10</option>
            <option value={15}>15</option>
            <option value={25}>25</option>
            <option value={50}>50</option>
          </select>
        </div>
      </div>
    </div>
  );
};

export default ReviewFilters;
```

### 7.3 Review Card Component

Create a file: `src/components/GoogleBusiness/ReviewCard.jsx`

```javascript
import React, { useState } from 'react';
import { useReplyToReview } from '../../hooks/useGoogleBusiness';

/**
 * Individual review card with reply functionality
 */
const ReviewCard = ({ review }) => {
  const [isReplying, setIsReplying] = useState(false);
  const [replyText, setReplyText] = useState('');
  
  const { mutate: replyToReview, isLoading: isSubmitting } = useReplyToReview();

  // Convert star rating enum to number
  const ratingMap = {
    'ONE': 1,
    'TWO': 2,
    'THREE': 3,
    'FOUR': 4,
    'FIVE': 5,
  };
  
  const numericRating = ratingMap[review.star_rating] || 0;

  const handleSubmitReply = () => {
    if (!replyText.trim()) return;

    replyToReview(
      { reviewId: review.id, replyText },
      {
        onSuccess: () => {
          setReplyText('');
          setIsReplying(false);
        }
      }
    );
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'Unknown date';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
      {/* Reviewer Info */}
      <div className="flex items-start gap-4">
        {/* Reviewer Photo */}
        <div className="flex-shrink-0">
          {review.reviewer_photo_url ? (
            <img
              src={review.reviewer_photo_url}
              alt={review.reviewer_name}
              className="w-12 h-12 rounded-full object-cover"
            />
          ) : (
            <div className="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center">
              <span className="text-xl font-semibold text-gray-600">
                {review.reviewer_name?.charAt(0) || '?'}
              </span>
            </div>
          )}
        </div>

        {/* Review Content */}
        <div className="flex-1">
          {/* Reviewer Name & Rating */}
          <div className="flex items-center justify-between">
            <div>
              <h4 className="font-semibold text-gray-900">
                {review.reviewer_name || 'Anonymous'}
              </h4>
              <div className="flex items-center gap-2 mt-1">
                {/* Star Rating */}
                <div className="flex">
                  {[...Array(5)].map((_, index) => (
                    <svg
                      key={index}
                      className={`w-5 h-5 ${
                        index < numericRating ? 'text-yellow-400' : 'text-gray-300'
                      }`}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  ))}
                </div>
                <span className="text-sm text-gray-500">
                  {formatDate(review.review_created_at)}
                </span>
              </div>
            </div>
          </div>

          {/* Review Comment */}
          {review.comment && (
            <p className="mt-3 text-gray-700 whitespace-pre-wrap">
              {review.comment}
            </p>
          )}

          {/* Existing Reply */}
          {review.review_reply && (
            <div className="mt-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
              <div className="flex items-start gap-2">
                <svg className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" />
                </svg>
                <div className="flex-1">
                  <p className="text-sm font-medium text-blue-900">Your Reply</p>
                  <p className="mt-1 text-sm text-blue-800">{review.review_reply}</p>
                  {review.review_reply_updated_at && (
                    <p className="mt-1 text-xs text-blue-600">
                      Replied on {formatDate(review.review_reply_updated_at)}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Reply Form */}
          {!review.review_reply && (
            <div className="mt-4">
              {!isReplying ? (
                <button
                  onClick={() => setIsReplying(true)}
                  className="text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  Reply to this review
                </button>
              ) : (
                <div className="space-y-3">
                  <textarea
                    value={replyText}
                    onChange={(e) => setReplyText(e.target.value)}
                    placeholder="Write your reply..."
                    rows={4}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    maxLength={4000}
                  />
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-gray-500">
                      {replyText.length}/4000 characters
                    </span>
                    <div className="flex gap-2">
                      <button
                        onClick={() => {
                          setIsReplying(false);
                          setReplyText('');
                        }}
                        disabled={isSubmitting}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={handleSubmitReply}
                        disabled={isSubmitting || !replyText.trim()}
                        className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {isSubmitting ? 'Posting...' : 'Post Reply'}
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ReviewCard;
```

**Explanation:**
- **Star Rating Display**: Visual star rating with filled/empty stars
- **Reviewer Info**: Photo, name, and review date
- **Reply Functionality**: Inline reply form with character counter
- **Existing Reply Display**: Shows previously posted replies
- **Date Formatting**: Human-readable date format

---

## Step 8: Complete Main Application Component

### 8.1 Main Google Business Dashboard

Create a file: `src/pages/GoogleBusinessDashboard.jsx`

```javascript
import React, { useState } from 'react';
import ConnectAccountButton from '../components/GoogleBusiness/ConnectAccountButton';
import AccountsList from '../components/GoogleBusiness/AccountsList';
import LocationsList from '../components/GoogleBusiness/LocationsList';
import ReviewsList from '../components/GoogleBusiness/ReviewsList';

/**
 * Main dashboard for Google Business Profile integration
 */
const GoogleBusinessDashboard = () => {
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [selectedLocation, setSelectedLocation] = useState(null);

  const handleSelectAccount = (account) => {
    setSelectedAccount(account);
    setSelectedLocation(null); // Reset location when changing account
  };

  const handleSelectLocation = (location) => {
    setSelectedLocation(location);
  };

  const handleBackToAccounts = () => {
    setSelectedAccount(null);
    setSelectedLocation(null);
  };

  const handleBackToLocations = () => {
    setSelectedLocation(null);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            Google Business Profile
          </h1>
          <p className="mt-2 text-gray-600">
            Manage your Google Business reviews and locations
          </p>
        </div>

        {/* Breadcrumb Navigation */}
        {(selectedAccount || selectedLocation) && (
          <nav className="mb-6 flex items-center space-x-2 text-sm">
            <button
              onClick={handleBackToAccounts}
              className="text-blue-600 hover:text-blue-700 hover:underline"
            >
              Accounts
            </button>
            
            {selectedAccount && (
              <>
                <span className="text-gray-400">/</span>
                {selectedLocation ? (
                  <button
                    onClick={handleBackToLocations}
                    className="text-blue-600 hover:text-blue-700 hover:underline"
                  >
                    {selectedAccount.account_name}
                  </button>
                ) : (
                  <span className="text-gray-900">{selectedAccount.account_name}</span>
                )}
              </>
            )}
            
            {selectedLocation && (
              <>
                <span className="text-gray-400">/</span>
                <span className="text-gray-900">{selectedLocation.location_name}</span>
              </>
            )}
          </nav>
        )}

        {/* Main Content */}
        <div className="bg-white rounded-lg shadow-sm p-6">
          {!selectedAccount && !selectedLocation && (
            <div className="space-y-6">
              <div className="flex justify-end">
                <ConnectAccountButton />
              </div>
              <AccountsList onSelectAccount={handleSelectAccount} />
            </div>
          )}

          {selectedAccount && !selectedLocation && (
            <LocationsList 
              accountId={selectedAccount.id} 
              onSelectLocation={handleSelectLocation}
            />
          )}

          {selectedLocation && (
            <ReviewsList location={selectedLocation} />
          )}
        </div>
      </div>
    </div>
  );
};

export default GoogleBusinessDashboard;
```

**Explanation:**
- **State Management**: Tracks selected account and location
- **Breadcrumb Navigation**: Easy navigation between views
- **Conditional Rendering**: Shows appropriate component based on state
- **Responsive Layout**: Works on all screen sizes

---

## Step 9: OAuth Callback Handler

### 9.1 Create Callback Page

Create a file: `src/pages/GoogleBusinessCallback.jsx`

```javascript
import React, { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import googleBusinessApi from '../services/googleBusinessApi';

/**
 * Handle OAuth callback from Google
 */
const GoogleBusinessCallback = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const handleCallback = async () => {
      const code = searchParams.get('code');
      const error = searchParams.get('error');

      // Handle OAuth error
      if (error) {
        toast.error(`OAuth failed: ${error}`);
        navigate('/google-business');
        return;
      }

      // Handle OAuth success
      if (code) {
        try {
          toast.loading('Connecting your Google Business account...', { id: 'oauth' });

          // Get user ID from your auth state (adjust based on your auth implementation)
          const userId = localStorage.getItem('user_id');

          const response = await googleBusinessApi.handleCallback(code, userId);

          if (response.success) {
            toast.success('Google Business account connected successfully!', { id: 'oauth' });
            
            // Redirect to dashboard
            navigate('/google-business');
          } else {
            throw new Error(response.message || 'Failed to connect account');
          }
        } catch (error) {
          console.error('OAuth callback error:', error);
          toast.error(error.message || 'Failed to connect account', { id: 'oauth' });
          navigate('/google-business');
        }
      } else {
        // No code or error - invalid callback
        toast.error('Invalid OAuth callback');
        navigate('/google-business');
      }
    };

    handleCallback();
  }, [searchParams, navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="text-center">
        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mx-auto"></div>
        <p className="mt-4 text-lg text-gray-700">Connecting your Google Business account...</p>
        <p className="mt-2 text-sm text-gray-500">Please wait while we complete the setup.</p>
      </div>
    </div>
  );
};

export default GoogleBusinessCallback;
```

**Explanation:**
- **URL Parameters**: Extracts OAuth code from URL
- **Error Handling**: Handles OAuth errors gracefully
- **Loading State**: Shows loading indicator during processing
- **Automatic Redirect**: Redirects to dashboard after success

---

## Step 10: Setup Routes

### 10.1 Add Routes to Your App

Update your `src/App.js` or routing file:

```javascript
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import GoogleBusinessDashboard from './pages/GoogleBusinessDashboard';
import GoogleBusinessCallback from './pages/GoogleBusinessCallback';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Your other routes */}
        
        {/* Google Business Routes */}
        <Route path="/google-business" element={<GoogleBusinessDashboard />} />
        <Route path="/google-business/callback" element={<GoogleBusinessCallback />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
```

---

## Step 11: Complete Project Structure

Your final project structure should look like this:

```
src/
├── lib/
│   ├── axios.js                 # Axios configuration
│   └── queryClient.js           # React Query configuration
├── services/
│   └── googleBusinessApi.js     # API service layer
├── hooks/
│   └── useGoogleBusiness.js     # Custom React Query hooks
├── components/
│   └── GoogleBusiness/
│       ├── ConnectAccountButton.jsx
│       ├── AccountsList.jsx
│       ├── LocationsList.jsx
│       ├── ReviewsList.jsx
│       ├── ReviewFilters.jsx
│       └── ReviewCard.jsx
├── pages/
│   ├── GoogleBusinessDashboard.jsx
│   └── GoogleBusinessCallback.jsx
└── App.js                       # Main app with routes
```

---

## Step 12: Testing Your Implementation

### 12.1 Test OAuth Flow

1. Navigate to `/google-business`
2. Click "Connect Google Business"
3. Complete Google OAuth consent
4. Verify redirect to callback page
5. Check that account appears in dashboard

### 12.2 Test Review Syncing

```javascript
// In browser console
localStorage.setItem('auth_token', 'your-jwt-token');
```

1. Select an account
2. Select a location
3. Click "Sync Reviews"
4. Verify reviews appear

### 12.3 Test Reply Functionality

1. Find a review without a reply
2. Click "Reply to this review"
3. Type your reply
4. Click "Post Reply"
5. Verify reply appears on Google Business Profile

---

## Complete Usage Example

Here's a complete example of how everything works together:

```javascript
// Example: Using the hooks directly in a component

import React from 'react';
import {
  useGoogleBusinessAccounts,
  useGoogleBusinessReviews,
  useReplyToReview,
  useSyncReviews
} from './hooks/useGoogleBusiness';

function MyComponent() {
  // Fetch accounts
  const { data: accounts, isLoading } = useGoogleBusinessAccounts();
  
  // Fetch reviews for location ID 123
  const { data: reviews } = useGoogleBusinessReviews(123, {
    rating: 5,
    days: 30
  });
  
  // Reply mutation
  const { mutate: reply } = useReplyToReview();
  
  // Sync mutation
  const { mutate: sync } = useSyncReviews();
  
  const handleReply = (reviewId) => {
    reply({
      reviewId,
      replyText: 'Thank you for your feedback!'
    });
  };
  
  const handleSync = (locationId) => {
    sync(locationId);
  };
  
  return (
    <div>
      {/* Your UI */}
    </div>
  );
}
```

---

## Best Practices & Tips

### 1. **Error Handling**
```javascript
// Always handle errors in your components
const { data, error, isLoading } = useGoogleBusinessReviews(locationId);

if (error) {
  return <ErrorComponent error={error} />;
}
```

### 2. **Loading States**
```javascript
// Show loading indicators
{isLoading && <LoadingSpinner />}
{isSyncing && <SyncingIndicator />}
```

### 3. **Optimistic Updates**
```javascript
// Update UI before server confirms
const { mutate } = useMutation({
  onMutate: async (newData) => {
    // Cancel outgoing refetches
    await queryClient.cancelQueries(['reviews']);
    
    // Snapshot previous value
    const previousReviews = queryClient.getQueryData(['reviews']);
    
    // Optimistically update
    queryClient.setQueryData(['reviews'], (old) => [...old, newData]);
    
    return { previousReviews };
  },
  onError: (err, newData, context) => {
    // Rollback on error
    queryClient.setQueryData(['reviews'], context.previousReviews);
  },
});
```

### 4. **Caching Strategy**
```javascript
// Configure cache times based on data freshness needs
useQuery({
  queryKey: ['reviews'],
  queryFn: fetchReviews,
  staleTime: 5 * 60 * 1000,  // 5 minutes for reviews
  cacheTime: 30 * 60 * 1000, // 30 minutes in cache
});
```

---

## Troubleshooting

### Common Issues:

**1. "Network Error"**
- Check if Laravel API is running
- Verify `REACT_APP_API_URL` in `.env`
- Check CORS settings in Laravel

**2. "401 Unauthorized"**
- Verify auth token is being sent
- Check token expiration
- Ensure user is logged in

**3. "Reviews not syncing"**
- Check Google Business API credentials
- Verify location has reviews on Google
- Check Laravel logs for errors

**4. "OAuth redirect not working"**
- Verify redirect URL in Google Cloud Console
- Check route configuration
- Ensure callback page exists

---

## 🎉 Congratulations!

You now have a complete, production-ready Google Business Profile integration with:

✅ OAuth authentication
✅ Account & location management
✅ Review fetching & syncing
✅ Reply functionality
✅ Filtering & pagination
✅ Error handling
✅ Loading states
✅ Toast notifications
✅ Responsive UI

Your React app can now seamlessly interact with Google Business Profile reviews!
