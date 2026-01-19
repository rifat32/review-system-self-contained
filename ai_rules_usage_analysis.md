# AI Rules Usage Analysis

## 1. `rules:regenerate-explanations` Command

### What It Does
The `rules:regenerate-explanations` command regenerates AI-powered explanations for business rules. It uses OpenA to generate human-readable explanations (`short_explanation`, `detailed_explanation`, `why_it_matters`) for rules that:
- Are missing explanations
- Have outdated explanations (rule was modified after explanation was generated)

### When It Runs
**Scheduled Daily at**5:00 AM** (see [app/Console/Kernel.php](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Console/Kernel.php) line 45-50):
```php
$schedule->call(function () {
    Artisan::call('rules:regenerate-explanations', [
        '--missing-only' => true,
        '--outdated-only' => true
    ]);
})->name('regenerate-explanations')->dailyAt('05:00');
```

### Options Available
- `--business=ID` - Regenerate for specific business only
- `--rule=ID` - Regenerate for specific rule only
- `--all` - Force regenerate all rules
- `--missing-only` - Only rules without explanations
- `--outdated-only` - Only rules with outdated explanations
- `--limit=50` - Maximum rules to process (default: 50)

### Is It Needed?
**YES, but sparingly.** The command is needed because:
1. **Initial Setup**: New rules created via API may not have explanations yet
2. **Rule Modifications**: When rule conditions/actions change, explanations become outdated
3. **Failed Generations**: If OpenAI API fails during rule creation, explanations are missing

**However**, it's NOT heavily used in reports. Rule explanations are mainly for:
- **UI Display**: Showing users what their rules do in plain English
- **Rule Management**: Helping users understand rule purpose when editing
- **Support Tickets**: Using `detailed_explanation` in ticket descriptions

---

## 2. API Endpoints Using AI Rules & Aggregations

### A. AI Rules Management APIs ([AiRuleController](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Http/Controllers/AiRuleController.php#11-476))

All under `/v1.0/ai-rules`:

