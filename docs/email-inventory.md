# Complete Email & Mailable Audit Report

**Project**: FeedGenius Review System (`review-system-self-contained`)  
**Generated Date**: August 1, 2026  
**Audited Directory**: `/run/media/md-rony-mia/2f07b954-9593-455e-a276-21daa6c5d9c3/feed-genius/review-system-self-contained`

---

## 1. Executive Summary

This document provides a comprehensive inventory of all email notifications and Mailable classes in this Laravel application. It is designed as a practical reference for frontend developers and QA engineers to trigger, inspect, and test every email flow without reading backend source code.

* **Total Mailables Found**: 17 Mailable classes (all located in `app/Mail/`).
* **Laravel Notifications (`app/Notifications/`)**: 0 (the project uses Mailable classes exclusively).
* **Mail Driver Configured (`.env`)**: `MAIL_DRIVER=smtp` (Host: `mail.smartcollegeportal.com`, Port: `465`).
* **Queue Connection (`.env`)**: `QUEUE_CONNECTION=sync` (Emails are dispatched synchronously during the HTTP request lifecycle).
* **Email Feature Flag (`.env.example`)**: `SEND_EMAIL=FALSE` (Certain registration and webhook email dispatches are guarded by `if (env("SEND_EMAIL") == true)`).

---

## 2. Mail Driver & Queue Configuration

### Mail Driver Check (`.env` / `.env.example`)
* **Active Driver**: `smtp`
* **Host**: `mail.smartcollegeportal.com`
* **Port**: `465` (SSL)
* **From Address**: `development-dont-reply@smartcollegeportal.com`
* **From Name**: `FeedGenius`

> [!WARNING]
> **Testing Environment Note**: If `SEND_EMAIL=FALSE` in `.env`, some email dispatches inside `CustomWebhookController` and `OwnerController` will be bypassed. To test emails locally or via SMTP/log, ensure `SEND_EMAIL=TRUE` or `SEND_EMAIL=true` in your local `.env`.

### Queue Configuration Check (`.env` / `.env.example`)
* **Queue Connection**: `QUEUE_CONNECTION=sync`
* **Behavior**: All Mailables (including those implementing `ShouldQueue` like `RuleAlertMail`) will execute **immediately and synchronously** within the HTTP request thread.
* **Worker Requirement**: No background queue worker (`php artisan queue:work`) is required while `QUEUE_CONNECTION=sync`. If changed to `database`, `redis`, or `sqs`, a queue worker **must** be running to process queued emails.

---

## 3. Email Inventory Table

