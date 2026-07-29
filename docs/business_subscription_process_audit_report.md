# End-to-End Audit: Business Registration & Subscription Process

This document provides a complete audit of the codebase involved in the business lifecycle—from initial registration to plan updates and Stripe subscription tracking. 

---

## 1. Registration Flow (Owner Controller)

**Entry Point:** `routes/api.php` (`POST /owner/user/registration`)
**Controller:** `app/Http/Controllers/OwnerController.php`
**Method:** `createUserWithBusinessClient()`

### Code Logic:
- Validates the incoming payload via `CreateUserWithBusinessRequest`.
- Wraps the entire operation in a `DB::transaction()` to ensure the user, business, and settings are either completely saved or fully rolled back.
- Looks up the requested `ServicePlan` to find the default `free_trial_duration_days`.
- Creates the `User` owner account.
- Delegates the actual business record creation to the `BusinessProfileService`.
- Sets up default `SurveyPageSetting` and `QrCodeSetting` for the newly created business.

---

## 2. Business Profile Creation & Trial Sync

**Service:** `app/Services/Business/BusinessProfileService.php`
**Method:** `createBusinessWithSchedule()` -> `createBusiness()`

### Code Logic:
- Handles discount calculations using `DiscountUtil`.
- Calculates the `trial_end_date` dynamically (using the passed payload, or defaulting to the `ServicePlan`'s `free_trial_duration_date`, or 14 days fallback).
- Inserts a new record into the `businesses` table with the `service_plan_id`, `start_date`, and `trial_end_date`.
- **Subscription Sync:** Instantly creates a "trial" record in the `business_subscriptions` table. It sets `status = 'active'`, `stripe_status = 'trialing'`, and `amount = 0`. This ensures manual registrations have a valid history record from Day 1.
- Creates the business opening hours schedule (`BusinessDay`).

---

## 3. Stripe Payment & Webhook Handling

**Entry Point:** `routes/api.php` (`POST webhooks/stripe`)
**Controller:** `app/Http/Controllers/CustomWebhookController.php`
**Methods:** `handleChargeSucceeded()` and `handleSubscriptionPaymentSucceeded()`

### Code Logic:
- Listens for `checkout.session.completed` (first payment) and `invoice.payment_succeeded` (renewals) from Stripe.
- Looks up the associated `User` and `ServicePlan`.
- Inserts a **new** paid record into `business_subscriptions` containing the payment `amount`, the `transaction_id`, and sets `status = 'active'`. 
- Overwrites the `service_plan_id` and `openai_token_limit` on the `businesses` table to keep the active state synchronized with Stripe's truth.
- Dispatches notification emails to administrators (`UserRegistered` or `UserSubscriptionRenewed`).

---

## 4. Manual Admin Updates (Plan Change Tracking)

**Entry Point:** `routes/api.php` (`PUT /v1.0/businesses/{businessId}`)
**Controller:** `app/Http/Controllers/BusinessController.php`
**Method:** `UpdateBusiness()`

### Code Logic:
- Verifies that the user owns the business or has `superadmin` privileges.
- Wraps the update in a `DB::transaction()`.
- **Plan Change Detection:** Compares the payload's `service_plan_id` with the current `$business->service_plan_id`.
- If a change is detected:
  1. Finds any active subscriptions for this business in `business_subscriptions` and updates them to `status = 'canceled'` (converting them to history).
  2. Calculates the new `end_date` (prioritizing the passed `trial_end_date` from the payload, or falling back to the new plan's duration).
  3. Creates a new active entry in `business_subscriptions` with `stripe_status = 'manual'` to indicate it was changed by an admin, not Stripe.
- Finally, updates the `businesses` table with the new payload.

---

## 5. Model Boot Hooks & Relationships

**Model:** `app/Models/Business.php`
**Method:** `boot()` -> `static::updating()`

### Code Logic:
- Listens for any updates to the `Business` model.
- If the `service_plan_id` is modified, it executes a query to delete any `BusinessModule` overrides associated with the business: `\App\Models\BusinessModule::where('business_id', $business->id)->delete()`. This forces the business to inherit the modules belonging to the new plan.
- Provides relationships like `current_subscription` (fetching only the active record that hasn't passed its end date).

---

## 6. Frontend API — Data Retrieval

**Entry Point:** `routes/api.php` (`GET /v1.0/my-subscription`)
**Controller:** `app/Http/Controllers/BusinessSubscriptionController.php`
**Method:** `getMySubscription()`

### Code Logic:
- Fetches the authenticated user's business alongside all subscription history (`subscriptions` relationship) and the current plan configuration.
- Calculates if the user is in an active trial window based on the `trial_end_date` located on the `businesses` table.
- Determines a single unified `subscription_status` (`trial`, `active`, `expired`, or `none`) by combining logic from the trial window and the `stripe_status` of the most recent `business_subscriptions` record.
- Returns the data required for the UI to block features or prompt upgrades.