| Method | Endpoint | Purpose | Returns AI Data |
|--------|----------|---------|-----------------|
| GET | `/` | List all rules | ✅ YES - Returns `short_explanation` via [getFormattedExplanation()](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Models/AiRule.php#219-233) |
| POST | `/` | Create new rule | ✅ YES - Returns generated explanations |
| GET | `/missing-explanations` | Get rules needing explanations | ✅ YES - Stats on missing explanations |
| POST | `/batch-regenerate` | Batch regenerate explanations | ✅ YES - Regeneration results |
| GET | `/{ruleId}` | Get rule details | ✅ YES - Full rule with [explanations](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Models/AiRule.php#199-218) object |
| PUT | `/{ruleId}` | Update rule | ✅ YES - Updated rule with explanations |
| DELETE | `/{ruleId}` | Delete rule | ❌ NO |
| PATCH | `/{ruleId}/toggle` | Toggle enabled | ❌ NO |
| POST | `/{ruleId}/regenerate-explanations` | Regenerate for one rule | ✅ YES - New explanations |

**Explanation Fields Returned:**
```json
{
  "explanations": {
    "short": "AI Rule: Sentiment Analysis",
    "detailed": "This rule monitors reviews...",
    "why": "Business impact explanation...",
    "generated_at": "2 hours ago",
    "is_complete": true,
    "is_outdated": false
  }
}
```

---

### B. Rule-Based Reports ([RuleReportController](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Http/Controllers/RuleReportController.php#9-294))

All under `/v1.0/reports`:

| Method | Endpoint | Purpose | Uses AI Rules |
|--------|----------|---------|---------------|
| GET | `/sentiment-analysis` | Sentiment breakdown by default rule | ✅ YES - Uses default rule evaluations |
| GET | `/emotion-intensity` | Emotion analysis | ✅ YES - Uses default rule evaluations |
| GET | `/rating-comment-mismatch` | Mismatched ratings | ✅ YES - Uses default rule evaluations |
| GET | `/category-issues` | Category detection | ✅ YES - Uses default rule evaluations |
| GET | `/service-types` | Service type breakdown | ✅ YES - Uses default rule evaluations |
| GET | `/business-areas` | Business area mentions | ✅ YES - Uses default rule evaluations |
| GET | `/staff-mentions` | Staff mention tracking | ✅ YES - Uses default rule evaluations |
| GET | `/staff-performance-risk` | At-risk staff detection | ✅ YES - Uses default rule evaluations |
| GET | `/flagged-reviews` | Flagged/critical reviews | ✅ YES - Uses default rule evaluations |

**These reports depend on:**
- **Default AI Rules** (9 system rules created per business)
- **`RuleReportService`** which queries `AiRuleTrigger` records
- **Rule evaluations** stored when rules execute

**Typical Response Structure:**
```json
{
  "summary": {
    "total_triggers": 450,
    "affected_reviews": 320,
    "period": "last_30_days"
  },
  "data": [
    {
      "date": "2026-01-19",
      "positive": 45,
      "neutral": 12,
      "negative": 8
    }
  ],
  "top_issues": [...],
  "recommendations": [...]
}
```

---

### C. Dashboard APIs (`DashboardController`)

| Method | Endpoint | AI/Aggregation Used |
|--------|----------|---------------------|
| GET | `/v1.0/dashboard/insights-overview` | ✅ Sentiment aggregation |
| GET | `/v1.0/dashboard/monthly-trends` | ✅ Sentiment trends over time |
| GET | `/v1.0/dashboard/top-worst-services` | ✅ AI-based service ranking |
| GET | `/v1.0/dashboard/staff-performance` | ✅ Sentiment breakdown, AI processor |
| GET | `/v1.0/dashboard/staff-insights` | ✅ AI aggregated sentiment |
| GET | `/v1.0/dashboard/ai-insights` | ✅ AI-driven insights and recommendations |
| GET | `/v1.0/dashboard/metrics` | ✅ Overall sentiment calculation |
| GET | `/v1.0/dashboard/recent-reviews` | ❌ Raw review data only |
| GET | `/v1.0/dashboard/rating-breakdown` | ❌ Simple rating counts |
| GET | `/v1.0/dashboard/tags-breakdown` | ❌ Tag counts only |
| GET | `/v3.0/dashboard-report` | ✅ Comprehensive AI aggregations |
| GET | `/v1.0/reviews/overall-dashboard/{businessId}` | ✅ Sentiment status, AI processing |
| GET | `/v1.0/branch-dashboard/{branchId}` | ✅ Branch sentiment trends |
| GET | `/v1.0/reports/branch-comparison` | ✅ Branch sentiment comparison |

**AI Services Used:**
- `aiProcessorService->calculateAggregatedSentiment()`
- `aiProcessorService->getSentimentTrendOverTime()`
- Sentiment score calculations
- Emotion intensity analysis

---

### D. Recommendation APIs (`RecommendationController`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/recommendations/generate` | Generate AI recommendations |
| GET | `/api/recommendations` | List recommendations |
| GET | `/api/recommendations/{id}/explain` | Explain recommendation rationale |
| GET | `/api/dashboard/insights` | Dashboard insights with recommendations |

**Uses `Recommendation` model** - stores AI-generated actionable recommendations based on review patterns.

---

### E. Branch APIs (`BranchController`)

| Method | Endpoint | AI Data |
|--------|----------|---------|
| GET | `/v1.0/branches/{branchId}/ai-insights` | ✅ YES - AI insights per branch |
| GET | `/v1.0/branches/{branchId}/recommendations` | ✅ YES - Branch recommendations |
| GET | `/v1.0/branches/{branchId}/metrics` | ✅ YES - Includes sentiment |

---

### F. Staff Performance APIs (`StaffController`)

| Method | Endpoint | AI Data |
|--------|----------|---------|
| GET | `/v1.0/staffs/metrics` | ✅ Sentiment breakdown |
| GET | `/v1.0/staffs/compliment-ratio` | ✅ AI-based positive/negative ratio |
| GET | `/v1.0/staffs/top-staffs` | ✅ AI ranking |
| GET | `/v1.0/staffs/{staffId}/performance-report` | ✅ Sentiment distribution |
| GET | `/v1.0/staffs/{staffId}/rating-trends` | ✅ Sentiment over time |

---

## 3. Summary: Do We Need `rules:regenerate-explanations`?

### ✅ **KEEP IT** - But Consider Optimizations

**Reasons to Keep:**
1. **Explanations appear in 10+ API endpoints** (all AI rule  management endpoints)
2. **User-facing feature**: Helps users understand their custom rules
3. **Support integration**: Used in support ticket descriptions
4. **Fallback mechanism**: Handles failed API generations during rule creation

**Optimizations to Consider:**
1. **Reduce frequency**: Daily at 5 AM might be overkill
   - Consider **weekly** instead, or trigger only when needed
2. **Add manual trigger UI**: Let users regenerate on-demand from frontend
3. **Improve initial generation**: Ensure explanations are always created during rule creation
4. **Cache explanations**: Explanations rarely change, consider caching

**Current Impact:**
- **Low**: Only processes rules with `--missing-only` or `--outdated-only` flags
- **Limited scope**: Max 50 rules per run (throttled)
- **Off-peak**: Runs at 5 AM when traffic is low

---

## 4. Complete List of Dynamic AI APIs

### APIs That Return AI-Generated/Aggregated Data:

1. **AI Rules** (10 endpoints) - Rule explanations
2. **Rule Reports** (9 endpoints) - Rule evaluation analytics
3. **Dashboard** (13+ endpoints) - Sentiment, trends, insights
4. **Recommendations** (4 endpoints) - AI recommendations
5. **Branch AI** (3 endpoints) - Branch-level AI insights
6. **Staff AI** (5 endpoints) - Staff sentiment analysis

**Total: ~44 API endpoints use AI/rule data dynamically**

All of these depend on:
- `ReviewNew` table with AI fields: [sentiment](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Http/Controllers/RuleReportController.php#18-57), `sentiment_score`, [emotion](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Http/Controllers/RuleReportController.php#58-89), `topics`, `key_phrases`
- [AiRule](file:///home/rifat/rifat/uk_client/review/backend/review-system/app/Models/AiRule.php#76-349) and `AiRuleTrigger` tables for rule evaluations