| # | Email Name | Mailable Class | Primary Blade View | Trigger Method / Location | Queued? | How to Test |
|---|---|---|---|---|---|---|
| 1 | Forgot Password | `ForgetPasswordMail` | `mail.reset_password` | `ForgotPasswordController.php:105` | No | `POST /v1.0/forgot-password` with registered email |
| 2 | Resend Email Verification | `VerifyMail` | `mail.dynamic_mail` | `AuthController.php:110` | No | `POST /resend-email-verify-mail` with email |
| 3 | Web Verification Resend | `ResendVerificationMail` | `mail.resend_verification` | `routes/web.php:283` | No | `GET /resend-verification/{id}` |
| 4 | Client Account Creation | `AccountCreateMail` | `mail.account_created` | `OwnerController.php:433` | No | `POST /v1.0/client/create-user-with-business` |
| 5 | New Business Admin Alert | `NewBusinessAdminNotification` | `email.admin_business_registered` | `OwnerController.php:437` | No | `POST /v1.0/client/create-user-with-business` |
| 6 | User Registration Notify | `NotifyMail` | `mail.dynamic_mail` | `OwnerController.php:183`, `UserService.php:55` | No | `POST /owner/user/registration` |
| 7 | Staff/Manager Welcome | `ManagerWelcomeMail` | `mail.manager_welcome` | `UserController.php:913` | No | `POST /v1.0/users` (Admin creating staff) |
| 8 | Developer OTP Code | `DevOtpMail` | `mail.dev_otp` | `DevAccessController.php:80` | No | `POST /dev-send-otp` with allowed dev email |
| 9 | Public Contact Form | `ContactFormMail` | `mail.contact_template` | `EmailController.php:70` | No | `POST /v1.0/client/email/send-email` |
| 10 | Admin Business Signup Alert | `UserRegistered` | `email.user_registered` | `CustomWebhookController.php:118, 308` | No | Trigger Stripe Checkout/PaymentIntent webhook |
| 11 | Subscription Renewed Alert | `UserSubscriptionRenewed` | `email.user_subscription_renewed` | `CustomWebhookController.php:193` | No | Trigger Stripe `invoice.payment_succeeded` webhook |
| 12 | Payment Success Confirmation | `UserPaymentSuccess` | `email.user_payment_success` | `CustomWebhookController.php:307` | No | Trigger Stripe `payment_intent.succeeded` webhook |
| 13 | Payment Failure Alert | `UserPaymentFailed` | `email.user_payment_failed` | `SubscriptionController.php:236`, `CustomWebhookController.php:360` | No | Trigger `GET /subscription/failed` or Stripe failure webhook |
| 14 | Review Notification Alert | `ReviewNotificationMail` | `mail.review_notification` | `ReviewService.php:472, 523, 589` | No | `POST /v1.0/reviews/{businessId}` (Submit review) |
| 15 | AI Rule Triggered Alert | `RuleAlertMail` | Inline HTML | `RuleExecutionService.php:326` | Yes (`ShouldQueue`) | `php artisan rules:execute-scheduled` |
| 16 | Scheduled User Review Report | `UserReviewReportMail` | `user-review-report-mail` | `UserReviewReport.php:259` | No | `php artisan user_review_report:generate` |
| 17 | Scheduled Guest Review Report | `GuestUserReviewReportMail` | `guest-user-review-report-mail` | `GuestUserReviewReport.php:263` | No | `php artisan guest_user_review_report:generate` |

---

## 4. Detailed Trigger Reference

### 1. `ForgetPasswordMail`
* **File Path**: `app/Mail/ForgetPasswordMail.php`
* **Class Name**: `App\Mail\ForgetPasswordMail`
* **View Rendered**:
  ```php
  return $this->subject(subject: 'Reset Your Password')
      ->view(view: 'mail.reset_password', data: [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $token)
  ```
* **Subject Line**: `"Reset Your Password"`
* **Trigger Point**: `app/Http/Controllers/ForgotPasswordController.php:105`
* **Trigger Code Snippet**:
  ```php
  Mail::to($user->email)->send(new ForgetPasswordMail($user, $token));
  ```
* **Route**: `POST /v1.0/forgot-password` (`ForgotPasswordController::storeForgetPassword`)
* **Conditions / Guards**: Sent if the requested email matches an existing active user account.
* **How to Test**: Submit a `POST` request to `/v1.0/forgot-password` with JSON payload `{"email": "user@example.com"}` via Postman or curl.

---

### 2. `VerifyMail`
* **File Path**: `app/Mail/VerifyMail.php`
* **Class Name**: `App\Mail\VerifyMail`
* **View Rendered**:
  ```php
  return $this->view('mail.dynamic_mail', ["html_content" => $html_final]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user)
  ```
* **Subject Line**: Dynamic (Resolved from database table `email_templates` where `type = 'email_verification_mail'`).
* **Trigger Point**: `app/Http/Controllers/AuthController.php:110`
* **Trigger Code Snippet**:
  ```php
  Mail::to($user->email)->send(new VerifyMail($user));
  ```
* **Route**: `POST /resend-email-verify-mail` (`AuthController::resendEmailVerifyByToken`)
* **Conditions / Guards**: `if ($user->email_verified_at)` returns 400 Bad Request ("Email is already verified"). Sent only if user email is unverified.
* **How to Test**: Send a `POST` request to `/resend-email-verify-mail` with `{"email": "unverified@example.com"}`.

