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
use App\Services\Business\BusinessService;
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
 * - New owner, business, branches, staff
 * - Multiple surveys with 25+ questions
 * - 300 reviews distributed across branches/staff/time
 * - All data created through APIs/services (no raw DB inserts for AI data)
 * - Ready for AI cron execution and analytics
 */
class AiReadyDemoBusinessSeeder extends Seeder
{
    private BusinessService $businessService;
    private ReviewService $reviewService;
    private AIProcessorService $aiProcessorService;

    private User $owner;
    private Business $business;
    private array $branches = [];
    private array $staff = [];
    private array $surveys = [];
    private array $questions = [];
    private array $tags = [];
    private array $stars = [];
    private array $reviewTexts = [];

    private const REVIEW_COUNT = 300;
    private const BRANCHES_COUNT = 4;
    private const STAFF_PER_BRANCH = 3;
    private const WEEKS_OF_DATA = 12;

    public function __construct()
    {
        $this->businessService = app(BusinessService::class);
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
            echo "⏰ Date Range: " . Carbon::now()->subWeeks(self::WEEKS_OF_DATA)->format('Y-m-d') . " to " . Carbon::now()->format('Y-m-d') . "\n";
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
        ];

        // Use BusinessService to create business (ensures all defaults and AI rules are set)
        $this->business = $this->businessService->createBusiness($this->owner, $businessData);

        // Update owner's business_id link
        $this->updateOwnerBusinessRelation();

        // Create default branch
        $this->businessService->createDefaultBranch($this->business);

