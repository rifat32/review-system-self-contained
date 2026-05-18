<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\ActivityLog;
use App\Models\Branch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\File;
use App\Models\ReviewNew;
use App\Models\AiRule;
use App\Services\Rule\RuleExecutionService;


class SetupController extends Controller
{
    use ErrorUtil;


    public function setupPassport()
    {
        try {
            // Clear caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $this->privatePassportSetup();

            return response()->json([
                'success' => true,
                'message' => 'Passport Setup Complete'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Passport setup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function setup()
    {

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Clear storage logs
        $log_path = storage_path('logs');
        if (File::exists($log_path)) {
            File::cleanDirectory($log_path); // removes all files inside logs
        }

        // Run migrations
        Artisan::call('migrate:fresh');

        $this->privatePassportSetup();

        // Generate Swagger Documentation
        Artisan::call('l5-swagger:generate');

        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);




        return response()->json([
            'success' => true,
            'message' => 'Setup Complete'
        ]);
    }

    private function privatePassportSetup()
    {
        // Run passport migrations
        Artisan::call('migrate', [
            '--path' => 'vendor/laravel/passport/database/migrations',
            '--force' => true
        ]);

        // Install passport (creates encryption keys and oauth clients)
        Artisan::call('passport:install', [
            '--force' => true,
            '--no-interaction' => true
        ]);
    }

    public function roleRefresh(Request $request)
    {

        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run the roles and permissions seeder
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Roles and Permissions refreshed successfully'
        ], 200);
    }

    // MIGRATE
    public function migrate(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run migrations
        Artisan::call('check:migrate');

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Migrations applied successfully'
        ], 200);
    }

    // MIGRATE
    public function migrateStatus(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run migrations
        Artisan::call('migrate:status');

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Migrations status retrieved successfully',
            'data' => Artisan::output()
        ], 200);
    }

    // ROLLBACK MIGRATE
    public function rollbackMigration(Request $request)
    {
        try {
            $result = Artisan::call('migrate:rollback');

            return response()->json([
                'message' => 'Last Migration Rolled Back',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            // LOG ERROR MESSAGE
            // log_message([
            //     'message' => 'Migration Roll Back Failed',
            //     'data' => $e->getMessage()
            // ], 'roll_back.log');

            return response()->json([
                'message' => 'Last Migration Rolled Back',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // CLEAR CACHE
    public function clearCache(Request $request)
    {

        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ], 200);
    }

    public function runArtisanCommand(Request $request)
    {
        $command = $request->query('command');

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command parameter is missing'
            ], 400);
        }

        try {
            // strip 'php artisan' prefix if present
            $command = preg_replace('/^php\s+artisan\s+/', '', $command);

            // Define allowed patterns or blocked commands
            $isAllowed = str_starts_with($command, 'schedule:') ||
                str_starts_with($command, 'recommendations:') ||
                str_starts_with($command, 'rules:') ||
                str_starts_with($command, 'reviews:') ||
                str_starts_with($command, 'reports:') ||
                str_contains($command, 'review_report:') ||
                str_contains($command, 'businesses:') ||
                in_array(explode(' ', $command)[0], ['optimize:clear', 'config:clear', 'cache:clear', 'route:clear', 'view:clear', 'check:migrate', 'l5-swagger:generate', 'businesses:purge-deleted']);

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'This command is not allowed for security reasons or to prevent breaking the project.'
                ], 403);
            }

            Artisan::call($command);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'command' => $command,
                'output' => $output
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Command execution failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getActivityLogs(Request $request)
    {
        $activity_logs = ActivityLog::when(!empty($request->status_code), function ($query) use ($request) {
            $query->where('status_code', $request->status_code);
        })
            ->when(!empty($request->user_id), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when(!empty($request->business_id), function ($query) use ($request) {
                $query->whereExists(function ($subQuery) use ($request) {
                    $subQuery->select(DB::raw(1))
                        ->from(DB::connection('mysql')->getDatabaseName() . '.users')
                        ->whereColumn('activity_logs.user_id', 'users.id')
                        ->where('users.business_id', $request->business_id);
                });
            })
            ->when(!empty($request->api_url), function ($query) use ($request) {
                $query->where('api_url', 'like', '%' . $request->api_url . '%');
            })
            ->when(!empty($request->ip_address), function ($query) use ($request) {
                $query->where('ip_address', $request->ip_address);
            })
            ->when(!empty($request->request_method), function ($query) use ($request) {
                $query->where('request_method', $request->request_method);
            })
            ->when($request->filled('is_error'), function ($query) use ($request) {
                $query->where('is_error', $request->boolean('is_error') ? 1 : 0);
            })
            ->when(!empty($request->date), function ($query) use ($request) {
                $query->whereDate('created_at', $request->date);
            })
            ->when(!empty($request->id), function ($query) use ($request) {
                $query->where('id', $request->id);
            })
            ->orderbyDesc('id')
            ->paginate(20);

        return view('user-activity-log', compact('activity_logs'));
    }

    /**
     * Sync old processed reviews that don't have rule outcomes yet.
     */
    public function syncOldReviewOutcomes(Request $request)
    {
        $this->storeActivity($request, "Data Sync", "Syncing old review outcomes");

        $ruleExecutionService = app(RuleExecutionService::class);

        $reviews = ReviewNew::where('is_ai_processed', 1)
            ->whereDoesntHave('rule_outcomes')
            ->orderBy('id', 'desc')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No reviews found that need syncing.'
            ]);
        }

        $syncedCount = 0;
        $rulesTriggeredCount = 0;

        foreach ($reviews as $review) {
            $activeRules = AiRule::where('business_id', $review->business_id)
                ->where('enabled', true)
                ->get();

            if ($activeRules->isEmpty()) {
                continue;
            }

            $aiData = $ruleExecutionService->getReviewAIData($review);
            $payload = ['review_id' => $review->id];

            foreach ($activeRules as $rule) {
                $outcome = $ruleExecutionService->evaluate($rule, $aiData, $payload);
                if ($outcome) {
                    $rulesTriggeredCount++;
                }
            }

            $syncedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully synced outcomes for {$syncedCount} reviews.",
            'details' => [
                'reviews_processed' => $syncedCount,
                'rule_outcomes_created' => $rulesTriggeredCount
            ]
        ]);
    }

    /**
     * Dedicated method to backfill dashboard rule outcomes via artisan command.
     */
    public function backfillDashboardRules(Request $request)
    {
        try {
            Artisan::call('optimize:clear');
            $optimizeOutput = Artisan::output();

            $command = 'rules:backfill-outcomes';

            if ($request->has('business_id')) {
                $businessIds = explode(',', $request->query('business_id'));
                foreach ($businessIds as $id) {
                    if (is_numeric(trim($id))) {
                        $command .= ' --business_id=' . trim($id);
                    }
                }
            } else {
                $command .= ' --all';
            }

            if ($request->boolean('dry_run')) {
                $command .= ' --dry-run';
            }

            Artisan::call($command);
            $backfillOutput = Artisan::output();

            return response()->json([
                'success' => true,
                'command_run' => $command,
                'message' => 'Dashboard rule outcomes backfilled successfully.',
                'optimize_output' => trim($optimizeOutput),
                'backfill_output' => trim($backfillOutput)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backfill failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add missing pre-computed reviews for testing business (owner email rifatbilal...)
     * to populate remaining 0 dashboard values without calling OpenAI.
     * Generates exactly 2 reviews per 0 value category (Total 10 reviews).
     */
    public function addMissingDashboardReviews(Request $request)
    {
        try {
            // Find owner by exact email 'rifatbilalphilips@gmail.com'
            $owner = \App\Models\User::where('email', 'rifatbilalphilips@gmail.com')->first();

            if (!$owner || !$owner->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find a business associated with owner exact email rifatbilalphilips@gmail.com'
                ], 404);
            }

            $business = \App\Models\Business::find($owner->business_id);

            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business ID ' . $owner->business_id . ' not found.'
                ], 404);
            }

            $createdCount = 0;

            // Get required foreign keys
            $branch = \App\Models\Branch::where('business_id', $business->id)->first();
            $staff = \App\Models\User::where('business_id', $business->id)->where('type', 'staff')->first();
            $customer = \App\Models\User::where('type', 'customer')->first();
            $survey = \App\Models\Survey::where('business_id', $business->id)->first();
            $question = \App\Models\Question::where('business_id', $business->id)->first();
            
            $star5 = \App\Models\Star::where('value', 5)->first();
            $star4 = \App\Models\Star::where('value', 4)->first();
            $star3 = \App\Models\Star::where('value', 3)->first();
            $star1 = \App\Models\Star::where('value', 1)->first();

            if (!$branch || !$staff || !$customer || !$survey || !$question || !$star5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required foreign key entities (branch, staff, customer, survey, question, or stars) for Business ID ' . $business->id
                ], 422);
            }

            $staffIdStr = (string) $staff->id;
            $staffName = $staff->first_Name . ' ' . $staff->last_Name;

            // Exactly 2 reviews per category (Total 10 reviews)
            $reviewConfigs = [
                // Pair 1: High Emotion (2 reviews)
                [
                    'comment' => 'I am absolutely thrilled and overjoyed with my stay! Everything was spectacularly wonderful and made me so happy!',
                    'star' => $star5,
                    'sentiment_label' => 'positive', 'sentiment_score' => 0.9,
                    'emotion' => ['primary' => 'joy', 'intensity' => 'high'],
                    'tags' => [], 'areas' => [], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],
                [
                    'comment' => 'I am extremely furious and outraged by the lack of care! This made me incredibly angry and frustrated beyond belief!',
                    'star' => $star1,
                    'sentiment_label' => 'negative', 'sentiment_score' => 0.1,
                    'emotion' => ['primary' => 'anger', 'intensity' => 'high'],
                    'tags' => [], 'areas' => [], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],

                // Pair 2: Rating Mismatch (2 reviews) - Requires Rating > 3 AND Sentiment == negative
                [
                    'comment' => 'The room was terrible, dirty, and the staff was extremely rude. I hated every single minute of my stay here.',
                    'star' => $star5,
                    'sentiment_label' => 'negative', 'sentiment_score' => 0.1,
                    'emotion' => ['primary' => 'anger', 'intensity' => 'high'],
                    'tags' => [], 'areas' => [], 'topics' => [], 'mismatch' => 1, 'mismatch_type' => 'rating_high_comment_negative'
                ],
                [
                    'comment' => 'Awful experience with noisy neighbors and uncomfortable beds. Would never recommend this place to anyone.',
                    'star' => $star5,
                    'sentiment_label' => 'negative', 'sentiment_score' => 0.1,
                    'emotion' => ['primary' => 'disgust', 'intensity' => 'high'],
                    'tags' => [], 'areas' => [], 'topics' => [], 'mismatch' => 1, 'mismatch_type' => 'rating_high_comment_negative'
                ],

                // Pair 3: Category Alerts (2 reviews) - Requires keywords: price, quality, delivery
                [
                    'comment' => 'The price of the room was way too expensive for the quality of food provided.',
                    'star' => $star3,
                    'sentiment_label' => 'negative', 'sentiment_score' => 0.3,
                    'emotion' => ['primary' => 'sadness', 'intensity' => 'medium'],
                    'tags' => ['price', 'quality'], 'areas' => [],
                    'topics' => [['main_category' => 'Pricing', 'sub_category' => 'Value', 'sentiment' => 'negative', 'severity' => 'medium', 'evidence_from_comment' => 'price of the room']],
                    'mismatch' => 0, 'mismatch_type' => null
                ],
                [
                    'comment' => 'We had issues with the delivery of our extra towels and the overall quality of room service.',
                    'star' => $star3,
                    'sentiment_label' => 'negative', 'sentiment_score' => 0.3,
                    'emotion' => ['primary' => 'disappointment', 'intensity' => 'medium'],
                    'tags' => ['delivery', 'quality'], 'areas' => [],
                    'topics' => [['main_category' => 'Service', 'sub_category' => 'Delivery', 'sentiment' => 'negative', 'severity' => 'medium', 'evidence_from_comment' => 'delivery of our extra towels']],
                    'mismatch' => 0, 'mismatch_type' => null
                ],

                // Pair 4: Service Identified (2 reviews) - Requires keywords: installation, maintenance
                [
                    'comment' => 'The installation of the new air conditioning unit in our room was handled very professionally.',
                    'star' => $star4,
                    'sentiment_label' => 'positive', 'sentiment_score' => 0.8,
                    'emotion' => ['primary' => 'satisfaction', 'intensity' => 'medium'],
                    'tags' => ['installation'], 'areas' => [], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],
                [
                    'comment' => 'The maintenance team was quick to fix the plumbing leak in the bathroom.',
                    'star' => $star4,
                    'sentiment_label' => 'positive', 'sentiment_score' => 0.8,
                    'emotion' => ['primary' => 'relief', 'intensity' => 'medium'],
                    'tags' => ['maintenance'], 'areas' => [], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],

                // Pair 5: Area Identified (2 reviews) - Requires area_insights
                [
                    'comment' => 'The reception lobby was beautifully decorated and very welcoming.',
                    'star' => $star5,
                    'sentiment_label' => 'positive', 'sentiment_score' => 0.9,
                    'emotion' => ['primary' => 'joy', 'intensity' => 'medium'],
                    'tags' => [], 'areas' => ['Reception Lobby'], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],
                [
                    'comment' => 'The dining area was clean, spacious, and had a wonderful atmosphere for breakfast.',
                    'star' => $star5,
                    'sentiment_label' => 'positive', 'sentiment_score' => 0.9,
                    'emotion' => ['primary' => 'joy', 'intensity' => 'medium'],
                    'tags' => [], 'areas' => ['Dining Area'], 'topics' => [], 'mismatch' => 0, 'mismatch_type' => null
                ],
            ];

            foreach ($reviewConfigs as $cfg) {
                $areaInsights = array_map(fn($area) => [
                    'area_name' => $area,
                    'sentiment' => $cfg['sentiment_label'],
                    'mention_context' => strtolower($area)
                ], $cfg['areas']);

                $review = \App\Models\ReviewNew::create([
                    'business_id' => $business->id,
                    'description' => 'Customer feedback',
                    'user_id' => $customer->id,
                    'comment' => $cfg['comment'],
                    'raw_text' => $cfg['comment'],
                    'ip_address' => '192.168.1.' . rand(10, 99),
                    'is_overall' => 1,
                    'status' => 'pending',
                    'order_no' => 0,
                    'sentiment_score' => $cfg['sentiment_score'],
                    'sentiment_label' => $cfg['sentiment_label'],
                    'emotion' => json_encode($cfg['emotion']),
                    'key_phrases' => json_encode([
                        'tags' => $cfg['tags'],
                        'staff_mentions' => [[
                            'id' => $staffIdStr,
                            'name' => $staffName,
                            'sentiment' => $cfg['sentiment_label'],
                            'risk_level' => $cfg['sentiment_label'] === 'negative' ? 'high' : 'low',
                            'blame_detected' => $cfg['sentiment_label'] === 'negative'
                        ]],
                        'areas_mentioned' => $cfg['areas']
                    ]),
                    'topics' => json_encode($cfg['topics']),
                    'ai_confidence' => 1.00,
                    'is_ai_processed' => 1,
                    'ai_processed_at' => now(),
                    'ai_model' => 'gpt-4o-mini',
                    'openai_raw_response' => json_encode([
                        'sentiment' => ['label' => $cfg['sentiment_label'], 'score' => $cfg['sentiment_score']],
                        'emotion' => $cfg['emotion'],
                        'moderation' => ['is_abusive' => false, 'safe_for_public_display' => true, 'issues_found' => [], 'severity' => 'low'],
                        'rating_comment_alignment' => [
                            'is_aligned' => $cfg['mismatch'] === 0,
                            'mismatch_type' => $cfg['mismatch_type'],
                            'confidence' => 1,
                            'explanation' => $cfg['mismatch'] ? 'Rating contradicts comment.' : 'Perfectly aligned.'
                        ],
                        'category_analysis' => $cfg['topics'],
                        'staff_intelligence' => [
                            'staff_id' => $staffIdStr,
                            'staff_name' => $staffName,
                            'mentioned_explicitly' => true,
                            'sentiment_towards_staff' => $cfg['sentiment_label'],
                            'risk_level' => $cfg['sentiment_label'] === 'negative' ? 'high' : 'low',
                            'blame_detected' => $cfg['sentiment_label'] === 'negative'
                        ],
                        'service_unit_intelligence' => ['unit_type' => 'Other', 'unit_id' => '', 'issues_detected' => [], 'maintenance_required' => in_array('maintenance', $cfg['tags']), 'severity' => 'low'],
                        'area_insights' => $areaInsights,
                        'summary' => [
                            'one_line' => 'Customer feedback provided.',
                            'manager_summary' => $cfg['comment'],
                            'customer_sentiment_summary' => 'Customer expressed ' . $cfg['sentiment_label'] . ' sentiment.',
                            'overall_assessment' => $cfg['sentiment_label']
                        ],
                        'rule_outcomes' => ['is_sentiment_flagged', 'is_high_emotion', 'is_mismatch', 'is_category_detected', 'is_service_identified', 'is_area_detected', 'is_staff_mentioned', 'is_staff_risk', 'is_critical_alert']
                    ]),
                    'ai_insights' => json_encode([
                        'staff_intelligence' => [
                            'staff_id' => $staffIdStr,
                            'staff_name' => $staffName,
                            'mentioned_explicitly' => true,
                            'sentiment_towards_staff' => $cfg['sentiment_label'],
                            'risk_level' => $cfg['sentiment_label'] === 'negative' ? 'high' : 'low',
                            'blame_detected' => $cfg['sentiment_label'] === 'negative'
                        ],
                        'area_insights' => $areaInsights,
                    ]),
                    'rating_comment_mismatch' => $cfg['mismatch'],
                    'mismatch_insights' => $cfg['mismatch'] ? json_encode([
                        'is_aligned' => false,
                        'mismatch_type' => $cfg['mismatch_type']
                    ]) : null,
                    'branch_id' => $branch->id,
                    'staff_id' => $staff->id,
                    'survey_id' => $survey->id,
                    'source' => 'web'
                ]);

                // Create ReviewValueNew to ensure calculated_rating matches star
                if ($cfg['star']) {
                    \App\Models\ReviewValueNew::create([
                        'review_id' => $review->id,
                        'question_id' => $question->id,
                        'star_id' => $cfg['star']->id,
                    ]);
                }

                $createdCount++;
            }

            // Run backfill for this business to populate review_rule_outcomes
            \Illuminate\Support\Facades\Artisan::call('rules:backfill-outcomes', [
                '--business_id' => $business->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$createdCount} pre-computed reviews (exactly 2 per category) and populated all remaining 0 dashboard values for Business '{$business->Name}' (Owner: {$owner->email})!",
                'details' => [
                    'reviews_created' => $createdCount,
                    'business_id' => $business->id,
                    'business_name' => $business->Name,
                    'owner_email' => $owner->email
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to add missing dashboard reviews", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add missing dashboard reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up older duplicate AI insight records across all businesses.
     * Consolidates recommendation foreign keys to point to the latest insight record before deleting duplicates.
     */
    public function cleanupDuplicateInsights(Request $request)
    {
        try {
            $businesses = \App\Models\InsightRecord::select('business_id')->distinct()->pluck('business_id');
            $cleanedCount = 0;
            $consolidatedRecsCount = 0;

            foreach ($businesses as $businessId) {
                // Group by main_category and sub_category
                $categories = \App\Models\InsightRecord::where('business_id', $businessId)
                    ->select('main_category', 'sub_category')
                    ->distinct()
                    ->get();

                foreach ($categories as $cat) {
                    $records = \App\Models\InsightRecord::where('business_id', $businessId)
                        ->where('main_category', $cat->main_category)
                        ->where('sub_category', $cat->sub_category)
                        ->orderBy('time_window_end', 'desc')
                        ->get();

                    if ($records->count() > 1) {
                        $latestRecord = $records->first();
                        $duplicates = $records->slice(1);

                        foreach ($duplicates as $duplicate) {
                            // Update recommendations to point to the latest record
                            $recsUpdated = \App\Models\Recommendation::where('insight_id', $duplicate->id)
                                ->update(['insight_id' => $latestRecord->id]);
                            $consolidatedRecsCount += $recsUpdated;

                            $duplicate->delete();
                            $cleanedCount++;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully cleaned up {$cleanedCount} duplicate AI insight records and consolidated {$consolidatedRecsCount} recommendation references across all businesses!",
                'details' => [
                    'duplicate_insights_deleted' => $cleanedCount,
                    'recommendations_consolidated' => $consolidatedRecsCount
                ]
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to clean up duplicate insights", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean up duplicate insights: ' . $e->getMessage()
            ], 500);
        }
    }
}
