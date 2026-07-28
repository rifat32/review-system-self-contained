# AI Review Process & Cron Job System — Full Audit Report

**Generated:** 2026-07-28  
**Project:** FeedGenius — `review-system-self-contained`  
**Auditor:** Antigravity (automated discovery pass — no code changes applied)

---

## Executive Summary

The AI review pipeline is functional and well-structured overall, with OpenAI `gpt-4o-mini` processing reviews every 5 minutes via a cron-driven console command. However, the system has **three high-severity issues**: the OpenAI API key is partially set to a non-functional placeholder (`sk-proj-`) in both `.env` and `.env.example`; the queue is configured as `sync` in both files, meaning AI processing happens **synchronously on the same cron process** without a resilient retry system; and the scheduler's `reviews:process` command is **guarded by `config('services.openai.enabled')` which does not exist**, meaning that guard expression always evaluates to the config default (`true`), creating a silent configuration gap. Additionally, the `tags.tag` column has no minimum-length constraint, and AI-returned `tags` are stored indirectly in a JSON blob rather than in the `tags` table — the actual `tags` table can receive empty-string rows through the `BusinessProfileService` label-creation path.

---

## 1. AI Model(s) In Use

| Purpose | Provider | Model Name | Config Source | Notes |
|---|---|---|---|---|
| Review sentiment, emotion, moderation, category, staff, area, alerts | OpenAI | `gpt-4o-mini` | `.env` line 67 / `config/services.php` line 22 | Pulled from env ✅ |
| Default fallback model (cost calculation) | OpenAI | `gpt-4o-mini` | `OpenAIProcessorService.php` line 1550 | Hardcoded string as fallback ⚠️ |
| Hugging Face (secondary, inactive) | HuggingFace | N/A | `config/services.php` line 17; `HF_API_KEY` in `.env` line 65 | Key present but no HF processor class found in codebase |

### Evidence

**`.env` — lines 66–67:**
```
OPENAI_API_KEY=sk-proj-
OPENAI_MODEL=gpt-4o-mini
```

**`config/services.php` — lines 20–24:**
```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'timeout' => env('OPENAI_TIMEOUT', 30)
],
```

**`app/Services/AIProcessor/OpenAIProcessorService.php` — lines 161–162:**
```php
$apiKey = \config('services.openai.api_key');
$model  = \config('services.openai.model', 'gpt-4o-mini');
```

**`app/Services/AIProcessor/OpenAIProcessorService.php` — line 1550 (hardcoded fallback):**
```php
$modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini']; // Default to gpt-4o-mini
```
> ⚠️ **Maintainability issue:** `'gpt-4o-mini'` is hardcoded in the cost calculator. If the model is changed via `.env`, the pricing table also needs manual update.

---

## 2. AI Review Process Flow

### Trigger
The process is **cron-driven** (every 5 minutes), not event-driven. No queue jobs are dispatched; all work is synchronous.

---

**Step 1 — Scheduler triggers `reviews:process`**

**File:** `app/Console/Kernel.php` — lines 27–30

```php
if (config('services.openai.enabled', true)) {
    $schedule->call(function () {
        Artisan::call('reviews:process');
    })->name('process-reviews')->everyFiveMinutes()->withoutOverlapping();
```

**Logic:** Every 5 minutes the scheduler checks `config('services.openai.enabled', true)`. Because there is **no `enabled` key** in `config/services.php`, this always resolves to the default `true`. The command runs with `withoutOverlapping()` which uses a cache lock to prevent concurrent executions.

---

**Step 2 — `ProcessAIReviews::handle()` boots the batch**

**File:** `app/Console/Commands/ProcessAIReviews.php` — lines 26–83

```php
public function handle()
{
    Log::channel('daily')->info("AI Review Processing started at " . now());
    try {
        $this->processBatch();
        Log::channel('daily')->info("Processing completed successfully at " . now());
    } catch (\Exception $e) {
        $errorMessage = "❌ Processing failed: " . $e->getMessage();
        Log::channel('daily')->info("ERROR: " . $errorMessage);
    }
}
```