---

### 3. `ResendVerificationMail`
* **File Path**: `app/Mail/ResendVerificationMail.php`
* **Class Name**: `App\Mail\ResendVerificationMail`
* **View Rendered**:
  ```php
  return $this->subject('Verify Your Email Address - ' . config('app.name'))
              ->view('mail.resend_verification')
              ->with([...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $verificationUrl)
  ```
* **Subject Line**: `"Verify Your Email Address - " . config('app.name')`
* **Trigger Point**: `routes/web.php:283`
* **Trigger Code Snippet**:
  ```php
  Mail::to($user->email)->send(new ResendVerificationMail($user, $verificationUrl));
  ```
* **Route**: `GET /resend-verification/{id}`
* **Conditions / Guards**: Aborts with 404 if user ID does not exist, or 400 if user email is already verified.
* **How to Test**: Access `GET http://localhost:8000/resend-verification/{userId}` in your web browser or Postman.

---

### 4. `AccountCreateMail`
* **File Path**: `app/Mail/AccountCreateMail.php`
* **Class Name**: `App\Mail\AccountCreateMail`
* **View Rendered**:
  ```php
  return $this->subject('Welcome to ' . config('app.name'))
              ->view('mail.account_created')
              ->with([...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $verificationUrl)
  ```
* **Subject Line**: `"Welcome to " . config('app.name')`
* **Trigger Point**: `app/Http/Controllers/OwnerController.php:433`
* **Trigger Code Snippet**:
  ```php
  Mail::to($validatedData["email"])->send(new AccountCreateMail($user, $verificationUrl));
  ```
* **Route**: `POST /v1.0/client/create-user-with-business` (`OwnerController::createUserWithBusinessClient`)
* **Conditions / Guards**: Fails if validation fails (e.g. duplicate email, missing service_plan_id).
* **How to Test**: Submit the public business registration form or `POST` payload to `/v1.0/client/create-user-with-business`.

---

### 5. `NewBusinessAdminNotification`
* **File Path**: `app/Mail/NewBusinessAdminNotification.php`
* **Class Name**: `App\Mail\NewBusinessAdminNotification`
* **View Rendered**:
  ```php
  return $this->subject("New Business Registered: " . ($this->business->Name ?? 'N/A'))
              ->view('email.admin_business_registered');
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $business, $planName)
  ```
* **Subject Line**: `"New Business Registered: " . ($this->business->Name ?? 'N/A')`
* **Trigger Point**: `app/Http/Controllers/OwnerController.php:437`
* **Trigger Code Snippet**:
  ```php
  $adminEmails = ['info@feedgenius.ai', 'rifatbilalphilips@gmail.com', 'rony.mia7800@gmail.com'];
  Mail::to($adminEmails)->send(new \App\Mail\NewBusinessAdminNotification($user, $business, $plan->name ?? 'N/A'));
  ```
* **Route**: `POST /v1.0/client/create-user-with-business` (`OwnerController::createUserWithBusinessClient`)
* **Conditions / Guards**: Dispatched alongside `AccountCreateMail` when a new business signs up.
* **How to Test**: Submit a new business signup payload to `POST /v1.0/client/create-user-with-business`.

---

### 6. `NotifyMail`
* **File Path**: `app/Mail/NotifyMail.php`
* **Class Name**: `App\Mail\NotifyMail`
* **View Rendered**:
  ```php
  return $this->view('mail.dynamic_mail', ["html_content" => $html_final]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user)
  ```
* **Subject Line**: Dynamic (Resolved from `EmailTemplate` table where `type = 'email_verification_mail'`).
* **Trigger Points**:
  1. `app/Http/Controllers/OwnerController.php:183`
     ```php
     if (env("SEND_EMAIL") == "TRUE") {
         Mail::to($validatedData["email"])->send(new NotifyMail($user));
     }
     ```
  2. `app/Services/User/UserService.php:55`
     ```php
     Mail::to($email)->send(new NotifyMail($user));
     ```
