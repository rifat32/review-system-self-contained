<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessArea;
use App\Models\BusinessService;
use App\Models\QuestionCategory;
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
 * - Business services and areas (multiple services and areas)
 * - Multiple surveys with 40+ questions (overall + survey-specific)
 * - 300 reviews from registered users:
 *   - 150 registered user overall reviews (is_overall=1) with business services/areas
 *   - 150 registered user survey reviews (is_overall=0) with business services/areas
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
    private array $businessServices = [];
    private array $businessAreas = [];
    private array $subCategoryMap = [];

    private array $seederData;

    // private const REVIEW_COUNT = 20;
    // private const REGISTERED_OVERALL_REVIEWS = 10;
    // private const REGISTERED_SURVEY_REVIEWS = 10;
    // private const REGISTERED_USERS_COUNT = 10;

    private const REVIEW_COUNT = 300;
    private const REGISTERED_OVERALL_REVIEWS = 150;
    private const REGISTERED_SURVEY_REVIEWS = 150;
    private const REGISTERED_USERS_COUNT = 150;
    private const BRANCHES_COUNT = 4;
    private const STAFF_PER_BRANCH = 3;
    private const DAYS_OF_DATA = 90;
    private const SERVICES_PER_BUSINESS = 5;
    private const AREAS_PER_SERVICE = 3;

    public function __construct()
    {
        $this->businessProfileService = app(BusinessProfileService::class);
        $this->reviewService = app(ReviewService::class);
        $this->aiProcessorService = app(AIProcessorService::class);
        $this->seederData = require database_path('seeders/data/AiReadyDemoBusinessData.php');
    }

    /**
     * Run the database seeds.
     */
    public function run(string $email = 'rifatbilalphilips@gmail.com'): void
    {
        DB::beginTransaction();

        try {
            echo "🚀 Starting AI-Ready Demo Business Seeder...\n\n";

            // Step 1: Create owner user
            $this->createOwner($email);
            echo "✅ Owner created: {$this->owner->email}\n";

            // Step 1.5: Cleanup existing data for this business
            // $this->cleanupExistingData();
            // echo "✅ Cleaned up existing data for '{$this->seederData['business']['business_name']}'\n";


            // Step 2: Create business using service
            $this->createBusinessWithService();
            echo "✅ Business created: {$this->business->Name}\n";

            // Step 3: Create branches
            $this->createBranches();
            echo "✅ Created " . count($this->branches) . " branches\n";

            // Step 3.5: Create question categories and sub-categories
            $this->createQuestionCategories();
            echo "✅ Created question categories and sub-categories\n";

            // Step 4: Create staff using controller/service logic
            $this->createStaff();
            echo "✅ Created " . count($this->staff) . " staff members\n";

            // Step 4.5: Create registered users for reviews
            $this->createRegisteredUsers();
            echo "✅ Created " . count($this->registeredUsers) . " registered users\n";

            // Step 5: Create business services and areas
            $this->createBusinessServicesAndAreas();
            echo "✅ Created " . count($this->businessServices) . " services and " . count($this->businessAreas) . " areas\n";

            // Step 6: Create surveys and questions
            $this->createSurveysAndQuestions();
            echo "✅ Created " . count($this->surveys) . " surveys with " . count($this->questions) . " questions\n";

            // Step 7: Link services to surveys
            $this->linkServicesToSurveys();
            echo "✅ Linked services to surveys\n";

            // Step 8: Load stars and tags
            $this->loadStarsAndTags();
            echo "✅ Loaded " . count($this->stars) . " stars and " . count($this->tags) . " tags\n";

            // ============ NEW STEP: Create QuestionStar and StarTag relationships ============
            $this->createQuestionStarAndStarTagRelationships();
            echo "✅ Created QuestionStar and StarTag relationships\n";
            // ================================================================================

            // Step 9: Prepare review templates
            $this->prepareReviewTexts();

            // Step 10: Create 300 reviews using proper review creation flow
            $this->createReviews();
            echo "✅ Created " . self::REVIEW_COUNT . " reviews with services/areas\n";

            DB::commit();

            echo "\n🎉 Demo business seeded successfully!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📧 Email: {$this->owner->email}\n";
            echo "🏢 Business: {$this->business->Name}\n";
            echo "🏪 Branches: " . count($this->branches) . "\n";
            echo "👥 Staff: " . count($this->staff) . "\n";
            echo "🛠️  Services: " . count($this->businessServices) . "\n";
            echo "📍 Areas: " . count($this->businessAreas) . "\n";
            echo "📝 Reviews: " . self::REVIEW_COUNT . " (150 overall + 150 survey)\n";
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
        $this->owner = User::updateOrCreate(
            ['email' => $email],
            [
                'password' => Hash::make('12345678@We'),
                'first_Name' => 'Demo',
                'last_Name' => 'Owner',
                'phone' => '+1234567890',
                'type' => 'business_Owner',
                'remember_token' => Str::random(10),
                'is_active' => true,
            ]
        );
        $this->owner->email_verified_at = now();
        $this->owner->save();

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
        $businessData = $this->seederData['business'];

        $businessData["trial_end_date"] = Carbon::now()->addDays(365);


        // Use BusinessService to create business (ensures all defaults and AI rules are set)
        $this->business = $this->businessProfileService->createBusiness($this->owner, [
            ...$businessData,
            'business_type' => $this->seederData['business']['business_type'] ?? 'hotel'
        ]);

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
        $branchData = $this->seederData['branches'];

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
        $firstNames = $this->seederData['names']['staff']['first'];
        $lastNames = $this->seederData['names']['staff']['last'];

        $nameIndex = 0;

        foreach ($this->branches as $branchIndex => $branch) {
            for ($i = 0; $i < self::STAFF_PER_BRANCH; $i++) {
                $firstName = $firstNames[$nameIndex % count($firstNames)];
                $lastName = $lastNames[$nameIndex % count($lastNames)];
                $nameIndex++;

                $ownerPrefix = explode('@', $this->owner->email)[0];
                $staff = User::updateOrCreate(
                    ['email' => strtolower($firstName . '.' . $lastName . '.' . $ownerPrefix . $branchIndex . '@aidemo.com')],
                    [
                        'password' => Hash::make('12345678@We'),
                        'first_Name' => $firstName,
                        'last_Name' => $lastName,
                        'phone' => '+4477000000' . str_pad($nameIndex, 2, '0', STR_PAD_LEFT),
                        'type' => 'staff',
                        'business_id' => $this->business->id,
                        'job_title' => $i === 0 ? 'Senior Server' : 'Server',
                        'join_date' => Carbon::now()->subMonths(rand(6, 24))->format('Y-m-d'),
                        'is_active' => true,
                    ]
                );
                $staff->email_verified_at = now();
                $staff->save();

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
        $firstNames = $this->seederData['names']['customers']['first'];
        $lastNames = $this->seederData['names']['customers']['last'];

        for ($i = 0; $i < self::REGISTERED_USERS_COUNT; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $user = User::updateOrCreate(
                ['email' => strtolower('customer.' . ($i + 1) . '@aidemo.com')],
                [
                    'password' => Hash::make('12345678@We'),
                    'first_Name' => $firstName,
                    'last_Name' => $lastName,
                    'phone' => '+44770000' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'type' => 'customer',
                    'business_id' => null, // Global customer
                    'remember_token' => Str::random(10),
                    'is_active' => true,
                ]
            );
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole('customer');

            $this->registeredUsers[] = $user;
        }
    }

    /**
     * Create business services and areas
     */
    private function createBusinessServicesAndAreas(): void
    {
        // Create business services
        $services = $this->seederData['services'];

        foreach ($services as $serviceData) {
            $service = BusinessService::create([
                'business_id' => $this->business->id,
                'name' => $serviceData['name'],
                'description' => $serviceData['description'],
                'is_active' => true,
                'question_title' => 'How was your experience with our ' . $serviceData['name'] . '?',
            ]);

            $this->businessServices[] = $service;

            // Create areas for this service
            foreach ($serviceData['areas'] as $areaName) {
                $area = BusinessArea::create([
                    'business_id' => $this->business->id,
                    'business_service_id' => $service->id,
                    'area_name' => $areaName,
                    'is_active' => true,
                ]);

                $this->businessAreas[] = $area;
            }
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

        // Create additional surveys for specific services
        $surveyNames = $this->seederData['surveys']['specific_names'];

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
        $overallQuestionTemplates = $this->seederData['questions']['overall'];

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
        $surveyQuestionTemplates = $this->seederData['questions']['survey_specific'];

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
     * Link business services to surveys
     */
    private function linkServicesToSurveys(): void
    {
        // Link each service-specific survey to its corresponding business service
        // Surveys indices: 0-1 are overall, 2-6 are service-specific
        foreach ($this->businessServices as $index => $service) {
            if (isset($this->surveys[$index + 2])) { // +2 to skip overall surveys
                DB::table('business_service_surveys')->insert([
                    'business_service_id' => $service->id,
                    'survey_id' => $this->surveys[$index + 2]->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Link questions to sub-categories
        $this->linkQuestionsToCategories();
    }

    private function createQuestionCategories(): void
    {
        echo "📂 Creating question categories and sub-categories...\n";

        $categories = $this->seederData['question_categories'];

        foreach ($categories as $parentTitle => $subTitles) {
            // Special handling for "Staff" which is default
            if ($parentTitle === 'Staff') {
                $parent = QuestionCategory::where('title', 'Staff')->where('is_default', true)->first();
            } else {
                $parent = QuestionCategory::updateOrCreate(
                    ['title' => $parentTitle],
                    [
                        'business_id' => $this->business->id,
                        'description' => "{$parentTitle} category for {$this->business->Name}",
                        'is_active' => true,
                        'is_default' => false,
                        'created_by' => $this->owner->id,
                    ]
                );
            }

            if (!$parent) continue;

            foreach ($subTitles as $subTitle) {
                $sub = QuestionCategory::updateOrCreate(
                    ['title' => $subTitle],
                    [
                        'parent_question_category_id' => $parent->id,
                        'business_id' => $this->business->id,
                        'description' => "{$subTitle} sub-category",
                        'is_active' => true,
                        'is_default' => false,
                        'created_by' => $this->owner->id,
                    ]
                );

                $this->subCategoryMap[$subTitle] = $sub->id;
            }
        }
    }

    private function linkQuestionsToCategories(): void
    {
        echo "🔗 Linking questions to sub-categories...\n";

        $mappings = $this->seederData['question_category_mappings'];

        foreach ($this->questions as $question) {
            $questionText = $question->question;
            if (isset($mappings[$questionText])) {
                $subCategoryName = $mappings[$questionText];
                if (isset($this->subCategoryMap[$subCategoryName])) {
                    $subCategoryId = $this->subCategoryMap[$subCategoryName];

                    DB::table('q_q_sub_categories')->updateOrInsert(
                        ['question_id' => $question->id, 'question_sub_category_id' => $subCategoryId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    private function loadStarsAndTags(): void
    {
        // Load existing stars (should be 1-5)
        $this->stars = Star::orderBy('value')->get()->toArray();

        if (empty($this->stars)) {
            for ($i = 1; $i <= 5; $i++) {
                $star = Star::create(['value' => $i]);
                $this->stars[] = $star->toArray();
            }
        }

        // Ensure all required tags from data file exist
        $requiredTags = $this->seederData['tags']['default'];
        foreach ($requiredTags as $tagName) {
            Tag::updateOrCreate(
                ['tag' => $tagName],
                [
                    'business_id' => null,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        }

        // Load all active default tags into memory for mapping
        $this->tags = Tag::where('is_default', true)->where('is_active', true)->get()->toArray();
    }

    // ====================== NEW METHOD: Create QuestionStar and StarTag relationships ======================
    /**
     * Create QuestionStar and StarTag relationships for all questions
     * This follows the same pattern as your controller's storeOwnerQuestion method
     */
    private function createQuestionStarAndStarTagRelationships(): void
    {
        echo "🔗 Creating QuestionStar and StarTag relationships...\n";

        // Define tag mappings for each star value
        // This matches the logic in your controller where specific tags are associated with specific stars
        $starTagMappings = $this->seederData['star_tag_mappings'];

        // Create a map of tag names to tag IDs for quick lookup
        $tagNameToId = [];
        foreach ($this->tags as $tag) {
            $tagNameToId[$tag['tag']] = $tag['id'];
        }

        // Process each question
        foreach ($this->questions as $question) {
            $questionId = $question['id'] ?? $question->id;

            // For each star (1-5), create QuestionStar relationship
            foreach ($this->stars as $star) {
                $starId = $star['id'];
                $starValue = $star['value'];

                // Create QuestionStar relationship (question + star)
                DB::table('question_stars')->insert([
                    'question_id' => $questionId,
                    'star_id' => $starId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create StarTag relationships for this star value
                if (isset($starTagMappings[$starValue])) {
                    foreach ($starTagMappings[$starValue] as $tagName) {
                        if (isset($tagNameToId[$tagName])) {
                            DB::table('star_tags')->insert([
                                'question_id' => $questionId,
                                'star_id' => $starId,
                                'tag_id' => $tagNameToId[$tagName],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        echo "   Created QuestionStar relationships: " . (count($this->questions) * 5) . " records\n";

        // Count total StarTag relationships created
        $starTagCount = 0;
        foreach ($this->questions as $question) {
            foreach ($this->stars as $star) {
                $starValue = $star['value'];
                if (isset($starTagMappings[$starValue])) {
                    $starTagCount += count($starTagMappings[$starValue]);
                }
            }
        }
        echo "   Created StarTag relationships: " . $starTagCount . " records\n";
    }
    // =======================================================================================================

    /**
     * Prepare review text templates with sentiment alignment
     */
    private function prepareReviewTexts(): void
    {
        $this->reviewTexts = $this->seederData['review_templates'];
    }

    /**
     * Create 300 reviews using proper ReviewNew creation + ReviewService
     * All reviews are from registered users:
     * - 150 registered user overall reviews (is_overall=1) with business services/areas
     * - 150 registered user survey reviews (is_overall=0) with business services/areas
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

                // Select random business service and area for this review
                $serviceAreaPair = $this->getRandomServiceAndArea();
                $businessServiceId = $serviceAreaPair['service_id'];
                $businessAreaId = $serviceAreaPair['area_id'];

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
                    // 'business_service_id' => $businessServiceId, // REMOVED: column doesn't exist in review_news
                    // 'business_area_id' => $businessAreaId,    // REMOVED: column doesn't exist in review_news
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

                // Also attach business service via pivot table (many-to-many relationship)
                if ($businessServiceId) {
                    DB::table('review_business_services')->insert([
                        'review_id' => $review->id,
                        'business_service_id' => $businessServiceId,
                        'business_area_id' => $businessAreaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

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

            // Realistic tags based on star rating
            $tagIds = [];
            $starTagMappings = $this->seederData['star_tag_mappings'] ?? [];
            if (isset($starTagMappings[$starValue])) {
                $possibleTagNames = $starTagMappings[$starValue];

                // Map of tag names to IDs for this business/default
                $tagNameToId = [];
                foreach ($this->tags as $tag) {
                    $tagNameToId[$tag['tag']] = $tag['id'];
                }

                foreach ($possibleTagNames as $tagName) {
                    if (isset($tagNameToId[$tagName])) {
                        $tagIds[] = $tagNameToId[$tagName];
                    }
                }

                // Randomly pick 1-2 tags from the mapping for realism
                if (!empty($tagIds)) {
                    $numTags = min(rand(1, 2), count($tagIds));
                    $selectedIndices = (array)array_rand($tagIds, $numTags);
                    $tagIds = array_map(fn($idx) => $tagIds[$idx], $selectedIndices);
                }
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
     * Get random business service and area pair
     */
    private function getRandomServiceAndArea(): array
    {
        // Get random service
        $service = $this->businessServices[array_rand($this->businessServices)];

        // Get areas for this service
        $serviceAreas = array_filter(
            $this->businessAreas,
            fn($area) => $area->business_service_id === $service->id
        );

        // Get random area from this service
        $area = $serviceAreas[array_rand($serviceAreas)];

        return [
            'service_id' => $service->id,
            'area_id' => $area->id
        ];
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
