@php
    $taglist_inrow = 4;


  $total_review =  (
                                    $data["part1"]["star_1_selected_count"]
                                    +
                                    $data["part1"]["star_2_selected_count"]
                                    +
                                    $data["part1"]["star_3_selected_count"]
                                    +
                                    $data["part1"]["star_4_selected_count"]
                                    +
                                    $data["part1"]["star_5_selected_count"]


                                    )

@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

</head>

<style>
    .center {
        text-align: center;
    }

    .center img {
        display: block;
    }

    .branding {
        width: 100%;
        height: auto;

        color: rgb(12, 17, 53);

    }

    .logo {
        width: 100px;
    }

    .business_name {
        font-size: 20px;
    }

    .qu {
        width: 100%;
        font-size: 17px;
        text-align: left;
        margin: 8px 0px;
    }

    .single_question_container {
        background-color: #fff;
        border-radius: 10px;
        margin: 10px 0px;
        width: 95%;
        box-shadow: 1px 1px 5px #aaa;
        padding: 5px 10px;
    }

    .indicator {
        display: block;
        background-color: gainsboro;
        height: 5px;
        width: 100px;
        border-radius: 30px;
        overflow: hidden;
    }

    .single_graph_container {
        width: 100%;

    }

    .star {
        font-size: 10px;
        width: 70px;
    }

    .single_star_details {}

    .stat_rating_counter {
        font-size: 10px;

    }

    .tags {
        width: 100%;

    }

    .single_tag {
        font-size: 10px;
        height: 16px;
        margin: 2px 0px;
        background-color: rgb(12, 17, 53);
        color: white;


        border-radius: 30px;
        padding: 2px 3px;
        gap: 5px;
    }

    .tag_counter {
        padding: 0px 2px;
        background-color: #fff;
        color: rgb(12, 17, 53);
        text-align: center;
        width: auto;
        max-width: 50px;
        border-radius: 30px;
        display: inline-block;
    }
</style>
<style>
    .indicator2 {
        display: inline-block;
        background-color: gainsboro;
        height: 20px;
        width: 300px;
        border-radius: 5px;
        overflow: hidden;
    }

    .stat_rating_counter2 {
        font-size: 10px;
    }
</style>
<body>
    <main style="background: #F6F7FF;">
        <div class="branding">
            <table style="width: 100%;">
<tr>
    <td width="25"></td>
    <td style="text-align: center" align="center">
        <img src="{{(env("APP_URL") . '/' . $business['Logo'])}}" alt="logo" style="text-align: center; height:50px; widht:50px" height="50" width="50">

    </td>
    <td width="25"></td>
</tr>

<tr>
    <td width="25"></td>
    <td align="center">
        <h1 class="business_name"
        style="display: block; margin-left: auto; margin-right: auto; font-size: 16px; text-align: center;">
       Quick Review </h1>
    </td>

    <td width="25"></td>
</tr>



<tr>
    <td width="25"></td>
    <td align="center">
        <h3
                    style="display: block; margin-left: auto; margin-right: auto; font-size: 16px; text-align: center;font-size: 30px;line-height: 5px;">
                    Weekly Review Report </h3>
    </td>

    <td width="25"></td>
</tr>


<tr>
    <td width="25"></td>
    <td align="center">
        <h3
        style="display: block; margin-left: auto; margin-right: auto; font-size: 16px; text-align: center; text-align: center; font-size: 18px; margin: 10px 0px;">
        <strong>Duration: {{ $start_date_of_previous_week->formatLocalized('%d/%m/%Y') }} to {{ $end_date_of_previous_week->formatLocalized('%d/%m/%Y') }} </strong></h3>
    </td>

    <td width="25"></td>
</tr>











            </table>

            <table width="100%" style=" margin-left: auto; margin-right: auto;  text-align: center; background:#172C41; border-radius: 10px;">
                <tbody>
                    <tr>
                        <td rowspan="2" style="font-size: 40px; font-weight: bolder; height: 80px; color:#fff;">{{$business["Name"]}}</td>
                        <td width="50%">
                            <div>
                                <table>
                                    <td colspan="25">
                                        <span style="
                                            display: inline-block;color:#fff;" class="stat_rating_counter2">5 Star</span>
                                    </td>
                                    <td>
                                        <span class="indicator2">
                                            <span
                                                style="display: block;
