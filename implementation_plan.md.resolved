# AI Rule Engine - Implementation Plan

## Overview
Implementing comprehensive AI rule-based automation features for the review system, including wizard-based rule creation, emotion detection, rating mismatch detection, performance tracking, notifications, and bulk operations.

## User Review Required

> [!IMPORTANT]
> This implementation adds **10 new database tables/columns**, **7 new controllers/services**, and **multiple API endpoints**. All features are designed to be dynamic and data-driven with no hardcoded business logic.

> [!WARNING]
> **Breaking Changes**: None. All additions are backward compatible.

## Proposed Changes

### Database Layer

#### [NEW] Migration: `create_review_emotions_table.php`
Creates table to store emotion analysis results with intensity levels and confidence scores.

**Columns**: review_id, emotion, intensity_score, intensity_level, confidence, keywords_matched

#### [NEW] Migration: `create_rating_mismatches_table.php`
Tracks cases where star ratings don't match comment sentiment.

**Columns**: review_id, business_id, mismatch_type, severity, rating, detected_sentiment, status, reviewed_by

#### [NEW] Migration: `create_ai_rule_triggers_table.php`
Logs every rule execution for performance tracking and auditing.

**Columns**: rule_id, review_id, business_id, confidence_score, matched_conditions, actions_triggered, outcome

#### [NEW] Migration: `create_ai_rule_metrics_table.php`
Aggregated performance metrics per rule.

**Columns**: rule_id, lifetime_triggers, true_positives, false_positives, precision_rate, reviews_flagged, coaching_actions

---

### Models Layer

#### [NEW] [ReviewEmotion.php](file:///e:/review-system-self-contained/app/Models/ReviewEmotion.php)
Eloquent model for emotion detection results with relationships to reviews.

#### [NEW] [RatingMismatch.php](file:///e:/review-system-self-contained/app/Models/RatingMismatch.php)
Model for tracking rating-sentiment mismatches with status workflow.

#### [NEW] [AiRuleTrigger.php](file:///e:/review-system-self-contained/app/Models/AiRuleTrigger.php)
Model for rule execution history with outcome verification.

#### [NEW] [AiRuleMetric.php](file:///e:/review-system-self-contained/app/Models/AiRuleMetric.php)
Model for aggregated rule performance metrics with auto-calculation methods.

---

### Helpers Layer (Business Logic)

#### [NEW] [ConditionBuilderHelper.php](file:///e:/review-system-self-contained/app/Helpers/ConditionBuilderHelper.php)
**Purpose**: Dynamic condition evaluation engine supporting nested groups, multiple operators, and complex logic trees.

**Key Methods**:
- `validateConditionTree()` - Validates condition structure
- `evaluateConditions()` - Recursively evaluates condition trees with AND/OR logic
- `evaluateSingleCondition()` - Matches individual conditions against review data
- `matchSentiment()`, `matchNumeric()`, `matchText()` - Type-specific matchers

#### [MODIFY] [AIProcessor.php](file:///e:/review-system-self-contained/app/Helpers/AIProcessor.php)
**Purpose**: Add emotion detection and mismatch detection capabilities.

**New Methods**:
- `detectEmotions()` - Detects joy, anger, frustration, satisfaction, disappointment with intensity
- `getIntensityLevel()` - Converts scores to low/medium/high
- `detectRatingMismatch()` - Identifies rating-comment discrepancies
- `calculateMismatchSeverity()` - Determines mismatch severity level

#### [NEW] [RuleNotificationService.php](file:///e:/review-system-self-contained/app/Services/RuleNotificationService.php)
**Purpose**: Handle all rule-triggered notifications via email, Slack, and in-app.

**Key Methods**:
- `notifyRuleTrigger()` - Main orchestration method
- `notifyManagers()` - In-app notifications
- `sendSlackNotification()` - Slack webhook integration
- `sendEmailNotification()` - Email notifications
- `sendDailyDigest()` - Batch daily summaries

#### [NEW] [RuleMetricsService.php](file:///e:/review-system-self-contained/app/Services/RuleMetricsService.php)
**Purpose**: Track and calculate rule performance metrics.

**Key Methods**:
- `recordTrigger()` - Log rule execution
- `updateMetrics()` - Update aggregated statistics
- `calculatePrecisionRate()` - Compute accuracy metrics
- `getPerformanceReport()` - Generate performance summaries

---

### Controllers Layer (API Interface)

#### [NEW] [RuleWizardController.php](file:///e:/review-system-self-contained/app/Http/Controllers/RuleWizardController.php)
**Purpose**: 4-step guided rule creation wizard with preview functionality.

**Endpoints**:
- `POST /v1.0/rule-wizard/step1` - Name & description
- `POST /v1.0/rule-wizard/step2` - Data source selection
- `POST /v1.0/rule-wizard/step3` - Conditions builder
- `POST /v1.0/rule-wizard/step4/preview` - What-if analysis
- `POST /v1.0/rule-wizard/step4/activate` - Finalize rule creation

