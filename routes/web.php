<?php


use App\Http\Controllers\SetupController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SwaggerLoginController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\SeederController;

use App\Mail\ResendVerificationMail;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateWrapper;
use App\Models\Review;
use App\Models\ReviewNew;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;


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

// Welcome Route
Route::get('/', function () {
    return view('welcome');
});

// Run demo seeder (accepts ?email=custom@domain.com). Restricted to local/debug.
Route::get('/run-demo-seeder', [SeederController::class, 'runDemo']);


Route::get('/generate-ai', function () {
    ReviewNew::whereNotNull("raw_text")->update(['is_ai_processed' => 0]);

    if (request()->boolean("generate")) {
        Artisan::call('reviews:process');
    }
});

Route::get('/reviews', function () {
    return response()->json([
        "data" => ReviewNew::whereNotNull("raw_text")->select(
            'id',
            'raw_text',
            'sentiment_score',
            'sentiment',
            'emotion',
            'key_phrases',
            'topics',
            'moderation_results',
            'ai_suggestions',
            'staff_suggestions',
            'language',
            'ai_confidence',
            'sentiment_label',
            'openai_raw_response',
            'summary',
            'rating_comment_mismatch',
            'mismatch_insights',

            'transcription_metadata',
            'branch_id',
            'is_ai_processed',
            'is_abusive',
            'staff_id',
            'created_at',
            'updated_at',



        )->get()
    ]);
});



// SWAGGER REFRESH
Route::get('/swagger-refresh', function () {
    // Clear caches
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');

    // Force regenerate (clears old cache)
    Artisan::call('l5-swagger:generate');
    return redirect('/api/documentation#');
});

// SETUP PASSPORT
Route::get('/setup-passport', [SetupController::class, "setupPassport"])->name('setup.passport');

// GENERATE PDF REPORTS
Route::get('/pdf', function () {
    Artisan::call('guest_user_review_report:generate');
    Artisan::call('user_review_report:generate');
    return "pdf generated";
});

// MIGRATION
Route::get('/migrate', [SetupController::class, "migrate"]);
Route::get('/migrate-status', [SetupController::class, "migrateStatus"]);
Route::get('/rollback-migrate', [SetupController::class, 'rollbackMigration'])->name('rollbackMigration');

// CLEAR CACHE
Route::get('/clear-cache', [SetupController::class, "clearCache"]);
// RUN ARTISAN COMMAND
Route::get('/run-artisan', [SetupController::class, "runArtisanCommand"]);
// ONE TIME DB OPERATION
Route::get('/one-time-db-operation', [SetupController::class, "oneTimeDBOperation"]);

// CHANGE PASSWORD FOR TEST USER
Route::get('/change-password', function () {
    $user = User::where('email', 'test.tags@yopmail.com')->firstOrFail();
    $user->password = Hash::make('12345678');
    $user->save();
    return redirect('/')->with('success', 'Password changed successfully!');
});


// SWAGGER LOGIN
Route::get("/swagger-login", [SwaggerLoginController::class, "login"])->name("login.view");
Route::post("/swagger-login", [SwaggerLoginController::class, "passUser"]);

// SETUP PROJECT
Route::get("/setup", [SetupController::class, "setup"]);

// ROLE AND PERMISSION REFRESH
Route::get('/roleRefresh', [SetupController::class, "roleRefresh"])->name("roleRefresh");

// GET Activity Log
Route::get('/activity-log', [SetupController::class, "getActivityLogs"])->name("activity-log");

// Custom API
Route::get('/custom-test-api', function () {
    return view("test_api_custom");
})->name("custom_api_test");

// EMAIL VERIFICATION LINK
Route::get("/activate/{token}", function (Request $request, $token) {
    $user = User::where([
        "email_verify_token" => $token,
    ])->first();

    if (!$user) {
        return view('auth.verification_result', [
            'status' => 'error',
            'page_title' => 'Verification Failed',
            'message' => 'Invalid or expired verification link.'
        ]);
    }

    // Check expiry
    if ($user->email_verify_token_expires && \Carbon\Carbon::parse($user->email_verify_token_expires)->isPast()) {
        return view('auth.verification_result', [
            'status' => 'error',
            'page_title' => 'Verification Failed',
            'message' => 'This verification link has expired.'
        ]);
    }

    $user->email_verified_at = now();
    $user->email_verify_token = null; // Clear token after use
    $user->email_verify_token_expires = null;
    $user->save();

    return view('auth.verification_result', [
        'status' => 'success',
        'page_title' => 'Email Verified Successfully!',
        'message' => 'Your email has been successfully verified! You can now log in.'
    ]);
});

// RESEND VERIFICATION EMAIL
Route::post('/resend-verification', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return view('auth.verification_result', [
            'status' => 'error',
            'page_title' => 'Verification Failed',
            'message' => 'We could not find a user with that email address.'
        ]);
    }

    if ($user->email_verified_at) {
        return view('auth.verification_result', [
            'status' => 'success', // or 'info', but success works to direct them to login
            'page_title' => 'Email Verified Successfully!',
            'message' => 'Your email is already verified. You can log in.'
        ]);
    }

    // Generate new token
    $user->email_verify_token = Illuminate\Support\Str::random(30);
    $user->email_verify_token_expires = now()->addDay();
    $user->save();

    // Send email
    try {
        $verificationUrl = env('APP_URL') . '/activate/' . $user->email_verify_token . '?email=' . urlencode($user->email);
        Mail::to($user->email)->send(new ResendVerificationMail($user, $verificationUrl));
    } catch (\Exception $e) {
        return view('auth.verification_result', [
            'status' => 'error',
            'page_title' => 'Failed to Send Email',
            'message' => 'Failed to send verification email. Please try again later.'
        ]);
    }

    return view('auth.verification_result', [
        'status' => 'success',
        'page_title' => 'Verification Email Sent',
        'message' => 'A new verification link has been sent to ' . $user->email
    ]);
});

Route::get("/test-pdf", [TestController::class, "testReport"]);
Route::get("/test-pdf2", [TestController::class, "testReport2"]);

Route::get("/orders/redirect-to-stripe", [StripeController::class, "redirectUserToStripe"]);

Route::get("/orders/get-success-payment", [StripeController::class, "stripePaymentSuccess"])->name("order.success_payment");
Route::get("/orders/get-failed-payment", [StripeController::class, "stripePaymentFailed"])->name("order.failed_payment");

Route::get('/storage-proxy/{path}', function ($path) {
    $file_path = storage_path('app/public/' . $path);

    if (!file_exists($file_path)) {
        abort(404);
    }

    return response()->file($file_path, [
        'Content-Type' => mime_content_type($file_path),
        'Access-Control-Allow-Origin' => '*',
    ]);
})->where('path', '.*');

Route::get('/user-action', function (Request $request) {
    $user = User::find($request->id);
    $user->delete();
    return "done";
});
