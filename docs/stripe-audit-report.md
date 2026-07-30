# Stripe Integration Audit Report

**Generated:** 2026-07-30  
**Project:** FeedGenius — `review-system-self-contained`  
**Auditor:** Antigravity (automated discovery pass — no code changes applied)

---

## Executive Summary

The Stripe integration utilizes Stripe Checkout Sessions, which successfully offloads PCI compliance and sensitive card handling to Stripe. Key security practices like server-side price validation and webhook signature verification are correctly implemented. However, the system has **critical architectural vulnerabilities**: environment variables for Stripe keys are missing from `.env` files, webhooks lack idempotency checks (risking duplicate subscription records), and the webhook handler fundamentally misinterprets Order payments as Subscription renewals, completely failing to mark Orders as paid. 

---

## 🚨 Critical Vulnerabilities

### 1. Webhook Misinterprets Order Payments as Subscriptions (Data Corruption)
- **Location:** `app/Http/Controllers/CustomWebhookController.php` lines 71-117 (`handleChargeSucceeded`)
- **Explanation:** When a user pays for an `Order` via `StripeController`, it triggers a `checkout.session.completed` webhook. The `CustomWebhookController` catches this but **assumes it is a subscription payment**. If `metadata["service_plan_id"]` is missing (which it is for Orders), the code falls back to `$user->business->service_plan_id` (line 85) and creates a new `BusinessSubscription` instead of updating the `Order` status. 
- **Result:** Orders are paid for in Stripe but never marked as paid in the local database. Instead, ghost subscriptions are created, leading to massive data corruption.
- **Snippet:**
```php
$service_plan = !empty($metadata["service_plan_id"])
    ? ServicePlan::find($metadata["service_plan_id"])
    : ServicePlan::find($user->business->service_plan_id); // Falls back to existing plan for Orders!
```

### 2. Missing Idempotency in Webhooks
- **Location:** `app/Http/Controllers/CustomWebhookController.php` lines 92-102
- **Explanation:** Stripe guarantees "at least once" delivery of webhooks, meaning they occasionally send the same event twice. The webhook handler immediately runs `BusinessSubscription::create(...)` without checking if the `transaction_id` (`$data['id']`) already exists in the database.
- **Result:** A duplicate webhook will result in duplicate active subscription rows and multiple "UserRegistered" emails being sent.

---

## 1. Key Storage & Security Findings

| Severity | Issue | Location |
|---|---|---|
| 🟠 High | **Stripe Keys missing from `.env` and `.env.example`** | `.env` / `.env.example` |

- **Explanation:** While the codebase securely reads keys from the environment via `config('cashier.stripe.secret')`, the variables `STRIPE_KEY`, `STRIPE_SECRET`, and `STRIPE_WEBHOOK_SECRET` are completely missing from `.env.example`. This means new deployments will default to `null` keys, causing 500 Server Errors when attempting to create a Checkout Session.
- **Config mapping:** `config/cashier.php` maps these correctly:
```php
// config/cashier.php:20
'secret' => env('STRIPE_SECRET'),
```
- **PCI Compliance (🟢 Pass):** Raw card numbers are NEVER touched. All flows redirect the user to Stripe's hosted Checkout Session.

---

## 2. Payment Initiation Flow

### Flow A: Order Payments (`StripeController`)
- **Route:** `GET /orders/redirect-to-stripe`
- **Method:** `app/Http/Controllers/StripeController.php` line 17 (`redirectUserToStripe`)
- **Security Check (🟢 Pass):** Trust is maintained server-side. The price is calculated from the local database `Order` model, preventing frontend price manipulation.
```php
// app/Http/Controllers/StripeController.php:75
'unit_amount' => $order->amount * 100, // Amount in cents
```
- **Bug (🟡 Medium):** Unhandled Stripe Exceptions. There is no `try/catch` around `Session::create($session_data);` (line 118). If the API key is missing or Stripe is down, the user receives an unhandled 500 error instead of a graceful failure message.