color:#fff;
                                                @if($total_review>0)
                                                width:{{((100/$total_review) * $data["part1"]["star_5_selected_count"])}}%;
                                                @else
                                                width:0%;
                                                @endif


                                                background-color: #AB8438; height: 100%; border-radius: 5px;"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="stat_rating_counter2" style="color:#fff;">
                                            {{  $data["part1"]["star_5_selected_count"]}}
                                        </span>
                                    </td>
                                </table>



                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <div>
                                <table>
                                    <td>
                                        <span style="display: inline-block; color:#fff;" class="stat_rating_counter2">4 Star</span>
                                    </td>

                                    <td>
                                        <span class="indicator2">
                                            <span
                                                style="display: block;
                                                color:#fff;
                                                @if($total_review>0)
                                                width:{{((100/$total_review) * $data["part1"]["star_4_selected_count"])}}%;
                                                @else
                                                width:0%;
                                                @endif

                                                background-color: #AB8438; height: 100%; border-radius: 5px;"></span>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="stat_rating_counter2" style="color:#fff;">
                                            {{  $data["part1"]["star_4_selected_count"]}}
                                        </span>
                                    </td>
                                </table>



                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td rowspan="2">
                            <div>
                                <table width="100%" style="">
                                    <tr>

                                        <td style=" text-align: right; vertical-align: center; ">
                                            <img style="width: 50px; height: 50px;  "
                                            src="https://static.vecteezy.com/system/resources/previews/018/251/149/original/star-shape-symbol-on-transparent-background-free-png.png"
                                            alt="">
                                        </td>
                                        <td style=" text-align: left; vertical-align: top;" >
                                            <span
                                            style="font-size: 70px;
margin-top:-20px;
                                            font-weight: bold; display: inline-block; color:#fff; ">{{number_format((float)$data["part1"]["total_rating"], 1)}}</span>
                                        </td>
                                    </tr>
                                </table>


                            </div>
                        </td>
                        <td width="50%">
                            <div>
                                <table>
                                    <td>
                                        <span style="display: inline-block; color:#fff;" class="stat_rating_counter2">3 Star</span>
                                    </td>
                                    <td>
                                        <span class="indicator2">
                                            <span
                                                style="display: block;
                                                color:#fff;
                                                @if($total_review>0)
                                                width:{{((100/$total_review) * $data["part1"]["star_3_selected_count"])}}%;
                                                @else
                                                width:0%;
                                                @endif

                                                background-color: #AB8438; height: 100%; border-radius: 5px;"></span>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="stat_rating_counter2" style="color:#fff;">
                                            {{  $data["part1"]["star_3_selected_count"]}}
                                        </span>
                                    </td>
                                </table>



                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <div>
                                <table>
                                    <td>
                                        <span style="display: inline-block; color:#fff;" class="stat_rating_counter2 ">2 Star</span>
                                    </td>

                                    <td>
                                        <span class="indicator2">
                                            <span
                                                style="display: block;
                                                color:#fff;
                                                  @if($total_review>0)
                                                width:{{((100/$total_review) * $data["part1"]["star_2_selected_count"])}}%;
                                                @else
                                                width:0%;
                                                @endif

                                                background-color: #AB8438; height: 100%; border-radius: 5px;"></span>
                                        </span>
                                    </td>


                                    <td>
                                        <span class="stat_rating_counter2" style="color:#fff;">
                                            {{  $data["part1"]["star_2_selected_count"]}}
                                        </span>
                                    </td>
                                </table>



                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span
                                style=" height: 40px; padding: 2px 10px; border-radius: 30px; background: #fff; box-shadow: 1px 1px 3px #ddd; color: #172C41; text-align:left;">
                                Based on
                                {{
$total_review

                                    }}

                                ratings
                            </span>
                        </td>
                        <td width="50%">
                            <div>
                                <table>
                                    <td>
                                        <span style="display: inline-block; color:#fff;" class="stat_rating_counter2">1 Star</span>
                                    </td>
                                    <td>
                                        <span class="indicator2">
                                            <span
                                                style="display: block;
