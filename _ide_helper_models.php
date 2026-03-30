<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @OA\Schema (
 *     schema="AiRule",
 *     type="object",
 *     title="AI Rule",
 *     description="AI Rule model for automated review analysis and actions",
 *     required={"id", "rule_id", "rule_name", "category", "priority", "enabled", "conditions", "actions"},
 *     @OA\Property(property="id", type="integer", example=1, description="Primary key"),
 *     @OA\Property(property="rule_id", type="string", example="custom_abc123", description="Unique rule identifier"),
 *     @OA\Property(property="rule_name", type="string", example="Low Rating Alert", description="Human-readable rule name"),
 *     @OA\Property(property="description", type="string", example="Alert managers when rating is below threshold", description="Rule description"),
 *     @OA\Property(property="scope", type="string", enum={"business", "system"}, example="business", description="Rule scope"),
 *     @OA\Property(property="business_id", type="integer", example=1, description="Business ID (null for system rules)"),
 *     @OA\Property(
 *         property="category",
 *         type="string",
 *         enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"},
 *         example="sentiment",
 *         description="Rule category"
 *     ),
 *  *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         enum={"critical", "high", "medium", "low"},
 *         example="high",
 *         description="Priority level"
 *     ),
 *     @OA\Property(property="enabled", type="boolean", example=true, description="Whether rule is active"),
 *     @OA\Property(
 *         property="conditions",
 *         type="array",
 *         description="Array of conditions that must be met",
 *         @OA\Items(
 *             type="object",
 *             required={"source", "type", "operator", "value"},
 *             @OA\Property(property="source", type="string", enum={"Comment", "Rating", "Staff", "Area", "Emotion", "Trend"}, description="Data source"),
 *             @OA\Property(property="type", type="string", enum={"sentiment", "rating", "keyword", "staff_mention", "area_mention", "emotion", "service_type", "frequency", "trend_direction"}, description="Condition type"),
 *             @OA\Property(property="operator", type="string", enum={"equals", "contains", "greater_than", "less_than", "between", "not_equals", "starts_with", "ends_with", "regex"}, description="Comparison operator"),
 *             @OA\Property(property="value", type="string", description="Value to compare against"),
 *             @OA\Property(property="logic", type="string", enum={"AND", "OR"}, nullable=true, description="Logic operator to combine with next condition")
 *         )
 *     ),
 *     @OA\Property(
 *         property="actions",
 *         type="array",
 *         description="Actions to execute when rule matches",
 *         @OA\Items(type="string", enum={"flag_review", "notify_manager", "recommend_coaching", "link_staff", "escalate", "notify_slack", "notify_email"})
 *     ),
 *     @OA\Property(property="multi_tag_detection", type="boolean", example=false, description="Detect multiple matching tags"),
 *     @OA\Property(property="trigger_only_on_first_occurrence", type="boolean", example=false, description="Only trigger on first occurrence"),
 *    @OA\Property(property="run_frequency", type="string", enum={"real_time", "hourly", "daily", "weekly"}, example="daily", description="How often to run"),
 *     @OA\Property(property="cooldown_days", type="integer", example=7, description="Days to wait before triggering again"),
 *     @OA\Property(property="deduplication_scope", type="string", enum={"review", "staff", "category", "branch", "staff_category"}, example="staff", description="Deduplication scope"),
 *     @OA\Property(property="applies_to", type="string", enum={"new_reviews_only", "all_reviews"}, example="new_reviews_only", description="Which reviews to apply to"),
 *     @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer"), nullable=true, description="Specific branches (null = all branches)"),
 *     @OA\Property(property="created_by", type="integer", example=1, description="User who created the rule"),
 *     @OA\Property(property="version", type="integer", example=1, description="Rule version number"),
 *     @OA\Property(property="ai_explanation_title", type="string", nullable=true, description="AI-generated short explanation"),
 *     @OA\Property(property="ai_plain_explanation", type="string", nullable=true, description="AI-generated detailed explanation"),
 *     @OA\Property(property="ai_why_it_matters", type="string", nullable=true, description="AI-generated business impact"),
 *     @OA\Property(property="ai_when_it_triggers", type="string", nullable=true, description="AI-generated trigger conditions"),
 *     @OA\Property(property="ai_generated_at", type="string", format="date-time", nullable=true, description="When AI explanations were generated"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-14T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-14T10:00:00Z")
 * )
 * @property int $id
 * @property string $rule_id
 * @property string $rule_name
 * @property string|null $description
 * @property string|null $key_name
 * @property string|null $value
 * @property string $scope
 * @property string|null $business_type
 * @property int|null $business_id
 * @property array<array-key, mixed>|null $branch_ids
 * @property string $category
 * @property string $priority
 * @property bool $enabled
 * @property bool $multi_tag_detection
 * @property bool $trigger_only_on_first_occurrence
 * @property string $applies_to
 * @property float|null $precision_rate
 * @property int $lifetime_triggers
 * @property array<array-key, mixed> $conditions
 * @property array<array-key, mixed> $actions
 * @property array<array-key, mixed>|null $explainability
 * @property string|null $ai_explanation_title
 * @property string|null $ai_plain_explanation
 * @property string|null $ai_why_it_matters
 * @property string|null $ai_when_it_triggers
 * @property string|null $ai_manager_tip
 * @property \Illuminate\Support\Carbon|null $ai_generated_at
 * @property string|null $short_explanation
 * @property string|null $detailed_explanation
 * @property string|null $why_it_matters
 * @property \Illuminate\Support\Carbon|null $explanation_generated_at
 * @property string $run_frequency
 * @property int $cooldown_days Minimum days between same issue alerts
 * @property string $deduplication_scope Defines what counts as same issue
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $next_run_at
 * @property string $created_by
 * @property bool $is_default True for system-owned default rules, false for user-created custom rules
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Recommendation> $recommendations
 * @property-read int|null $recommendations_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule byCategory(string $category)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule byPriority(string $priority)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule customRules()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule defaultRules()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule enabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule forBusiness(int $businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereActions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiExplanationTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiManagerTip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiPlainExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiWhenItTriggers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAiWhyItMatters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereAppliesTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereBranchIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereBusinessType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereCooldownDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereDeduplicationScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereDetailedExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereExplainability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereExplanationGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereKeyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereLastRunAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereLifetimeTriggers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereMultiTagDetection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereNextRunAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule wherePrecisionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereRuleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereRunFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereShortExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereTriggerOnlyOnFirstOccurrence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRule whereWhyItMatters($value)
 */
	class AiRule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $rule_id
 * @property int $lifetime_triggers
 * @property int $true_positives
 * @property int $false_positives
 * @property int $pending_verification
 * @property float|null $precision_rate
 * @property int $reviews_flagged
 * @property int $coaching_actions
 * @property int $escalations
 * @property int $notifications_sent
 * @property \Illuminate\Support\Carbon|null $last_triggered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AiRule $rule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereCoachingActions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereEscalations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereFalsePositives($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereLastTriggeredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereLifetimeTriggers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereNotificationsSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric wherePendingVerification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric wherePrecisionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereReviewsFlagged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereTruePositives($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleMetric whereUpdatedAt($value)
 */
	class AiRuleMetric extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $rule_id
 * @property int $review_id
 * @property int $business_id
 * @property string|null $dedup_key
 * @property bool $was_suppressed
 * @property string|null $suppressed_reason
 * @property int|null $staff_id
 * @property string|null $category
 * @property float $confidence_score
 * @property array<array-key, mixed>|null $matched_conditions
 * @property array<array-key, mixed>|null $actions_triggered
 * @property string $outcome
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verification_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\ReviewNew $review
 * @property-read \App\Models\AiRule $rule
 * @property-read \App\Models\User|null $verifier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger forRule(string $ruleId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger highConfidence(float $threshold = 80)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger pendingVerification()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereActionsTriggered($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereConfidenceScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereDedupKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereMatchedConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereOutcome($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereSuppressedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereVerificationNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereVerifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger whereWasSuppressed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiRuleTrigger withinDateRange($startDate, $endDate)
 */
	class AiRuleTrigger extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $manager_id
 * @property string|null $street
 * @property string|null $door_no
 * @property string|null $city
 * @property string|null $country
 * @property string|null $postcode
 * @property int $is_active
 * @property int $is_geo_enabled
 * @property string|null $branch_code
 * @property string|null $lat
 * @property string|null $long
 * @property int $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read mixed $avg_rating
 * @property-read \App\Models\User|null $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewNew> $reviews
 * @property-read int|null $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch branchGlobalFilters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch filterByDateRange(bool $isComparisonDateRange = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch filters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereBranchCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereDoorNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsGeoEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereLong($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch wherePostcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedAt($value)
 */
	class Branch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $joining_date
 * @property \Illuminate\Support\Carbon|null $leaving_date
 * @property bool $is_active
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch $branch
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereJoiningDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereLeavingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BranchMember whereUserId($value)
 */
	class BranchMember extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $is_active
 * @property int $show_image
 * @property string $Name
 * @property string|null $About
 * @property string|null $Webpage
 * @property string|null $PhoneNumber
 * @property string|null $EmailAddress
 * @property string|null $homeText
 * @property string|null $AdditionalInformation
 * @property string|null $GoogleMapApi
 * @property string|null $Address
 * @property string|null $PostCode
 * @property string|null $Logo
 * @property int $OwnerID
 * @property string|null $Key_ID
 * @property string|null $expiry_date
 * @property string $Status
 * @property string|null $business_type
 * @property string|null $Layout
 * @property int $Is_guest_user
 * @property int $is_review_slider
 * @property int $review_only
 * @property string|null $review_type
 * @property string|null $google_map_iframe
 * @property string|null $header_image
 * @property string|null $rating_page_image
 * @property string|null $placeholder_image
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property string|null $client_primary_color
 * @property string|null $client_secondary_color
 * @property string|null $client_tertiary_color
 * @property int $user_review_report
 * @property int $guest_user_review_report
 * @property string|null $pin
 * @property int $is_report_email_enabled
 * @property string $time_zone
 * @property int $is_guest_user_overall_review
 * @property int $is_guest_user_survey
 * @property int $is_guest_user_survey_required
 * @property int $is_guest_user_show_stuffs
 * @property int $is_guest_user_show_stuff_image
 * @property int $is_guest_user_show_stuff_name
 * @property int $is_registered_user_overall_review
 * @property int $is_registered_user_survey
 * @property int $is_registered_user_survey_required
 * @property int $is_registered_user_show_stuffs
 * @property int $is_registered_user_show_stuff_image
 * @property int $is_registered_user_show_stuff_name
 * @property int $enable_ip_check
 * @property int $enable_location_check
 * @property numeric|null $latitude
 * @property numeric|null $longitude
 * @property int $review_distance_limit
 * @property numeric $threshold_rating
 * @property array<array-key, mixed>|null $review_labels
 * @property int|null $guest_survey_id
 * @property int|null $registered_user_survey_id
 * @property int $enable_detailed_survey
 * @property bool $is_treat_manager_as_staff
 * @property string|null $export_settings
 * @property int $has_rule_management
 * @property array<array-key, mixed>|null $default_color_threshold
 * @property int $is_branch
 * @property int $openai_token_limit
 * @property int|null $service_plan_id
 * @property string|null $start_date
 * @property string|null $service_plan_discount_code
 * @property string $trial_end_date
 * @property float|null $service_plan_discount_amount
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $last_recommendation_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $default_branch_id
 * @property int $is_setup_complete
 * @property int $step_no
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessModule> $businessModules
 * @property-read int|null $business_modules_count
 * @property-read \App\Models\BusinessSubscription|null $current_subscription
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $customers
 * @property-read int|null $customers_count
 * @property-read \App\Models\Branch|null $defaultBranch
 * @property-read mixed $is_self_registered_businesses
 * @property-read bool $is_subscribed
 * @property-read bool $is_token_limit_reached
 * @property-read \App\Models\Survey|null $guest_survey
 * @property-read \App\Models\User|null $owner
 * @property-read \App\Models\QrCodeSetting|null $qrCodeSettings
 * @property-read \App\Models\Survey|null $registered_user_survey
 * @property-read \App\Models\ServicePlan|null $service_plan
 * @property-read \App\Models\BusinessSubscription|null $subscription
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessSubscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessDay> $times
 * @property-read int|null $times_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business activeStatus($is_active)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business filterClients()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereAbout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereAdditionalInformation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereBusinessType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereClientPrimaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereClientSecondaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereClientTertiaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDefaultBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDefaultColorThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEmailAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEnableDetailedSurvey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEnableIpCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEnableLocationCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereExportSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereGoogleMapApi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereGoogleMapIframe($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereGuestSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereGuestUserReviewReport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereHasRuleManagement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereHeaderImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereHomeText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserOverallReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserShowStuffImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserShowStuffName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserShowStuffs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserSurvey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsGuestUserSurveyRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserOverallReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserShowStuffImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserShowStuffName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserShowStuffs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserSurvey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsRegisteredUserSurveyRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsReportEmailEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsReviewSlider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsSetupComplete($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereIsTreatManagerAsStaff($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereKeyID($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereLastRecommendationAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereLayout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereOpenaiTokenLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereOwnerID($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePlaceholderImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePostCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePrimaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereRatingPageImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereRegisteredUserSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereReviewDistanceLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereReviewLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereReviewOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereReviewType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereSecondaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereServicePlanDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereServicePlanDiscountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereServicePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereShowImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereStepNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereThresholdRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTimeZone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTrialEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUserReviewReport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereWebpage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withoutTrashed()
 */
	class Business extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $business_service_id
 * @property string $area_name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\BusinessService $business_service
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea forBusiness($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea forBusinessService($businessServiceId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereAreaName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereBusinessServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessArea whereUpdatedAt($value)
 */
	class BusinessArea extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $day
 * @property int $business_id
 * @property int $is_weekend
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessTimeSlot> $timeSlots
 * @property-read int|null $time_slots_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereIsWeekend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDay whereUpdatedAt($value)
 */
	class BusinessDay extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $is_enabled
 * @property int $business_id
 * @property int $module_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\Module $module
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessModule whereUpdatedAt($value)
 */
	class BusinessModule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $question_title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessArea> $business_areas
 * @property-read int|null $business_areas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewNew> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Survey> $surveys
 * @property-read int|null $surveys_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService forBusiness($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereQuestionTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessService whereUpdatedAt($value)
 */
	class BusinessService extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $survey_id
 * @property int $business_service_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey whereBusinessServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessServiceSurvey whereUpdatedAt($value)
 */
	class BusinessServiceSurvey extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $service_plan_id
 * @property string $start_date
 * @property string $end_date
 * @property string $status
 * @property float|null $amount
 * @property string|null $paid_at
 * @property string|null $transaction_id
 * @property int $openai_token_limit
 * @property string|null $stripe_id
 * @property string|null $stripe_status
 * @property string|null $stripe_price
 * @property string|null $stripe_plan
 * @property int|null $quantity
 * @property string|null $trial_ends_at
 * @property string|null $ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\ServicePlan $service_plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereOpenaiTokenLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereServicePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStripePlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStripePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereStripeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSubscription whereUpdatedAt($value)
 */
	class BusinessSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_day_id
 * @property string $start_at
 * @property string $end_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessDay $businessDay
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereBusinessDayId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessTimeSlot whereUpdatedAt($value)
 */
	class BusinessTimeSlot extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $view_date
 * @property int $daily_views
 * @property int $business_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereDailyViews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyView whereViewDate($value)
 */
	class DailyView extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $device_token
 * @property string|null $device_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereDeviceToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUserId($value)
 */
	class DeviceToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $type
 * @property string $template
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereUpdatedAt($value)
 */
	class EmailTemplate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property string $type
 * @property string $template
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplateWrapper whereUpdatedAt($value)
 */
	class EmailTemplateWrapper extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $full_name
 * @property string|null $phone
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestUser whereUpdatedAt($value)
 */
	class GuestUser extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $main_category
 * @property string|null $sub_category
 * @property int $mentions_count
 * @property string|null $severity
 * @property string|null $confidence_level
 * @property string|null $trend
 * @property string|null $sentiment
 * @property bool $staff_blame_detected
 * @property array<array-key, mixed>|null $review_ids
 * @property \Illuminate\Support\Carbon|null $time_window_start
 * @property \Illuminate\Support\Carbon|null $time_window_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Recommendation> $recommendations
 * @property-read int|null $recommendations_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereConfidenceLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereMainCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereMentionsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereReviewIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereSentiment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereStaffBlameDetected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereSubCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereTimeWindowEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereTimeWindowStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereTrend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsightRecord whereUpdatedAt($value)
 */
	class InsightRecord extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $title
 * @property int|null $business_id
 * @property string|null $thumbnail
 * @property string|null $leaflet_data
 * @property string|null $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereLeafletData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereThumbnail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leaflet whereUpdatedAt($value)
 */
	class Leaflet extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $is_enabled
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessModule> $business_modules
 * @property-read int|null $business_modules_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereUpdatedAt($value)
 */
	class Module extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $note_contents
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereNoteContents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereUserId($value)
 */
	class Note extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $receiver_id
 * @property int|null $sender_id
 * @property int|null $business_id
 * @property string|null $sender_type
 * @property string|null $message
 * @property string|null $title
 * @property string|null $type
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property string|null $link
 * @property string $priority
 * @property string|null $status
 * @property int $entity_id
 * @property array<array-key, mixed>|null $entity_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $receiver
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification notificationFilters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereEntityIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReceiverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSenderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $business_id
 * @property int|null $review_id
 * @property int|null $branch_id
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property numeric $estimated_cost
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\ReviewNew|null $review
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage dateRange(?string $startDate = null, ?string $endDate = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage forBusiness($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereCompletionTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereEstimatedCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage wherePromptTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAITokenUsage whereTotalTokens($value)
 */
	class OpenAITokenUsage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $slug
 * @property array<array-key, mixed>|null $qrStyling
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereQrStyling($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrCodeSetting whereUpdatedAt($value)
 */
	class QrCodeSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @OA\Schema (
 *     schema="Question",
 *     type="object",
 *     title="Question",
 *     description="Review question model",
 *     required={"question", "is_active"}
 * )
 * @property int $id
 * @property string $question
 * @property string $type
 * @property int|null $business_id
 * @property int $is_default
 * @property bool $is_active
 * @property string|null $sentiment
 * @property bool $is_overall
 * @property bool $show_in_guest_user
 * @property bool $show_in_user
 * @property string|null $survey_name
 * @property int $order_no
 * @property int $is_staff
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuestionStar> $question_stars
 * @property-read int|null $question_stars_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuestionCategory> $question_sub_categories
 * @property-read int|null $question_sub_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewValueNew> $review_values
 * @property-read int|null $review_values_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SurveyQuestion> $survey_questions
 * @property-read int|null $survey_questions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Survey> $surveys
 * @property-read int|null $surveys_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question filterByOverall()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question filterForUser($user, $request)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereIsOverall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereIsStaff($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereSentiment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereShowInGuestUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereShowInUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereSurveyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedAt($value)
 */
	class Question extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $business_id
 * @property int|null $parent_question_category_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, QuestionCategory> $children
 * @property-read int|null $children_count
 * @property-read \App\Models\User|null $creator
 * @property-read QuestionCategory|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questions
 * @property-read int|null $questions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory filters($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereParentQuestionCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionCategory whereUpdatedAt($value)
 */
	class QuestionCategory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property int $question_sub_category_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory whereQuestionSubCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionQuestionSubCategory whereUpdatedAt($value)
 */
	class QuestionQuestionSubCategory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property int $star_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Star|null $star
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar whereStarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionStar whereUpdatedAt($value)
 */
	class QuestionStar extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int|null $insight_id
 * @property int|null $rule_id
 * @property string $type
 * @property string $text
 * @property string|null $confidence
 * @property int $priority
 * @property array<array-key, mixed>|null $evidence
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\InsightRecord|null $insight
 * @property-read \App\Models\AiRule|null $rule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereInsightId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereUpdatedAt($value)
 */
	class Recommendation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $review_id
 * @property int $business_service_id
 * @property int $business_area_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessArea $business_area
 * @property-read \App\Models\BusinessService $business_service
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereBusinessAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereBusinessServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewBusinessService whereUpdatedAt($value)
 */
	class ReviewBusinessService extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $description
 * @property int $business_id
 * @property int|null $user_id
 * @property int|null $guest_id
 * @property string|null $comment
 * @property string|null $source
 * @property string|null $language
 * @property string|null $responded_at
 * @property string|null $review_type
 * @property string|null $reply_content
 * @property string|null $raw_text
 * @property array<array-key, mixed>|null $emotion
 * @property array<array-key, mixed>|null $key_phrases
 * @property string|null $ip_address
 * @property int $is_overall
 * @property int|null $staff_id
 * @property string $status
 * @property bool $is_flagged
 * @property int $order_no
 * @property float|null $sentiment_score
 * @property array<array-key, mixed>|null $topics
 * @property array<array-key, mixed>|null $moderation_results
 * @property array<array-key, mixed>|null $ai_suggestions
 * @property array<array-key, mixed>|null $staff_suggestions
 * @property int|null $survey_id
 * @property bool $is_voice_review
 * @property string|null $voice_url
 * @property int|null $voice_duration
 * @property array<array-key, mixed>|null $transcription_metadata
 * @property int|null $is_private
 * @property int $rating_comment_mismatch
 * @property array<array-key, mixed>|null $mismatch_insights
 * @property int|null $branch_id
 * @property numeric|null $ai_confidence Confidence score 0.00-1.00
 * @property string|null $sentiment_label very_negative, negative, neutral, positive, very_positive
 * @property array<array-key, mixed>|null $openai_raw_response
 * @property array<array-key, mixed>|null $ai_insights
 * @property array<array-key, mixed>|null $ai_recommendations
 * @property int $is_abusive
 * @property string|null $summary
 * @property array<array-key, mixed>|null $service_analysis
 * @property int $is_ai_processed
 * @property string|null $audio
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessService> $business_services
 * @property-read int|null $business_services_count
 * @property-read mixed $ai_metadata
 * @property-read mixed $calculated_rating
 * @property-read \App\Models\GuestUser|null $guest_user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewBusinessService> $review_business_services
 * @property-read int|null $review_business_services_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewValueNew> $review_values
 * @property-read int|null $review_values_count
 * @property-read \App\Models\User|null $staff
 * @property-read \App\Models\Survey|null $survey
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewValueNew> $value
 * @property-read int|null $value_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByBranchIds()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByBusinessArea()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByBusinessService()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByDateRange(bool $isComparisonDateRange = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByInsight()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByIsOverall()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByIsVoiceReview()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByOverall($is_overall)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByQuestionCategory()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByRating()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByReviewIds()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterBySentimentScore()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByStaff()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByStarIds()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterBySurveyIds()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByTagIds()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew filterByTopics()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew globalReviewFilters($show_published_only = 0, $is_staff_review = 0, $turn_off_branch_filter = 0)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew reviewFilters()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereAiConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereAiInsights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereAiRecommendations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereAiSuggestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereAudio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereDoesNotMeetsThreshold($is_staff_review = 0)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereEmotion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereGuestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsAbusive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsAiProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsFlagged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsOverall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereIsVoiceReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereKeyPhrases($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereMeetsThreshold($is_staff_review = 0)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereMismatchInsights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereModerationResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereOpenaiRawResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereRatingCommentMismatch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereRawText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereReplyContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereReviewType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereSentimentLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereSentimentScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereServiceAnalysis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereStaffSuggestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereTopics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereTranscriptionMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereVoiceDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew whereVoiceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewNew withCalculatedRating()
 */
	class ReviewNew extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property int $star_id
 * @property int $review_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Question|null $question
 * @property-read \App\Models\ReviewNew|null $review
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew filterByBusiness($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew filterByOverall($is_overall)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereMeetsThreshold($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereStarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewValueNew whereUpdatedAt($value)
 */
	class ReviewValueNew extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $business_id
 * @property int $is_default
 * @property int $is_system_default
 * @property int $is_default_for_business
 * @property string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereIsDefaultForBusiness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereIsSystemDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property float $duration_months
 * @property int $openai_token_limit
 * @property int $free_trial_duration_date
 * @property int $is_active
 * @property float $set_up_amount
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Module> $modules
 * @property-read int|null $modules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ServicePlanModule> $service_plan_modules
 * @property-read int|null $service_plan_modules_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereDurationMonths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereFreeTrialDurationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereOpenaiTokenLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereSetUpAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlan whereUpdatedAt($value)
 */
	class ServicePlan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property float $discount_amount
 * @property int $service_plan_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ServicePlan $servicePlan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereServicePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanDiscountCode whereUpdatedAt($value)
 */
	class ServicePlanDiscountCode extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $is_enabled
 * @property int $service_plan_id
 * @property int $module_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Module $module
 * @property-read \App\Models\ServicePlan $service_plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereServicePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePlanModule whereUpdatedAt($value)
 */
	class ServicePlanModule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $staff_id
 * @property numeric $rating
 * @property string $status
 * @property array<array-key, mixed>|null $skill_gaps
 * @property array<array-key, mixed>|null $training_recommendations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\User $staff
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot needsImprovement()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot topPerforming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereSkillGaps($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereTrainingRecommendations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffPerformanceSnapshot whereUpdatedAt($value)
 */
	class StaffPerformanceSnapshot extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewValueNew> $review_values
 * @property-read int|null $review_values_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StarTag> $star_tags
 * @property-read int|null $star_tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star filterByOverall($is_overall)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Star whereValue($value)
 */
	class Star extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $star_id
 * @property int $tag_id
 * @property int $question_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tag|null $tag
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereStarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereTagId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTag whereUpdatedAt($value)
 */
	class StarTag extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property int|null $star_id
 * @property int $tag_id
 * @property int $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Question|null $question
 * @property-read \App\Models\Star|null $star
 * @property-read \App\Models\Tag|null $tag
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereStarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereTagId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarTagQuestion whereUpdatedAt($value)
 */
	class StarTagQuestion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $review_id
 * @property string|null $external_id
 * @property string $subject
 * @property string $description
 * @property string $priority
 * @property string $status
 * @property string|null $assigned_to
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\ReviewNew $review
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereReviewId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereUpdatedAt($value)
 */
	class SupportTicket extends \Eloquent {}
}

namespace App\Models{
/**
 * @OA\Schema (
 *     schema="Survey",
 *     type="object",
 *     title="Survey",
 *     description="Survey model",
 *     required={"name", "business_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Customer Feedback Survey"),
 *     @OA\Property(property="business_id", type="integer", example=1),
 *     @OA\Property(property="show_in_guest_user", type="boolean", example=true),
 *     @OA\Property(property="show_in_user", type="boolean", example=true),
 *     @OA\Property(property="order_no", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * @property int $id
 * @property string $name
 * @property int $business_id
 * @property int $show_in_guest_user
 * @property int $show_in_user
 * @property int $order_no
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessService> $business_services
 * @property-read int|null $business_services_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewNew> $reviews
 * @property-read int|null $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereShowInGuestUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereShowInUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereUpdatedAt($value)
 */
	class Survey extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $background_color
 * @property string $overall_heading
 * @property string $survey_heading
 * @property string $heading_color
 * @property string $sub_heading
 * @property string $sub_heading_color
 * @property string $question_text_color
 * @property string $question_background_color
 * @property string $tag_text_color
 * @property string $tag_background_color
 * @property string $tag_active_text_color
 * @property string $tag_active_background_color
 * @property string $service_text_color
 * @property string $service_background_color
 * @property string $service_area_text_color
 * @property string $service_area_background_color
 * @property string $active_service_area_text_color
 * @property string $active_service_area_background_color
 * @property string $staff_heading
 * @property string $staff_heading_color
 * @property string $staff_background_color
 * @property string $staff_card_background_color
 * @property string $staff_name_color
 * @property string $staff_role_color
 * @property string $staff_active_background_color
 * @property string $staff_active_border_color
 * @property string $remarks_button_text
 * @property string $remarks_button_text_color
 * @property string $remarks_button_background_color
 * @property string $remarks_text
 * @property string $remarks_text_color
 * @property string $remarks_background_color
 * @property string $field_background_color
 * @property string $field_text_color
 * @property string $details_heading
 * @property string $details_heading_color
 * @property string $details_background_color
 * @property string $details_label_color
 * @property string $actions_background_color
 * @property string $actions_buttons_text_color
 * @property string $actions_button_background_color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business $business
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereActionsBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereActionsButtonBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereActionsButtonsTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereActiveServiceAreaBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereActiveServiceAreaTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereDetailsBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereDetailsHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereDetailsHeadingColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereDetailsLabelColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereFieldBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereFieldTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereHeadingColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereOverallHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereQuestionBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereQuestionTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksButtonBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksButtonText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksButtonTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereRemarksTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereServiceAreaBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereServiceAreaTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereServiceBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereServiceTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffActiveBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffActiveBorderColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffCardBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffHeadingColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffNameColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereStaffRoleColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereSubHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereSubHeadingColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereSurveyHeading($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereTagActiveBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereTagActiveTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereTagBackgroundColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereTagTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyPageSetting whereUpdatedAt($value)
 */
	class SurveyPageSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @OA\Schema (
 *     schema="SurveyQuestion",
 *     type="object",
 *     title="SurveyQuestion",
 *     description="Survey question pivot model",
 *     required={"survey_id", "question_id"}
 * )
 * @property int $id
 * @property int $survey_id
 * @property int $question_id
 * @property int|null $order_no
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Question $question
 * @property-read \App\Models\Survey $survey
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyQuestion whereUpdatedAt($value)
 */
	class SurveyQuestion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $tag
 * @property int|null $business_id
 * @property int $is_default
 * @property int $is_active
 * @property string|null $category
 * @property string|null $sentiment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewValueNew> $review_values
 * @property-read int|null $review_values_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag filterByOverall()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereSentiment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereUpdatedAt($value)
 */
	class Tag extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $first_Name
 * @property string|null $last_Name
 * @property string|null $phone
 * @property string|null $image
 * @property string|null $resetPasswordToken
 * @property string|null $resetPasswordExpires
 * @property string|null $type
 * @property string|null $pin
 * @property string|null $post_code
 * @property string|null $Address
 * @property string|null $door_no
 * @property string $email
 * @property string|null $email_verify_token
 * @property string|null $email_verify_token_expires
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property int $login_attempts
 * @property string|null $last_failed_login_attempt_at
 * @property string|null $remember_token
 * @property int|null $business_id
 * @property string|null $date_of_birth
 * @property string|null $job_title
 * @property string|null $join_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property string|null $trial_ends_at
 * @property-read \App\Models\BranchMember|null $branch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Business> $businesses
 * @property-read int|null $businesses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewNew> $feedbacks
 * @property-read int|null $feedbacks_count
 * @property-read mixed $default_branch_id
 * @property-read mixed $name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Client> $oauthApps
 * @property-read int|null $oauth_apps_count
 * @property-read \App\Models\Business|null $ownedBusiness
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReviewNew> $staffReviews
 * @property-read int|null $staff_reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Cashier\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Token> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User filter()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User filterCustomers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User filterStaff($businessId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User hasExpiredGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDoorNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifyToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifyTokenExpires($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereJobTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereJoinDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastFailedLoginAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmLastFour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePostCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereResetPasswordExpires($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereResetPasswordToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent {}
}

