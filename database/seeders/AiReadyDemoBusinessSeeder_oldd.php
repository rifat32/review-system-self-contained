<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessArea;
use App\Models\BusinessService;
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
 * Hotel Business Seeder - Updated for Fusion Desserts
 * 
 * Creates a complete hotel business with room-related questions
 * One survey is mandatory, one is optional
 */
class AiReadyDemoBusinessSeeder_oldd extends Seeder
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
    private array $categoryMap = [];
    private array $subCategoryMap = [];

    private const REVIEW_COUNT = 300;
    private const REGISTERED_OVERALL_REVIEWS = 150;
    private const REGISTERED_SURVEY_REVIEWS = 150;
    private const REGISTERED_USERS_COUNT = 150;
    private const BRANCHES_COUNT = 1; // Hotel typically has one main location
    private const STAFF_PER_BRANCH = 4; // 4 staff members as requested
    private const DAYS_OF_DATA = 90;
    private const SERVICES_PER_BUSINESS = 4; // Hotel services
    private const AREAS_PER_SERVICE = 3;

    public function __construct()
    {
        $this->businessProfileService = app(BusinessProfileService::class);
        $this->reviewService = app(ReviewService::class);
        $this->aiProcessorService = app(AIProcessorService::class);
    }

    /**
     * Run the database seeds.
     */
    public function run(string $email = 'fusiondesserts@example.com'): void
    {
        DB::beginTransaction();

        try {
            echo "🚀 Starting Hotel Business Seeder (Fusion Desserts)...\n\n";

            // Cleanup existing business data if it exists to allow re-seeding
            $this->cleanupExistingData($email);

            // Step 1: Create owner user
            $this->createOwner($email);
            echo "✅ Owner created: {$this->owner->email}\n";

            // Step 2: Create business using service
            $this->createBusinessWithService();
            echo "✅ Hotel Business created: {$this->business->Name}\n";

            // Step 3: Create branch (hotel location)
            $this->createBranches();
            echo "✅ Created " . count($this->branches) . " branch (hotel location)\n";

            // Step 4: Create staff using AI-generated style profiles
            $this->createStaff();
            echo "✅ Created " . count($this->staff) . " staff members\n";

            // Step 4.5: Create registered users for reviews
            $this->createRegisteredUsers();
            echo "✅ Created " . count($this->registeredUsers) . " registered users\n";

            // Step 5: Create hotel services and areas
            $this->createBusinessServicesAndAreas();
            echo "✅ Created " . count($this->businessServices) . " hotel services and " . count($this->businessAreas) . " areas\n";

            // Step 5.5: Create categories and sub-categories
            $this->createCategoriesAndSubCategories();
            echo "✅ Created hotel categories and sub-categories\n";

            // Step 6: Create surveys and questions - ONE with mandatory survey, ONE with optional
            $this->createSurveysAndQuestions();
            echo "✅ Created " . count($this->surveys) . " surveys with " . count($this->questions) . " questions\n";

            // Step 7: Link services to surveys
            $this->linkServicesToSurveys();
            echo "✅ Linked services to surveys\n";

            // Step 8: Load stars and tags
            $this->loadStarsAndTags();
            echo "✅ Loaded " . count($this->stars) . " stars and " . count($this->tags) . " tags\n";

            // Step 9: Create QuestionStar and StarTag relationships
            $this->createQuestionStarAndStarTagRelationships();
            echo "✅ Created QuestionStar and StarTag relationships\n";

            // Step 10: Prepare review templates
            $this->prepareReviewTexts();

            // Step 11: Create 300 reviews using proper review creation flow
            $this->createReviews();
            echo "✅ Created " . self::REVIEW_COUNT . " reviews with services/areas\n";

            DB::commit();

            echo "\n🎉 Hotel business seeded successfully!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "🏨 Business: Fusion Desserts Hotel\n";
            echo "📧 Email: {$this->owner->email}\n";
            echo "🏪 Location: " . $this->branches[0]->city . "\n";
            echo "👥 Staff: " . count($this->staff) . "\n";
            echo "🛏️  Room Types/Services: " . count($this->businessServices) . "\n";
            echo "📝 Reviews: " . self::REVIEW_COUNT . " (150 overall + 150 survey)\n";
            echo "📊 Surveys: 2 (one mandatory, one optional with room questions)\n";
            echo "⏰ Date Range: " . Carbon::now()->subDays(self::DAYS_OF_DATA)->format('Y-m-d') . " to " . Carbon::now()->format('Y-m-d') . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            throw $e;
        }
    }

    private function createOwner(string $email): void
    {
        $this->owner = User::firstOrCreate(
            ['email' => $email],
            [
                'password' => Hash::make('12345678@We'),
                'first_Name' => 'Fusion',
                'last_Name' => 'Desserts',
                'phone' => '+441234567890',
                'type' => 'business_Owner',
                'remember_token' => Str::random(10),
            ]
        );
        $this->owner->email_verified_at = Carbon::now();
        $this->owner->save();

        $this->owner->assignRole('business_owner');
    }

    /**
     * Cleanup existing data for this business
     */
    private function cleanupExistingData(string $email): void
    {
        $owner = User::where('email', $email)->first();
        if ($owner) {
            $business = Business::where('Name', 'Fusion Desserts Hotel & Restaurant')->first();
            if ($business) {
                // Delete everything related to this business
                DB::table('review_news')->where('business_id', $business->id)->delete();
                DB::table('surveys')->where('business_id', $business->id)->delete();
                DB::table('questions')->where('business_id', $business->id)->delete();
                DB::table('question_categories')->where('business_id', $business->id)->delete();
                DB::table('business_services')->where('business_id', $business->id)->delete();
                DB::table('business_areas')->where('business_id', $business->id)->delete();
                DB::table('branches')->where('business_id', $business->id)->delete();

                // Finally delete the business itself
                $business->delete();
            }
        }
    }

    /**
     * Update owner with business_id (after business is created)
     */
    private function updateOwnerBusinessRelation(): void
    {
        $this->owner->update(['business_id' => $this->business->id]);
    }

    /**
     * Create hotel business for Fusion Desserts
     */
    private function createBusinessWithService(): void
    {
        $businessData = [
            'business_name' => 'Fusion Desserts Hotel & Restaurant',
            'business_address' => '25 Baker Street, Marylebone',
            'business_postcode' => 'W1U 8EW',
            'business_EmailAddress' => 'reservations@fusiondessertshotel.co.uk',
            'business_PhoneNumber' => '+442074862500',
            'business_About' => 'Luxury boutique hotel with world-class dessert restaurant. Experience the perfect fusion of comfort and culinary excellence.',
            'business_GoogleMapApi' => '',
            'business_homeText' => 'Welcome to Fusion Desserts Hotel',
            'business_AdditionalInformation' => '24/7 Room Service, Free WiFi, Dessert Tasting Menu Available',
            'business_Webpage' => 'https://www.fusiondessertshotel.co.uk',
            'header_image' => '/header_image/default.webp',
            'rating_page_image' => '/rating_page_image/default.webp',
            'placeholder_image' => '/placeholder_image/default.webp',
            'primary_color' => '#8B4513', // Chocolate brown
            'secondary_color' => '#FFD700', // Gold
            'client_primary_color' => '#8B4513',
            'client_secondary_color' => '#FFD700',
            'client_tertiary_color' => '#FFFFFF',
            'user_review_report' => true,
            'review_type' => 'star',
            'is_branch' => false,
            'is_review_slider' => false,
            'review_only' => false,
            'service_plan_id' => 3,
            "trial_end_date" => Carbon::now()->addDays(365),
        ];

        // Use BusinessService to create business
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
            'is_guest_user_survey' => false, // No survey for guests
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
     * Create hotel branch/location
     */
    private function createBranches(): void
    {
        $branch = Branch::create([
            'business_id' => $this->business->id,
            'name' => 'Fusion Desserts Hotel London',
            'address' => '25 Baker Street',
            'street' => 'Baker Street',
            'door_no' => '25',
            'city' => 'London',
            'country' => 'UK',
            'postcode' => 'W1U 8EW',
            'phone' => '+442074862500',
            'email' => 'london@fusiondessertshotel.co.uk',
            'is_active' => true,
            'is_default' => true,
            'is_geo_enabled' => false,
            'branch_code' => 'FDH001',
        ]);

        $this->branches[] = $branch;

        // Set default branch
        $this->business->update(['default_branch_id' => $this->branches[0]->id]);
    }

    /**
     * Create 4 staff members with AI-style names (as requested)
     */
    private function createStaff(): void
    {
        $staffMembers = [
            ['first' => 'Eleanor', 'last' => 'Vanilla', 'title' => 'Pastry Chef', 'img' => 'eleanor_ai.jpg'],
            ['first' => 'Marcus', 'last' => 'Chocolate', 'title' => 'Head Chef', 'img' => 'marcus_ai.jpg'],
            ['first' => 'Sophia', 'last' => 'Berry', 'title' => 'Hotel Manager', 'img' => 'sophia_ai.jpg'],
            ['first' => 'Leo', 'last' => 'Caramel', 'title' => 'Front Desk Manager', 'img' => 'leo_ai.jpg'],
        ];

        foreach ($staffMembers as $index => $staffData) {
            $staff = User::updateOrCreate(
                ['email' => strtolower($staffData['first'] . '.' . $staffData['last'] . '@fusiondessertshotel.co.uk')],
                [
                    'password' => Hash::make('12345678@We'),
                    'first_Name' => $staffData['first'],
                    'last_Name' => $staffData['last'],
                    'phone' => '+447700123' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'type' => 'staff',
                    'business_id' => $this->business->id,
                    'job_title' => $staffData['title'],
                    'join_date' => Carbon::now()->subMonths(rand(6, 24))->format('Y-m-d'),
                ]
            );
            $staff->email_verified_at = now();
            $staff->save();

            $staff->assignRole('business_staff');

            // Create BranchMember record
            \App\Models\BranchMember::create([
                'branch_id' => $this->branches[0]->id,
                'user_id' => $staff->id,
                'role' => 'staff',
                'joining_date' => $staff->join_date,
                'is_active' => true,
            ]);

            $this->staff[] = [
                'user' => $staff,
                'branch' => $this->branches[0],
            ];
        }
    }

    /**
     * Create registered users (hotel guests)
     */
    private function createRegisteredUsers(): void
    {
        $firstNames = ['James', 'Emma', 'Oliver', 'Charlotte', 'William', 'Amelia', 'Henry', 'Isabella'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];

        for ($i = 0; $i < self::REGISTERED_USERS_COUNT; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $user = User::updateOrCreate(
                ['email' => "hotel_guest_{$i}@example.com"],
                [
                    'password' => Hash::make('12345678@We'),
                    'first_Name' => 'Guest',
                    'last_Name' => (string)$i,
                    'phone' => '+447911' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
                    'type' => 'customer',
                    'business_id' => $this->business->id,
                    'remember_token' => Str::random(10),
                ]
            );
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole('customer');

            $this->registeredUsers[] = $user;
        }
    }

    /**
     * Create hotel services and areas (rooms)
     */
    private function createBusinessServicesAndAreas(): void
    {
        // Hotel room types as services
        $services = [
            [
                'name' => 'Deluxe Room',
                'description' => 'Spacious room with king bed and city view',
                'areas' => ['Room 101-120', 'Floor 1-2', 'City View Wing']
            ],
            [
                'name' => 'Executive Suite',
                'description' => 'Luxury suite with separate living area',
                'areas' => ['Room 201-210', 'Executive Floor', 'Park View Wing']
            ],
            [
                'name' => 'Dessert Tasting Experience',
                'description' => 'Premium dessert tasting menu at our restaurant',
                'areas' => ['Main Restaurant', 'Private Dining', 'Terrace Area']
            ],
            [
                'name' => 'Spa & Wellness',
                'description' => 'Hotel spa and wellness facilities',
                'areas' => ['Spa Center', 'Treatment Rooms', 'Relaxation Area']
            ]
        ];

        foreach ($services as $serviceData) {
            $service = BusinessService::updateOrCreate(
                [
                    'business_id' => $this->business->id,
                    'name' => $serviceData['name'],
                ],
                [
                    'description' => $serviceData['description'],
                    'is_active' => true,
                    'question_title' => 'How was your experience with our ' . $serviceData['name'] . '?',
                ]
            );

            $this->businessServices[] = $service;

            // Create areas for this service
            foreach ($serviceData['areas'] as $areaName) {
                $area = BusinessArea::updateOrCreate(
                    [
                        'business_id' => $this->business->id,
                        'business_service_id' => $service->id,
                        'area_name' => $areaName,
                    ],
                    [
                        'is_active' => true,
                    ]
                );

                $this->businessAreas[] = $area;
            }
        }
    }

    /**
     * Create surveys and questions for hotel
     * One mandatory survey and one optional survey with room-related questions
     */
    private function createSurveysAndQuestions(): void
    {
        // Create MANDATORY survey (must be completed after review)
        $mandatorySurvey = Survey::create([
            'name' => 'Mandatory Hotel Feedback',
            'business_id' => $this->business->id,
            'show_in_guest_user' => false,
            'show_in_user' => true,
            'order_no' => 1,
            'is_active' => true,
        ]);

        // Create OPTIONAL survey (can be skipped)
        $optionalSurvey = Survey::create([
            'name' => 'Optional Room Service Feedback',
            'business_id' => $this->business->id,
            'show_in_guest_user' => false,
            'show_in_user' => true,
            'order_no' => 2,
            'is_active' => true,
        ]);

        // Assign surveys to business
        $this->business->update([
            'guest_survey_id' => null, // No guest surveys
            'registered_user_survey_id' => $mandatorySurvey->id, // Primary is mandatory
        ]);

        $this->surveys[] = $mandatorySurvey;
        $this->surveys[] = $optionalSurvey;

        // Create overall questions for mandatory survey
        $overallQuestionTemplates = [
            'How would you rate your overall hotel stay?',
            'How comfortable was your room?',
            'How would you rate the cleanliness of your room?',
            'How responsive was the hotel staff to your requests?',
            'How would you rate the quality of our desserts?',
            'How likely are you to recommend our hotel to others?',
            'How satisfied were you with the check-in/check-out process?',
            'How would you rate the hotel amenities?',
            'How was the quality of room service?',
            'Overall, how satisfied were you with your stay?',
        ];

        foreach ($overallQuestionTemplates as $index => $questionText) {
            $questionCategory = null;
            if (str_contains(strtolower($questionText), 'clean')) {
                $questionCategory = $this->categoryMap['Cleanliness'] ?? null;
            } elseif (str_contains(strtolower($questionText), 'comfort') || str_contains(strtolower($questionText), 'stay')) {
                $questionCategory = $this->categoryMap['Comfort'] ?? null;
            } elseif (str_contains(strtolower($questionText), 'staff') || str_contains(strtolower($questionText), 'check-in') || str_contains(strtolower($questionText), 'responsive')) {
                $questionCategory = $this->categoryMap['Service'] ?? null;
            } elseif (str_contains(strtolower($questionText), 'dessert')) {
                $questionCategory = $this->categoryMap['Food & Beverage'] ?? null;
            }

            $question = Question::create([
                'question' => $questionText,
                'business_id' => $this->business->id,
                'is_default' => false,
                'is_active' => true,
                'show_in_guest_user' => false,
                'show_in_user' => true,
                'type' => 'star',
                'order_no' => $index + 1,
                'is_overall' => true,
            ]);

            $this->overallQuestions[] = $question;
            $this->questions[] = $question;

            // Attach to category and sub-category if applicable
            if ($questionCategory) {
                $syncIds = [$questionCategory->id];
                $subCat = $this->subCategoryMap[$questionCategory->title] ?? null;
                if ($subCat) {
                    $syncIds[] = $subCat->id;
                }
                $question->question_sub_categories()->sync($syncIds);
            }

            // Attach to mandatory survey
            DB::table('survey_questions')->insert([
                'survey_id' => $mandatorySurvey->id,
                'question_id' => $question->id,
                'order_no' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create room-specific questions for optional survey (with room number selection)
        $roomQuestionTemplates = [
            // Room quality questions
            [
                'text' => 'Please select your room number:',
                'type' => 'dropdown',
                'options' => '101,102,103,104,105,106,107,108,109,110,201,202,203,204,205,206,207,208,209,210'
            ],
            [
                'text' => 'How was the cleanliness of your bathroom?',
                'type' => 'star'
            ],
            [
                'text' => 'How comfortable was the bed?',
                'type' => 'star'
            ],
            [
                'text' => 'How was the temperature control in your room?',
                'type' => 'star'
            ],
            [
                'text' => 'How would you rate the noise level in your room?',
                'type' => 'star'
            ],
            [
                'text' => 'What type of room did you stay in?',
                'type' => 'dropdown',
                'options' => 'Deluxe Room,Executive Suite,Family Room,Accessible Room'
            ],
            // Dessert/restaurant questions
            [
                'text' => 'Did you try our dessert tasting menu?',
                'type' => 'dropdown',
                'options' => 'Yes,No,Not yet but plan to'
            ],
            [
                'text' => 'How would you rate the dessert presentation?',
                'type' => 'star'
            ],
            [
                'text' => 'How fresh were the dessert ingredients?',
                'type' => 'star'
            ],
            [
                'text' => 'How would you rate our room service desserts?',
                'type' => 'star'
            ],
        ];

        foreach ($roomQuestionTemplates as $index => $questionData) {
            $questionCategory = null;
            if (str_contains(strtolower($questionData['text']), 'clean') || str_contains(strtolower($questionData['text']), 'bathroom')) {
                $questionCategory = $this->categoryMap['Cleanliness'] ?? null;
            } elseif (str_contains(strtolower($questionData['text']), 'bed') || str_contains(strtolower($questionData['text']), 'comfort') || str_contains(strtolower($questionData['text']), 'room') || str_contains(strtolower($questionData['text']), 'noise')) {
                $questionCategory = $this->categoryMap['Comfort'] ?? null;
            } elseif (str_contains(strtolower($questionData['text']), 'dessert')) {
                $questionCategory = $this->categoryMap['Food & Beverage'] ?? null;
            }

            $question = Question::create([
                'question' => $questionData['text'],
                'business_id' => $this->business->id,
                'is_default' => false,
                'is_active' => true,
                'show_in_guest_user' => false,
                'show_in_user' => true,
                'type' => $questionData['type'],
                'order_no' => count($this->overallQuestions) + $index + 1,
                'is_overall' => false,
            ]);

            $this->surveyQuestions[] = $question;
            $this->questions[] = $question;

            // Attach to category and sub-category if applicable
            if ($questionCategory) {
                $syncIds = [$questionCategory->id];
                $subCat = $this->subCategoryMap[$questionCategory->title] ?? null;
                if ($subCat) {
                    $syncIds[] = $subCat->id;
                }
                $question->question_sub_categories()->sync($syncIds);
            }

            // Attach to optional survey
            DB::table('survey_questions')->insert([
                'survey_id' => $optionalSurvey->id,
                'question_id' => $question->id,
                'order_no' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Link hotel services to surveys
     */
    private function linkServicesToSurveys(): void
    {
        // Link room services to optional survey (index 1)
        $optionalSurvey = $this->surveys[1];

        foreach ($this->businessServices as $service) {
            DB::table('business_service_surveys')->insert([
                'business_service_id' => $service->id,
                'survey_id' => $optionalSurvey->id,
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
                $star = \App\Models\Star::create(['value' => $i]);
                $this->stars[] = $star->toArray();
            }
        }

        // Load default tags
        $this->tags = Tag::where('is_default', true)->where('is_active', true)->get()->toArray();

        // If no tags, create hotel-specific ones
        if (empty($this->tags)) {
            $hotelTags = [
                'Excellent',
                'Good',
                'Average',
                'Poor',
                'Terrible',
                'Clean',
                'Comfortable',
                'Noisy',
                'Spacious',
                'Luxurious',
                'Dirty',
                'Rude Staff',
                'Slow Service',
                'Small Room',
                'Perfect',
                'Delicious',
                'Highly Recommended',
                'Needs Improvement',
                'Friendly',
                'Professional',
                'Standard',
                'Decent'
            ];
            foreach ($hotelTags as $tagName) {
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
     * Create hotel categories and sub-categories
     */
    private function createCategoriesAndSubCategories(): void
    {
        $categories = [
            'Cleanliness' => 'Overall hygiene and sanitation of hotel premises and rooms',
            'Comfort' => 'Quality of stay, bedding, and room amenities',
            'Service' => 'Staff responsiveness, check-in/out, and hospitality',
            'Food & Beverage' => 'Quality of desserts, room service, and restaurant experience',
        ];

        foreach ($categories as $title => $description) {
            $category = \App\Models\QuestionCategory::updateOrCreate(
                [
                    'title' => $title,
                    'business_id' => $this->business->id,
                ],
                [
                    'description' => $description,
                    'is_active' => true,
                    'is_default' => false,
                    'created_by' => $this->owner->id,
                ]
            );

            $this->categoryMap[$title] = $category;

            // Create a sub-category for each
            $subCategory = \App\Models\QuestionCategory::updateOrCreate(
                [
                    'title' => $title . ' Sub',
                    'business_id' => $this->business->id,
                    'parent_question_category_id' => $category->id,
                ],
                [
                    'description' => 'Detailed ' . $title . ' metrics',
                    'is_active' => true,
                    'is_default' => false,
                    'created_by' => $this->owner->id,
                ]
            );

            $this->subCategoryMap[$title] = $subCategory;
        }
    }

    /**
     * Create QuestionStar and StarTag relationships
     */
    private function createQuestionStarAndStarTagRelationships(): void
    {
        echo "🔗 Creating QuestionStar and StarTag relationships...\n";

        // Define tag mappings for each star value
        $starTagMappings = [
            1 => ['Terrible', 'Poor', 'Noisy', 'Dirty', 'Rude Staff'],
            2 => ['Poor', 'Needs Improvement', 'Slow Service', 'Small Room'],
            3 => ['Average', 'Decent', 'Standard', 'Okay'],
            4 => ['Good', 'Comfortable', 'Friendly', 'Clean'],
            5 => ['Excellent', 'Luxurious', 'Perfect', 'Delicious', 'Highly Recommended', 'Spacious', 'Professional'],
        ];

        // Create a map of tag names to tag IDs
        $tagNameToId = [];
        foreach ($this->tags as $tag) {
            $tagNameToId[$tag['tag']] = $tag['id'];
        }

        // Process each question
        foreach ($this->questions as $question) {
            $questionId = $question['id'] ?? $question->id;

            // For star-type questions only, create QuestionStar relationship
            if ($question['type'] ?? $question->type === 'star') {
                foreach ($this->stars as $star) {
                    $starId = $star['id'];
                    $starValue = $star['value'];

                    // Create QuestionStar relationship
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
                                // Create StarTag relationship
                                DB::table('star_tags')->insert([
                                    'question_id' => $questionId,
                                    'star_id' => $starId,
                                    'tag_id' => $tagNameToId[$tagName],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                // Also create StarTagQuestion relationship
                                DB::table('star_tag_questions')->insert([
                                    'question_id' => $questionId,
                                    'star_id' => $starId,
                                    'tag_id' => $tagNameToId[$tagName],
                                    'is_default' => false,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }
        }

        echo "   Created QuestionStar relationships\n";
        echo "   Created StarTag relationships\n";
    }

    /**
     * Prepare hotel-specific review text templates
     */
    private function prepareReviewTexts(): void
    {
        $this->reviewTexts = [
            // Positive (5 stars)
            ['rating' => 5, 'comment' => 'Exceptional stay! The room was spotless, bed incredibly comfortable, and the dessert tasting menu was divine. Staff went above and beyond.'],
            ['rating' => 5, 'comment' => 'Perfect boutique hotel experience. From check-in to check-out, everything was flawless. The desserts are truly world-class.'],
            ['rating' => 5, 'comment' => 'Luxurious room with amazing amenities. The attention to detail was impressive. Best hotel dessert experience ever!'],
            ['rating' => 5, 'comment' => 'Outstanding service and beautiful rooms. The chocolate soufflé was the best I\'ve ever had. Will definitely return.'],

            // Good (4 stars)
            ['rating' => 4, 'comment' => 'Very comfortable stay. Great location, clean rooms, and delicious desserts. Minor issue with room temperature but quickly resolved.'],
            ['rating' => 4, 'comment' => 'Enjoyed our stay. Staff were friendly and room was spacious. Dessert selection could be larger but quality was excellent.'],
            ['rating' => 4, 'comment' => 'Good hotel with comfortable beds. Dessert restaurant is a highlight. Would recommend for a sweet getaway.'],
            ['rating' => 4, 'comment' => 'Solid hotel experience. Room service was prompt and desserts were delicious. Some noise from hallway but otherwise great.'],

            // Average (3 stars)
            ['rating' => 3, 'comment' => 'Decent stay. Room was clean but basic. Desserts were good but not exceptional. Average hotel experience.'],
            ['rating' => 3, 'comment' => 'Okay for the price. Room was smaller than expected. Desserts were tasty but service was slow at peak times.'],
            ['rating' => 3, 'comment' => 'Average hotel. Nothing spectacular but nothing terrible either. Dessert menu was the best part.'],
            ['rating' => 3, 'comment' => 'Room was adequate. Some maintenance issues but staff addressed them. Desserts were the highlight of the stay.'],

            // Below Average (2 stars)
            ['rating' => 2, 'comment' => 'Disappointing stay. Room was not properly cleaned. Desserts were good but couldn\'t compensate for poor room condition.'],
            ['rating' => 2, 'comment' => 'Not worth the price. Noisy room, slow room service. Only positive was the dessert quality.'],
            ['rating' => 2, 'comment' => 'Expected more based on reviews. Room had maintenance issues. Desserts saved the experience.'],
            ['rating' => 2, 'comment' => 'Below expectations. Check-in took too long. Room amenities not working properly. Good desserts though.'],

            // Poor (1 star)
            ['rating' => 1, 'comment' => 'Terrible experience. Dirty room, unhelpful staff. Only the desserts were edible. Would not recommend.'],
            ['rating' => 1, 'comment' => 'Worst hotel stay. Room was unclean, bed uncomfortable. Desserts were the only edible food. Avoid.'],
            ['rating' => 1, 'comment' => 'Complete disappointment. No hot water, noisy AC. Even the desserts couldn\'t save this stay.'],
            ['rating' => 1, 'comment' => 'Awful experience from start to finish. Overpriced and underdelivered. Only good thing was dessert quality.'],
        ];
    }

    /**
     * Create 300 reviews using proper ReviewNew creation + ReviewService
     */
    private function createReviews(): void
    {
        $startDate = Carbon::now()->subDays(self::DAYS_OF_DATA);
        $endDate = Carbon::now();
        $totalDays = $startDate->diffInDays($endDate);

        $reviewsCreated = 0;
        $progress = 0;

        echo "📝 Creating " . self::REVIEW_COUNT . " hotel reviews...\n";

        // Get surveys
        $mandatorySurvey = $this->surveys[0]; // Mandatory survey
        $optionalSurvey = $this->surveys[1]; // Optional survey

        // Create reviews in 2 groups (only registered users)
        $reviewGroups = [
            // Group 1: Registered overall reviews with mandatory survey
            [
                'count' => self::REGISTERED_OVERALL_REVIEWS,
                'is_overall' => true,
                'survey' => $mandatorySurvey,
                'label' => 'Mandatory Survey Reviews',
            ],
            // Group 2: Registered survey reviews with optional survey (room questions)
            [
                'count' => self::REGISTERED_SURVEY_REVIEWS,
                'is_overall' => false,
                'survey' => $optionalSurvey,
                'label' => 'Optional Survey Reviews',
            ],
        ];

        foreach ($reviewGroups as $group) {
            echo "   Creating {$group['count']} {$group['label']}...\n";

            for ($i = 0; $i < $group['count']; $i++) {
                // Uniform temporal distribution
                $daysAgo = $this->weightedRandomDays($totalDays);
                $createdAt = Carbon::now()->subDays($daysAgo)->subSeconds(rand(0, 86399));

                // Select random staff member
                $staffMember = $this->staff[array_rand($this->staff)];

                // Weighted rating distribution
                $rating = $this->weightedRandomRating();

                // Get matching review text
                $reviewTemplate = $this->getReviewTemplate($rating);

                // Pick random registered user (hotel guest)
                $userId = $this->registeredUsers[array_rand($this->registeredUsers)]->id;

                // Select random hotel service and area for this review
                $serviceAreaPair = $this->getRandomServiceAndArea();
                $businessServiceId = $serviceAreaPair['service_id'];
                $businessAreaId = $serviceAreaPair['area_id'];

                // Create review using ReviewNew
                $review = new ReviewNew([
                    'survey_id' => $group['survey']->id,
                    'description' => 'Hotel stay feedback',
                    'business_id' => $this->business->id,
                    'user_id' => $userId,
                    'comment' => $reviewTemplate['comment'],
                    'raw_text' => $reviewTemplate['comment'],
                    'ip_address' => $this->randomIp(),
                    'is_overall' => $group['is_overall'],
                    'staff_id' => $staffMember['user']->id,
                    'branch_id' => $this->branches[0]->id,
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

                // Also attach business service via pivot table
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
     * Create review values for a review
     */
    private function createReviewValues(ReviewNew $review, int $baseRating, bool $isOverall): void
    {
        // Select appropriate questions based on is_overall
        $questionPool = $isOverall ? $this->overallQuestions : $this->surveyQuestions;

        if (empty($questionPool)) {
            $questionPool = $this->questions;
        }

        // Select 3-6 random questions from the appropriate pool
        $numQuestions = rand(3, 6);
        $numQuestions = min($numQuestions, count($questionPool));

        if ($numQuestions === 0) {
            return;
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

            // Skip dropdown questions for star rating
            $questionType = $question['type'] ?? $question->type;
            if ($questionType !== 'star') {
                continue;
            }

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

            $values[] = [
                'question_id' => $question['id'] ?? $question->id,
                'star_id' => $starId,
                'tag_ids' => $tagIds,
            ];
        }

        // Use ReviewService to store values
        $this->reviewService->storeReviewValues($review, $values, $this->business);
    }

    /**
     * Get random hotel service and area pair
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