color:#fff;
                                                @if($total_review>0)
                                                width:{{((100/$total_review) * $data["part1"]["star_1_selected_count"])}}%;
                                                @else
                                                width:0%;
                                                @endif

                                                background-color: #AB8438; height: 100%; border-radius: 5px;"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="stat_rating_counter2" style="color:#fff;">
                                            {{  $data["part1"]["star_1_selected_count"]}}
                                        </span>
                                    </td>
                                </table>



                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
        <div>

            @foreach ($data["part2"] as $index=>$question)

            @php
            $total_star_count=0;
            if (!empty($question["stars"])) {
                $star_collection = collect($question["stars"]);
                $total_star_count = $star_collection->sum('stars_count');
            }








            @endphp


            <div class="single_question_container">
                <h4 class="qu">{{$index + 1}}. {{$question["question"]}}</h4>

                <div class="single_question">
                    <div class="single_star_details">
                        <table style="width: 100%;">
                            <!-- 1STAR  -->
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <div class="single_graph_container">
                                        <table>
                                            <tr>
                                                <td>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">



                                                    </span>

                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>


                                                </td>



                                                <td> <span class="indicator">
                                                        <span
                                                            style="display: block;
                                                             @if($total_star_count > 0)
                                                            width:

                                                            {{((100/$total_star_count)*$question["stars"][0]["stars_count"])}}%;
                                                            @else
                                                            width:0%;
                                                            @endif

                                                           background-color: #AB8438; height: 100%; border-radius: 30px;"></span>
                                                    </span></td>


                                                <td>
                                                    <span class="stat_rating_counter">

                                                        @if (!empty($question["stars"]))
                                                        {{$question["stars"][0]["stars_count"]}}
                                                        @else
                                                        0
                                                        @endif





                                                    </span>
                                                </td>





                                            </tr>
                                        </table>



                                    </div>
                                </td>

                                <td style="width: 50%;">
                                    <div>
                                        <table>
                                            @php
