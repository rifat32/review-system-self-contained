<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessDay;
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
            'guest_user_review_report' => $payloadData['guest_user_review_report'] ?? false,
            'review_type' => $payloadData['review_type'] ?? 'star',
            'google_map_iframe' => $payloadData['google_map_iframe'] ?? '',
            'show_image' => $payloadData['show_image'] ?? '',
            'Is_guest_user' => $payloadData['Is_guest_user'] ?? false,
            'is_review_slider' => $payloadData['is_review_slider'] ?? false,
            'review_only' => $payloadData['review_only'] ?? true,
            'is_branch' => $payloadData['is_branch'] ?? false,
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
            ];

            // Insert branch data
            if (!empty($branchData)) {
                Branch::insert($branchData);
            }
        });
    }
}