**Logic:** Logs start/end to both the daily Laravel log and `storage/logs/ai_process.log`. Any exception from `processBatch()` is caught and logged but **does not re-throw**, so the scheduler always sees a clean exit.

---

**Step 3 — `processBatch()` fetches unprocessed reviews**

**File:** `app/Console/Commands/ProcessAIReviews.php` — lines 88–96

```php
$query = ReviewNew::whereNotNull('raw_text')
    ->where('is_ai_processed', 0);

$limit   = $this->option('limit');
$reviews = $query->orderBy('id', 'asc')->limit($limit)->get();
```

**Logic:** Fetches up to `--limit` (default 100) reviews where `raw_text` is not null and `is_ai_processed = 0`, ordered oldest-first.

---

**Step 4 — Per-review subscription and token limit checks**

**File:** `app/Console/Commands/ProcessAIReviews.php` — lines 150–181

```php
$business = $review->business;
if (!$business)                    { continue; }   // line 151
if (!$business->is_subscribed)     { continue; }   // line 162
if ($business->is_token_limit_reached) { continue; }  // line 173
```

**Logic:** Guards against orphan reviews, unsubscribed businesses, and businesses that have consumed their AI token quota.

---

**Step 5 — `OpenAIProcessorService::analyzeReview()` is called**

**File:** `app/Console/Commands/ProcessAIReviews.php` — line 192

```php
$result = $this->processor->analyzeReview($review, false);
```

---

**Step 6 — Payload construction**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 1141–1222

```php
public function createPayloadFromReview(ReviewNew $review): array
{
    $text = $review->raw_text ?? $review->comment ?? '';
    // ...
    return [
        'review_text'       => $text,
        'rating'            => $avgRating,
        'question_ratings'  => $questionRatings,
        'staff_info'        => $staffInfo,
        'business_services' => $business_services,
        'review_id'         => $review->id,
        'business_id'       => $review->business_id,
        'metadata'          => [ ... ]
    ];
}
```

**Logic:** Assembles full review context including question ratings, staff info, and business area/service associations. Uses `raw_text` first, falls back to `comment`.

---

**Step 7 — Business module enablement check**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — line 1302

```php
$enabledModules = $this->getBusinessAiModules($businessId);
```

**Logic:** Queries `business_subscriptions → service_plans → modules` to determine which AI feature modules are active for this business. Returns `[]` for unsubscribed or on error.

---

**Step 8 — System prompt construction**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 669–1084

The **base (always-included) section** of the prompt (lines 671–700):

```
You are an AI Experience Intelligence Engine. Analyze customer reviews and return ONLY valid JSON in this exact structure:

{
  "language": { "detected": "language code", "translated_text": "..." },
  "sentiment": { "label": "negative|neutral|positive", "score": 0.0 to 1.0 },
  "emotion": { "primary": "joy|sadness|anger|fear|surprise|disgust|neutral", "intensity": "low|medium|high" },
  "moderation": { "is_abusive": true|false, "safe_for_public_display": true|false, ... },
  "rating_comment_alignment": { "is_aligned": true|false, "mismatch_type": "...", "confidence": 0.0-1.0, ... },
  "explainability": { "confidence_score": 0.0-1.0, ... },
  "summary": { "one_line": "...", "manager_summary": "...", "overall_assessment": "..." }
}
```

Optional sections appended per `$enabledModules`: `category_analysis`, `staff_intelligence`, `service_unit_intelligence`, `area_insights`, `business_insights`, `recommendations`, `alerts`, `flags`.

> ⚠️ The prompt schema does **not** include a `"tags"` key, yet `buildKeyPhrases()` at line 100 reads `$result['tags'] ?? []`. This means the `tags` element in `key_phrases` is always `[]` — a dead/incomplete feature.

