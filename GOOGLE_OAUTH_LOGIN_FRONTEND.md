# Google OAuth - Axios + TanStack Query

## Axios Setup

```javascript
// src/lib/axios.js
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://127.0.0.1:8000',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

## Auth Service

```javascript
// src/services/authService.js
import api from '../lib/axios';

const authService = {
  // Redirect to Google OAuth
  loginWithGoogle: () => {
    const apiUrl = process.env.REACT_APP_API_URL || 'http://127.0.0.1:8000';
    window.location.href = `${apiUrl}/auth/google/redirect`;
  },

  // Get current user
  getUser: async () => {
    const response = await api.get('/api/user');
    return response.data;
  },

  // Logout
  logout: () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
  },
};

export default authService;
```

---

## TanStack Query Hook

```javascript
// src/hooks/useAuth.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import authService from '../services/authService';

export const useAuth = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const token = localStorage.getItem('auth_token');

  // Get user query
  const { data: user, isLoading } = useQuery({
    queryKey: ['user'],
    queryFn: authService.getUser,
    enabled: !!token,
    retry: false,
  });

  // Login (just redirects)
  const loginWithGoogle = () => {
    authService.loginWithGoogle();
  };

  // Logout mutation
  const { mutate: logout } = useMutation({
    mutationFn: () => {
      authService.logout();
      return Promise.resolve();
    },
    onSuccess: () => {
      queryClient.clear();
      navigate('/login');
    },
  });

  return {
    user,
    isLoading,
    isAuthenticated: !!user,
    loginWithGoogle,
    logout,
  };
};
```

---

## Callback Handler

```javascript
// src/pages/AuthCallback.jsx
import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import api from '../lib/axios';

function AuthCallback() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryClient = useQueryClient();

  useEffect(() => {
    const token = searchParams.get('token');
    const error = searchParams.get('error');

    if (error) {
      navigate('/login?error=' + error);
      return;
    }

    if (!token) {
      navigate('/login');
      return;
    }

    // Store token
    localStorage.setItem('auth_token', token);

    // Fetch and cache user
    api.get('/api/user')
      .then((res) => {
        queryClient.setQueryData(['user'], res.data);
        navigate('/dashboard');
      })
      .catch(() => {
        localStorage.removeItem('auth_token');
        navigate('/login?error=auth_failed');
      });
  }, [searchParams, navigate, queryClient]);

  return <div>Loading...</div>;
}

export default AuthCallback;
```

---

## Usage

```javascript
// In any component
import { useAuth } from '../hooks/useAuth';

function MyComponent() {
  const { user, isLoading, loginWithGoogle, logout } = useAuth();

  if (isLoading) return <div>Loading...</div>;

  return (
    <div>
      {user ? (
        <>
          <p>Welcome, {user.first_Name}!</p>
          <button onClick={logout}>Logout</button>
        </>
      ) : (
        <button onClick={loginWithGoogle}>Login with Google</button>
      )}
    </div>
  );
}
```

---

## Endpoints

```
GET /auth/google/redirect    - Start OAuth
GET /auth/google/callback    - Handle callback
GET /api/user                - Get user (requires token)
```