* **Routes**: `POST /owner/user/registration` (`OwnerController::createUser2`)
* **Conditions / Guards**: `env("SEND_EMAIL") == "TRUE"`.
* **How to Test**: Send `POST` to `/owner/user/registration` with new user details while `SEND_EMAIL=TRUE`.

---

### 7. `ManagerWelcomeMail`
* **File Path**: `app/Mail/ManagerWelcomeMail.php`
* **Class Name**: `App\Mail\ManagerWelcomeMail`
* **View Rendered**:
  ```php
  return $this->view('mail.manager_welcome', [...])
              ->subject('Welcome to ' . $this->businessName . ' - ' . $roleName . ' Account Created');
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $password, $businessName)
  ```
* **Subject Line**: `"Welcome to " . $this->businessName . " - " . $roleName . " Account Created"`
* **Trigger Point**: `app/Http/Controllers/UserController.php:913`
* **Trigger Code Snippet**:
  ```php
  Mail::to($user->email)->send(new ManagerWelcomeMail($user, $plainPassword, $business_name));
  ```
* **Route**: `POST /v1.0/users` (`UserController::createUser`)
* **Conditions / Guards**: Requires bearer token authentication (`auth:api`) with permission to add staff/manager members.
* **How to Test**: As an authenticated business owner/admin, send `POST /v1.0/users` with payload `{"email": "manager@example.com", "first_Name": "Jane", "last_Name": "Doe", "role": "manager", "branch_id": 1}`.

---

### 8. `DevOtpMail`
* **File Path**: `app/Mail/DevOtpMail.php`
* **Class Name**: `App\Mail\DevOtpMail`
* **View Rendered**:
  ```php
  return $this->subject('Developer Verification Code - ' . config('app.name'))
              ->view('mail.dev_otp');
  ```
* **Constructor Signature**:
  ```php
  public function __construct($otp)
  ```
* **Subject Line**: `"Developer Verification Code - " . config('app.name')`
* **Trigger Point**: `app/Http/Controllers/DevAccessController.php:80`
* **Trigger Code Snippet**:
  ```php
  Mail::to($email)->send(new DevOtpMail($otp));
  ```
* **Route**: `POST /dev-send-otp` (`DevAccessController::sendOtp`)
* **Conditions / Guards**: Sent only if the submitted email exists in `DEV_ACCESS_EMAILS` env list (e.g. `rifatbilalphilips@gmail.com`).
* **How to Test**: Submit `POST /dev-send-otp` with `{"email": "rifatbilalphilips@gmail.com"}` from the developer login page (`GET /dev-login`).

---

### 9. `ContactFormMail`
* **File Path**: `app/Mail/ContactFormMail.php`
* **Class Name**: `App\Mail\ContactFormMail`
* **View Rendered**:
  ```php
  return $this->from($fromAddress, $fromName)
              ->replyTo(...)
              ->subject('New Contact Message: ' . ($this->data['subject'] ?? ''))
              ->view('mail.contact_template');
  ```
* **Constructor Signature**:
  ```php
  public function __construct($data)
  ```
* **Subject Line**: `"New Contact Message: " . ($this->data['subject'] ?? '')`
* **Trigger Point**: `app/Http/Controllers/EmailController.php:70`
* **Trigger Code Snippet**:
  ```php
  Mail::to([$receiverEmail, "rifatblalphilips@gmail.com"])->send(new ContactFormMail($validated));
  ```
* **Route**: `POST /v1.0/client/email/send-email` (`EmailController::sendEmail`)
* **Conditions / Guards**: Requires valid fields: `first_name`, `last_name`, `email`, `subject`, `message` (min 10 chars).
* **How to Test**: Send a `POST` request to `/v1.0/client/email/send-email` with JSON `{"first_name": "Test", "last_name": "User", "email": "test@example.com", "subject": "Inquiry", "message": "Hello, this is a test message."}`.

---