---

**Step 9 — User message construction**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 1096–1097

```php
$message  = "REVIEW TO ANALYZE:\n";
$message .= "Text: \"{$text}\"\n";
$message .= "Overall Rating: {$rating}/5\n";
```

**Logic:** Review text is interpolated directly — see prompt injection note in Section 7.

---

**Step 10 — OpenAI API call**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 200–221

```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Content-Type'  => 'application/json',
])
    ->timeout(config('ai.openai.request.process_timeout') ?? 60)
    ->retry(config('ai.openai.request.retry_times') ?? 3, config('ai.openai.request.retry_sleep') ?? 1000)
    ->post('https://api.openai.com/v1/chat/completions', [
        'model'           => $model,
        'temperature'     => config('ai.openai.request.temperature') ?? 0.1,
        'max_tokens'      => $dynamicMaxTokens,
        'response_format' => ['type' => 'json_object'],
        'messages'        => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage]
        ]
    ]);
```

**Logic:** 60-second timeout; 3 retries with 1000ms sleep. `response_format: json_object` enforces valid JSON output.

---

**Step 11 — Response parsing (three-level fallback)**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 248–353

```php
$content = $data['choices'][0]['message']['content'] ?? '';

if (empty($content)) {
    throw new \Exception('No content in OpenAI response');
}

if ($finishReason === 'length') {
    $content = $this->fixTruncatedJson($content);
}

$cleanedContent = $this->cleanJsonContent($content);
$result = json_decode($cleanedContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $fixedContent = $this->fixCommonJsonIssues($cleanedContent);
    $result = json_decode($fixedContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $extractedJson = $this->extractJsonFromString($cleanedContent);
        $result = json_decode($extractedJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON from OpenAI: ...');
        }
    }
}
```

**Expected format:** JSON object per the system prompt. If all three parse attempts fail → exception → review stays `is_ai_processed = 0` for retry next run.

---

**Step 12 — Database write**

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 1313–1322

```php
$dbData = $this->convertForDatabase($openAIResult, $review, $enabledModules);
$review->fill($dbData);
$this->ruleExecutionService->resetRuleOutcomes($review);
$review->save();
```

**Logic:** `convertForDatabase()` maps AI JSON to Eloquent `review_news` columns. After save, real-time rules are triggered (lines 1325–1332).

---

**Step 13 — 300ms rate-limit delay**

**File:** `app/Console/Commands/ProcessAIReviews.php` — line 224

```php
usleep(300000); // 300ms delay
```

---

## 3. Error Handling & Edge Cases

### API Call Failure

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 223–230, 433–444

```php
if ($response->failed()) {
    Log::error('OpenAI API failed', [
        'status'  => $response->status(),
        'error'   => $response->body(),
        'headers' => $response->headers()
    ]);
    throw new \Exception('OpenAI API error: ' . $response->status());
}
// ...
} catch (\Exception $e) {
    Log::error('OpenAI processing failed', ['error' => $e->getMessage(), ...]);
    throw $e; // re-throws to caller
}
```

**What happens:** Exception propagates to `processBatch()` per-review try/catch (line 212) → `$failedCount++` → log → continue to next review. Review stays `is_ai_processed = 0` and retries next run.

### Empty/Malformed Response

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 356–362

```php
if (!isset($result['sentiment']) || !isset($result['sentiment']['label'])) {
    Log::warning('Missing required fields in OpenAI response', [...]);
    // NO throw — processing continues with missing fields
}
```

> ⚠️ **Issue:** Missing `sentiment.label` only logs a warning. Processing continues, potentially persisting NULL or derived-default values.

### Validation Before Insert

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — lines 1423–1424

```php
$sentimentScore = $this->normalizeSentimentScore(
    $result['sentiment']['score'] ?? config('ai.topics.intensity_mapping.default', 0.5)
);
$sentimentLabel = $this->normalizeSentimentLabel($result['sentiment']['label'] ?? null, $sentimentScore);
```

