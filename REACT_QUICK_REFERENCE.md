# React Google Business Integration - Quick Reference

## 📚 Complete Guide Overview

I've created **TWO comprehensive guides** for integrating Google Business Profile with React:

1. **Part 1**: Setup, Configuration, API Layer, Hooks
2. **Part 2**: UI Components, Reviews, Replies, Complete Examples

---

## 🚀 Quick Start (5 Minutes)

### Step 1: Install Dependencies
```bash
npm install @tanstack/react-query axios react-router-dom react-hot-toast
```

### Step 2: Create Environment File
```env
# .env
REACT_APP_API_URL=http://127.0.0.1:8000/api
```

### Step 3: Copy Files from Guides

Copy these files from the guides to your project:

**Configuration:**
- `src/lib/axios.js`
- `src/lib/queryClient.js`

**Services:**
- `src/services/googleBusinessApi.js`

**Hooks:**
- `src/hooks/useGoogleBusiness.js`

**Components:**
- `src/components/GoogleBusiness/ConnectAccountButton.jsx`
- `src/components/GoogleBusiness/AccountsList.jsx`
- `src/components/GoogleBusiness/LocationsList.jsx`
- `src/components/GoogleBusiness/ReviewsList.jsx`
- `src/components/GoogleBusiness/ReviewFilters.jsx`
- `src/components/GoogleBusiness/ReviewCard.jsx`

**Pages:**
- `src/pages/GoogleBusinessDashboard.jsx`
- `src/pages/GoogleBusinessCallback.jsx`

### Step 4: Update App.js

```javascript
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from './lib/queryClient';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import GoogleBusinessDashboard from './pages/GoogleBusinessDashboard';
import GoogleBusinessCallback from './pages/GoogleBusinessCallback';

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/google-business" element={<GoogleBusinessDashboard />} />
          <Route path="/google-business/callback" element={<GoogleBusinessCallback />} />
        </Routes>
        <Toaster position="top-right" />
      </BrowserRouter>
    </QueryClientProvider>
  );
}
```

### Step 5: Run Your App
```bash
npm start
```

Navigate to `http://localhost:3000/google-business`

---

## 📖 What Each File Does

### Configuration Layer

**`lib/axios.js`**
- Configures Axios with base URL
- Adds auth token to all requests
- Handles errors globally
- Unwraps response data

**`lib/queryClient.js`**
- Configures React Query
- Sets cache times
- Configures retry logic
- Enables background refetching

### Service Layer

**`services/googleBusinessApi.js`**
- All API calls in one place
- Functions for OAuth, accounts, locations, reviews
- Consistent promise-based interface
- Easy to test and mock

### Hooks Layer

**`hooks/useGoogleBusiness.js`**
- Custom React Query hooks
- Manages server state
- Handles caching
- Provides loading/error states
- Automatic refetching
- Toast notifications

**Available Hooks:**
- `useGoogleBusinessAccounts()` - Fetch accounts
- `useDisconnectAccount()` - Disconnect account
- `useGoogleBusinessLocations(accountId)` - Fetch locations
- `useToggleLocationSync()` - Toggle sync status
- `useGoogleBusinessReviews(locationId, params)` - Fetch reviews
- `useSyncReviews()` - Sync reviews manually
- `useReplyToReview()` - Reply to review
- `useGoogleOAuth()` - Initiate OAuth

### Component Layer

**`ConnectAccountButton.jsx`**
- Button to start OAuth flow
- Shows loading state
- Redirects to Google

**`AccountsList.jsx`**
- Lists connected accounts
- Shows account details
- Disconnect functionality
- Empty/loading/error states

**`LocationsList.jsx`**
- Shows locations for account
- Toggle sync on/off
- Location details
- Navigate to reviews

**`ReviewsList.jsx`**
- Displays reviews with pagination
- Filter controls
- Sync button
- Empty/loading states

**`ReviewFilters.jsx`**
- Filter by rating
- Filter by time period
- Set reviews per page

**`ReviewCard.jsx`**
- Individual review display
- Star rating visualization
- Reply form
- Shows existing replies

