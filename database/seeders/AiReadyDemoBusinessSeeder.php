<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Question;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\User;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\Business\BusinessProfileService;
use App\Services\Review\ReviewService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


/**
 * AI-Ready Demo Business Seeder
 * 
 * Creates a complete, production-ready business with:
 * - New owner, business, branches, staff, registered users
 * - Multiple surveys with 40+ questions (overall + survey-specific)
 * - 300 reviews from registered users:
 *   - 150 registered user overall reviews (is_overall=1)
 *   - 150 registered user survey reviews (is_overall=0)
 * - All data created through APIs/services (no raw DB inserts for AI data)
 * - Ready for AI cron execution and analytics
 */
class AiReadyDemoBusinessSeeder extends Seeder
{
    private BusinessProfileService $businessProfileService;
    private ReviewService $reviewService;
    private AIProcessorService $aiProcessorService;

    private User $owner;
    private Business $business;
    private array $branches = [];
    private array $staff = [];
    private array $surveys = [];
    private array $questions = [];
    private array $overallQuestions = [];
    private array $surveyQuestions = [];
    private array $tags = [];
    private array $stars = [];
    private array $reviewTexts = [];
    private array $registeredUsers = [];

    private const REVIEW_COUNT = 300;
    private const REGISTERED_OVERALL_REVIEWS = 150;
    private const REGISTERED_SURVEY_REVIEWS = 150;
    private const REGISTERED_USERS_COUNT = 150;
    private const BRANCHES_COUNT = 4;
    private const STAFF_PER_BRANCH = 3;
    private const DAYS_OF_DATA = 90;

    public function __construct()
    {
        $this->businessProfileService = app(BusinessProfileService::class);
        $this->reviewService = app(ReviewService::class);
        $this->aiProcessorService = app(AIProcessorService::class);
    }

    /**
     * Run the database seeds.
     */
    public function run(string $email = 'demo@example.com'): void
    {
        DB::beginTransaction();

        try {
            echo "🚀 Starting AI-Ready Demo Business Seeder...\n\n";

            // Step 1: Create owner user
            $this->createOwner($email);
            echo "✅ Owner created: {$this->owner->email}\n";

            // Step 2: Create business using service
            $this->createBusinessWithService();
            echo "✅ Business created: {$this->business->Name}\n";

            // Step 3: Create branches
            $this->createBranches();
            echo "✅ Created " . count($this->branches) . " branches\n";

            // Step 4: Create staff using controller/service logic
            $this->createStaff();
            echo "✅ Created " . count($this->staff) . " staff members\n";

            // Step 4.5: Create registered users for reviews
            $this->createRegisteredUsers();
            echo "✅ Created " . count($this->registeredUsers) . " registered users\n";

            // Step 5: Create surveys and questions
            $this->createSurveysAndQuestions();
            echo "✅ Created " . count($this->surveys) . " surveys with " . count($this->questions) . " questions\n";

            // Step 6: Load stars and tags
            $this->loadStarsAndTags();
            echo "✅ Loaded " . count($this->stars) . " stars and " . count($this->tags) . " tags\n";

            // Step 7: Prepare review templates
            $this->prepareReviewTexts();

            // Step 8: Create 300 reviews using proper review creation flow
            $this->createReviews();
            echo "✅ Created " . self::REVIEW_COUNT . " reviews across time/branches/staff\n";

            DB::commit();

            echo "\n🎉 Demo business seeded successfully!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📧 Email: {$this->owner->email}\n";
            echo "🏢 Business: {$this->business->Name}\n";
            echo "🏪 Branches: " . count($this->branches) . "\n";
            echo "👥 Staff: " . count($this->staff) . "\n";
            echo "📝 Reviews: " . self::REVIEW_COUNT . "\n";
            echo "⏰ Date Range: " . Carbon::now()->subDays(self::DAYS_OF_DATA)->format('Y-m-d') . " to " . Carbon::now()->format('Y-m-d') . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "🤖 Ready for AI cron execution!\n\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            throw $e;
        }
    }