Sentiment fields always have fallback defaults. However, there is **no validation** that `summary`, `language`, or `emotion` are non-empty before `$review->save()` at line 1322.

### Failure Logging

**`app/Helpers/helpers.php` — lines 23–34:**
```php
function log_message(mixed $message, string $fileName = 'debug.log'): void
{
    $timestamp = now()->format('Y-m-d H:i:s');
    $fullPath  = storage_path("logs/{$fileName}");
    if (!is_string($message)) {
        $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($fullPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}
```

Writes to `storage/logs/ai_process.log`. In addition, `Log::channel('daily')` writes to `storage/logs/laravel-YYYY-MM-DD.log`.

### Dead-Letter / Failed Jobs

Because `QUEUE_CONNECTION=sync`, **no queue jobs are used**. There are no `failed_jobs` table entries for AI failures. Reviews that fail simply stay at `is_ai_processed = 0` and are retried on the next cron run.

---

## 4. Cron Job Inventory

**File:** `app/Console/Kernel.php`

| File:Line | Command | Schedule | Overlap Protection | Failure Handling | Purpose |
|---|---|---|---|---|---|
| `Kernel.php:17–19` | `guest_user_review_report:generate` | Daily 03:00 | None ⚠️ | Silent | Generate & email PDF guest review report |
| `Kernel.php:21–23` | `user_review_report:generate` | Daily 04:00 | None ⚠️ | Silent | Generate & email PDF registered-user report |
| `Kernel.php:28–30` | `reviews:process` | Every 5 min (gated by `openai.enabled` config) | `withoutOverlapping()` ✅ | Per-review try/catch; top-level exception swallowed | **Main AI review pipeline** |
| `Kernel.php:34–36` | `rules:execute-scheduled` | Every 1 min (gated by same config) | None ⚠️ | Per-rule try/catch | Execute scheduled AI rules |
| `Kernel.php:40–42` | `recommendations:generate` | Daily 03:00 | None ⚠️ | Per-business try/catch | Generate insight recommendations |
| `Kernel.php:46–51` | `recommendations:cleanup --days=90 --force` | Weekly Sun 04:00 | None ⚠️ | Not audited | Purge old recommendations |
| `Kernel.php:55–57` | `businesses:delete-permanently` | Daily 00:00 UTC | None ⚠️ | Not audited | Hard-delete old soft-deleted businesses |

> **Server crontab:** Cannot verify from code — check manually with `crontab -l` or `/etc/cron.d/`. Confirm `* * * * * php /path/to/artisan schedule:run` is present.

### AI Pipeline Trigger Code

**File:** `app/Console/Kernel.php` — lines 27–36

```php
if (config('services.openai.enabled', true)) {
    $schedule->call(function () {
        Artisan::call('reviews:process');
    })->name('process-reviews')->everyFiveMinutes()->withoutOverlapping();

    $schedule->call(function () {
        Artisan::call('rules:execute-scheduled');
    })->name('execute-rules')->everyMinute();
}
```

**Trigger type:** Cron-driven only. No event-driven dispatch found.

---

## 5. Queue System

**`.env` — line 24:** `QUEUE_CONNECTION=sync`  
**`.env.example` — line 24:** `QUEUE_CONNECTION=sync`

**`config/queue.php` — line 16:**
```php
'default' => env('QUEUE_CONNECTION', 'sync'),
```

The AI pipeline uses **no queue jobs**. All processing is synchronous within the cron command.

**HTTP-level retry config (`config/ai.php` lines 194–203):**
```php
'retry_times'     => 3,
'retry_sleep'     => 1000, // milliseconds
'process_timeout' => 60,
```

**Supervisor/Horizon:** No `config/horizon.php`, no `supervisor.conf`, no Horizon package found. Must be confirmed manually on the server.