### Page Layer

**`GoogleBusinessDashboard.jsx`**
- Main dashboard page
- State management for navigation
- Breadcrumb navigation
- Conditional rendering

**`GoogleBusinessCallback.jsx`**
- Handles OAuth redirect
- Processes authorization code
- Shows loading state
- Redirects to dashboard

---

## 🎯 Common Use Cases

### Use Case 1: Connect Google Business Account

```javascript
import { useGoogleOAuth } from './hooks/useGoogleBusiness';

function MyComponent() {
  const { mutate: connect, isLoading } = useGoogleOAuth();
  
  return (
    <button onClick={() => connect()} disabled={isLoading}>
      {isLoading ? 'Connecting...' : 'Connect Google Business'}
    </button>
  );
}
```

### Use Case 2: Display Reviews

```javascript
import { useGoogleBusinessReviews } from './hooks/useGoogleBusiness';

function ReviewsComponent({ locationId }) {
  const { data, isLoading, error } = useGoogleBusinessReviews(locationId, {
    rating: 5,
    days: 30,
    per_page: 15
  });
  
  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error: {error.message}</div>;
  
  return (
    <div>
      {data.data.map(review => (
        <div key={review.id}>{review.comment}</div>
      ))}
    </div>
  );
}
```

### Use Case 3: Reply to Review

```javascript
import { useReplyToReview } from './hooks/useGoogleBusiness';

function ReplyButton({ reviewId }) {
  const { mutate: reply, isLoading } = useReplyToReview();
  
  const handleReply = () => {
    reply({
      reviewId,
      replyText: 'Thank you for your feedback!'
    });
  };
  
  return (
    <button onClick={handleReply} disabled={isLoading}>
      {isLoading ? 'Posting...' : 'Reply'}
    </button>
  );
}
```

### Use Case 4: Sync Reviews

```javascript
import { useSyncReviews } from './hooks/useGoogleBusiness';

function SyncButton({ locationId }) {
  const { mutate: sync, isLoading } = useSyncReviews();
  
  return (
    <button onClick={() => sync(locationId)} disabled={isLoading}>
      {isLoading ? 'Syncing...' : 'Sync Reviews'}
    </button>
  );
}
```

---

## 🔑 Key Concepts Explained

### React Query Benefits

**1. Automatic Caching**
```javascript
// First call - fetches from server
const { data } = useGoogleBusinessReviews(123);

// Second call (within 5 min) - uses cache
const { data } = useGoogleBusinessReviews(123);
```

**2. Background Refetching**
```javascript
// Automatically refetches when:
// - Window regains focus
// - Network reconnects
// - Interval expires
```

**3. Optimistic Updates**
```javascript
// UI updates immediately, rollback on error
mutate(newData, {
  onMutate: (data) => {
    // Update UI optimistically
  },
  onError: (error, data, context) => {
    // Rollback on error
  }
});
```

### Axios Interceptors

**Request Interceptor:**
```javascript
// Automatically adds token to every request
config.headers.Authorization = `Bearer ${token}`;
```

**Response Interceptor:**
```javascript
// Unwraps data and handles errors
return response.data; // No need for response.data.data
```

---

## 🎨 Styling Guide

The components use **Tailwind CSS** classes. If you're not using Tailwind:

### Option 1: Install Tailwind
```bash
npm install -D tailwindcss
npx tailwindcss init
```

### Option 2: Replace with Your CSS

Replace Tailwind classes with your own:

```javascript
// Before (Tailwind)
<button className="px-4 py-2 bg-blue-600 text-white rounded-md">

// After (Custom CSS)
<button className="btn btn-primary">
```

### Option 3: Use Inline Styles

```javascript
<button style={{
  padding: '8px 16px',
  backgroundColor: '#2563eb',
  color: 'white',
  borderRadius: '6px'
}}>
```

---

## 🐛 Debugging Tips

### Enable React Query DevTools

```javascript
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

<QueryClientProvider client={queryClient}>
  <App />
  <ReactQueryDevtools initialIsOpen={false} />
</QueryClientProvider>
```

