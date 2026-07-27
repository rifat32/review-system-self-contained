# Notification System Technical Report

## 1. Overview
The notification system in this Laravel codebase is a multi-channel architecture designed to alert business owners, branch managers, and system administrators about review activities and system events. It leverages three primary channels: **In-App Notifications** (stored in the database), **Push Notifications** (delivered to Android devices via Firebase Cloud Messaging), and **Email Notifications** (delivered via SMTP/Mailgun/SES). The core orchestration happens inside `NotificationService`, which is primarily triggered synchronously during the review submission process (`ReviewService`) and asynchronously via the AI Rule execution engine and scheduled cron commands.

## 2. Flow Diagram
```text
[Trigger Source]
       |
       v
[Condition Check] 
(e.g., Rating threshold, AI rule match, Report enabled)
       |
       v
[Recipient Resolution] 
(e.g., Branch Manager vs. Business Owner, Specific Email)
       |
       v
[Delivery Channels]
       |---> In-App DB (`notifications` table via `NotificationService::send_notification`)
       |---> Push FCM (`NotificationService::sendNotificationToFirebaseUser`) --> HTTP to Firebase API
       |---> Email (`Mail::to()->send()` or `Mail::queue()`) --> SMTP/Third-Party API
```

## 3. File-by-File Breakdown
- **`app/Services/Notification/NotificationService.php`**: Core service managing Firebase HTTP API integration, FCM push delivery logic, and inserting/updating in-app `Notification` database records.
- **`app/Http/Controllers/NotificationController.php`**: API Controller handling HTTP requests to fetch user notifications, mark them as read, delete them, or manually broadcast custom notifications (restricted to super admins).
- **`app/Services/Review/ReviewService.php`**: Triggers immediate in-app, push, and email notifications to managers/owners when a new review is submitted, branching logic based on the review's calculated rating against a CSAT threshold.
- **`app/Services/Rule/RuleExecutionService.php`**: Evaluates processed reviews against AI rules (e.g., sentiment, staff mentions) and triggers custom email alerts (`notify_email` action) to designated recipients.
- **`app/Console/Commands/GuestUserReviewReport.php` & `UserReviewReport.php`**: Scheduled commands responsible for generating PDF reports of review metrics and sending them out via email.
- **`app/Console/Kernel.php`**: The Laravel task scheduler that orchestrates the timing of the AI rule engine and the periodic review report emails.

## 4. Notification Conditions Table

| Notification type | Trigger (file:line/event) | Condition to send | Recipient(s) | Channel | Timing (immediate/queued/scheduled) | Template/view used |
| --- | --- | --- | --- | --- | --- | --- |
| **New Review (High Rating)** | `ReviewService.php:storeReviewValues` | `guest_id` exists AND `averageRating >= thresholdRating` | Branch Manager (if exists) OR Business Owner | In-App, Email, Push (FCM) | Immediate (Synchronous) | `App\Mail\ReviewNotificationMail` |
| **Low Rating Review** | `ReviewService.php:storeReviewValues` | `guest_id` exists AND `averageRating < thresholdRating` | Both Branch Manager AND Business Owner | In-App, Email, Push (FCM) | Immediate (Synchronous) | `App\Mail\ReviewNotificationMail` |
| **AI Rule Alert** | `RuleExecutionService.php:executeActions` | Review matches predefined AI rule conditions & has `notify_email` action | Configured `$rule->recipient` | Email | Scheduled/Background | `App\Mail\RuleAlertMail` or Raw Text |
| **Custom Broadcast** | `NotificationController.php:createNotification` | Authenticated user is `super_admin` | Configured `$request->receiver_id` | In-App | Immediate (Synchronous) | None (DB record) |
| **Review Report** | `Kernel.php` -> `GuestUserReviewReport` | Business has `guest_user_review_report = true` & `is_report_email_enabled = true` | Fixed admin emails & `$business->EmailAddress` | Email | Scheduled (Daily at 03:00/04:00) | `guest-user-review-report-pdf` (attached) |

## 5. Edge Cases & Gaps Noticed

1. **Synchronous Email & Push Delays (`ReviewService.php`)**: 
   When a new review is submitted, `Mail::to()->send()` and `sendNotificationToFirebaseUser()` (which has a 30s HTTP timeout) are called synchronously. If the SMTP server or Firebase API is slow, the user submitting the review will experience a severe request timeout or an HTTP 500 error. *Recommendation: Use `Mail::queue()` and dispatch Push notifications via a queued Job.*
2. **TypeError Risk on Null Owner ID (`ReviewService.php`)**: 
   If a high-rating review occurs for a business without a branch manager, it falls back to `$ownerId = $business->OwnerID`. If `OwnerID` is null, it passes `null` to `sendNotificationToFirebaseUser(int $userId, ...)`. Because the parameter is strictly typed as `int`, PHP will throw a fatal `TypeError` and crash the review submission.
3. **Incomplete Recipient Logic for High Ratings (`ReviewService.php`)**: 
   For positive reviews, if a branch manager exists, the notification is *only* sent to the branch manager. The business owner is entirely excluded from high-rating notifications for that branch.
4. **FCM Fallback / iOS Hardcoding (`NotificationService.php`)**: 
   iOS push notifications are hardcoded to be skipped (`// TODO: iOS push notifications will be implemented later`). Furthermore, errors inside `sendNotificationToDevice` are caught and returned as an array, but the caller (`ReviewService`) does not check the return value or handle failures, masking delivery issues.
5. **Missing FK Constraints / Null Checks (`NotificationService.php`)**: 
   `send_notification()` does not verify if the `$data['receiver_id']` actually exists in the `users` table before attempting to insert into the `notifications` table, potentially causing SQL constraint violations if invalid IDs are passed.
6. **Hardcoded Report Recipients (`GuestUserReviewReport.php`)**: 
   The scheduled report commands have hardcoded developer emails (`drrifatalashwad0@gmail.com`, `asjadtariq@gmail.com`) in the CC/To list. These should be moved to a configuration file or `.env` variable.
7. **Unused Notification Types**: 
   The `NotificationService::getMessageByType()` method defines `review_replied` and `staff_mentioned` types, but a full codebase search reveals these exact event types are currently unutilized in any active triggers (they appear to be planned/stubbed features).