**`config/queue.php` — lines 87–91 (failed jobs config):**
```php
'failed' => [
    'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table'    => 'failed_jobs',
],
```
This is only activated by async queue workers — not used by the sync AI pipeline.

---

## 6. Data Integrity Checks

### `tags` Table Schema

**File:** `database/migrations/2022_03_29_142428_create_tags_table.php` — lines 17–29

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('tag');                          // NOT NULL, max 255, NO min-length, NO unique
    $table->unsignedBigInteger('business_id')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->string('category')->nullable();
    $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
    $table->timestamps();
});
```

Key observations:
- `tag`: NOT NULL, VARCHAR(255), **no minimum-length constraint**, **no unique index on `(tag, business_id)`**.
- `category` and `sentiment`: nullable — AI/business tags may have these as NULL.
- No `CHECK` constraint preventing empty strings.

### `review_news` Table (AI-Written Columns)

**File:** `database/migrations/2022_03_29_142333_create_review_news_table.php` — lines 47, 66, 74

```php
$table->float('sentiment_score')->nullable();
$table->string('sentiment_label', 20)->nullable();
$table->boolean('is_ai_processed')->default(0);
$table->decimal('ai_confidence', 3, 2)->nullable();
$table->text('summary')->nullable();
```

All AI-written columns are properly nullable. `convertForDatabase()` always provides a value or null via the fallback chain — no empty-string risk here.

---

## 7. Root Cause Analysis: Empty-String Inserts Into `tags.tag`

The AI pipeline does **not** write directly to the `tags` table. `buildKeyPhrases()` at line 100 stores `$result['tags'] ?? []` into the `key_phrases` JSON column of `review_news`.

The real empty-string insertion risk is in the **business setup flow**:

**File:** `app/Services/Business/BusinessProfileService.php` — lines 811–818

```php
if (isset($data['labels'])) {
    foreach ($data['labels'] as $label) {
        Tag::create([
            'tag'         => ucwords($label),   // Line 814 — no empty-string guard
            'business_id' => $business->id,
        ]);
    }
}
```

**Step-by-step trace:**
1. API caller submits a `labels` array as part of business setup data.
2. No validation exists to check if `$label` is non-empty before `Tag::create()`.
3. `ucwords('')` returns `''` (empty string).
4. `Tag::create(['tag' => '', ...])` succeeds — MySQL allows empty strings in `VARCHAR NOT NULL` without a `CHECK` constraint.
5. Empty-string row is inserted into `tags.tag`.

**Comparison — safe paths:**

`TagController::createMultipleTags()` lines 628–632 is **safe** — uses `->filter()`:
```php
$uniqueTags = collect($validated['tags'])
    ->map(fn($t) => trim((string) $t))
    ->filter()       // removes empty strings ✅
    ->unique()
    ->values();