### Check Network Requests

```javascript
// In axios.js, add logging
axiosInstance.interceptors.request.use((config) => {
  console.log('Request:', config.method.toUpperCase(), config.url);
  return config;
});

axiosInstance.interceptors.response.use((response) => {
  console.log('Response:', response.status, response.data);
  return response.data;
});
```

### Monitor Query State

```javascript
const { data, isLoading, error, isFetching, isRefetching } = useQuery(...);

console.log({
  isLoading,    // Initial load
  isFetching,   // Any fetch (including background)
  isRefetching, // Background refetch
  error         // Error object
});
```

---

## 📊 Data Flow Diagram

```
User Action
    ↓
Component calls Hook
    ↓
Hook uses React Query
    ↓
React Query calls API Service
    ↓
API Service uses Axios
    ↓
Axios sends HTTP request
    ↓
Laravel API processes
    ↓
Response flows back up
    ↓
React Query caches result
    ↓
Hook returns data to Component
    ↓
Component renders UI
```

---

## 🔒 Security Checklist

- ✅ Store auth token securely (httpOnly cookies recommended)
- ✅ Use HTTPS in production
- ✅ Validate user input before sending
- ✅ Handle errors without exposing sensitive data
- ✅ Implement CORS properly in Laravel
- ✅ Set appropriate cache times
- ✅ Clear sensitive data on logout

---

## 🚀 Performance Tips

### 1. Lazy Load Components
```javascript
const ReviewsList = lazy(() => import('./components/GoogleBusiness/ReviewsList'));
```

### 2. Optimize Re-renders
```javascript
const MemoizedReviewCard = React.memo(ReviewCard);
```

### 3. Prefetch Data
```javascript
const queryClient = useQueryClient();

// Prefetch on hover
const handleMouseEnter = () => {
  queryClient.prefetchQuery({
    queryKey: ['reviews', locationId],
    queryFn: () => fetchReviews(locationId)
  });
};
```

### 4. Pagination
```javascript
// Use pagination instead of loading all reviews
const { data } = useGoogleBusinessReviews(locationId, {
  page: currentPage,
  per_page: 15
});
```

---

## 📝 Customization Examples

### Custom Toast Styling

```javascript
import toast from 'react-hot-toast';

toast.success('Success!', {
  style: {
    background: '#10b981',
    color: '#fff',
  },
  iconTheme: {
    primary: '#fff',
    secondary: '#10b981',
  },
});
```

### Custom Loading Spinner

```javascript
const LoadingSpinner = () => (
  <div className="flex justify-center">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
  </div>
);
```

### Custom Error Display

```javascript
const ErrorMessage = ({ error }) => (
  <div className="bg-red-50 border-l-4 border-red-400 p-4">
    <div className="flex">
      <div className="flex-shrink-0">
        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
        </svg>
      </div>
      <div className="ml-3">
        <p className="text-sm text-red-700">{error.message}</p>
      </div>
    </div>
  </div>
);
```

---

## 🎓 Learning Resources

### React Query
- [Official Docs](https://tanstack.com/query/latest)
- [Video Tutorial](https://www.youtube.com/watch?v=novnyCaa7To)

### Axios
- [Official Docs](https://axios-http.com/)
- [Interceptors Guide](https://axios-http.com/docs/interceptors)

### React Router
- [Official Docs](https://reactrouter.com/)

---

## ✅ Final Checklist

Before going to production:

- [ ] Environment variables configured
- [ ] Error handling implemented
- [ ] Loading states added
- [ ] Toast notifications working
- [ ] OAuth flow tested
- [ ] Review syncing tested
- [ ] Reply functionality tested
- [ ] Responsive design verified
- [ ] CORS configured in Laravel
- [ ] API endpoints secured
- [ ] Token refresh implemented
- [ ] Production build tested

---

## 🎉 You're Ready!

You now have everything you need to integrate Google Business Profile reviews into your React application!

**Need help?** Refer to:
- Part 1: Setup & Configuration
- Part 2: Components & Examples
- This Quick Reference

Happy coding! 🚀
