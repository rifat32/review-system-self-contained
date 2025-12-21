<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Business;
use App\Models\BusinessArea;
use App\Models\BusinessService;
use App\Models\Question;
use App\Models\Tag;
use App\Models\Star;
use App\Models\QuestionStar;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\GuestUser;
use App\Models\Survey;
use Carbon\Carbon;

class DashboardTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Clear existing test data but preserve business and stars
        ReviewValueNew::truncate();
        ReviewNew::truncate();
        GuestUser::truncate();
        QuestionStar::truncate();
        Question::truncate();
        Tag::truncate();
        BusinessArea::truncate();
        BusinessService::truncate();
        Survey::truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Use existing business ID 3
        $business = Business::find(3);
        if (!$business) {
            $this->command->error('Business ID 3 not found! Creating a test business...');
            $business = Business::create([
                'id' => 3,
                'name' => 'Test Restaurant & Lounge',
                'OwnerID' => 1,
                'enable_ip_check' => false,
                'enable_location_check' => false,
                'review_distance_limit' => 1000,
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'enable_detailed_survey' => true,
                'export_settings' => json_encode(['format' => 'csv', 'include_comments' => true]),
            ]);
        }

        // Get existing stars (1-5)
        $stars = Star::orderBy('value')->get();
        if ($stars->isEmpty()) {
            $this->command->error('No stars found in database! Creating stars...');
            $stars = [];
            for ($i = 1; $i <= 5; $i++) {
                $star = Star::create([
                    'value' => $i,
                    'name' => "{$i} Star",
                    'description' => "{$i} out of 5 rating",
                    'color' => match($i) {
                        1 => '#ef4444',
                        2 => '#f97316',
                        3 => '#eab308',
                        4 => '#84cc16',
                        5 => '#22c55e',
                    }
                ]);
                $stars[] = $star;
            }
            $stars = collect($stars);
        }

        // Create or get admin user for business owner
        $owner = User::firstOrCreate(
            ['id' => $business->OwnerID],
            [
                'first_Name' => 'John',
                'last_Name' => 'Doe',
                'email' => 'owner@test.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Update business owner if needed
        if ($business->OwnerID != $owner->id) {
            $business->update(['OwnerID' => $owner->id]);
        }

        // Create staff members (only if they don't exist)
        $staffMembers = [
            ['first_Name' => 'Sarah', 'last_Name' => 'Johnson', 'job_title' => 'Manager'],
            ['first_Name' => 'Michael', 'last_Name' => 'Chen', 'job_title' => 'Head Chef'],
            ['first_Name' => 'Emma', 'last_Name' => 'Wilson', 'job_title' => 'Server'],
            ['first_Name' => 'David', 'last_Name' => 'Brown', 'job_title' => 'Bartender'],
            ['first_Name' => 'Lisa', 'last_Name' => 'Taylor', 'job_title' => 'Host'],
            ['first_Name' => 'Robert', 'last_Name' => 'Garcia', 'job_title' => 'Server'],
            ['first_Name' => 'Maria', 'last_Name' => 'Rodriguez', 'job_title' => 'Cook'],
            ['first_Name' => 'James', 'last_Name' => 'Miller', 'job_title' => 'Server'],
        ];

        $staffUsers = [];
        foreach ($staffMembers as $staff) {
            $email = Str::slug($staff['first_Name'] . ' ' . $staff['last_Name']) . '@test.com';
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'first_Name' => $staff['first_Name'],
                    'last_Name' => $staff['last_Name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'job_title' => $staff['job_title'],
                    'business_id' => $business->id,
                ]);
                
                // Assign business_staff role if using Spatie permissions
                if (class_exists('Spatie\Permission\Models\Role')) {
                    $user->assignRole('business_staff');
                }
            }
            $staffUsers[] = $user;
        }

        // Create Business Services
        $services = [
            ['name' => 'Dining', 'description' => 'Main restaurant dining service'],
            ['name' => 'Takeaway', 'description' => 'Food takeaway service'],
            ['name' => 'Delivery', 'description' => 'Home delivery service'],
            ['name' => 'Catering', 'description' => 'Event catering service'],
            ['name' => 'Bar/Lounge', 'description' => 'Bar and lounge services'],
        ];

        $businessServices = [];
        foreach ($services as $service) {
            $businessService = BusinessService::create([
                'name' => $service['name'],
                'description' => $service['description'],
                'business_id' => $business->id,
            ]);
            $businessServices[] = $businessService;
        }

        // Create Business Areas
        $areas = [
            ['area_name' => 'Main Dining Hall', 'business_service_id' => $businessServices[0]->id],
            ['area_name' => 'Outdoor Patio', 'business_service_id' => $businessServices[0]->id],
            ['area_name' => 'Private Dining Room', 'business_service_id' => $businessServices[0]->id],
            ['area_name' => 'Takeaway Counter', 'business_service_id' => $businessServices[1]->id],
            ['area_name' => 'Main Bar', 'business_service_id' => $businessServices[4]->id],
            ['area_name' => 'Wine Lounge', 'business_service_id' => $businessServices[4]->id],
        ];

        $businessAreas = [];
        foreach ($areas as $area) {
            $businessArea = BusinessArea::create([
                'area_name' => $area['area_name'],
                'business_service_id' => $area['business_service_id'],
                'business_id' => $business->id,
            ]);
            $businessAreas[] = $businessArea;
        }

        // Create Tags/Categories
        $tags = [
            ['name' => 'Food Quality', 'color' => '#3b82f6'],
            ['name' => 'Service Speed', 'color' => '#8b5cf6'],
            ['name' => 'Staff Friendliness', 'color' => '#06b6d4'],
            ['name' => 'Cleanliness', 'color' => '#10b981'],
            ['name' => 'Ambiance', 'color' => '#f59e0b'],
            ['name' => 'Price Value', 'color' => '#ec4899'],
            ['name' => 'Location', 'color' => '#6366f1'],
            ['name' => 'Menu Variety', 'color' => '#14b8a6'],
            ['name' => 'Drink Quality', 'color' => '#f97316'],
            ['name' => 'Waiting Time', 'color' => '#8b5cf6'],
        ];

        $tagModels = [];
        foreach ($tags as $tag) {
            $tagModel = Tag::create([
                'tag' => $tag['name'],
                'business_id' => $business->id,
            ]);
            $tagModels[] = $tagModel;
        }

        // Create Questions with different types
        $questions = [
            [
                'question' => 'How was the overall dining experience?',
                'type' => 'rating',
                'is_active' => true,
                'is_overall' => true,
                'is_default' => false,
                'show_in_user' => true,
            ],
            [
                'question' => 'How would you rate the food quality?',
                'type' => 'rating',
                'is_active' => true,
                'is_overall' => false,
                'is_default' => false,
                'show_in_user' => true,
            ],
            [
                'question' => 'How was the service from our staff?',
                'type' => 'rating',
                'is_active' => true,
                'is_overall' => false,
                'is_default' => false,
                'show_in_user' => true,
            ],
            [
                'question' => 'How clean was the restaurant?',
                'type' => 'rating',
                'is_active' => true,
                'is_overall' => false,
                'is_default' => false,
                'show_in_user' => true,
            ],
            [
                'question' => 'Was the food served at the right temperature?',
                'type' => 'yes_no',
                'is_active' => true,
                'is_overall' => false,
                'is_default' => false,
                'show_in_user' => true,
            ],
            [
                'question' => 'What could we improve? (Select all that apply)',
                'type' => 'multi_select',
                'is_active' => true,
                'is_overall' => false,
                'is_default' => false,
                'show_in_user' => true,
            ],
        ];

        $questionModels = [];
        foreach ($questions as $questionData) {
            $question = Question::create(array_merge($questionData, [
                'business_id' => $business->id,
            ]));
            $questionModels[] = $question;
        }

        // Create Question-Stars relationships using existing stars
        foreach ($questionModels as $question) {
            foreach ($stars as $star) {
                QuestionStar::create([
                    'question_id' => $question->id,
                    'star_id' => $star->id,
                ]);
            }
        }

        // Create Survey
        $survey = Survey::create([
            'name' => 'Customer Satisfaction Survey',
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        // Sample review comments for different ratings
        $positiveComments = [
            "Excellent experience! The food was fantastic and the service was outstanding.",
            "Great ambiance and delicious food. Will definitely come back!",
            "Best restaurant in town. The staff went above and beyond.",
            "Perfect date night spot. The wine selection was impressive.",
            "Food was cooked to perfection. Service was prompt and friendly.",
            "Wonderful dining experience from start to finish.",
            "The chef's special was amazing! Highly recommended.",
            "Staff was very attentive and made us feel special.",
            "Beautiful presentation and flavors. Worth every penny.",
            "Exceptional service! Made our anniversary memorable.",
        ];

        $neutralComments = [
            "Good food but service was a bit slow.",
            "Average experience. Nothing special but nothing bad either.",
            "Food was okay but could use more seasoning.",
            "Decent place but a bit overpriced.",
            "Service was average, food was average.",
            "It was okay for a quick dinner.",
            "Nothing to complain about but nothing amazing either.",
            "Met expectations but didn't exceed them.",
            "Food was good but the wait was long.",
            "Standard restaurant experience.",
        ];

        $negativeComments = [
            "Very disappointed. Food was cold when served.",
            "Service was terrible. Waited 45 minutes for drinks.",
            "Overpriced for the quality of food served.",
            "Dirty tables and slow service.",
            "Food was bland and undercooked.",
            "Rude staff and long waiting times.",
            "Would not recommend. Multiple issues during our visit.",
            "Terrible experience. Food arrived late and cold.",
            "Staff seemed inexperienced and uninterested.",
            "Worst dining experience I've had in years.",
        ];

        // Sample guest names and phones
        $guestNames = [
            'John Smith', 'Emma Johnson', 'Michael Brown', 'Sarah Davis', 'David Wilson',
            'Lisa Martinez', 'Robert Taylor', 'Jennifer Anderson', 'William Thomas',
            'Maria Jackson', 'James White', 'Patricia Harris', 'Charles Martin',
            'Susan Thompson', 'Joseph Garcia', 'Margaret Martinez', 'Thomas Robinson',
            'Dorothy Clark', 'Christopher Rodriguez', 'Nancy Lewis', 'Daniel Lee',
            'Karen Walker', 'Paul Hall', 'Betty Allen', 'Mark Young', 'Helen King',
            'Steven Wright', 'Sandra Scott', 'Edward Green', 'Donna Adams',
        ];

        $guestPhones = [
            '555-0101', '555-0102', '555-0103', '555-0104', '555-0105',
            '555-0106', '555-0107', '555-0108', '555-0109', '555-0110',
            '555-0111', '555-0112', '555-0113', '555-0114', '555-0115',
        ];

        // Generate 100 reviews with realistic distribution
        $now = Carbon::now();

        $this->command->info('Generating 100 reviews...');
        $progressBar = $this->command->getOutput()->createProgressBar(100);

        for ($i = 1; $i <= 100; $i++) {
            // Decide if this is a registered user or guest review (70% guest, 30% registered)
            $isGuest = rand(1, 100) <= 70;
            
            // Determine rating distribution (more positive than negative for realistic data)
            $ratingRand = rand(1, 100);
            if ($ratingRand <= 60) {
                // 60% positive (4-5 stars)
                $rating = rand(40, 50) / 10; // 4.0 to 5.0
                $comments = $positiveComments;
                $sentiment = 'positive';
            } elseif ($ratingRand <= 85) {
                // 25% neutral (3-4 stars)
                $rating = rand(30, 39) / 10; // 3.0 to 3.9
                $comments = $neutralComments;
                $sentiment = 'neutral';
            } else {
                // 15% negative (1-3 stars)
                $rating = rand(10, 29) / 10; // 1.0 to 2.9
                $comments = $negativeComments;
                $sentiment = 'negative';
            }

            // Random date within last 90 days (with some older ones for historical data)
            $daysAgo = $i <= 80 ? rand(0, 90) : rand(91, 180); // 80% within 90 days, 20% older
            $createdAt = $now->copy()->subDays($daysAgo);
            
            // Random time of day
            $hour = rand(11, 22); // Restaurant hours
            $minute = rand(0, 59);
            $createdAt->setTime($hour, $minute);

            // Random staff member (some reviews mention staff, some don't)
            $staffId = rand(1, 100) <= 40 ? $staffUsers[rand(0, count($staffUsers) - 1)]->id : null;
            
            // Random business area
            $areaId = $businessAreas[rand(0, count($businessAreas) - 1)]->id;
            
            // Random business service
            $serviceId = $businessServices[rand(0, count($businessServices) - 1)]->id;
            
            // Random comment
            $comment = $comments[rand(0, count($comments) - 1)];
            
            // Add staff name to comment if staff is mentioned
            if ($staffId && rand(1, 100) <= 30) {
                $staff = User::find($staffId);
                $comment .= " Special thanks to " . $staff->first_Name . " for great service!";
            }

            // Create guest user if needed
            $guestId = null;
            $userId = null;
            
            if ($isGuest) {
                $guestName = $guestNames[rand(0, count($guestNames) - 1)];
                $guestPhone = $guestPhones[rand(0, count($guestPhones) - 1)];
                
                $guest = GuestUser::create([
                    'full_name' => $guestName,
                    'phone' => $guestPhone,
                    'email' => rand(1, 100) <= 30 ? Str::slug($guestName) . '@example.com' : null,
                    'created_at' => $createdAt,
                ]);
                $guestId = $guest->id;
            } else {
                // Create a random registered user for reviews
                $randomName = $guestNames[rand(0, count($guestNames) - 1)];
                $nameParts = explode(' ', $randomName);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? 'Smith';
                $email = Str::slug($firstName . ' ' . $lastName) . rand(100, 999) . '@customer.com';
                
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'first_Name' => $firstName,
                        'last_Name' => $lastName,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                $userId = $user->id;
            }

            // Create the review
            $review = ReviewNew::create([
                'survey_id' => $survey->id,
                'description' => 'Dining Experience Review',
                'business_id' => $business->id,
                'rate' => $rating,
                'user_id' => $userId,
                'guest_id' => $guestId,
                'comment' => $comment,
                'raw_text' => $comment,
                'ip_address' => '192.168.1.' . rand(1, 255),
                'is_overall' => rand(1, 100) <= 30, // 30% are overall reviews
                'staff_id' => $staffId,
                'branch_id' => null,
                'business_area_id' => $areaId,
                'business_service_id' => $serviceId,
                'is_voice_review' => rand(1, 100) <= 10, // 10% are voice reviews
                'is_ai_processed' => false,
                'sentiment_score' => $sentiment === 'positive' ? rand(70, 100) / 100 : 
                                    ($sentiment === 'neutral' ? rand(40, 60) / 100 : rand(0, 30) / 100),
                'sentiment_label' => $sentiment,
                'emotion' => $sentiment === 'positive' ? 'joy' : 
                             ($sentiment === 'neutral' ? 'neutral' : 'sadness'),
                'ai_confidence' => rand(85, 98) / 100,
                'is_abusive' => false,
                'is_private' => rand(1, 100) <= 5, // 5% are private
                'order_no' => $i,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create review values (answers to questions)
            foreach ($questionModels as $question) {
                if ($question->type === 'rating') {
                    // For rating questions, give a star rating based on overall rating
                    $questionRating = max(1, min(5, round($rating + (rand(-10, 10) / 10))));
                    $star = $stars->where('value', $questionRating)->first();
                    
                    if ($star) {
                        // Random tag for this question
                        $tagId = $tagModels[rand(0, count($tagModels) - 1)]->id;
                        
                        ReviewValueNew::create([
                            'review_id' => $review->id,
                            'question_id' => $question->id,
                            'star_id' => $star->id,
                            'tag_id' => $tagId,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                    }
                }
            }

            // Add some replies to reviews (business responses)
            if (rand(1, 100) <= 40) { // 40% of reviews have replies
                $replyDays = rand(1, 7);
                $replyAt = $createdAt->copy()->addDays($replyDays);
                
                $replyTemplates = [
                    "Thank you for your feedback! We're glad you enjoyed your visit.",
                    "We appreciate you taking the time to share your experience with us.",
                    "Thank you for your review. We're sorry to hear about your experience and would like to make it right.",
                    "We're delighted you had a great time! Looking forward to serving you again.",
                    "Thank you for your valuable feedback. We're continuously working to improve.",
                ];
                
                $review->update([
                    'reply_content' => $replyTemplates[rand(0, count($replyTemplates) - 1)],
                    'responded_at' => $replyAt,
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();

        // Generate some AI insights data
        $this->generateAIInsights($business);

        $this->command->info('✅ Successfully seeded 100 reviews with all related data!');
        $this->command->info('📊 Business ID: ' . $business->id);
        $this->command->info('👥 Staff Members: ' . count($staffUsers));
        $this->command->info('📍 Business Areas: ' . count($businessAreas));
        $this->command->info('📝 Questions: ' . count($questionModels));
        $this->command->info('🏷️ Tags: ' . count($tagModels));
        $this->command->info('⭐ Stars used: ' . $stars->pluck('value')->implode(', '));
    }

    private function generateAIInsights($business): void
    {
        // Generate some AI insights data for the dashboard
        $insights = [
            'trending_themes' => [
                'Food Quality' => ['count' => 45, 'sentiment' => 0.85],
                'Service Speed' => ['count' => 32, 'sentiment' => 0.78],
                'Staff Friendliness' => ['count' => 28, 'sentiment' => 0.92],
                'Cleanliness' => ['count' => 18, 'sentiment' => 0.95],
                'Price Value' => ['count' => 15, 'sentiment' => 0.65],
            ],
            'top_positive_aspects' => [
                'Friendly staff',
                'Food presentation',
                'Clean environment',
                'Atmosphere',
                'Wine selection',
            ],
            'areas_for_improvement' => [
                'Wait times during peak hours',
                'Menu item availability',
                'Temperature consistency',
                'Parking availability',
                'Dessert variety',
            ],
            'staff_performance' => [
                'average_rating' => 4.2,
                'top_performer' => 'Sarah Johnson',
                'improvement_needed' => 'David Brown',
                'customer_satisfaction' => 88,
            ],
            'sentiment_trends' => [
                'positive_trend' => '+5%',
                'response_rate' => '42%',
                'average_response_time' => '2.3 days',
            ],
            'review_distribution' => [
                '5_star' => 35,
                '4_star' => 25,
                '3_star' => 20,
                '2_star' => 12,
                '1_star' => 8,
            ],
            'response_metrics' => [
                'total_reviews' => 100,
                'replied_reviews' => 40,
                'average_rating' => 4.1,
                'nps_score' => 68,
            ],
        ];

        // Store insights in cache for dashboard display
        cache()->put('business_' . $business->id . '_ai_insights', $insights, 3600);
        
        $this->command->info('🤖 AI insights data generated and cached');
    }
}