### 10. `UserRegistered`
* **File Path**: `app/Mail/UserRegistered.php`
* **Class Name**: `App\Mail\UserRegistered`
* **View Rendered**:
  ```php
  return $this->subject("New Business Alert: " . ($business->Name ?? 'N/A') . " registered")
              ->view('email.user_registered', [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $subscription)
  ```
* **Subject Line**: `"New Business Alert: " . ($business->Name ?? 'N/A') . " registered"`
* **Trigger Points**:
  1. `app/Http/Controllers/CustomWebhookController.php:118` (Stripe `checkout.session.completed` webhook)
     ```php
     Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com'])->send(new UserRegistered($user, $subscription));
     ```
  2. `app/Http/Controllers/CustomWebhookController.php:308` (Stripe `payment_intent.succeeded` webhook)
     ```php
     Mail::to(users: ['kids20acc@gmail.com', 'ralashwad@gmail.com', 'rony.mia7800@gmail.com'])->send(mailable: new UserRegistered(user: $user, subscription: $subscription));
     ```
* **Route**: `POST /webhooks/stripe` (`CustomWebhookController::handleStripeWebhook`)
* **Conditions / Guards**: Requires `env("SEND_EMAIL") == true` and valid Stripe webhook payload containing business metadata.
* **How to Test**: Simulate a Stripe `checkout.session.completed` or `payment_intent.succeeded` webhook event targeting `POST /webhooks/stripe` (using Stripe CLI or Postman).

---

### 11. `UserSubscriptionRenewed`
* **File Path**: `app/Mail/UserSubscriptionRenewed.php`
* **Class Name**: `App\Mail\UserSubscriptionRenewed`
* **View Rendered**:
  ```php
  return $this->subject(subject: "Subscription Renewed: " . $business_name)
              ->view(view: 'email.user_subscription_renewed', data: [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, $subscription)
  ```
* **Subject Line**: `"Subscription Renewed: " . $business_name`
* **Trigger Point**: `app/Http/Controllers/CustomWebhookController.php:193`
* **Trigger Code Snippet**:
  ```php
  $recipients = array_filter(array_unique([$user->email, 'kids20acc@gmail.com', 'ralashwad@gmail.com', 'rony.mia7800@gmail.com']));
  Mail::to(users: $recipients)->send(mailable: new UserSubscriptionRenewed(user: $user, subscription: $subscription));
  ```
* **Route**: `POST /webhooks/stripe` (`CustomWebhookController::handleStripeWebhook`)
* **Conditions / Guards**: `if (env("SEND_EMAIL") == true)` and Stripe event `invoice.payment_succeeded` with `billing_reason == 'subscription_cycle'`.
* **How to Test**: Send a Stripe `invoice.payment_succeeded` mock payload to `POST /webhooks/stripe`.

---

### 12. `UserPaymentSuccess`
* **File Path**: `app/Mail/UserPaymentSuccess.php`
* **Class Name**: `App\Mail\UserPaymentSuccess`
* **View Rendered**:
  ```php
  return $this->subject(subject: 'Payment Confirmation: Thank You for Your Purchase')
              ->view(view: 'email.user_payment_success', data: [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, array $paymentDetails = [])
  ```
* **Subject Line**: `"Payment Confirmation: Thank You for Your Purchase"`
* **Trigger Point**: `app/Http/Controllers/CustomWebhookController.php:307`
* **Trigger Code Snippet**:
  ```php
  Mail::to(users: $recipients)->send(mailable: new UserPaymentSuccess(user: $user, paymentDetails: $payment_details));
  ```
* **Route**: `POST /webhooks/stripe` (`CustomWebhookController::handleStripeWebhook`)
* **Conditions / Guards**: `if (env("SEND_EMAIL") == true)` during `payment_intent.succeeded`.
* **How to Test**: Dispatch a Stripe `payment_intent.succeeded` webhook event payload to `POST /webhooks/stripe`.

---