        // Create default AI rules
        $this->businessService->createDefaultAiRules($this->business);

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
     * Create surveys and 25+ questions using API/service structure
     */
    private function createSurveysAndQuestions(): void
    {
        // Create 5+ surveys
        $surveyNames = [
            'Overall Experience',
            'Food Quality',
            'Service Quality',
            'Ambiance & Cleanliness',
            'Value for Money'
        ];

        foreach ($surveyNames as $index => $name) {
            $survey = Survey::create([
                'name' => $name,
                'business_id' => $this->business->id,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'order_no' => $index + 1,
                'is_active' => true,
            ]);

            $this->surveys[] = $survey;
        }

        // Create 25+ questions across surveys
        $questionTemplates = [
            // Overall Experience
            ['question' => 'How would you rate your overall experience?', 'is_overall' => true],
            ['question' => 'Would you recommend us to friends and family?', 'is_overall' => false],
            ['question' => 'How likely are you to return?', 'is_overall' => false],
            ['question' => 'How satisfied are you with your visit today?', 'is_overall' => false],
            ['question' => 'How do you rate our establishment compared to competitors?', 'is_overall' => false],

            // Food Quality
            ['question' => 'How would you rate the taste of your meal?', 'is_overall' => false],
            ['question' => 'How would you rate food presentation?', 'is_overall' => false],
            ['question' => 'How fresh were the ingredients?', 'is_overall' => false],
            ['question' => 'How appropriate were the portion sizes?', 'is_overall' => false],
            ['question' => 'How would you rate the menu variety?', 'is_overall' => false],
            ['question' => 'How was the temperature of your food?', 'is_overall' => false],

            // Service Quality
            ['question' => 'How friendly was the staff?', 'is_overall' => false],
            ['question' => 'How knowledgeable was the staff about the menu?', 'is_overall' => false],
            ['question' => 'How quick was the service?', 'is_overall' => false],
            ['question' => 'How attentive was the staff to your needs?', 'is_overall' => false],
            ['question' => 'How efficient was the order processing?', 'is_overall' => false],
            ['question' => 'How professional was the staff?', 'is_overall' => false],

            // Ambiance & Cleanliness
            ['question' => 'How clean was the dining area?', 'is_overall' => false],
            ['question' => 'How comfortable was the seating?', 'is_overall' => false],
            ['question' => 'How pleasant was the atmosphere?', 'is_overall' => false],
            ['question' => 'How appropriate was the music/noise level?', 'is_overall' => false],
            ['question' => 'How clean were the restrooms?', 'is_overall' => false],
            ['question' => 'How modern was the decor?', 'is_overall' => false],

            // Value for Money
            ['question' => 'How fair were the prices?', 'is_overall' => false],
            ['question' => 'How good was the value for money?', 'is_overall' => false],
            ['question' => 'How satisfied were you with drink prices?', 'is_overall' => false],
            ['question' => 'Would you say the quality matched the price?', 'is_overall' => false],
        ];

        foreach ($questionTemplates as $index => $template) {
            $surveyIndex = min((int)($index / 6), count($this->surveys) - 1);

            $question = Question::create([
                'question' => $template['question'],
                'business_id' => $this->business->id,
                'is_default' => false,
                'is_active' => true,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'type' => 'star',
                'order_no' => $index + 1,
                'is_overall' => $template['is_overall'],
            ]);

            // Attach question to survey
            DB::table('survey_questions')->insert([
                'survey_id' => $this->surveys[$surveyIndex]->id,
                'question_id' => $question->id,
                'order_no' => ($index % 6) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->questions[] = $question;
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
     * This ensures events fire, jobs dispatch, and AI preprocessing runs
     */
    private function createReviews(): void
    {
        $startDate = Carbon::now()->subWeeks(self::WEEKS_OF_DATA);
        $endDate = Carbon::now();
        $totalDays = $startDate->diffInDays($endDate);

        $reviewsCreated = 0;
        $progress = 0;

        echo "📝 Creating " . self::REVIEW_COUNT . " reviews...\n";

        for ($i = 0; $i < self::REVIEW_COUNT; $i++) {
            // Temporal distribution: more recent reviews
            $daysAgo = $this->weightedRandomDays($totalDays);
            $createdAt = Carbon::now()->subDays($daysAgo)->setTime(rand(9, 21), rand(0, 59));

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

            // Create review using ReviewNew (triggers observers/events)
            $review = ReviewNew::create([
                'survey_id' => $this->surveys[array_rand($this->surveys)]->id,
                'description' => 'Customer feedback',
                'business_id' => $this->business->id,
                'user_id' => null, // Guest review for simplicity
                'guest_id' => null, // Will create guest users if needed
                'comment' => $reviewTemplate['comment'],
                'raw_text' => $reviewTemplate['comment'],
                'ip_address' => $this->randomIp(),
                'is_overall' => true,
                'staff_id' => $staffMember['user']->id,
                'branch_id' => $branch->id,
                'is_ai_processed' => 0, // AI cron will process these
                'source' => rand(0, 1) ? 'web' : 'app',
                'is_voice_review' => false,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create review values (questions + stars) using ReviewService
            $this->createReviewValues($review, $rating);

            $reviewsCreated++;

            // Progress indicator
            $newProgress = (int)(($reviewsCreated / self::REVIEW_COUNT) * 100);
            if ($newProgress > $progress && $newProgress % 10 === 0) {
                $progress = $newProgress;
                echo "   Progress: {$progress}%\n";
            }
        }
    }

    /**
     * Create review values for a review (question answers with ratings)
     * Note: ReviewService expects tag_ids in the array and will sync them after creating review_value
     */
    private function createReviewValues(ReviewNew $review, int $baseRating): void
    {
        // Select 3-6 random questions from the survey
        $numQuestions = rand(3, 6);
        $selectedQuestions = array_rand(array_column($this->questions, 'id'), min($numQuestions, count($this->questions)));

        if (!is_array($selectedQuestions)) {
            $selectedQuestions = [$selectedQuestions];
        }

        $values = [];

        foreach ($selectedQuestions as $questionIndex) {
            $question = $this->questions[$questionIndex];

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
                'question_id' => $question['id'],
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
        // Exponential distribution favoring recent dates
        $random = mt_rand() / mt_getrandmax();
        return (int)($maxDays * pow($random, 2));
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
