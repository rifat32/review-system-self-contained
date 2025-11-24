<?php


use App\Http\Controllers\SetupController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SwaggerLoginController;
use App\Http\Controllers\TestController;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateWrapper;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});



Route::get('/swagger-refresh', function () {
    Artisan::call('l5-swagger:generate');
    return "swagger generated";
});


Route::get('/passport', function () {

    ini_set('memory_limit', '512M');

    Artisan::call('passport:install');
    return "passport";
});


Route::get('/pdf', function () {
    Artisan::call('guest_user_review_report:generate');
    Artisan::call('user_review_report:generate');
    return "pdf generated";
});


Route::get('/migrate', [SetUpController::class, "migrate"]);

Route::get('/clear-cache', [SetupController::class, "clearCache"]);

Route::get('/change-password', function () {
    $user = User::where('email', 'test.tags@yopmail.com')->firstOrFail();
    $user->password = Hash::make('12345678');
    $user->save();
    return redirect('/')->with('success', 'Password changed successfully!');
});



Route::get("/swagger-login", [SwaggerLoginController::class, "login"])->name("login.view");
Route::post("/swagger-login", [SwaggerLoginController::class, "passUser"]);



Route::get("/setup", [SetupController::class, "setup"]);

Route::get('/roleRefresh', [SetUpController::class, "roleRefresh"])->name("roleRefresh");
Route::get('/activity-log', [SetUpController::class, "getActivityLogs"])->name("activity-log");
Route::get('/custom-test-api', function () {
    return view("test_api_custom");
})->name("custom_api_test");



Route::get("/activate/{token}", function (Request $request, $token) {
    $user = User::where([
        "email_verify_token" => $token,
    ])
        ->where("email_verify_token_expires", ">", now())
        ->first();
    if (!$user) {
        return redirect((env('FRONT_END_URL') . "/invalid-token"));
    }
    $user->email_verified_at = now();
    $user->save();


    $email_content = EmailTemplate::where([
        "type" => "welcome_message",
        "is_active" => 1

    ])->first();

    $html_content = json_decode($email_content->template);
    $html_content =  str_replace("[FirstName]", $user->first_Name, $html_content);
    $html_content =  str_replace("[LastName]", $user->last_Name, $html_content);
    $html_content =  str_replace("[FullName]", ($user->first_Name . " " . $user->last_Name), $html_content);
    $html_content =  str_replace("[AccountVerificationLink]", (env('APP_URL') . '/activate/' . $user->email_verify_token), $html_content);
    $html_content =  str_replace("[ForgotPasswordLink]", (env('FRONT_END_URL') . '/fotget-password/' . $user->resetPasswordToken), $html_content);


    $email_template_wrapper = EmailTemplateWrapper::where([
        "id" => 1
    ])
        ->first();

    $email_template_wrapper = EmailTemplateWrapper::where([
        "id" => 1
    ])
        ->first();


    $html_final = json_decode($email_template_wrapper->template);
    $html_final =  str_replace("[content]", $html_content, $html_final);
    return view('mail.dynamic_mail', ["html_content" => $html_final]);
});





Route::get("/test-pdf", [TestController::class, "testReport"]);
Route::get("/test-pdf2", [TestController::class, "testReport2"]);




Route::get("/orders/redirect-to-stripe", [StripeController::class, "redirectUserToStripe"]);

Route::get("/orders/get-success-payment", [StripeController::class, "stripePaymentSuccess"])->name("order.success_payment");
Route::get("/orders/get-failed-payment", [StripeController::class, "stripePaymentFailed"])->name("order.failed_payment");
