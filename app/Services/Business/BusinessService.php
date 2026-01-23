<?php

namespace App\Services\Business;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessDay;
use App\Models\AiRule;
use App\Models\Question;
use App\Models\Star;
use App\Models\ReviewValueNew;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessService
{
    /**
     * Enrich business object with ratings and timing information
     *
     * @param Business $business
     * @param int $dayOfWeek
     * @param Request $request
     * @return Business
     */
    public function enrichBusinessWithRatingsAndTiming($business, $dayOfWeek, $request)
    {
        $totalCount = 0;
        $totalRating = 0;

        foreach (Star::get() as $star) {
            $selectedCount = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $selectedCount = $selectedCount->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            $selectedCount = $selectedCount->count();

            $totalCount += $selectedCount * $star->value;
            $totalRating += $selectedCount;
        }

        $average_rating = $totalCount > 0 ? $totalCount / $totalRating : 0;

        $timing = $business->times()->with("timeSlots")->where('day', $dayOfWeek)->first();

        $business->average_rating = $average_rating;
        $business->total_rating_count = $totalCount;
        $business->out_of = 5;
        $business->timing = $timing;

        return $business;
    }

    /**
     * Create business with schedule and default questions
     */
    public function createBusinessWithSchedule(User $user, array $payloadData): Business
    {
        $business = $this->createBusiness($user, $payloadData);

        // CREATE BUSINESS SCHEDULE
        $this->createBusinessSchedule($business, $payloadData['times']);


        // CREATE DEFAULT BRANCH
        $this->createDefaultBranch($business);

        // CREATE DEFAULT AI RULES
        $this->createDefaultAiRules($business);

        return $business->fresh();
    }

    /**
     * Create a new business
     */
    public function createBusiness(User $user, array $payloadData): Business
    {
        return Business::create([
            'OwnerID' => $user->id,
            'Status' => 'Inactive',
            'Key_ID' => Str::random(10),
            'expiry_date' => now()->addDays(15)->format('d-m-Y'),
            'Name' => $payloadData['business_name'],
            'Address' => $payloadData['business_address'],
            'PostCode' => $payloadData['business_postcode'],
            'EmailAddress' => $payloadData['business_EmailAddress'] ?? '',
            'GoogleMapApi' => $payloadData['business_GoogleMapApi'] ?? '',
            'homeText' => $payloadData['business_homeText'] ?? '',
            'AdditionalInformation' => $payloadData['business_AdditionalInformation'] ?? '',
            'Webpage' => $payloadData['business_Webpage'] ?? '',
            'PhoneNumber' => $payloadData['business_PhoneNumber'] ?? '',
            'About' => $payloadData['business_About'] ?? '',
            'Layout' => $payloadData['business_Layout'] ?? '',
            'header_image' => $payloadData['header_image'] ?? '/header_image/default.webp',
            'rating_page_image' => $payloadData['rating_page_image'] ?? '/rating_page_image/default.webp',
            'placeholder_image' => $payloadData['placeholder_image'] ?? '/placeholder_image/default.webp',
            'primary_color' => $payloadData['primary_color'] ?? '',
            'secondary_color' => $payloadData['secondary_color'] ?? '',
            'client_primary_color' => $payloadData['client_primary_color'] ?? '#172c41',
            'client_secondary_color' => $payloadData['client_secondary_color'] ?? '#ac8538',
            'client_tertiary_color' => $payloadData['client_tertiary_color'] ?? '#ffffff',
            'user_review_report' => $payloadData['user_review_report'] ?? false,
            'review_type' => $payloadData['review_type'] ?? 'star',
            'google_map_iframe' => $payloadData['google_map_iframe'] ?? '',
            'show_image' => $payloadData['show_image'] ?? '',
            'is_review_slider' => $payloadData['is_review_slider'] ?? false,
            'review_only' => $payloadData['review_only'] ?? true,
            'is_branch' => $payloadData['is_branch'] ?? false,
            'has_rule_management' => $payloadData['has_rule_management'] ?? false,
            'is_treat_manager_as_staff' => $payloadData['is_treat_manager_as_staff'] ?? false,
            'Is_guest_user' => true,
            'guest_user_review_report' => true,
            'is_guest_user_overall_review' => true,
            'is_guest_user_survey' => false,
            'is_registered_user_overall_review' => true,
            'is_registered_user_survey' => false,
            'is_guest_user_show_stuffs' => true,
            'is_guest_user_show_stuff_image' => true,
            'is_guest_user_show_stuff_name' => true,
            'is_registered_user_show_stuffs' => true,
            'is_registered_user_show_stuff_image' => true,
            'is_registered_user_show_stuff_name' => true,
            'service_plan_id' => $payloadData['service_plan_id'],
        ]);
    }

    /**
     * Create business schedule with days and time slots
     */
    public function createBusinessSchedule(Business $business, array $times): void
    {
        // Remove existing schedule
        BusinessDay::where('business_id', $business->id)->delete();

        foreach ($times as $dayData) {
            $businessDay = BusinessDay::create([
                'business_id' => $business->id,
                'day' => $dayData['day'],
                'is_weekend' => $dayData['is_weekend'],
            ]);

            foreach ($dayData['time_slots'] as $timeSlot) {
                $businessDay->timeSlots()->create([
                    'start_at' => $timeSlot['start_at'],
                    'end_at' => $timeSlot['end_at'],
                ]);
            }
        }
    }

    /**
     * Create default questions for business
     */
    public function createDefaultQuestions(Business $business): void
    {
        DB::transaction(function () use ($business) {
            $defaultQuestions = Question::where([
                'business_id' => null,
                'is_default' => true
            ])->get();

            $questionsData = $defaultQuestions->map(fn($question) => [
                'question' => $question->question,
                'business_id' => $business->id,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now()
            ])->toArray();

            if (!empty($questionsData)) {
                Question::insert($questionsData);
            }
        });
    }
    /**
     * Create default questions for business
     */
    public function createDefaultBranch(Business $business): void
    {
        DB::transaction(function () use ($business) {

            // Create Main Branch
            $branchData = [
                'business_id' => $business->id,
                'name' => $business->Name,
                'address' => $business->Address,
                'street' => null,
                'door_no' => null,
                'city' => $business->city ?? null,
                'country' => $business->country ?? null,
                'postcode' => $business->PostCode ?? null,
                'phone' => $business->PhoneNumber ?? null,
                'email' => $business->EmailAddress ?? null,
                'is_active' => true,
                'is_geo_enabled' => true,
                'branch_code' => 'MAIN_BRANCH',
                'lat' => $business->latitude ?? null,
                'long' => $business->longitude ?? null,
                'manager_id' => null,
                'is_default' => true,
            ];

            // Insert branch data
            if (!empty($branchData)) {
                Branch::insert($branchData);
            }
        });
    }

    /**
     * Create 9 default AI rules for a specific business
     * These rules are hardcoded and created whenever a new business is registered
     */
    public function createDefaultAiRules(Business $business): void
    {
        $businessId = $business->id;
        $rules = [
            [
                'rule_id' => 'SENTIMENT_ANALYSIS.' . $businessId,
                'rule_name' => 'Sentiment Analysis',
                'description' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 96.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'positive'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'neutral'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'sentiment_categorized'],
                'ai_explanation_title' => 'Sentiment Analysis',
                'ai_plain_explanation' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'ai_why_it_matters' => 'Understanding the general mood of customer feedback helps in broad quality assessment.',
                'ai_when_it_triggers' => 'Triggers on every review to assign a sentiment category.'
            ],
            [
                'rule_id' => 'EMOTION_INTENSITY.' . $businessId,
                'rule_name' => 'Emotion Intensity Detection',
                'description' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 91.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Emotion', 'type' => 'intensity', 'operator' => 'greater_than', 'value' => 0.7]
                    ]
                ],
                'actions' => ['tag' => 'high_emotion_intensity'],
                'ai_explanation_title' => 'Emotion Intensity Detection',
                'ai_plain_explanation' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'ai_why_it_matters' => 'High intensity emotions often signal critical issues or exceptional praise.',
                'ai_when_it_triggers' => 'Triggers when strong emotions are detected in review text.'
            ],
            [
                'rule_id' => 'RATING_COMMENT_MISMATCH.' . $businessId,
                'rule_name' => 'Rating & Comment Mismatch',
                'description' => 'Detect when a high numerical rating is paired with a negative written review.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'trend',
                'priority' => 'high',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 88.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'greater_than', 'value' => 3],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'mismatch_detected', 'alert' => true],
                'ai_explanation_title' => 'Rating & Comment Mismatch',
                'ai_plain_explanation' => 'Detect when a high numerical rating is paired with a negative written review.',
                'ai_why_it_matters' => 'Identifies hidden dissatisfaction where customers are polite with stars but critical in text.',
                'ai_when_it_triggers' => 'Triggers when stars are high (4+) but comment sentiment is negative.'
            ],
            [
                'rule_id' => 'CATEGORY_ISSUE_DETECTION.' . $businessId,
                'rule_name' => 'Category Issue Detection',
                'description' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 85.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'price'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'quality'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'delivery']
                    ]
                ],
                'actions' => ['tag' => 'category_assigned'],
                'ai_explanation_title' => 'Category Issue Detection',
                'ai_plain_explanation' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'ai_why_it_matters' => 'Enables granular analysis of specific business problems.',
                'ai_when_it_triggers' => 'Triggers when keywords related to pricing, quality, or delivery are found.'
            ],
            [
                'rule_id' => 'SERVICE_TYPE_DETECTION.' . $businessId,
                'rule_name' => 'Service Type Detection',
                'description' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'area',
                'priority' => 'low',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 93.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'installation'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'maintenance']
                    ]
                ],
                'actions' => ['tag' => 'service_type_identified'],
                'ai_explanation_title' => 'Service Type Detection',
                'ai_plain_explanation' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'ai_why_it_matters' => 'Helps routes feedback to the correct department.',
                'ai_when_it_triggers' => 'Triggers when specific service terms are mentioned.'
            ],
            [
                'rule_id' => 'BUSINESS_AREA_DETECTION.' . $businessId,
                'rule_name' => 'Business Area Detection',
                'description' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'area',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 89.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Area', 'type' => 'area_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'area_identified'],
                'ai_explanation_title' => 'Business Area Detection',
                'ai_plain_explanation' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'ai_why_it_matters' => 'Identifies exactly where in the business an issue or win occurred.',
                'ai_when_it_triggers' => 'Triggers when AI detects a physical area mention in the review.'
            ],
            [
                'rule_id' => 'STAFF_MENTION_DETECTION.' . $businessId,
                'rule_name' => 'Staff Mention Detection',
                'description' => 'Extract employee names or roles from comments to track individual mentions.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'staff',
                'priority' => 'low',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 95.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'staff_identified'],
                'ai_explanation_title' => 'Staff Mention Detection',
                'ai_plain_explanation' => 'Extract employee names or roles from comments to track individual mentions.',
                'ai_why_it_matters' => 'Enables staff-level performance tracking and recognition.',
                'ai_when_it_triggers' => 'Triggers when a staff member or role is explicitly mentioned.'
            ],
            [
                'rule_id' => 'STAFF_PERFORMANCE_RISK.' . $businessId,
                'rule_name' => 'Staff Performance Risk',
                'description' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'staff',
                'priority' => 'critical',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 82.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'staff_risk_flagged', 'alert' => true],
                'ai_explanation_title' => 'Staff Performance Risk',
                'ai_plain_explanation' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'ai_why_it_matters' => 'Protects brand reputation by identifying problematic staff behavior early.',
                'ai_when_it_triggers' => 'Triggers when staff are mentioned in a negative context.'
            ],
            [
                'rule_id' => 'FLAG_AND_ALERT.' . $businessId,
                'rule_name' => 'Flag and Alert Detection',
                'description' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'critical',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 97.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'less_than', 'value' => 2]
                    ]
                ],
                'actions' => ['alert' => true, 'notification' => 'emergency'],
                'ai_explanation_title' => 'Flag and Alert Detection',
                'ai_plain_explanation' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'ai_why_it_matters' => 'Ensures immediate action on the most sensitive customer issues.',
                'ai_when_it_triggers' => 'Triggers on very low ratings or critical emergency keywords.'
            ]
        ];

        foreach ($rules as $ruleData) {
            AiRule::updateOrCreate(
                ['rule_id' => $ruleData['rule_id']],
                [
                    'rule_name' => $ruleData['rule_name'],
                    'description' => $ruleData['description'],
                    'scope' => $ruleData['scope'],
                    'business_id' => $ruleData['business_id'],
                    'category' => $ruleData['category'],
                    'priority' => $ruleData['priority'],
                    'enabled' => $ruleData['enabled'],
                    'is_default' => $ruleData['is_default'],
                    'precision_rate' => $ruleData['precision_rate'],
                    'conditions' => $ruleData['conditions'], // Cast to array in model
                    'actions' => $ruleData['actions'],
                    'ai_explanation_title' => $ruleData['ai_explanation_title'],
                    'ai_plain_explanation' => $ruleData['ai_plain_explanation'],
                    'ai_why_it_matters' => $ruleData['ai_why_it_matters'],
                    'ai_when_it_triggers' => $ruleData['ai_when_it_triggers'],
                    'run_frequency' => 'daily',
                    'version' => 1,
                    'created_by' => 'system'
                ]
            );
        }
    }
}
