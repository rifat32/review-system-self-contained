# Audit Report: AI Token Usage Tracking

## Executive Summary
**Can we currently report per-user/per-business token usage today? Yes!** 
The application is already successfully capturing OpenAI token usage, persisting it to a dedicated relational database table, correctly associating it with the respective business contexts, and exposing it via a robust aggregation reporting controller. There is only a minor gap regarding the hardcoded cost calculation which could drift from live OpenAI pricing.

---

## 1. Current State: Token Capture
Token usage is actively captured immediately upon receiving the response from the OpenAI API.

- **File:** `app/Services/AIProcessor/OpenAIProcessorService.php`
- **Line:** 382–386 (inside `processReviewWithOpenAI`)
- **Code Snippet:**
  ```php
  // Extract token usage
  $usage = $data['usage'] ?? [];
  $promptTokens = $usage['prompt_tokens'] ?? 0;
  $completionTokens = $usage['completion_tokens'] ?? 0;
  $totalTokens = $usage['total_tokens'] ?? 0;
  ```
- **Explanation:** The system parses the standard OpenAI `$data['usage']` block and extracts `prompt_tokens`, `completion_tokens`, and `total_tokens`. It successfully avoids discarding the data.

---

## 2. Current State: Storage Schema
Usage is stored in a queryable table specifically designed for AI token usage.

- **File:** `database/migrations/2025_12_20_125146_create_openai_token_usage_table.php` (Line 11)
- **Table Definition:**
  ```php
  Schema::create('openai_token_usage', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('business_id')->nullable()->index(); // Links to businesses
      $table->unsignedBigInteger('review_id')->nullable()->index();   // Links to reviews
      $table->unsignedBigInteger('branch_id')->nullable()->index();   // Links to branches
      $table->string('model')->index();
      $table->integer('prompt_tokens')->default(0);
      $table->integer('completion_tokens')->default(0);
      $table->integer('total_tokens')->default(0);
      $table->decimal('estimated_cost', 10, 6)->default(0);
      $table->json('metadata')->nullable();
      $table->timestamp('created_at')->index();
  });
  ```
- **Explanation:** This is a fully normalized table that perfectly links token consumption and costs to the `business_id` (Tenant/Company), as well as tracing it back to the specific `review_id` that triggered the generation. 

---

## 3. Current State: Write Path
Token usage is persisted directly into the database immediately following the API call.

- **File:** `app/Services/AIProcessor/OpenAIProcessorService.php`
- **Line:** 1496 (inside the `trackTokenUsage` helper method called by the API runner)
- **Code Snippet:**
  ```php
  \App\Models\OpenAITokenUsage::create([
      'business_id' => $businessId,
      'review_id' => $reviewId,
      'branch_id' => $branchId,
      'model' => $model,
      'prompt_tokens' => $promptTokens,
      'completion_tokens' => $completionTokens,
      'total_tokens' => $totalTokens,
      'estimated_cost' => $cost,
      'metadata' => $metadata,
      'created_at' => \now(),
  ]);
  ```
- **Explanation:** This cleanly records a new row for every single AI processing event, persisting all token counts and passing metadata (such as the enabled AI modules, response lengths, and caching flags).

---

## 4. Current State: User/Business Attribution
The system cleanly associates AI processing to the business owner, even when processed asynchronously.

- **File:** `app/Models/OpenAITokenUsage.php` (Line 36)
  ```php
  public function business() {
      return $this->belongsTo(Business::class);
  }
  ```
- **Context Preservation:** 
  The codebase passes the `business_id` through the `$payload` array during the API execution (`$payload['business_id']`). Even when processing happens via background jobs (`app/Console/Commands/ProcessAIReviews.php` line 191 calls `$this->processor->analyzeReview($review)`), the context is successfully retained because the `business_id` is fetched natively from the `$review->business_id` model state.

---

## 5. Current State: Reporting & Aggregation
The system already features a comprehensive analytics endpoint specifically for AI Token Reporting.

- **File:** `app/Http/Controllers/OpenAITokenReportController.php` (Lines 226-242)
- **Code Snippet:**
  ```php
  $results = $query->selectRaw('
      business_id,
      COUNT(*) as total_requests,
      SUM(total_tokens) as total_tokens,
      SUM(estimated_cost) as total_cost
  ')
  ->whereNotNull('business_id')
  ->groupBy('business_id')
  ->orderByDesc('total_tokens')
  ->limit(20) // Limit to top 20 businesses
  ->get();
  ```
- **Explanation:** This API controller exposes routes like `GET /api/openai-tokens/report` with full filtering capabilities (date ranges, models, branches). It returns `total_cost`, `avg_cost_per_request`, and a `business_breakdown` aggregated via `SUM()`. 

---

## 6. Gaps Found

### Gap 1: Hardcoded OpenAI Pricing Rates
- **Severity:** Low / Medium
- **File:** `app/Services/AIProcessor/OpenAIProcessorService.php` (Line 1551)
- **Exact Code Snippet:**
  ```php
  $pricing = [
      'gpt-4o-mini' => ['input_per_million' => 0.15, 'output_per_million' => 0.60],
      'gpt-4o' => ['input_per_million' => 5.00, 'output_per_million' => 15.00],
  ];
  ```
- **Logic Explanation:** The pricing calculation relies on a hardcoded array within the service class. If OpenAI changes their pricing model or introduces new tiers, the `estimated_cost` written to the database will become silently inaccurate unless a developer manually updates this service file.
- **Suggested Fix:** Move these rates into `config/ai.php` (e.g., `config('ai.openai.pricing')`) so they can be modified via environment variables or easily updated in a centralized configuration without touching service logic. 

---

## Recommendations

1. **Move Pricing to Config (Quick Win):** Refactor `calculateEstimatedCost` to pull from Laravel `config()` so pricing updates do not require code deployment.
2. **Dashboard UI Verification:** Given the robust backend API (`OpenAITokenReportController`), verify if the frontend Super Admin dashboard is actually consuming this endpoint to display the metrics visually. 
3. **Archiving Strategy (Long Term):** The `openai_token_usage` table logs every single review individually. At scale, this table will become extremely large. Consider a monthly rollup/aggregation job to summarize costs per business, followed by pruning records older than 90 days.