    /**
     * Create owner user (new, never reuse)
     */
    private function createOwner(string $email): void
    {
        $this->owner = User::create([
            'email' => $email,
            'password' => Hash::make('12345678@We'),
            'first_Name' => 'Demo',
            'last_Name' => 'Owner',
            'phone' => '+1234567890',
            'type' => 'business_Owner',
            'remember_token' => Str::random(10),
            'email_verified_at' => now(),
        ]);

        $this->owner->assignRole('business_owner');
    }

    /**
     * Update owner with business_id (after business is created)
     */
    private function updateOwnerBusinessRelation(): void
    {
        $this->owner->update(['business_id' => $this->business->id]);
    }

    /**
     * Create business using BusinessService (respects domain logic)
     */
    private function createBusinessWithService(): void
    {
        $businessData = [
            'business_name' => 'AI Demo Restaurant & Cafe',
            'business_address' => '123 Main Street, Downtown',
            'business_postcode' => 'SW1A 1AA',
            'business_EmailAddress' => 'contact@aidemo.com',
            'business_PhoneNumber' => '+441234567890',
            'business_About' => 'A modern restaurant chain focused on exceptional customer service',
            'business_GoogleMapApi' => '',
            'business_homeText' => 'Welcome to AI Demo Restaurant',
            'business_AdditionalInformation' => 'Open 7 days a week',
            'business_Webpage' => 'https://aidemo.com',
            'header_image' => '/header_image/default.webp',
            'rating_page_image' => '/rating_page_image/default.webp',
            'placeholder_image' => '/placeholder_image/default.webp',
            'primary_color' => '#172c41',
            'secondary_color' => '#ac8538',
            'client_primary_color' => '#172c41',
            'client_secondary_color' => '#ac8538',
            'client_tertiary_color' => '#ffffff',
            'user_review_report' => true,
            'review_type' => 'star',
            'is_branch' => true,
            'is_review_slider' => false,
            'review_only' => false,
            'service_plan_id' => 3,

            "trial_end_date" => Carbon::now()->addDays(365),

        ];


        // Use BusinessService to create business (ensures all defaults and AI rules are set)
        $this->business = $this->businessProfileService->createBusiness($this->owner, $businessData);

        // Update owner's business_id link
        $this->updateOwnerBusinessRelation();

        // Create default branch
        $this->businessProfileService->createDefaultBranch($this->business);

        // Create default AI rules
        $this->businessProfileService->createDefaultAiRules($this->business);

        // Set business settings
        $this->business->update([
            'threshold_rating' => 3.5,
            'enable_ip_check' => false,
            'enable_location_check' => false,
            'Is_guest_user' => true,
            'is_guest_user_overall_review' => true,
            'is_guest_user_survey' => true,
            'is_guest_user_show_stuffs' => true,
            'is_registered_user_overall_review' => true,
            'is_registered_user_survey' => true,
            'is_registered_user_show_stuffs' => true,
            'is_active' => 1,
        ]);

        // Refresh to get default branch
        $this->business->refresh();
    }

    /**
     * Create 4 branches
     */
    private function createBranches(): void
    {
        $branchData = [
            ['name' => 'Downtown Branch', 'city' => 'London', 'postcode' => 'SW1A 1AA'],
            ['name' => 'Westside Branch', 'city' => 'Manchester', 'postcode' => 'M1 1AA'],
            ['name' => 'Eastside Branch', 'city' => 'Birmingham', 'postcode' => 'B1 1AA'],
            ['name' => 'Northside Branch', 'city' => 'Leeds', 'postcode' => 'LS1 1AA'],
        ];

        foreach ($branchData as $index => $data) {
            $branch = Branch::create([
                'business_id' => $this->business->id,
                'name' => $data['name'],
                'address' => ($index + 100) . ' High Street',
                'street' => 'High Street',
                'door_no' => (string)($index + 100),
                'city' => $data['city'],
                'country' => 'UK',
                'postcode' => $data['postcode'],
                'phone' => '+4412345678' . $index,
                'email' => strtolower(str_replace(' ', '', $data['name'])) . '@aidemo.com',
                'is_active' => true,
                'is_default' => $index === 0,
                'is_geo_enabled' => false,
                'branch_code' => 'BR' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
            ]);

            $this->branches[] = $branch;
        }

        // Set default branch
        $this->business->update(['default_branch_id' => $this->branches[0]->id]);
    }