$tag_ratings_count = 0;
if(!empty($question["stars"][0]["tag_ratings"])) {
    $tag_ratings_count = count($question["stars"][0]["tag_ratings"]);
}



                               $total_loops = intval($tag_ratings_count/$taglist_inrow) ;


                                            @endphp


                                            @for ($i = 0; $i <= $total_loops; $i++)

                                            @if ($i != $total_loops)
                                            <tr>
                                                @for ($j = 0; $j <= ($taglist_inrow-1); $j++)

                                                <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                    {{$question["stars"][0]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} - {{$question["stars"][0]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                </td>

                                                @endfor

                                            </tr>
                                            <tr>
                                                <td height="5"></td>
                                            </tr>
                                            @else

                                                <tr>
                                                    @for ($j = 0; (($i * $taglist_inrow) + $j) < $tag_ratings_count; $j++)

                                                    <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                        {{$question["stars"][0]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} {{$question["stars"][0]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                    </td>

                                                    @endfor


                                                </tr>


                                            @endif


@endfor

                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <!-- 1STAR  -->



                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <div class="single_graph_container">
                                        <table>
                                            <tr>
                                                <td colspan="10"><span class="star">
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">



                                                    </span>

                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    </span>
                                                </td>



                                                <td> <span class="indicator">
                                                        <span
                                                            style="display: block;
                                                            @if($total_star_count > 0)
                                                            width:

                                                            {{((100/$total_star_count)*$question["stars"][1]["stars_count"])}}%;
                                                            @else
                                                            width:0%;
                                                            @endif

                                                            background-color: #AB8438; height: 100%; border-radius: 30px;"></span>
                                                    </span></td>


                                                <td>
                                                    <span class="stat_rating_counter">
                                                        @if (!empty($question["stars"]))
                                                        {{$question["stars"][1]["stars_count"]}}
                                                        @else
                                                        0
                                                        @endif
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>



                                    </div>
                                </td>

                                <td style="width: 50%;">
                                    <div>
                                        <table>
                                            @php

$tag_ratings_count = 0;
if(!empty($question["stars"][1]["tag_ratings"])) {
    $tag_ratings_count = count($question["stars"][1]["tag_ratings"]);
}

                                            $total_loops = intval($tag_ratings_count/$taglist_inrow) ;


                                                         @endphp


                                                         @for ($i = 0; $i <= $total_loops; $i++)

                                                         @if ($i != $total_loops)
                                                         <tr>
                                                             @for ($j = 0; $j <= ($taglist_inrow-1); $j++)

                                                             <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                 {{$question["stars"][1]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} - {{$question["stars"][1]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                             </td>

                                                             @endfor

                                                         </tr>
                                                         <tr>
                                                             <td height="5"></td>
                                                         </tr>
                                                         @else
                                                         <tr>
                                                            @for ($j = 0; (($i * $taglist_inrow) + $j) < $tag_ratings_count; $j++)

                                                            <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                {{$question["stars"][1]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}}
                                                               -
                                                                {{$question["stars"][1]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                            </td>

                                                            @endfor


                                                        </tr>
                                                         @endif


             @endfor

                                        </table>
                                    </div>
                                </td>
                            </tr>













                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <div class="single_graph_container">
                                        <table>
                                            <tr>
                                                <td colspan="10"><span class="star">
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">



                                                    </span>

                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    </span>
                                                </td>



                                                <td> <span class="indicator">
                                                        <span
                                                            style="display: block;

                                                            @if($total_star_count > 0)
                                                            width:{{((100/$total_star_count)*$question["stars"][2]["stars_count"])}}%;
                                                            @else
                                                            width:0%;
                                                            @endif
                                                           background-color: #AB8438; height: 100%; border-radius: 30px;"></span>
                                                    </span></td>


                                                <td>
                                                    <span class="stat_rating_counter">
                                                        @if (!empty($question["stars"]))
                                                        {{$question["stars"][2]["stars_count"]}}
                                                        @else
                                                        0
                                                        @endif
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>



                                    </div>
                                </td>

                                <td style="width: 50%;">
                                    <div>
                                        <table>
                                            @php

$tag_ratings_count = 0;
if(!empty($question["stars"][2]["tag_ratings"])) {
    $tag_ratings_count = count($question["stars"][2]["tag_ratings"]);
}

                                            $total_loops = intval($tag_ratings_count/$taglist_inrow) ;


                                                         @endphp


                                                         @for ($i = 0; $i <= $total_loops; $i++)

                                                         @if ($i != $total_loops)
                                                         <tr>
                                                             @for ($j = 0; $j <= ($taglist_inrow-1); $j++)

                                                             <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                 {{$question["stars"][2]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} - {{$question["stars"][2]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                             </td>

                                                             @endfor

                                                         </tr>
                                                         <tr>
                                                             <td height="5"></td>
                                                         </tr>
                                                         @else
                                                         <tr>
                                                            @for ($j = 0; (($i * $taglist_inrow) + $j) < $tag_ratings_count; $j++)

                                                            <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                {{$question["stars"][2]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} -  {{$question["stars"][2]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                            </td>

                                                            @endfor


                                                        </tr>
                                                         @endif


             @endfor

                                        </table>
                                    </div>
                                </td>
                            </tr>





























                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <div class="single_graph_container">
                                        <table>
                                            <tr>
                                                <td colspan="10"><span class="star">
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">



                                                    </span>

                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star2.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    </span>
                                                </td>



                                                <td> <span class="indicator">
                                                        <span
                                                            style="display: block;

                                                            @if($total_star_count > 0)
                                                            width:{{((100/$total_star_count)*$question["stars"][3]["stars_count"])}}%;
                                                            @else
                                                            width:0%;
                                                            @endif

                                                         background-color: #AB8438; height: 100%; border-radius: 30px;"></span>
                                                    </span></td>


                                                <td>
                                                    <span class="stat_rating_counter">
                                                        @if (!empty($question["stars"]))
                                                        {{$question["stars"][3]["stars_count"]}}
                                                        @else
                                                        0
                                                        @endif
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>



                                    </div>
                                </td>

                                <td style="width: 50%;">
                                    <div>
                                        <table>
                                            @php

$tag_ratings_count = 0;
if(!empty($question["stars"][3]["tag_ratings"])) {
    $tag_ratings_count = count($question["stars"][3]["tag_ratings"]);
}

                                            $total_loops = intval($tag_ratings_count/$taglist_inrow) ;


                                                         @endphp


                                                         @for ($i = 0; $i <= $total_loops; $i++)

                                                         @if ($i != $total_loops)
                                                         <tr>
                                                             @for ($j = 0; $j <= ($taglist_inrow-1); $j++)

                                                             <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                 {{$question["stars"][3]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}}
                                                               -
                                                                 {{$question["stars"][3]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                             </td>

                                                             @endfor

                                                         </tr>
                                                         <tr>
                                                             <td height="5"></td>
                                                         </tr>
                                                         @else
                                                         <tr>
                                                            @for ($j = 0; (($i * $taglist_inrow) + $j) < $tag_ratings_count; $j++)

                                                            <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                                {{$question["stars"][3]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}}
                                                                -
                                                                {{$question["stars"][3]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                            </td>

                                                            @endfor


                                                        </tr>
                                                         @endif


             @endfor

                                        </table>
                                    </div>
                                </td>
                            </tr>



































                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <div class="single_graph_container">
                                        <table>
                                            <tr>
                                                <td colspan="10"><span class="star">
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">



                                                    </span>

                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    <span class="star">
                                                        <img style="width: 10px; height: 10px; object-fit: contain;"
                                                            src="{{(env('APP_URL') . "/" . "img/star1.jpg")}}"
                                                            alt="">

                                                    </span>
                                                    </span>
                                                </td>



                                                <td> <span class="indicator">
                                                        <span
                                                            style="display: block;
                                                              @if($total_star_count > 0)
                                                              width:{{((100/$total_star_count)*$question["stars"][4]["stars_count"])}}%;
                                                              @else
                                                            width:0%;
                                                            @endif
                                                           background-color: #AB8438; height: 100%; border-radius: 30px;"></span>
                                                    </span></td>


                                                <td>
                                                    <span class="stat_rating_counter">
                                                        @if (!empty($question["stars"]))
                                                        {{$question["stars"][4]["stars_count"]}}
                                                        @else
                                                        0
                                                        @endif
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>



                                    </div>
                                </td>

                                <td style="width: 50%;">
                                    <div>
                                        <table>

                                            @php


$tag_ratings_count = 0;
if(!empty($question["stars"][4]["tag_ratings"])) {
    $tag_ratings_count = count($question["stars"][4]["tag_ratings"]);
}

                               $total_loops = intval($tag_ratings_count/$taglist_inrow);


                                            @endphp


                                            @for ($i = 0; $i <= $total_loops; $i++)

                                            @if ($i != $total_loops)
                                            <tr>
                                                @for ($j = 0; $j <= ($taglist_inrow-1); $j++)

                                                <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                    {{$question["stars"][4]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} - {{$question["stars"][4]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                </td>

                                                @endfor

                                            </tr>
                                            <tr>
                                                <td height="5"></td>
                                            </tr>
                                            @else
                                            <tr>
                                                @for ($j = 0; (($i * $taglist_inrow) + $j) < $tag_ratings_count; $j++)

                                                <td style="display: inline; margin: 0px 2px;" class="single_tag">
                                                    {{$question["stars"][4]["tag_ratings"][(($i * $taglist_inrow) + $j)]->tag}} - {{$question["stars"][4]["tag_ratings"][(($i * $taglist_inrow) + $j)]->total}}
                                                </td>

                                                @endfor


                                            </tr>
                                            @endif


@endfor


                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach



        </div>
    </main>
</body>

</html>
