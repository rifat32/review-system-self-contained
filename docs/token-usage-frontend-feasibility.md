# Feasibility Report: Exposing AI Token Usage on Frontend

## Executive Summary
**Feasible with minor changes.** The foundation is fully in place: the `ServicePlan` model tracks token limits, the `openai_token_usage` table actively tracks real-time consumption, and we recently built the perfect API endpoint (`/api/v1.0/my-subscription`) to deliver this data. The primary change needed is updating the logic to calculate usage based on the actual *billing cycle* (from `business_subscriptions`) rather than a hardcoded calendar month, and appending these aggregated metrics to the existing API response.

---

## 1. Current State: Plan & Subscription Models
Service plans and business limits are clearly defined, with a few gaps in how usage windows are calculated.

- **Models:** 
  - `app/Models/ServicePlan.php` (Line 23): Contains the core `openai_token_limit` field.
  - `app/Models/Business.php` (Lines 298-309): Contains an accessor `getEstimatedReviewsLimitAttribute()` which translates raw tokens into a user-friendly "estimated reviews limit".
- **Gaps:**
  - `Business.php` (Line 311): The `getProcessedReviewsThisMonthAttribute()` accessor currently hardcodes the usage window to the calendar month (`now()->startOfMonth()`). This is incompatible with businesses that subscribe mid-month (e.g., 15th to 15th billing cycle).
  - The plan limit (`openai_token_limit`) is stored in tokens, but the accessors try to convert this to "Reviews". The frontend needs to know whether we are displaying raw Tokens or estimated Reviews to the user.

---

## 2. Current State: API Layer
We have an existing endpoint that perfectly suits this feature, but it needs to be extended.

- **Endpoint:** `GET /api/v1.0/my-subscription`
- **Controller:** `app/Http/Controllers/BusinessSubscriptionController.php` (Line 24)
- **Current Behavior:** It returns the `subscription_status`, `current_plan` (which includes the `openai_token_limit`), and the active `current_subscription` record (which has `start_date` and `end_date`).
- **Authentication:** Protected by `auth:api` middleware, ensuring businesses can only query their own data via `Auth::user()->business_id`.
- **Gaps:** It currently omits the *actual usage* data. We need to append fields like `tokens_used_this_cycle` and `usage_percentage`.

---

## 3. Current State: Frontend
- **Stack:** The Laravel application acts purely as a headless API (there are no Vue/React/Blade frontend application views inside this repository, only email templates in `resources/views`).
- **Relevant Page:** The frontend (e.g., Next.js/React) will likely display this on a "Billing" or "My Subscription" settings page.
- **Data Fetching:** Since the frontend will already be calling `GET /v1.0/my-subscription` to render the subscription status and plan details, appending usage data to this exact same response eliminates the need for any additional network round-trips.

---

## 4. Gaps to Close

### Gap 1: Inaccurate Billing Cycle Usage Calculation
- **File:** `app/Models/Business.php` (Line 281 & 314)
- **What's Missing:** The `SUM()` queries for token usage restrict by `now()->startOfMonth()`. If a user upgrades on the 20th, their usage from the 1st to the 19th will incorrectly count against their new plan's quota.
- **Suggested Fix:** Stop using `startOfMonth()`. Instead, query `openai_token_usage` where `created_at` is between the `$business->current_subscription->start_date` and `end_date`.

### Gap 2: Missing Usage Data in Subscription API
- **File:** `app/Http/Controllers/BusinessSubscriptionController.php` (Line 60)
- **What's Missing:** The API response `data` object lacks usage metrics.
- **Suggested Fix:** Calculate the tokens used within the current subscription's date range and append `tokens_used_this_cycle` and `tokens_limit` to the JSON response.

### Gap 3: Real-Time vs. Periodic Performance
- **What's Missing:** Performing a raw `SUM(total_tokens)` across thousands of `openai_token_usage` rows every time a user opens their dashboard could cause performance degradation over time.
- **Suggested Fix:** For a quick win, run the `SUM()` dynamically but cache the result in Redis for 15-30 minutes. Alternatively, add a `tokens_used` integer column to the `business_subscriptions` table, and increment it using an Eloquent Observer every time a new `OpenAITokenUsage` record is created.

---

## Proposed Implementation Plan

1. **Backend Database (Optional but Recommended):** 
   - Add `tokens_used` (integer, default 0) to the `business_subscriptions` table. 
   - Create an Observer on `OpenAITokenUsage` that increments the `tokens_used` column on the parent `business_subscriptions` record whenever a token log is saved.
2. **Backend API Extension:** 
   - Update `BusinessSubscriptionController::getMySubscription()` to include the usage.
   - Proposed JSON addition:
     ```json
     "usage": {
       "tokens_used": 15400,
       "tokens_limit": 50000,
       "usage_percentage": 30.8,
       "billing_cycle_start": "2026-07-15T00:00:00.000000Z",
       "billing_cycle_end": "2026-08-15T00:00:00.000000Z"
     }
     ```
3. **Frontend Implementation:**
   - Extend the frontend Billing/Subscription component to read the new `usage` object from the existing API response. Render a progress bar (using `usage_percentage`) and text ("15,400 / 50,000 tokens used").

---

## Open Questions for Implementation
1. **Raw Tokens vs. Estimated Reviews:** The backend currently stores `openai_token_limit` but tries to convert this into "Estimated Reviews" for the business models. For the frontend GUI, do you want to show users a progress bar of **raw tokens** (e.g. 15k / 50k tokens), or **reviews processed** (e.g. 15 / 50 reviews)?
2. **Hard Limits:** When a user hits 100% of their limit, should the backend actively block/reject further AI generation, or is this purely a visual warning for now?
3. **Performance:** Are you comfortable with caching the SUM query for ~15 minutes to save DB load, or do we strictly need real-time tracking (which requires adding the `tokens_used` column to `business_subscriptions`)?