### 13. `UserPaymentFailed`
* **File Path**: `app/Mail/UserPaymentFailed.php`
* **Class Name**: `App\Mail\UserPaymentFailed`
* **View Rendered**:
  ```php
  return $this->subject(subject: 'Action Required: Payment Failed Alert')
              ->view(view: 'email.user_payment_failed', data: [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($user, array $paymentDetails = [])
  ```
* **Subject Line**: `"Action Required: Payment Failed Alert"`
* **Trigger Points**:
  1. `app/Http/Controllers/SubscriptionController.php:236`
     ```php
     Mail::to(users: $recipients)->send(mailable: new UserPaymentFailed(user: $user, paymentDetails: $payment_details));
     ```
     Route: `GET /subscription/failed` (`SubscriptionController::stripePaymentFailed`)
  2. `app/Http/Controllers/CustomWebhookController.php:360`
     ```php
     Mail::to(users: $recipients)->send(mailable: new UserPaymentFailed(user: $user, paymentDetails: $payment_details));
     ```
     Route: `POST /webhooks/stripe` (Stripe `payment_intent.payment_failed`)
* **Conditions / Guards**: `if (env("SEND_EMAIL") == true)`.
* **How to Test**: Request `GET http://localhost:8000/subscription/failed?user_id={base64EncodedUserId}` or send a `payment_intent.payment_failed` Stripe webhook payload.

---

### 14. `ReviewNotificationMail`
* **File Path**: `app/Mail/ReviewNotificationMail.php`
* **Class Name**: `App\Mail\ReviewNotificationMail`
* **View Rendered**:
  ```php
  return $this->subject(subject: $this->title)
              ->view(view: 'mail.review_notification', data: [...]);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($title, $messageBody, $rating = null, $businessName = null, $userName = null)
  ```
* **Subject Line**: Dynamic (Passed via `$title` constructor argument, e.g., `"New 5-Star Review Received!"` or `"Low Rating Review Alert"`).
* **Trigger Points**:
  1. `app/Services/Review/ReviewService.php:472` (Notifies Branch Manager)
  2. `app/Services/Review/ReviewService.php:523` (Notifies Business Owner)
  3. `app/Services/Review/ReviewService.php:589` (Notifies Low Rating Alert recipient)
* **Routes**:
  - `POST /v1.0/reviews/{businessId}` (`ReviewNewController::createReviewByCustomer`)
  - `POST /v1.0/client/reviews/{businessId}/guest` (`ReviewNewController::storeReviewByGuest`)
* **Conditions / Guards**: Triggered automatically when a customer or guest submits a new review with valid ratings/answers.
* **How to Test**: Submit a review via `POST /v1.0/reviews/{businessId}` or `POST /v1.0/client/reviews/{businessId}/guest`.

---

### 15. `RuleAlertMail`
* **File Path**: `app/Mail/RuleAlertMail.php`
* **Class Name**: `App\Mail\RuleAlertMail`
* **Implements**: `ShouldQueue`
* **View Rendered**:
  ```php
  return $this->subject("AI Rule Alert: {$this->rule->rule_name}")
              ->html("<p>Review #{$this->review->id} triggered rule: <strong>{$this->rule->rule_name}</strong></p><p>Review Comment: {$this->review->comment}</p>");
  ```
* **Constructor Signature**:
  ```php
  public function __construct(AiRule $rule, ReviewNew $review)
  ```
* **Subject Line**: `"AI Rule Alert: {$this->rule->rule_name}"`
* **Trigger Point**: `app/Services/Rule/RuleExecutionService.php:326`
* **Trigger Code Snippet**:
  ```php
  Mail::to($recipient)->queue(new \App\Mail\RuleAlertMail($rule, $review));
  ```
* **Trigger Source**: Scheduled Console Command (`php artisan rules:execute-scheduled`, scheduled every 1 minute in `app/Console/Kernel.php`).
* **Conditions / Guards**: Rule recipient email must not be empty and rule action must be `SEND_EMAIL` or `FLAG_AND_ALERT`.
* **How to Test**: Run `php artisan rules:execute-scheduled` manually in terminal after creating a review that matches an active AI rule.

