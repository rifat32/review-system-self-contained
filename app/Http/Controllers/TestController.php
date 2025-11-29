<?php

namespace App\Http\Controllers;

use App\Mail\UserReviewReportMail;
use App\Models\Question;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PDF;


class TestController extends Controller
{
    public function getReport ($business,$start_date,$end_date) {



        $query =  Question::where(["business_id" => $business->id,"is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

    $data =  json_decode(json_encode($questions), true);
    foreach($questions as $key1=>$question){

        $tags_rating = [];
       $starCountTotal = 0;
       $starCountTotalTimes = 0;
        foreach($question->question_stars as $key2=>$questionStar){


            $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

            $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "star_id" => $questionStar->star->id,
                "review_news.guest_id" => NULL

                ]
            );
            if(!empty($start_date) && !empty($end_date)) {

                $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->whereBetween('review_news.created_at', [
                    $start_date,
                    $end_date
                ]);

            }
            $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->get()
            ->count();

            $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

            $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];
            $data[$key1]["stars"][$key2]["tag_ratings"] = [];
            if($starCountTotalTimes > 0) {
                $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
            }


            foreach($questionStar->star->star_tags as $key3=>$starTag){


         if($starTag->question_id == $question->id) {

            $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "tag_id" => $starTag->tag->id,
                "review_news.guest_id" => NULL
                ]
            );
            if(!empty($start_date) && !empty($end_date)) {

                $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                    $start_date,
                    $end_date
                ]);

            }

            $starTag->tag->count = $starTag->tag->count->get()->count();
            if($starTag->tag->count > 0) {
                array_push($tags_rating,json_decode(json_encode($starTag->tag)));
                           }


            $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "star_id" => $questionStar->star->id,
                "tag_id" => $starTag->tag->id,
                "review_news.guest_id" => NULL
                ]
            );
            if(!empty($start_date) && !empty($end_date)) {

                $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                    $start_date,
                    $end_date
                ]);

            }
            $starTag->tag->total = $starTag->tag->total->get()->count();

                if($starTag->tag->total > 0) {
                    unset($starTag->tag->count);
                    array_push($data[$key1]["stars"][$key2]["tag_ratings"],json_decode(json_encode($starTag->tag)));
                }


          }



            }

        }


        $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
    }





    $totalCount = 0;
    $ttotalRating = 0;

    foreach(Star::get() as $star) {

    $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
    ->where([
        "review_news.business_id" => $business->id,
        "star_id" => $star->id,
        "review_news.guest_id" => NULL
    ])
    ->distinct("review_value_news.review_id","review_value_news.question_id");
    if(!empty($start_date) && !empty($end_date)) {

        $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
            $start_date,
            $end_date
        ]);

    }
    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

    $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

    $ttotalRating += $data2["star_" . $star->value . "_selected_count"];

    }
    if($totalCount > 0) {
    $data2["total_rating"] = $totalCount / $ttotalRating;

    }
    else {
    $data2["total_rating"] = 0;

    }

    $data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
    "business_id" => $business->id,
    "guest_id" => NULL,
    ])
    ->globalFilters()
    ->orderBy('order_no', 'asc')
    ->whereNotNull("comment")
    ;
    if(!empty($start_date) && !empty($end_date)) {

    $data2["total_comment"] = $data2["total_comment"]
    ->whereBetween('review_news.created_at', [
        $start_date,
        $end_date
    ]);

    }
    $data2["total_comment"] = $data2["total_comment"]->get();

    return [
        "part1" =>  $data2,
        "part2" =>  $data
    ];

        }

   public function testReport() {
    Log::info('Task executed.');


    $business_list = Business::
    where([
        "user_review_report"=> TRUE,
    ])
    ->get();
    foreach($business_list as $business){

        $data =  $this->getReport($business,NULL,NULL);


        $pdf = PDF::loadView('user-review-report-pdf', compact("data","business"));
        $pdfContents = $pdf->output();

        return $pdf->download('pdf_file.pdf');

// $to=['drrifatalashwad0@gmail.com',$business->EmailAddress,"asjadtariq@gmail.com"];
        // $to=['drrifatalashwad0@gmail.com'];
        // Mail::to($to)
        // ->send(new UserReviewReportMail($pdfContents, 'report.pdf'));
    }
   }
   public function testReport2() {
    Log::info('Task executed.');


    $business_list = Business::
    where([
        "user_review_report"=> TRUE,
    ])
    ->get();
    foreach($business_list as $business){

        $data =  $this->getReport($business,NULL,NULL);

return response() ->json([$data,$business],200);
        $pdf = PDF::loadView('user-review-report-pdf', compact("data","business"));
        $pdfContents = $pdf->output();

        return $pdf->download('pdf_file.pdf');

// $to=['drrifatalashwad0@gmail.com',$business->EmailAddress,"asjadtariq@gmail.com"];
        // $to=['drrifatalashwad0@gmail.com'];
        // Mail::to($to)
        // ->send(new UserReviewReportMail($pdfContents, 'report.pdf'));
    }
   }
}