**Features**:
- Session-based wizard state management
- Real-time "what-if" preview on historical reviews
- Estimated trigger count and precision rate
- Impact summary generation

#### [NEW] [BulkRuleController.php](file:///e:/review-system-self-contained/app/Http/Controllers/BulkRuleController.php)
**Purpose**: Apply rules across multiple branches simultaneously.

**Endpoints**:
- `POST /v1.0/ai-rules/bulk-apply` - Clone rule to multiple branches
- `GET /v1.0/ai-rules/cross-branch` - Get applicable rules
- `POST /v1.0/ai-rules/bulk-enable` - Enable multiple rules
- `POST /v1.0/ai-rules/bulk-disable` - Disable multiple rules

#### [MODIFY] [AiRuleController.php](file:///e:/review-system-self-contained/app/Http/Controllers/AiRuleController.php)
**Purpose**: Add metrics and validation endpoints.

**New Methods**:
- `validateConditions()` - Validate condition structure
- `getRuleMetrics()` - Get performance metrics
- `getTriggerHistory()` - Get execution history
- `verifyTrigger()` - Mark trigger as true/false positive

---

### Email Layer

#### [NEW] [RuleTriggerNotification.php](file:///e:/review-system-self-contained/app/Mail/RuleTriggerNotification.php)
Email notification for immediate rule triggers with review details and action links.

#### [NEW] [DailyRuleDigest.php](file:///e:/review-system-self-contained/app/Mail/DailyRuleDigest.php)
Daily summary of all rule triggers grouped by priority and category.

#### [NEW] Email Templates
- `resources/views/emails/rule-trigger.blade.php`
- `resources/views/emails/daily-rule-digest.blade.php`

---

### Configuration Updates

#### [MODIFY] [services.php](file:///e:/review-system-self-contained/config/services.php)
Add Slack webhook configuration.

#### [MODIFY] [.env.example](file:///e:/review-system-self-contained/.env.example)
Add `SLACK_WEBHOOK_URL` environment variable.

---

### Routes

#### [MODIFY] [api.php](file:///e:/review-system-self-contained/routes/api.php)
Add new route groups:
- Rule Wizard routes (7 endpoints)
- Bulk rule operations (4 endpoints)
- Extended AI rule routes (4 endpoints)

---

## Verification Plan

### Automated Tests
No existing automated tests found for AI rules. Will implement basic integration tests.

**New Test File**: `tests/Feature/AiRuleWizardTest.php`
```bash
# Run tests
php artisan test --filter=AiRuleWizardTest
```

**Test Coverage**:
- Rule wizard step validation
- Condition evaluation logic
- Emotion detection accuracy
- Mismatch detection accuracy
- Metrics calculation correctness

### Manual Verification

#### 1. Database Migrations
```bash
# Run migrations
php artisan migrate

# Verify tables created
php artisan db:show
```

#### 2. Rule Wizard Flow
- Navigate to rule creation page
- Complete all 4 wizard steps
- Verify preview shows estimated triggers
- Activate rule and verify in database

#### 3. Emotion Detection
- Create review with emotional language
- Verify emotions detected in `review_emotions` table
- Check intensity levels (low/medium/high)

#### 4. Mismatch Detection
- Create 5-star review with negative comment
- Verify mismatch logged in `rating_mismatches` table
- Check severity calculation

#### 5. Rule Metrics
- Trigger a rule multiple times
- Verify `ai_rule_triggers` logs each execution
- Verify `ai_rule_metrics` aggregates correctly
- Check precision rate calculation

#### 6. Notifications
- Configure Slack webhook URL
- Trigger high-priority rule
- Verify email sent to managers
- Verify Slack notification posted
- Verify in-app notification created

#### 7. Bulk Operations
- Select multiple branches
- Apply rule to all branches
- Verify individual rules created per branch

---

## API Documentation

Will generate Swagger documentation for all new endpoints using existing L5-Swagger setup.

**Endpoints to document**: 15 new endpoints across 3 controllers

---

## Risk Assessment

**Low Risk**: All features are additive, no modifications to existing core logic.

**Database Impact**: +4 new tables (~50-100 rows per table initially)

**Performance Impact**: Minimal - emotion detection runs async, metrics use aggregation

**Rollback Plan**: Simply revert migrations if issues arise during testing

---

## Implementation Timeline

- **Phase 1**: Database & Models (2 hours)
- **Phase 2**: Helpers & Services (3 hours)
- **Phase 3**: Controllers (3 hours)
- **Phase 4**: Routes & Config (1 hour)
- **Phase 5**: Email Templates (1 hour)
- **Phase 6**: Testing (2 hours)

**Total Estimated Time**: ~12 hours of development work