---

### 16. `UserReviewReportMail`
* **File Path**: `app/Mail/UserReviewReportMail.php`
* **Class Name**: `App\Mail\UserReviewReportMail`
* **View Rendered**:
  ```php
  return $this->view('user-review-report-mail')
              ->attachData($this->pdfContents, $this->filename);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($pdfContents, $filename)
  ```
* **Subject Line**: Default mail subject.
* **Trigger Point**: `app/Console/Commands/UserReviewReport.php:259`
* **Trigger Code Snippet**:
  ```php
  Mail::to($to)->send(new UserReviewReportMail($pdfContents, 'report.pdf'));
  ```
* **Trigger Source**: Scheduled Console Command (`php artisan user_review_report:generate`, scheduled daily at 04:00 in `app/Console/Kernel.php`).
* **Conditions / Guards**: Target business must have `$business->is_report_email_enabled == true`.
* **How to Test**: Execute `php artisan user_review_report:generate` directly in the terminal.

---

### 17. `GuestUserReviewReportMail`
* **File Path**: `app/Mail/GuestUserReviewReportMail.php`
* **Class Name**: `App\Mail\GuestUserReviewReportMail`
* **View Rendered**:
  ```php
  return $this->view('guest-user-review-report-mail')
              ->attachData($this->pdfContents, $this->filename);
  ```
* **Constructor Signature**:
  ```php
  public function __construct($pdfContents, $filename)
  ```
* **Subject Line**: Default mail subject.
* **Trigger Point**: `app/Console/Commands/GuestUserReviewReport.php:263`
* **Trigger Code Snippet**:
  ```php
  Mail::to($to)->send(new GuestUserReviewReportMail($pdfContents, 'report.pdf'));
  ```
* **Trigger Source**: Scheduled Console Command (`php artisan guest_user_review_report:generate`, scheduled daily at 03:00 in `app/Console/Kernel.php`).
* **Conditions / Guards**: Target business must have `$business->guest_user_review_report == true`.
* **How to Test**: Execute `php artisan guest_user_review_report:generate` directly in the terminal.

---

## 5. Testing Gaps & Recommendations

### Gaps Identified
1. **Emails Guarded by `env("SEND_EMAIL")`**:
   - `UserRegistered`, `UserSubscriptionRenewed`, `UserPaymentSuccess`, `UserPaymentFailed`, and `NotifyMail` check `if (env("SEND_EMAIL") == true)` or `if (env("SEND_EMAIL") == "TRUE")`. If `.env` has `SEND_EMAIL=FALSE`, these dispatches will fail silently or skip sending.
2. **Webhook-Only Triggers**:
   - Stripe emails (`UserPaymentSuccess`, `UserSubscriptionRenewed`, `UserRegistered`) require a Stripe webhook payload sent to `POST /webhooks/stripe`. Without Stripe CLI or a webhooks simulator, testing these via the frontend interface is difficult.
3. **Scheduled Console Commands**:
   - `UserReviewReportMail`, `GuestUserReviewReportMail`, and `RuleAlertMail` are triggered by background console commands (`user_review_report:generate`, `guest_user_review_report:generate`, `rules:execute-scheduled`). They cannot be triggered by clicking a button in the frontend.

### Recommendations for Frontend Testing

1. **Local Mail Catching Tool**:
   - Install **Mailpit** or **Mailtrap** locally. Setting `MAIL_DRIVER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` with Mailpit allows frontend developers to instantly inspect all HTML emails rendered in a local browser inbox.
2. **Simulating Webhooks and Scheduled Mailables**:
   - Frontend developers can quickly test any Mailable directly using `php artisan tinker`:
     ```bash
     php artisan tinker
     ```
     ```php
     $user = App\Models\User::first();
     Mail::to('test@example.com')->send(new App\Mail\UserPaymentSuccess($user, ['amount' => 49.00, 'plan_name' => 'Pro']));
     ```