```

`TagController::createTag()` line 115–117 is **safe** — uses `required` validation:
```php
$data = $request->validate(['tag' => 'required|string|max:255']);
```

**Conclusion:** Only `BusinessProfileService.php:813–816` is the unsafe path.

---

## 8. Security & Cost Controls

### API Key Loading

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — line 161

```php
$apiKey = \config('services.openai.api_key');
```

Loaded from config → backed by `env('OPENAI_API_KEY')` → **not hardcoded** ✅.

**Empty-key guard — lines 164–166:**
```php
if (empty($apiKey)) {
    throw new \Exception('OpenAI API key not configured');
}
```

However, `.env` line 66 has `OPENAI_API_KEY=sk-proj-` (bare prefix). This is **not** caught by `empty()` and results in 401 on every API call.

### Rate Limiting / Throttling

- **Application-level:** 300ms `usleep` between reviews (`ProcessAIReviews.php:224`).
- **HTTP-level retry:** 3 retries, 1000ms backoff (`config/ai.php:201–202`).
- **No per-user/endpoint API rate limiting is implemented.**

### Prompt Injection Risk

**File:** `app/Services/AIProcessor/OpenAIProcessorService.php` — line 1097

```php
$message .= "Text: \"{$text}\"\n";
```

The raw `review_text` (from `$review->raw_text ?? $review->comment`) is **directly interpolated** into the user message without sanitization. This is a **prompt injection** vulnerability — a malicious reviewer could embed adversarial instructions. While `response_format: json_object` constrains output format, it does not prevent instruction overrides within that format.

### Cost Controls

- **Token limit per business:** `$business->is_token_limit_reached` checked before each review (`ProcessAIReviews.php:173`).
- **Token usage tracking:** `trackTokenUsage()` logs to `openai_token_usage` table on every successful call (`OpenAIProcessorService.php:1473–1505`).
- **Subscription check:** Unsubscribed businesses are skipped (`ProcessAIReviews.php:162`).

---

## 9. Logging & Observability

| Stage | Log Location | File:Line |
|---|---|---|
| Batch start | `daily` + `ai_process.log` | `ProcessAIReviews.php:28–45` |
| Review-level processing | `daily` + `ai_process.log` | `ProcessAIReviews.php:141–146` |
| Skip reasons | `daily` + `ai_process.log` | `ProcessAIReviews.php:152–179` |
| OpenAI API request (debug) | `daily` (debug) | `OpenAIProcessorService.php:191–198` |
| OpenAI API failure | `daily` (error) | `OpenAIProcessorService.php:224–228` |
| JSON parse failure | `daily` (error) | `OpenAIProcessorService.php:327–334` |
| Rating-sentiment mismatch | `daily` (warning) | `OpenAIProcessorService.php:297–315` |
| Truncated response | `daily` (warning) | `OpenAIProcessorService.php:270–275` |
| Analysis complete | `daily` (info) | `OpenAIProcessorService.php:1334–1338` |
| Review success/failure count | `daily` + `ai_process.log` | `ProcessAIReviews.php:231–293` |
| Token tracking failure | `daily` (error) | `OpenAIProcessorService.php:1501–1504` |

**Missing logging:** `$review->save()` at line 1322 has no surrounding log. A DB write failure bubbles to line 1345 catch block, but the log only captures the exception message — not that it was a DB error specifically.

---

## Issues Found

### Issue 1 — Critical: OpenAI API Key Is a Non-Functional Placeholder

| Field | Value |
|---|---|
| **Severity** | Critical |
| **File:Line** | `.env:66` |
| **Code** | `OPENAI_API_KEY=sk-proj-` |
| **Explanation** | `sk-proj-` is a bare prefix, not a real key. The `empty()` guard at `OpenAIProcessorService.php:164` passes this string (non-empty), so the code proceeds to call OpenAI with an invalid credential. Every call returns `401 Unauthorized`, gets retried 3 times, then throws. All 100 reviews per batch fail silently every 5 minutes. |
| **Suggested Fix** | Set the real key: `OPENAI_API_KEY=sk-proj-<actual_key>`. Add a length-based startup check that alerts when the key is suspiciously short. |

---

### Issue 2 — High: `QUEUE_CONNECTION=sync` — No Resilient Retry

| Field | Value |
|---|---|
| **Severity** | High |
| **File:Line** | `.env:24` and `.env.example:24` |
| **Code** | `QUEUE_CONNECTION=sync` |
| **Explanation** | All AI processing is synchronous inside the cron command. A single slow OpenAI call (60s timeout × 3 retries = up to 180s) blocks the entire batch. If the process is killed mid-batch, there is no job record to resume. The `failed_jobs` mechanism in `config/queue.php:87–91` is only active with async queue drivers. |
| **Suggested Fix** | Switch to `QUEUE_CONNECTION=database` or `redis`. Dispatch individual reviews as queued jobs. Configure `retry_after` and `tries` on the job class. |

---

### Issue 3 — High: `config('services.openai.enabled')` Key Does Not Exist

| Field | Value |
|---|---|
| **Severity** | High |
| **File:Line** | `app/Console/Kernel.php:27` |
| **Code** | `if (config('services.openai.enabled', true)) {` |
| **Explanation** | `config/services.php` has no `enabled` key under `openai`. The call always returns the default `true`. There is no way to disable the AI scheduler via config/env without editing `Kernel.php`. |
| **Suggested Fix** | Add `'enabled' => env('OPENAI_ENABLED', true)` to `config/services.php` openai block. Add `OPENAI_ENABLED=true` to `.env.example`. |

---

### Issue 4 — High: No Overlap Protection on `rules:execute-scheduled`

| Field | Value |
|---|---|
| **Severity** | High |
| **File:Line** | `app/Console/Kernel.php:34–36` |
| **Code** | `->name('execute-rules')->everyMinute();` (no `withoutOverlapping()`) |
| **Explanation** | This command runs every minute with no overlap protection. If a run exceeds 60 seconds, a second instance starts simultaneously, creating potential duplicate rule outcomes and race conditions on `last_run_at`/`next_run_at`. |
| **Suggested Fix** | Add `->withoutOverlapping()` to the `execute-rules` schedule chain. |

---

### Issue 5 — High: Prompt Injection (Raw User Input in Prompt)

| Field | Value |
|---|---|
| **Severity** | High |
| **File:Line** | `app/Services/AIProcessor/OpenAIProcessorService.php:1097` |
| **Code** | `$message .= "Text: \"{$text}\"\n";` |
| **Explanation** | `$text` is raw user-submitted review content injected directly into the OpenAI user message without sanitization. A malicious user could embed adversarial instructions. `response_format: json_object` only constrains output format — it does not prevent instruction overrides. |
| **Suggested Fix** | Wrap the review text in explicit delimiters and reinforce in the system prompt that text inside those delimiters is data only, not instructions. |

---

### Issue 6 — Medium: Empty-String Insert Risk in `tags` Table

| Field | Value |
|---|---|
| **Severity** | Medium |
| **File:Line** | `app/Services/Business/BusinessProfileService.php:813–816` |
| **Code** | `Tag::create(['tag' => ucwords($label), 'business_id' => $business->id]);` |
| **Explanation** | `$data['labels']` is iterated without validating that each `$label` is non-empty. `ucwords('')` returns `''`. MySQL accepts empty strings in `VARCHAR NOT NULL` without a `CHECK` constraint. |
| **Suggested Fix** | Add `if (empty(trim($label))) continue;` before the `Tag::create()` call. |

---

### Issue 7 — Medium: Missing Required Fields Warning Does Not Halt Processing

| Field | Value |
|---|---|
| **Severity** | Medium |
| **File:Line** | `app/Services/AIProcessor/OpenAIProcessorService.php:356–362` |
| **Code** | `if (!isset($result['sentiment']['label'])) { Log::warning(...); }` (no throw) |
| **Explanation** | Missing `sentiment.label` only logs a warning. Processing continues with potentially NULL or fallback sentinel values written to the DB, which may break rules and dashboard views. |
| **Suggested Fix** | Throw an exception when core required fields are missing, or explicitly log it as an error and use the fallback with a clear audit trail. |

---

### Issue 8 — Medium: Hardcoded Developer Email Addresses in Report Command

| Field | Value |
|---|---|
| **Severity** | Medium |
| **File:Line** | `app/Console/Commands/GuestUserReviewReport.php:258` |
| **Code** | `$to = ['drrifatalashwad0@gmail.com', $business->EmailAddress, "asjadtariq@gmail.com"];` |
| **Explanation** | Developer email addresses are hardcoded in a production command. Every weekly PDF report is CC'd to these addresses, creating a data privacy concern. |
| **Suggested Fix** | Move developer CC addresses to `.env` (e.g., `REPORT_CC_EMAILS=addr1,addr2`) and load via config. |

---

### Issue 9 — Low: AI `tags` Field Is Always Empty Array (Dead Feature)

| Field | Value |
|---|---|
| **Severity** | Low |
| **File:Line** | `app/Services/AIProcessor/OpenAIProcessorService.php:100` |
| **Code** | `'tags' => $result['tags'] ?? [],` |
| **Explanation** | The system prompt never instructs the AI to return a `tags` array. Therefore `$result['tags']` is always undefined and `key_phrases['tags']` is always `[]`. This is dead/incomplete feature code. |
| **Suggested Fix** | Either add a `"tags": [...]` field to the prompt schema, or remove the `'tags'` key from `buildKeyPhrases()`. |

---

### Issue 10 — Low: `log_message()` Uses Non-Atomic File Write

| Field | Value |
|---|---|
| **Severity** | Low |
| **File:Line** | `app/Helpers/helpers.php:33` |
| **Code** | `file_put_contents($fullPath, "[{$timestamp}] {$message}\n", FILE_APPEND);` |
| **Explanation** | `file_put_contents` with `FILE_APPEND` is not guaranteed atomic across concurrent processes. If multiple artisan commands write simultaneously, log entries can interleave. Low risk now with sync queue, but becomes real when queues are introduced. |
| **Suggested Fix** | Add `LOCK_EX` flag: `file_put_contents($fullPath, $line, FILE_APPEND | LOCK_EX);` or route to `Log::channel()`. |

---

### Issue 11 — Low: `ENAI_TEMPERATURE` Typo in `.env` (Dead Config Var)

| Field | Value |
|---|---|
| **Severity** | Low |
| **File:Line** | `.env:68` |
| **Code** | `ENAI_TEMPERATURE=0.2` |
| **Explanation** | The variable is named `ENAI_TEMPERATURE` (missing `OP` prefix). The code reads temperature from `config/ai.php` which has a hardcoded `0.1`. The `.env` variable is never loaded anywhere. |
| **Suggested Fix** | Rename to `OPENAI_TEMPERATURE=0.2`, add `'temperature' => env('OPENAI_TEMPERATURE', 0.1)` to `config/ai.php`, and update `.env.example`. |

---

## Recommendations (Prioritized)

### Quick Wins (< 1 hour each)

1. **[Critical]** Set the real OpenAI API key in `.env` — `OPENAI_API_KEY=sk-proj-<actual_value>`.
2. **[High]** Add `->withoutOverlapping()` to the `rules:execute-scheduled` schedule entry in `Kernel.php:36`.
3. **[High]** Add `'enabled' => env('OPENAI_ENABLED', true)` to `config/services.php` openai block; add `OPENAI_ENABLED=true` to `.env.example`.
4. **[Medium]** Fix empty-string tag insert in `BusinessProfileService.php:813` — add `if (empty(trim($label))) continue;`.
5. **[Low]** Fix `ENAI_TEMPERATURE` typo in `.env` and wire through `config/ai.php`.
6. **[Low]** Remove hardcoded developer emails from `GuestUserReviewReport.php:258` — move to `.env`/config.
7. **[Low]** Add `LOCK_EX` to `file_put_contents` in `helpers.php:33`.

### Larger Fixes (require design work)

8. **[High]** Switch to async queue processing — dispatch `ProcessSingleAIReview` jobs, set `QUEUE_CONNECTION=database` or `redis`, configure Supervisor or Horizon.
9. **[High]** Implement prompt injection protection — wrap `$text` in XML-style delimiters; add system-prompt instruction treating delimited content as data only.
10. **[Medium]** Throw (not warn) when core AI response fields like `sentiment.label` are missing.
11. **[Low]** Complete or remove the `tags` AI feature — either add it to the prompt schema or remove `$result['tags'] ?? []` from `buildKeyPhrases()`.
12. **[Low]** Add DB-level `CHECK (tag <> '')` constraint on `tags.tag` as last-resort safety net.

---

*End of audit. No code changes have been applied. Confirm all findings before implementing any fixes.*
