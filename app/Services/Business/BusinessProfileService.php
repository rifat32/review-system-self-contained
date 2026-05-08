<?php

namespace App\Services\Business;

use App\Http\Utils\DiscountUtil;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessArea;
use App\Models\BusinessDay;
use App\Models\AiRule;
use App\Models\BusinessService;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Star;
use App\Models\ReviewValueNew;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessProfileService
{
    use DiscountUtil;
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

        $average_rating = $totalRating > 0 ? $totalCount / $totalRating : 0;

        $timing = $business->times()->with("timeSlots")->where('day', $dayOfWeek)->first();

        $business->average_rating = round($average_rating, 1);
        $business->total_rating_count = $totalRating;
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

        //
        $this->createDefaultData($business, $payloadData['business_type']);

        return $business->fresh();
    }

    /**
     * Create a new business
     */
    public function createBusiness(User $user, array $payloadData): Business
    {
        $discountAmount = $this->getDiscountAmount($payloadData);

        return Business::create([
            'business_type' => $payloadData['business_type'],
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
            'service_plan_id' => $payloadData['service_plan_id'] ?? null,
            'service_plan_discount_code' => $payloadData['service_plan_discount_code'] ?? null,
            'service_plan_discount_amount' => $discountAmount,
            'start_date' => now()->toDateString(),
            'trial_end_date' => now()->addDays(14)->toDateString(),
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
        $mismatchHighRating = (float) config('ai.openai.anomalies.mismatch_high_rating', 4.0);

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
                'actions' => ['tag' => 'sentiment_categorized', 'icon' => 'sentiment_satisfied'],
                'short_explanation' => 'Sentiment Analysis',
                'detailed_explanation' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'why_it_matters' => 'Understanding the general mood of customer feedback helps in broad quality assessment.',
                'when_it_triggers' => 'Triggers on every review to assign a sentiment category.'
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
                'actions' => ['tag' => 'high_emotion_intensity', 'icon' => 'mood'],
                'short_explanation' => 'Emotion Intensity Detection',
                'detailed_explanation' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'why_it_matters' => 'High intensity emotions often signal critical issues or exceptional praise.',
                'when_it_triggers' => 'Triggers when strong emotions are detected in review text.'
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
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'greater_than_or_equal', 'value' => $mismatchHighRating],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'less_than', 'value' => 0.4]
                    ]
                ],
                'actions' => ['tag' => 'mismatch_detected', 'alert' => true, 'icon' => 'match_word'],
                'short_explanation' => 'Rating & Comment Mismatch',
                'detailed_explanation' => 'Detect when a high numerical rating is paired with a negative written review.',
                'why_it_matters' => 'Identifies hidden dissatisfaction where customers are polite with stars but critical in text.',
                'when_it_triggers' => 'Triggers when stars are high (4+) but comment sentiment is negative.'
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
                'actions' => ['tag' => 'category_assigned', 'icon' => 'category'],
                'short_explanation' => 'Category Issue Detection',
                'detailed_explanation' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'why_it_matters' => 'Enables granular analysis of specific business problems.',
                'when_it_triggers' => 'Triggers when keywords related to pricing, quality, or delivery are found.'
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
                'actions' => ['tag' => 'service_type_identified', 'icon' => 'home_repair_service'],
                'short_explanation' => 'Service Type Detection',
                'detailed_explanation' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'why_it_matters' => 'Helps routes feedback to the correct department.',
                'when_it_triggers' => 'Triggers when specific service terms are mentioned.'
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
                'actions' => ['tag' => 'area_identified', 'icon' => 'domain'],
                'short_explanation' => 'Business Area Detection',
                'detailed_explanation' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'why_it_matters' => 'Identifies exactly where in the business an issue or win occurred.',
                'when_it_triggers' => 'Triggers when AI detects a physical area mention in the review.'
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
                'actions' => ['tag' => 'staff_identified', 'icon' => 'badge'],
                'short_explanation' => 'Staff Mention Detection',
                'detailed_explanation' => 'Extract employee names or roles from comments to track individual mentions.',
                'why_it_matters' => 'Enables staff-level performance tracking and recognition.',
                'when_it_triggers' => 'Triggers when a staff member or role is explicitly mentioned.'
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
                'actions' => ['tag' => 'staff_risk_flagged', 'alert' => true, 'icon' => 'warning'],
                'short_explanation' => 'Staff Performance Risk',
                'detailed_explanation' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'why_it_matters' => 'Protects brand reputation by identifying problematic staff behavior early.',
                'when_it_triggers' => 'Triggers when staff are mentioned in a negative context.'
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
                'actions' => ['alert' => true, 'notification' => 'emergency', 'icon' => 'notifications_active'],
                'short_explanation' => 'Flag and Alert Detection',
                'detailed_explanation' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'why_it_matters' => 'Ensures immediate action on the most sensitive customer issues.',
                'when_it_triggers' => 'Triggers on very low ratings or critical emergency keywords.'
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
                    'short_explanation' => $ruleData['short_explanation'] ?? null,
                    'detailed_explanation' => $ruleData['detailed_explanation'] ?? null,
                    'why_it_matters' => $ruleData['why_it_matters'] ?? null,
                    'explainability' => [
                        'when_it_triggers' => $ruleData['when_it_triggers'] ?? null,
                    ],
                    'run_frequency' => 'daily',
                    'version' => 1,
                    'created_by' => 'system'
                ]
            );
        }
    }


    public function createDefaultData(Business $business, $businessType)
    {
        $businessTypesData = [
            "hotel" => [
                "label" => "Hotel",
                "services" => [
                    "front_desk" => ["Check-in", "Staff behavior", "Waiting time"],
                    "rooms" => ["Cleanliness", "Comfort", "Amenities"],
                    "housekeeping" => ["Room service", "Laundry"],
                    "food_and_beverage" => ["Breakfast", "Room dining"]
                ],
                "categories" => [
                    "service" => ["Speed", "Professionalism", "Helpfulness"],
                    "cleanliness" => ["Room", "Bathroom", "Public areas"],
                    "comfort" => ["Bed", "Noise", "Temperature"],
                    "food" => ["Taste", "Quality", "Presentation"]
                ],
                "questions" => [
                    ["text" => "How would you rate your overall stay?", "type" => "rating", "category" => "service"],
                    ["text" => "How clean was your room?", "type" => "rating", "category" => "cleanliness"],
                    ["text" => "How comfortable was your room?", "type" => "rating", "category" => "comfort"],
                    ["text" => "How was the quality of food and beverages?", "type" => "rating", "category" => "food"],
                    ["text" => "What could we improve for your next stay?", "type" => "comment", "category" => "service"]
                ],
                "labels" => ["staff", "room", "cleanliness", "food", "delay"]
            ],
            "cafe" => [
                "label" => "Cafe",
                "services" => [
                    "dine_in" => ["Seating", "Cleanliness"],
                    "takeaway" => ["Packaging", "Waiting time"]
                ],
                "categories" => [
                    "food" => ["Taste", "Freshness"],
                    "service" => ["Speed", "Friendliness"],
                    "ambience" => ["Cleanliness", "Comfort"]
                ],
                "questions" => [
                    ["text" => "How was the quality of food or drinks?", "type" => "rating", "category" => "food"],
                    ["text" => "How fast was the service?", "type" => "rating", "category" => "service"],
                    ["text" => "How friendly was our staff?", "type" => "rating", "category" => "service"],
                    ["text" => "How would you rate the ambience?", "type" => "rating", "category" => "ambience"],
                    ["text" => "Any suggestions or comments?", "type" => "comment", "category" => "ambience"]
                ],
                "labels" => ["coffee", "staff", "delay", "cleanliness"]
            ],
            "restaurant" => [
                "label" => "Restaurant",
                "services" => [
                    "dine_in" => ["Table service", "Cleanliness"],
                    "takeaway" => ["Packaging", "Order accuracy"]
                ],
                "categories" => [
                    "food" => ["Taste", "Portion size"],
                    "service" => ["Speed", "Courtesy"],
                    "ambience" => ["Cleanliness", "Noise"]
                ],
                "questions" => [
                    ["text" => "How was the taste of the food?", "type" => "rating", "category" => "food"],
                    ["text" => "How satisfied were you with the service?", "type" => "rating", "category" => "service"],
                    ["text" => "Was your order accurate?", "type" => "rating", "category" => "service"],
                    ["text" => "How would you rate the ambience?", "type" => "rating", "category" => "ambience"],
                    ["text" => "Any feedback for our team?", "type" => "comment", "category" => "service"]
                ],
                "labels" => ["food", "service", "delay", "staff"]
            ],
            "pharmacy" => [
                "label" => "Pharmacy",
                "services" => [
                    "counter" => ["Prescription handling", "Advice"],
                    "billing" => ["Speed", "Accuracy"]
                ],
                "categories" => [
                    "service" => ["Speed", "Accuracy"],
                    "staff" => ["Knowledge", "Courtesy"]
                ],
                "questions" => [
                    ["text" => "How satisfied were you with the service?", "type" => "rating", "category" => "service"],
                    ["text" => "Was the staff helpful and knowledgeable?", "type" => "rating", "category" => "staff"],
                    ["text" => "How fast was the billing process?", "type" => "rating", "category" => "service"],
                    ["text" => "Was your prescription handled accurately?", "type" => "rating", "category" => "service"],
                    ["text" => "Any suggestions for improvement?", "type" => "comment", "category" => "staff"]
                ],
                "labels" => ["staff", "medicine", "waiting"]
            ],
            "hospital" => [
                "label" => "Hospital",
                "services" => [
                    "outpatient" => ["Consultation", "Waiting time"],
                    "inpatient" => ["Nursing care", "Room cleanliness"],
                    "emergency" => ["Response time", "Support"]
                ],
                "categories" => [
                    "medical_care" => ["Doctor attention", "Treatment quality"],
                    "service" => ["Waiting time", "Communication"],
                    "hygiene" => ["Cleanliness", "Sanitation"]
                ],
                "questions" => [
                    ["text" => "How would you rate the medical care?", "type" => "rating", "category" => "medical_care"],
                    ["text" => "How was the waiting time?", "type" => "rating", "category" => "service"],
                    ["text" => "How clean were the facilities?", "type" => "rating", "category" => "hygiene"],
                    ["text" => "How supportive was the staff?", "type" => "rating", "category" => "medical_care"],
                    ["text" => "Any comments or concerns?", "type" => "comment", "category" => "service"]
                ],
                "labels" => ["doctor", "nurse", "cleanliness", "delay"]
            ],
            "fitness_center" => [
                "label" => "Fitness Center / Gym",
                "services" => [
                    "training" => ["Personal training", "Group classes"],
                    "facilities" => ["Equipment", "Locker rooms"]
                ],
                "categories" => [
                    "equipment" => ["Availability", "Condition"],
                    "staff" => ["Trainer support", "Guidance"],
                    "facility" => ["Cleanliness", "Crowding"]
                ],
                "questions" => [
                    ["text" => "How satisfied are you with the equipment?", "type" => "rating", "category" => "equipment"],
                    ["text" => "How helpful were the trainers?", "type" => "rating", "category" => "staff"],
                    ["text" => "How clean are the facilities?", "type" => "rating", "category" => "facility"],
                    ["text" => "Is the gym overcrowded during your visit?", "type" => "rating", "category" => "facility"],
                    ["text" => "Any suggestions for improvement?", "type" => "comment", "category" => "facility"]
                ],
                "labels" => ["trainer", "equipment", "cleanliness"]
            ],
            "beauty_salon" => [
                "label" => "Beauty Salon / Barbershop",
                "services" => [
                    "hair" => ["Haircut", "Styling"],
                    "skin" => ["Facial", "Treatment"]
                ],
                "categories" => [
                    "service" => ["Skill", "Professionalism"],
                    "hygiene" => ["Tools", "Cleanliness"],
                    "experience" => ["Comfort", "Ambience"]
                ],
                "questions" => [
                    ["text" => "How satisfied were you with the service?", "type" => "rating", "category" => "service"],
                    ["text" => "How skilled was the stylist?", "type" => "rating", "category" => "service"],
                    ["text" => "Was the salon clean and hygienic?", "type" => "rating", "category" => "hygiene"],
                    ["text" => "How comfortable was your experience?", "type" => "rating", "category" => "experience"],
                    ["text" => "Any comments or suggestions?", "type" => "comment", "category" => "experience"]
                ],
                "labels" => ["stylist", "cleanliness", "service"]
            ],
            "car_service" => [
                "label" => "Car Repair / Car Wash / Tire Shop",
                "services" => [
                    "repair" => ["Diagnosis", "Repair work"],
                    "wash" => ["Exterior", "Interior"],
                    "maintenance" => ["Inspection", "Tires"]
                ],
                "categories" => [
                    "service" => ["Speed", "Transparency"],
                    "quality" => ["Work quality", "Parts used"],
                    "pricing" => ["Fairness", "Clarity"]
                ],
                "questions" => [
                    ["text" => "How satisfied were you with the service?", "type" => "rating", "category" => "quality"],
                    ["text" => "Was the issue explained clearly?", "type" => "rating", "category" => "service"],
                    ["text" => "Was the pricing fair?", "type" => "rating", "category" => "pricing"],
                    ["text" => "Was the work completed on time?", "type" => "rating", "category" => "service"],
                    ["text" => "Any feedback or suggestions?", "type" => "comment", "category" => "service"]
                ],
                "labels" => ["repair", "delay", "cost"]
            ],
            "professional_services" => [
                "label" => "Accountant / Law Firm / Consultant / IT Services",
                "services" => [
                    "consultation" => ["Advice quality", "Understanding needs"],
                    "delivery" => ["Timeliness", "Accuracy"]
                ],
                "categories" => [
                    "expertise" => ["Knowledge", "Accuracy"],
                    "communication" => ["Clarity", "Responsiveness"],
                    "service" => ["Timeliness", "Reliability"]
                ],
                "questions" => [
                    ["text" => "How satisfied were you with the professional advice?", "type" => "rating", "category" => "expertise"],
                    ["text" => "How clear was the communication?", "type" => "rating", "category" => "communication"],
                    ["text" => "Was the service delivered on time?", "type" => "rating", "category" => "service"],
                    ["text" => "How confident are you in the outcome?", "type" => "rating", "category" => "expertise"],
                    ["text" => "Any comments or improvement suggestions?", "type" => "comment", "category" => "communication"]
                ],
                "labels" => ["expertise", "communication", "delay"]
            ],
            "others" => [
                "label" => "Other / Custom Business",
                "services" => [
                    "general" => ["Service quality", "Responsiveness"]
                ],
                "categories" => [
                    "service" => ["Quality", "Speed"],
                    "experience" => ["Satisfaction", "Ease"]
                ],
                "questions" => [
                    ["text" => "How satisfied were you with our service?", "type" => "rating", "category" => "service"],
                    ["text" => "How easy was it to interact with us?", "type" => "rating", "category" => "experience"],
                    ["text" => "Did we meet your expectations?", "type" => "rating", "category" => "experience"],
                    ["text" => "Would you recommend us to others?", "type" => "rating", "category" => "service"],
                    ["text" => "Any feedback or suggestions?", "type" => "comment", "category" => "experience"]
                ],
                "labels" => ["general", "feedback", "service"]
            ]
        ];

        $data = $businessTypesData[$businessType] ?? $businessTypesData['others'];

        DB::transaction(function () use ($business, $data) {
            // 1. Create Business Services and matching Areas
            if (isset($data['services'])) {
                foreach ($data['services'] as $serviceName => $areas) {
                    $businessService = BusinessService::create([
                        'business_id' => $business->id,
                        'name' => ucwords(str_replace('_', ' ', $serviceName)),
                        'is_active' => true,
                    ]);

                    foreach ($areas as $areaName) {
                        BusinessArea::create([
                            'business_id' => $business->id,
                            'business_service_id' => $businessService->id,
                            'area_name' => $areaName,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            // 2. Create Question Categories (Parent -> Children)
            $categoryMap = []; // Maps JSON category key to DB Category ID
            if (isset($data['categories'])) {
                foreach ($data['categories'] as $parentCatKey => $subCategories) {
                    $parentCat = QuestionCategory::create([
                        'title' => ucwords(str_replace('_', ' ', $parentCatKey)),
                        'business_id' => $business->id,
                        'is_active' => true,
                        'is_default' => false,
                    ]);

                    // Store child sub-category IDs (NOT parent ID)
                    $subCatIds = [];
                    foreach ($subCategories as $subcatName) {
                        $subCat = QuestionCategory::create([
                            'title' => $subcatName,
                            'business_id' => $business->id,
                            'parent_question_category_id' => $parentCat->id,
                            'is_active' => true,
                            'is_default' => false,
                        ]);
                        $subCatIds[] = $subCat->id;
                    }

                    // Map the parent key to its children IDs
                    $categoryMap[$parentCatKey] = $subCatIds;
                }
            }

            // 3. Create Questions
            if (isset($data['questions'])) {
                foreach ($data['questions'] as $index => $qParams) {
                    // Create the base question
                    $question = Question::create([
                        'question' => $qParams['text'],
                        'business_id' => $business->id,
                        'is_active' => true,
                        'is_default' => false,
                        'type' => $qParams['type'] === 'comment' ? 'comment' : 'star', // Adjust depending on schemas
                        'order_no' => $index + 1,
                    ]);

                    // Attach the child sub-category IDs (array) to the question via pivot table
                    if (isset($qParams['category']) && isset($categoryMap[$qParams['category']])) {
                        $question->question_sub_categories()->sync($categoryMap[$qParams['category']]);
                    }
                }
            }

            // 4. Create Tags/Labels
            if (isset($data['labels'])) {
                foreach ($data['labels'] as $label) {
                    Tag::create([
                        'Name' => ucwords($label),
                        'business_id' => $business->id,
                    ]);
                }
            }
        });
    }
}