### Flow B: Subscriptions (`SubscriptionController`)
- **Route:** `GET /subscription/redirect`
- **Method:** `app/Http/Controllers/SubscriptionController.php` line 20 (`redirectUserToStripe`)
- **Security Check (🟢 Pass):** Price is fetched securely via `ServicePlan::where(...)`.
- **Logic:** Registers a Stripe Customer if missing (`$user->stripe_id`), creates a Checkout Session for recurring payments, and properly applies a coupon if a discount exists (lines 111-124).

---

## 3. Webhook Handling Findings

- **Endpoint:** `POST api/webhooks/stripe` (`routes/api.php` line 69)
- **Signature Verification (🟢 Pass):** The controller properly verifies the Stripe signature to prevent spoofed webhook payloads.
```php
// app/Http/Controllers/CustomWebhookController.php:35-39
$event = Webhook::constructEvent(
    $payload,
    $sigHeader,
    $endpointSecret
);
```
- **Events Handled:**
  - `checkout.session.completed` -> Triggers `handleChargeSucceeded()`
  - `invoice.payment_succeeded` -> Triggers `handleSubscriptionPaymentSucceeded()`

---

## 4. Payment Confirmation & Data Persistence

- **Order Status is NEVER updated (🔴 Critical):** When an Order succeeds, Stripe redirects to `stripePaymentSuccess` (`StripeController.php:126`), which simply redirects the user to the frontend (`redirect()->away(...)`). Because the webhook handler ignores Order events (see Critical Vulnerabilities), the `Order` model is **never** marked as paid.
- **Subscription Creation (🟢 Pass):** On successful payment, a new `BusinessSubscription` is created and the `businesses.openai_token_limit` is synchronized properly.
```php
// app/Http/Controllers/CustomWebhookController.php:105-108
$user->business->update([
    'openai_token_limit' => $service_plan->openai_token_limit,
    'service_plan_id' => $service_plan->id
]);
```

---

## 5. Error Handling, Refunds, & Cancellations

- **Silent API Failures (🟠 High):** No fallback logic exists for API failures during session creation.
- **Refunds & Cancellations (🟠 High):** There are absolutely no routes, controllers, or API calls defined to handle canceling a subscription or refunding a user. If a user wants to cancel, an admin must do it manually via the Stripe Dashboard, which will currently NOT sync the cancellation back to the app (since `customer.subscription.deleted` is not handled in the webhook).
- **Payment Decline:** Handled securely by Stripe Hosted Checkout. If the user clicks "Cancel" on Stripe, they are redirected to `stripePaymentFailed` which logs/emails the failure and redirects them back.

---

## Recommendations

### Fix Immediately (Critical)
1. **Fix Webhook Routing:** Update `CustomWebhookController::handleChargeSucceeded` to check the `metadata` for `product_id` vs `service_plan_id`. If it's an order, update the `Order` table. If it's a subscription, create a `BusinessSubscription`.
2. **Add Idempotency:** Before inserting a `BusinessSubscription` or updating an `Order`, query the database for the `transaction_id` (`$data['id']`). If it exists, `return response()->json(['message' => 'Already processed']);`.

### Quick Wins (High)
3. **Update `.env.example`:** Add `STRIPE_KEY=`, `STRIPE_SECRET=`, and `STRIPE_WEBHOOK_SECRET=` so other developers know these are required.
4. **Wrap API Calls in `try/catch`:** Wrap `Session::create()` calls in the controllers to catch `\Stripe\Exception\ApiErrorException` and return a user-friendly JSON error instead of a stack trace.

### Longer-Term Improvements
5. **Handle Cancellation Webhooks:** Add a listener in `CustomWebhookController` for `customer.subscription.deleted` to automatically revoke access when a user cancels in Stripe.
6. **Implement In-App Cancellation:** Build an endpoint that calls `$stripe->subscriptions->cancel('sub_123')` so users can cancel without contacting support.