    /**
     * Create staff for each branch (following StaffController logic)
     */
    private function createStaff(): void
    {
        $firstNames = ['Amitabh', 'Sarah', 'Michael', 'Emma', 'David', 'Lisa', 'James', 'Anna', 'Robert', 'Maria', 'Tom', 'Sophie'];
        $lastNames = ['Bachchan', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Anderson', 'Taylor'];

        $nameIndex = 0;

        foreach ($this->branches as $branchIndex => $branch) {
            for ($i = 0; $i < self::STAFF_PER_BRANCH; $i++) {
                $firstName = $firstNames[$nameIndex % count($firstNames)];
                $lastName = $lastNames[$nameIndex % count($lastNames)];
                $nameIndex++;

                $staff = User::create([
                    'email' => strtolower($firstName . '.' . $lastName . $branchIndex .  '.' .  Str::random(5)   . '@aidemo.com'),
                    'password' => Hash::make('12345678@We'),
                    'first_Name' => $firstName,
                    'last_Name' => $lastName,
                    'phone' => '+4477000000' . str_pad($nameIndex, 2, '0', STR_PAD_LEFT),
                    'type' => 'staff',
                    'business_id' => $this->business->id,
                    'job_title' => $i === 0 ? 'Senior Server' : 'Server',
                    'join_date' => Carbon::now()->subMonths(rand(6, 24))->format('Y-m-d'),
                ]);

                $staff->assignRole('business_staff');

                // Step 4.5: Create BranchMember record (essential for staff-branch linkage)
                \App\Models\BranchMember::create([
                    'branch_id' => $branch->id,
                    'user_id' => $staff->id,
                    'role' => 'staff',
                    'joining_date' => $staff->join_date,
                    'is_active' => true,
                ]);

                $this->staff[] = [
                    'user' => $staff,
                    'branch' => $branch,
                ];
            }
        }
    }

    /**
     * Create registered users (customers who will submit reviews)
     */
    private function createRegisteredUsers(): void
    {
        $firstNames = [
            'John',
            'Jane',
            'Alice',
            'Bob',
            'Charlie',
            'Diana',
            'Eve',
            'Frank',
            'Grace',
            'Henry',
            'Isla',
            'Jack',
            'Kate',
            'Leo',
            'Mia',
            'Noah',
            'Olivia',
            'Peter',
            'Quinn',
            'Ruby'
        ];
        $lastNames = [
            'Smith',
            'Jones',
            'Williams',
            'Brown',
            'Wilson',
            'Moore',
            'Taylor',
            'Anderson',
            'Thomas',
            'Jackson',
            'White',
            'Harris',
            'Martin',
            'Thompson',
            'Garcia',
            'Martinez',
            'Robinson',
            'Clark',
            'Rodriguez',
            'Lewis'
        ];

        for ($i = 0; $i < self::REGISTERED_USERS_COUNT; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $user = User::create([
                'email' => strtolower($firstName . '.' . $lastName . '.' . Str::random(5) . '@customer.com'),
                'password' => Hash::make('12345678@We'),
                'first_Name' => $firstName,
                'last_Name' => $lastName,
                'phone' => '+44770000' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'type' => 'customer',
                'business_id' => null, // Regular customer, not tied to business
                'remember_token' => Str::random(10),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('customer');

            $this->registeredUsers[] = $user;
        }
    }

    /**
     * Create surveys and questions using API/service structure
     * Creates questions with is_overall=1 for overall business reviews
     * and is_overall=0 for survey-specific reviews
     */
    private function createSurveysAndQuestions(): void
    {
        // Create surveys for overall business reviews
        $overallGuestSurvey = Survey::create([
            'name' => 'Overall Guest Experience',
            'business_id' => $this->business->id,
            'show_in_guest_user' => true,
            'show_in_user' => false,
            'order_no' => 1,
            'is_active' => true,
        ]);

        $overallRegisteredSurvey = Survey::create([
            'name' => 'Overall Registered User Experience',
            'business_id' => $this->business->id,
            'show_in_guest_user' => false,
            'show_in_user' => true,
            'order_no' => 2,
            'is_active' => true,
        ]);

        // Assign overall surveys to business
        $this->business->update([
            'guest_survey_id' => $overallGuestSurvey->id,
            'registered_user_survey_id' => $overallRegisteredSurvey->id,
        ]);

        $this->surveys[] = $overallGuestSurvey;
        $this->surveys[] = $overallRegisteredSurvey;

        // Create additional surveys for specific surveys
        $surveyNames = [
            'Food Quality Survey',
            'Service Quality Survey',
            'Ambiance & Cleanliness Survey',
            'Value for Money Survey',
            'Delivery Experience Survey',
        ];

        foreach ($surveyNames as $index => $name) {
            $survey = Survey::create([
                'name' => $name,
                'business_id' => $this->business->id,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'order_no' => $index + 3,
                'is_active' => true,
            ]);

            $this->surveys[] = $survey;
        }

        // Create overall questions (is_overall = 1) - for overall business reviews
        $overallQuestionTemplates = [
            'How would you rate your overall experience?',
            'Would you recommend us to friends and family?',
            'How likely are you to return?',
            'How satisfied are you with your visit today?',
            'How do you rate our establishment compared to competitors?',
            'How professional was our service?',
            'How welcoming was our staff?',
            'How well did we meet your expectations?',
            'How would you rate the overall quality?',
            'How satisfied are you with your overall experience?',
        ];

        foreach ($overallQuestionTemplates as $index => $questionText) {
            $question = Question::create([
                'question' => $questionText,
                'business_id' => $this->business->id,
                'is_default' => false,
                'is_active' => true,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'type' => 'star',
                'order_no' => $index + 1,
                'is_overall' => true, // These are for overall business reviews
            ]);

            $this->overallQuestions[] = $question;
            $this->questions[] = $question;

            // Attach to both overall surveys
            DB::table('survey_questions')->insert([
                'survey_id' => $overallGuestSurvey->id,
                'question_id' => $question->id,
                'order_no' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('survey_questions')->insert([
                'survey_id' => $overallRegisteredSurvey->id,
                'question_id' => $question->id,
                'order_no' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create survey-specific questions (is_overall = 0)
        $surveyQuestionTemplates = [
            // Food Quality
            'How would you rate the taste of your meal?',
            'How would you rate food presentation?',
            'How fresh were the ingredients?',
            'How appropriate were the portion sizes?',
            'How would you rate the menu variety?',
            'How was the temperature of your food?',

            // Service Quality
            'How friendly was the staff?',
            'How knowledgeable was the staff about the menu?',
            'How quick was the service?',
            'How attentive was the staff to your needs?',
            'How efficient was the order processing?',
            'How well did the staff handle requests?',

            // Ambiance & Cleanliness
            'How clean was the dining area?',
            'How comfortable was the seating?',
            'How pleasant was the atmosphere?',
            'How appropriate was the music/noise level?',
            'How clean were the restrooms?',
            'How modern was the decor?',

            // Value for Money
            'How fair were the prices?',
            'How good was the value for money?',
            'How satisfied were you with drink prices?',
            'Would you say the quality matched the price?',
            'How satisfied were you with portion-to-price ratio?',
            'How competitive are our prices?',

            // Delivery Experience
            'How timely was the delivery?',
            'How well-packaged was your order?',
            'How accurate was your order?',
            'How satisfied were you with the delivery experience?',
            'How professional was the delivery driver?',
            'How would you rate the food temperature upon arrival?',
        ];

        foreach ($surveyQuestionTemplates as $index => $questionText) {
            $surveyIndex = min((int)($index / 6), count($this->surveys) - 3); // Skip the first 2 overall surveys

            $question = Question::create([
                'question' => $questionText,
                'business_id' => $this->business->id,
                'is_default' => false,
                'is_active' => true,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'type' => 'star',
                'order_no' => count($this->overallQuestions) + $index + 1,
                'is_overall' => false, // These are for survey-specific reviews
            ]);

            $this->surveyQuestions[] = $question;
            $this->questions[] = $question;

            // Attach to appropriate survey (skip first 2 overall surveys)
            DB::table('survey_questions')->insert([
                'survey_id' => $this->surveys[$surveyIndex + 2]->id,
                'question_id' => $question->id,
                'order_no' => ($index % 6) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Load stars and tags from database
     */
    private function loadStarsAndTags(): void
    {
        // Load existing stars (should be 1-5)
        $this->stars = Star::orderBy('value')->get()->toArray();

        // If no stars exist, create them
        if (empty($this->stars)) {
            for ($i = 1; $i <= 5; $i++) {
                $star = Star::create(['value' => $i]);
                $this->stars[] = $star->toArray();
            }
        }

        // Load default tags
        $this->tags = Tag::where('is_default', true)->where('is_active', true)->get()->toArray();

        // If no tags, create basic ones
        if (empty($this->tags)) {
            $defaultTags = ['Excellent', 'Good', 'Average', 'Poor', 'Terrible'];
            foreach ($defaultTags as $tagName) {
                $tag = Tag::create([
                    'tag' => $tagName,
                    'business_id' => null,
                    'is_default' => true,
                    'is_active' => true,
                ]);
                $this->tags[] = $tag->toArray();
            }
        }
    }

    /**
     * Prepare review text templates with sentiment alignment
     */
    private function prepareReviewTexts(): void
    {
        $this->reviewTexts = [
            // Positive (5 stars)
            ['rating' => 5, 'comment' => 'Absolutely outstanding experience! The food was exceptional, staff were incredibly attentive, and the atmosphere was perfect. Highly recommend!'],
            ['rating' => 5, 'comment' => 'One of the best dining experiences I\'ve had. Everything from service to food quality was top-notch. Will definitely be back!'],
            ['rating' => 5, 'comment' => 'Fantastic! Fresh ingredients, skillful preparation, and wonderful presentation. The staff went above and beyond.'],
            ['rating' => 5, 'comment' => 'Exceeded all expectations. Great value for money, beautiful ambiance, and delicious food. Five stars all the way!'],

            // Good (4 stars)
            ['rating' => 4, 'comment' => 'Very good overall. Food was tasty and service was friendly. Just a bit of a wait for our order but worth it.'],
            ['rating' => 4, 'comment' => 'Really enjoyed our meal. Staff were helpful and the atmosphere was nice. Would recommend.'],
            ['rating' => 4, 'comment' => 'Great food quality and good portion sizes. Service could be slightly faster but otherwise excellent.'],
            ['rating' => 4, 'comment' => 'Solid experience. Everything was good, though the dessert menu could be more varied.'],

            // Average (3 stars)
            ['rating' => 3, 'comment' => 'Decent meal, nothing spectacular. Service was okay but took a while to get attention.'],
            ['rating' => 3, 'comment' => 'Average experience. Food was fine but not memorable. Prices are a bit high for what you get.'],
            ['rating' => 3, 'comment' => 'It was alright. Some dishes were better than others. Staff seemed a bit overwhelmed.'],
            ['rating' => 3, 'comment' => 'Middle of the road. Good points and bad points balanced out to an okay experience.'],

            // Below Average (2 stars)
            ['rating' => 2, 'comment' => 'Disappointed with our visit. Food was bland and service was slow. Expected better based on reviews.'],
            ['rating' => 2, 'comment' => 'Not a great experience. Long wait times and food was lukewarm when it arrived. Staff didn\'t seem to care.'],
            ['rating' => 2, 'comment' => 'Below expectations. Menu sounded promising but execution was poor. Overpriced for the quality.'],
            ['rating' => 2, 'comment' => 'Had high hopes but left unsatisfied. Several issues with our order and no apology from staff.'],

            // Poor (1 star)
            ['rating' => 1, 'comment' => 'Terrible experience. Food was cold, service was rude, and the place was dirty. Would not recommend.'],
            ['rating' => 1, 'comment' => 'Worst dining experience in a long time. Completely unacceptable quality and service. Avoid this place.'],
            ['rating' => 1, 'comment' => 'Absolutely awful. Food was inedible, staff were unhelpful, and we waited over an hour. Never again.'],
            ['rating' => 1, 'comment' => 'Do not waste your money here. Poor hygiene, terrible food, and appalling customer service.'],
        ];
    }

    /**
     * Create 300 reviews using proper ReviewNew creation + ReviewService
     * All reviews are from registered users:
     * - 150 registered user overall reviews (is_overall=1)
     * - 150 registered user survey reviews (is_overall=0)
     */
    private function createReviews(): void
    {
        $startDate = Carbon::now()->subDays(self::DAYS_OF_DATA);
        $endDate = Carbon::now();
        $totalDays = $startDate->diffInDays($endDate);

        $reviewsCreated = 0;
        $progress = 0;

        echo "📝 Creating " . self::REVIEW_COUNT . " reviews...\n";

        // Get overall surveys
        $guestOverallSurvey = $this->surveys[0]; // Overall Guest Experience
        $registeredOverallSurvey = $this->surveys[1]; // Overall Registered User Experience

        // Get survey-specific surveys (indices 2-6)
        $specificSurveys = array_slice($this->surveys, 2);

        // Create reviews in 2 groups (only registered users)
        $reviewGroups = [
            // Group 1: Registered overall reviews (is_overall=1, survey_id = registered overall survey)
            [
                'count' => self::REGISTERED_OVERALL_REVIEWS,
                'is_overall' => true,
                'survey' => $registeredOverallSurvey,
                'label' => 'Registered Overall',
            ],
            // Group 2: Registered survey reviews (is_overall=0, survey_id = random specific survey)
            [
                'count' => self::REGISTERED_SURVEY_REVIEWS,
                'is_overall' => false,
                'survey' => null, // Will pick random from specificSurveys
                'label' => 'Registered Survey',
            ],
        ];

        foreach ($reviewGroups as $group) {
            echo "   Creating {$group['count']} {$group['label']} reviews...\n";

            for ($i = 0; $i < $group['count']; $i++) {
                // Uniform temporal distribution: random day and random second
                $daysAgo = $this->weightedRandomDays($totalDays);
                $createdAt = Carbon::now()->subDays($daysAgo)->subSeconds(rand(0, 86399));

                // Select random branch and staff from that branch
                $branchIndex = rand(0, count($this->branches) - 1);
                $branch = $this->branches[$branchIndex];

                // Get staff from this branch
                $branchStaff = array_filter($this->staff, fn($s) => $s['branch']->id === $branch->id);
                $staffMember = $branchStaff[array_rand($branchStaff)];

                // Weighted rating distribution (more 4-5 stars, fewer 1-2 stars)
                $rating = $this->weightedRandomRating();

                // Get matching review text
                $reviewTemplate = $this->getReviewTemplate($rating);

                // Pick random registered user
                $userId = $this->registeredUsers[array_rand($this->registeredUsers)]->id;

                // Determine survey_id
                $surveyId = $group['survey'] ? $group['survey']->id : $specificSurveys[array_rand($specificSurveys)]->id;

                // Create review using ReviewNew (triggers observers/events)
                $review = new ReviewNew([
                    'survey_id' => $surveyId,
                    'description' => 'Customer feedback',
                    'business_id' => $this->business->id,
                    'user_id' => $userId,
                    'comment' => $reviewTemplate['comment'],
                    'raw_text' => $reviewTemplate['comment'],
                    'ip_address' => $this->randomIp(),
                    'is_overall' => $group['is_overall'],
                    'staff_id' => $staffMember['user']->id,
                    'branch_id' => $branch->id,
                    'is_ai_processed' => 0,
                    'source' => rand(0, 1) ? 'web' : 'app',
                    'is_voice_review' => false,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $review->timestamps = false;

                $review->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $review->save();

                // Create review values (questions + stars) using ReviewService
                $this->createReviewValues($review, $rating, $group['is_overall']);

                $reviewsCreated++;

                // Progress indicator
                $newProgress = (int)(($reviewsCreated / self::REVIEW_COUNT) * 100);
                if ($newProgress > $progress && $newProgress % 10 === 0) {
                    $progress = $newProgress;
                    echo "   Progress: {$progress}%\n";
                }
            }
        }
    }

    /**
     * Create review values for a review (question answers with ratings)
     * Note: ReviewService expects tag_ids in the array and will sync them after creating review_value
     * 
     * @param ReviewNew $review The review to create values for
     * @param int $baseRating The base rating (1-5)
     * @param bool $isOverall Whether this is an overall review or survey-specific review
     */
    private function createReviewValues(ReviewNew $review, int $baseRating, bool $isOverall): void
    {
        // Select appropriate questions based on is_overall
        $questionPool = $isOverall ? $this->overallQuestions : $this->surveyQuestions;

        if (empty($questionPool)) {
            // Fallback to all questions if pool is empty
            $questionPool = $this->questions;
        }

        // Select 3-6 random questions from the appropriate pool
        $numQuestions = rand(3, 6);
        $numQuestions = min($numQuestions, count($questionPool));

        if ($numQuestions === 0) {
            return; // No questions available
        }

        $selectedQuestionIndices = $numQuestions === 1
            ? [array_rand($questionPool)]
            : array_rand($questionPool, $numQuestions);

        if (!is_array($selectedQuestionIndices)) {
            $selectedQuestionIndices = [$selectedQuestionIndices];
        }

        $values = [];

        foreach ($selectedQuestionIndices as $questionIndex) {
            $question = $questionPool[$questionIndex];

            // Rating varies ±1 from base rating
            $starValue = max(1, min(5, $baseRating + rand(-1, 1)));
            $starId = $this->stars[$starValue - 1]['id'];

            // Random tags (0-2 tags per question)
            $tagIds = [];
            $numTags = rand(0, 2);
            if ($numTags > 0 && !empty($this->tags)) {
                $selectedTagIndices = (array)array_rand($this->tags, min($numTags, count($this->tags)));
                $tagIds = array_map(fn($idx) => $this->tags[$idx]['id'], $selectedTagIndices);
            }

            // Format exactly as ReviewService expects (tag_ids will be filtered out during create, then synced)
            $values[] = [
                'question_id' => $question['id'] ?? $question->id,
                'star_id' => $starId,
                'tag_ids' => $tagIds, // This stays in array, ReviewService handles it properly
            ];
        }

        // Use ReviewService to store values
        // ReviewService will create ReviewValueNew (ignoring tag_ids as it's not fillable)
        // Then sync tags via $review_value->tags()->sync($value['tag_ids'])
        $this->reviewService->storeReviewValues($review, $values, $this->business);
    }

    /**
     * Weighted random days (more recent reviews)
     */
    private function weightedRandomDays(int $maxDays): int
    {
        // Uniform distribution matching suggested SQL: FLOOR(RAND() * maxDays)
        return rand(0, $maxDays - 1);
    }

    /**
     * Weighted random rating (more 4-5 stars, realistic distribution)
     */
    private function weightedRandomRating(): int
    {
        $rand = rand(1, 100);

        // Distribution: 5★=35%, 4★=30%, 3★=20%, 2★=10%, 1★=5%
        if ($rand <= 35) return 5;
        if ($rand <= 65) return 4;
        if ($rand <= 85) return 3;
        if ($rand <= 95) return 2;
        return 1;
    }

    /**
     * Get review template matching rating
     */
    private function getReviewTemplate(int $rating): array
    {
        $templates = array_filter($this->reviewTexts, fn($t) => $t['rating'] === $rating);
        return $templates[array_rand($templates)];
    }

    /**
     * Generate random IP address
     */
    private function randomIp(): string
    {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255);
    }
}
