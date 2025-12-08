<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BusinessDaysController;
use App\Http\Controllers\CustomerController;

use App\Http\Controllers\CustomWebhookController;

use App\Http\Controllers\DailyViewsController;
use App\Http\Controllers\DashboardManagementController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EmailTemplateWrapperController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\LeafletController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewNewController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SuperAdminReportController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Define route for GET method
Route::get('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for POST method
Route::post('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for PUT method
Route::put('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for DELETE method
Route::delete('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for PATCH method
Route::patch('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});



// /review-new/get/questions-all-report/guest

// Auth Route login user
Route::post('/resend-email-verify-mail', [AuthController::class, "resendEmailVerifyByToken"]);
Route::post('/auth', [AuthController::class, "userLogin"]);
Route::post('/auth/register', [AuthController::class, "userRegister"]);




Route::post('/owner/user/registration', [OwnerController::class, "createUser2"]);
Route::post('/v1.0/create-user-with-business', [OwnerController::class, "createUserWithBusiness"]);




Route::post('/v1.0/auth/check-user-email', [AuthController::class, "checkUserEmail"]);


Route::post('/owner', [OwnerController::class, "createUser"]);
// #################
// Owner Routes
// Authorization may be hide for some routes I do not know
// #################


// guest user
Route::post('/v1.0/register-guest-users', [OwnerController::class, "createGuestUser"]);
// end of guest user
Route::post('/owner/staffregister/{businessId}', [OwnerController::class, "createStaffUser"]);

Route::post('/owner/pin/{ownerId}', [OwnerController::class, "updatePin"]);

Route::get('/owner/{ownerId}', [OwnerController::class, "getOwnerById"]);

Route::get('/owner/getAllowner/withourbusiness', [OwnerController::class, "getOwnerNotHaveRestaurent"]);

Route::get('/owner/loaduser/bynumber/{phoneNumber}', [OwnerController::class, "getOwnerByPhoneNumber"]);


Route::get('/v1.0/business/{businessId}', [BusinessController::class, "getBusinessById"]);


Route::get('/review-new/get/questions-all/customer', [ReviewNewController::class, "getQuestionAllUnauthorized"]);

Route::get('/review-new/get/questions-all-overall/customer', [ReviewNewController::class, "getQuestionAllUnauthorizedOverall"]);




Route::get('/review-new/get/questions-all-report/unauthorized', [ReviewNewController::class, "getQuestionAllReportUnauthorized"]);


Route::get('/review-new/get/questions-all-report/guest/unauthorized', [ReviewNewController::class, "getQuestionAllReportGuestUnauthorized"]);




Route::post('/v1.0/review-new-guest/{businessId}', [ReviewNewController::class, "storeReviewByGuest"]);

Route::post('/v1.0/reviews/overall/ordering', [ReviewNewController::class, "orderOverallReviews"]);

Route::post('/v1.0/voice/transcribe', [ReviewNewController::class, "transcribeVoice"]);



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// @@@@@@@@@@@@@@@@@@@@  Protected Routes      @@@@@@@@@@@@@@@@@
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::middleware(['auth:api'])->group(function () {

    Route::post('/v1.0/review-new/{businessId}', [ReviewNewController::class, "createReview"]);
    Route::get('/v3.0/dashboard-report', [ReportController::class, "getDashboardReportV3"]);

    // #################
    // Question Management Routes
    // #################
    Route::get('/v1.0/questions', [QuestionController::class, 'getAllQuestions']);
    Route::get('/v1.0/questions/{id}', [QuestionController::class, 'questionById']);
    Route::post('/v1.0/questions', [QuestionController::class, 'createQuestion']);
    Route::delete('/v1.0/questions/{ids}', [QuestionController::class, 'deleteQuestion']);

    Route::patch('/v1.0/questions/ordering', [QuestionController::class, 'displayQuestionOrder']);
    Route::patch('/v1.0/questions/set-overall', [QuestionController::class, 'setOverallQuestions']);
    Route::patch('/v1.0/questions/toggle', [QuestionController::class, 'toggleQuestionActivation']);
    Route::patch('/v1.0/questions/{id}', [QuestionController::class, 'updatedQuestion'])->whereNumber('id');

    // Leaflet CRUD
    Route::post('/v1.0/leaflet/create',        [LeafletController::class, 'insertLeaflet']);
    Route::put('/v1.0/leaflet/update/{id}',        [LeafletController::class, 'editLeaflet']);
    Route::get('/v1.0/leaflet/get',           [LeafletController::class, 'getAllLeaflet']);
    Route::get('/v1.0/leaflet/get/{id}',      [LeafletController::class, 'leafletById']);
    Route::delete('/v1.0/leaflet/{ids}', [LeafletController::class, 'leafletDeleteById']);
    Route::post('/v1.0/leaflet-image',         [LeafletController::class, 'insertLeafletImage']);


    Route::get('/v1.0/staffs',        [StaffController::class, 'getAllStaffs']);   // list
    Route::get('/v1.0/staffs/{id}',   [StaffController::class, 'getStaffById']);    // read
    Route::post('/v1.0/staffs',        [StaffController::class, 'createStaff']);   // create
    Route::patch('/v1.0/staffs/{id}',   [StaffController::class, 'updateStaff']);  // update (partial)
    Route::delete('/v1.0/staffs/{id}',   [StaffController::class, 'deleteStaff']); // delete
    Route::post('/v1.0/staff-image',    [StaffController::class, 'uploadStaffImage']); // upload image


    // #################
    // notification  Routes
    // #################
    Route::post('/v1.0/tags', [TagController::class, 'createTag']);          // Create
    Route::get('/v1.0/tags', [TagController::class, 'getAllTags']);          // Get all (optional ?business_id=)
    Route::get('/v1.0/tags/{id}', [TagController::class, 'getTagById']);         // Get single
    Route::patch('/v1.0/tags/multiple/{businessId}', [TagController::class, 'createMultipleTags']);    // Update
    Route::patch('/v1.0/tags/{id}', [TagController::class, 'updateTag']);    // Update
    Route::delete('/v1.0/tags/{ids}', [TagController::class, 'deleteTag']);  // Delete multiple: /tags/1,2,3

    // #################
    // notification  Routes
    // #################

    Route::post('/v1.0/notification', [NotificationController::class, "createNotification"]);
    Route::patch('/v1.0/notification/{notificationId}', [NotificationController::class, "updateNotification"]);
    Route::get('/v1.0/notification', [NotificationController::class, "getNotification"]);
    Route::delete('/v1.0/notification/{notificationId}', [NotificationController::class, "deleteNotification"]);



    Route::get('/v1.0/user', [AuthController::class, "getAllUser"]);





    Route::post('/v1.0/header-image/{business_id}', [OwnerController::class, "createHeaderImage"]);

    Route::post('/v1.0/rating-page-image/{business_id}', [OwnerController::class, "createRatingPageImage"]);

    Route::post('/v1.0/placeholder-image/{business_id}', [OwnerController::class, "createPlaceholderImage"]);




    // #################
    // Auth Routes
    // #################


    Route::patch('/v1.0/upload/profile-image', [OwnerController::class, "updateImage"]);

    Route::post('/auth/check-pin/{id}', [AuthController::class, "verifyPin"]);
    Route::get('/auth', [AuthController::class, "getUsersWithRestaurants"]);
    Route::get('/auth/users', [AuthController::class, "getAllUsers"]);

    // #################
    // Restaurant Routes
    // #################



    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // expense type management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    Route::post('/v1.0/surveys', [SurveyController::class, "createSurvey"]);
    Route::put('/v1.0/surveys', [SurveyController::class, "updateSurvey"]);

    Route::post('/v1.0/surveys/ordering', [SurveyController::class, "orderSurveys"]);

    Route::get('/v1.0/surveys/{business_id}/{perPage}', [SurveyController::class, "getSurveys"]);
    Route::get('/v1.0/surveys/{business_id}', [SurveyController::class, "getAllSurveys"]);
    Route::delete('/v1.0/surveys/{id}', [SurveyController::class, "deleteSurveyById"]);
    // %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
    // expense type management section
    // %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%



    // ********************************************
    // user management section --role
    // ********************************************
    Route::get('/v1.0/initial-role-permissions', [RolesController::class, "getInitialRolePermissions"]);
    Route::get('/v1.0/initial-permissions', [RolesController::class, "getInitialPermissions"]);
    Route::post('/v1.0/roles', [RolesController::class, "createRole"]);
    Route::put('/v1.0/roles', [RolesController::class, "updateRole"]);
    Route::get('/v1.0/roles', [RolesController::class, "getRoles"]);
    Route::get('/v1.0/roles/{id}', [RolesController::class, "getRoleById"]);
    Route::delete('/v1.0/roles/{ids}', [RolesController::class, "deleteRolesByIds"]);
    // %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
    // end user management section
    // %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%





    // BUSINESS RELATED API
    Route::post('/v1.0/business', [BusinessController::class, "storeRestaurant"]);
    Route::post('/v1.0/business/by-owner-id', [BusinessController::class, "storeRestaurantByOwnerId"]);
    Route::get('/v1.0/business', [BusinessController::class, "getAllBusinesses"]);
    Route::patch('/v1.0/business/{businessId}', [BusinessController::class, "UpdateBusiness"]);
    Route::delete('/v1.0/business/{id}', [BusinessController::class, "deleteBusinessById"]);
    Route::get('/v1.0/restaurants/tables/{businessId}', [BusinessController::class, "getRestaurantTableByBusinessId"]);

    Route::delete('/v1.0/business/delete/force-delete/{email}', [BusinessController::class, "deleteBusinessByRestaurantIdForceDelete"]);


    Route::patch('/v1.0/business/upload-image/{businessId}', [BusinessController::class, "uploadRestaurantImage"]);

    // #################
    // branch Routes
    // #################
    Route::prefix('v1.0/branches')->group(function () {
        Route::get('/', [BranchController::class, 'getBranches']);
        Route::post('/', [BranchController::class, 'createBranch']);
        Route::get('/{id}', [BranchController::class, 'getBranchById']);
        Route::patch('/{id}', [BranchController::class, 'updateBranch']);
        Route::delete('/{id}', [BranchController::class, 'deleteBranches']);
    });

    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // business Time Management
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    Route::patch('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "updateBusinessDays"]);
    Route::get('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);



    Route::patch('/business/UpdateResturantStripeDetails/{restaurentId}', [StripeController::class, "UpdateResturantStripeDetails"]);
    Route::get('/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetails"]);











    // #################
    // daily views Routes

    Route::post('/v1.0/daily-views/{businessId}', [DailyViewsController::class, "createDailyView"]);
    Route::patch('/v1.0/daily-views/update/{businessId}', [DailyViewsController::class, "updateDailyView"]);

    // #################
    // forgot password Routes

    // #################

    Route::patch('/v1.0/auth/change-password', [ForgotPasswordController::class, "changePassword"]);



    // #################
    // review new  Routes
    // #################

    Route::get('/v1.0/review-new/values/{businessId}/{rate}', [ReviewNewController::class, "getReviewValue"]);

    Route::post('/review-new/reviewvalue/{businessId}/{rate}', [ReviewNewController::class, "store"]);
    Route::get('/review-new/getvalues/{businessId}', [ReviewNewController::class, "getreviewvalueById"]);
    Route::get('/review-new/getavg/review/{businessId}/{start}/{end}', [ReviewNewController::class, "getAverages"]);
    Route::post('/review-new/addupdatetags/{businessId}', [ReviewNewController::class, "store2"]);

    Route::get('/review-new/getreview/{businessId}/{rate}/{start}/{end}', [ReviewNewController::class, "filterReviews"]);
    Route::get('/review-new/getreviewAll/{businessId}', [ReviewNewController::class, "reviewByBusinessId"]);
    Route::get('/review-new/getcustomerreview/{businessId}/{start}/{end}', [ReviewNewController::class, "getCustommerReview"]);


    // #################
    // question  Routes
    // #################
    Route::post('/review-new/create/questions', [ReviewNewController::class, "storeQuestion"]);
    Route::put('/review-new/update/questions', [ReviewNewController::class, "updateQuestion"]);


    Route::put('/v1.0/review-new/set-overall-question', [ReviewNewController::class, "setOverallQuestion"]);




    Route::put('/review-new/update/active_state/questions', [ReviewNewController::class, "updateQuestionActiveState"]);

    Route::get('/v1.0/review-new/get/questions', [ReviewNewController::class, "getQuestion"]);


    Route::get('/review-new/get/questions-all', [ReviewNewController::class, "getQuestionAll"]);


    Route::get('/review-new/get/questions-all-report', [ReviewNewController::class, "getQuestionAllReport"]);
    Route::get('/review-new/get/questions-all-report/guest', [ReviewNewController::class, "getQuestionAllReportGuest"]);


    Route::get('/review-new/get/questions-all-report-by-user/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUser"]);
    Route::get('/review-new/get/questions-all-report-by-user-guest/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUserGuest"]);




    Route::get('/review-new/get/questions/{id}', [ReviewNewController::class, "getQuestionById"]);
    Route::get('/review-new/get/questions/{id}/{businessId}', [ReviewNewController::class, "getQuestionById2"]);
    Route::delete('/review-new/delete/questions/{id}', [ReviewNewController::class, "deleteQuestionById"]);

    // #################
    // tag  Routes
    // #################

    Route::post('/review-new/create/tags', [ReviewNewController::class, "storeTag"]);

    Route::post('/v1.0/review-new/create/tags/multiple/{businessId}', [ReviewNewController::class, "storeTagMultiple"]);

    Route::put('/review-new/update/tags', [ReviewNewController::class, "updatedTag"]);
    Route::get('/review-new/get/tags', [ReviewNewController::class, "getTag"]);
    Route::get('/review-new/get/tags/{id}', [ReviewNewController::class, "TagById"]);
    Route::get('/review-new/get/tags/{id}/{reataurantId}', [ReviewNewController::class, "getTagById2"]);
    Route::delete('/review-new/delete/tags/{id}', [ReviewNewController::class, "deleteTagById"]);
    // #################
    // Star Routes
    // #################
    Route::post('/review-new/create/stars', [ReviewNewController::class, "storeStar"]);
    Route::put('/review-new/update/stars', [ReviewNewController::class, "updateStar"]);
    Route::get('/review-new/get/stars', [ReviewNewController::class, "getStar"]);
    Route::get('/review-new/get/stars/{id}', [ReviewNewController::class, "getStarById"]);
    Route::get('/review-new/get/stars/{id}/{businessId}', [ReviewNewController::class, "getStarById2"]);
    Route::delete('/review-new/delete/stars/{id}', [ReviewNewController::class, "deleteStarById"]);





    Route::get('/review-new/get/questions-all-report/quantum', [ReviewNewController::class, "getQuestionAllReportQuantum"]);

    Route::get('/review-new/get/questions-all-report/guest/quantum', [ReviewNewController::class, "getQuestionAllReportGuestQuantum"]);




    // #################
    // Star tag question Routes
    // #################

    Route::post('/star-tag-question', [ReviewNewController::class, "storeStarTag"]);
    Route::put('/star-tag-question', [ReviewNewController::class, "updateStarTag"]);
    Route::get('/star-tag-question', [ReviewNewController::class, "getStarTag"]);
    Route::get('/star-tag-question/{id}', [ReviewNewController::class, "getStarTagById"]);
    Route::delete('/star-tag-question/{id}', [ReviewNewController::class, "deleteStarTagById"]);
    Route::get('/tag-count/star-tag-question/{businessId}', [ReviewNewController::class, "getSelectedTagCount"]);
    Route::get('/tag-count/star-tag-question/by-question/{questionId}', [ReviewNewController::class, "getSelectedTagCountByQuestion"]);



    Route::get('/v1.0/customers', [CustomerController::class, "getCustomers"]);



    Route::middleware(['superadmin'])->group(function () {

        Route::patch('/v1.0/auth/change-password-by-superadmin', [ForgotPasswordController::class, "changePasswordBySuperAdmin"]);


        Route::get('/v1.0/superadmin/dashboard-report/total-business', [SuperAdminReportController::class, "getTotalBusinessReport"]);


        Route::get('/v1.0/superadmin/dashboard-report/total-business-enabled', [SuperAdminReportController::class, "getTotalEnabledBusinessReport"]);

        Route::get('/v1.0/superadmin/dashboard-report/total-business-disabled', [SuperAdminReportController::class, "getTotalDisabledBusinessReport"]);




        Route::get('/v1.0/superadmin/dashboard-report/total-reviews', [SuperAdminReportController::class, "getTotalReviews"]);
        Route::get('/v1.0/superadmin/dashboard-report/today-reviews', [SuperAdminReportController::class, "getTodayReviews"]);
        Route::get('/v1.0/superadmin/dashboard-report/review-report', [SuperAdminReportController::class, "getReviewReport"]);
        Route::get('/v1.0/superadmin/customer-list/{perPage}', [UserController::class, "getCustomerReportSuperadmin"]);

        Route::get('/superadmin/owner-list/{perPage}', [UserController::class, "getOwnerReport"]);
        Route::delete('superadmin/user-delete/{id}', [UserController::class, "deleteCustomerById"]);
        // EMAIL TEMPLATE WRAPPER MANAGEMENT
        Route::prefix('v1.0/email-template-wrappers')->group(function () {
            Route::put('/{id}', [EmailTemplateWrapperController::class, "updateEmailTemplateWrapper"]);
            Route::get('/{id}', [EmailTemplateWrapperController::class, "getEmailTemplateWrapperById"]);
            Route::get('/', [EmailTemplateWrapperController::class, "getEmailTemplateWrappers"]);
        });


        // EMAIL TEMPLATE MANAGEMENT
        Route::prefix('v1.0/email-templates')->group(function () {
            Route::post('/', [EmailTemplateController::class, "createEmailTemplate"]);
            Route::put('/{id}', [EmailTemplateController::class, "updateEmailTemplate"]);
            Route::get('/', [EmailTemplateController::class, "getEmailTemplates"]);
            Route::get('/{id}', [EmailTemplateController::class, "getEmailTemplateById"]);
            Route::delete('/{ids}', [EmailTemplateController::class, "deleteEmailTemplateById"]);
        });
        Route::get('/v1.0/email-template-types', [EmailTemplateController::class, "getEmailTemplateTypes"]);
    });

    Route::get('/v1.0/customer-report', [ReportController::class, "customerDashboardReport"]);
    Route::get('/v1.0/business-report', [ReportController::class, "businessDashboardReport"]);
    Route::get('/v1.0/business-owner-dashboard', [DashboardManagementController::class, "getBusinessOwnerDashboardData"]);
    Route::get('/dashboard-report/get/table-report/{businessId}', [ReportController::class, "getTableReport"]);
    Route::get('/v1.0/dashboard-report/{businessId}', [ReportController::class, "getDashboardReport"]);
    Route::get('/v1.0/dashboard-report/business/get', [ReportController::class, "getBusinessReport"]);

    Route::get('/v1.0/reports/staff-comparison/{businessId}', [ReportController::class, 'staffComparison']);

    Route::get('/v1.0/reports/staff-performance/{businessId}/{staffId}', [ReportController::class, 'staffPerformance']);

    Route::get('/v1.0/reports/staff-dashboard/{businessId}', [ReportController::class, 'staffDashboard']);

    Route::get('/v1.0/reports/review-analytics/{businessId}', [ReportController::class, 'reviewAnalytics']);

    // #################
    // Review Owner Routes
    // #################

    Route::post('/review-new/owner/create/questions', [ReviewNewController::class, "storeOwnerQuestion"]);
    Route::patch('/review-new/owner/update/questions', [ReviewNewController::class, "updateOwnerQuestion"]);
    // #################
    // order Routes
    // #################


    // REVIEW CONTROLLER
    Route::get('/v1.0/review-new/{reviewId}', [ReviewNewController::class, "getReviewById"]);
});

// #################
// forget password Routes
// #################

Route::post('/v1.0/forgot-password', [ForgotPasswordController::class, "storeForgetPassword"]);
Route::patch('/v1.0/forget-password/reset/{token}', [ForgotPasswordController::class, "changePasswordByToken"]);

Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"]);
Route::get('/v1.0/client/businesses', [BusinessController::class, "getBusinessesClients"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// coupon management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::get('/client/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);
Route::get('/v1.0/client/staffs', [StaffController::class, 'getClientAllStaffs']);   // list



Route::get('/client/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetailsClient"]);


// Route::get('/review-new/getreview/{businessId}/{rate}/{start}/{end}', [ReviewNewController::class, "filterReviews"]);
Route::get('/review-new/getreviewAll/{businessId}', [ReviewNewController::class, "getReviewByBusinessId"]);
Route::get('/review-new/getcustomerreview/{businessId}/{start}/{end}', [ReviewNewController::class, "getCustommerReview"]);

// #################
// question  Routes
// #################

Route::put('/review-new/update/questions', [ReviewNewController::class, "updateQuestion"]);


Route::put('/v1.0/review-new/set-overall-question', [ReviewNewController::class, "setOverallQuestion"]);




Route::put('/review-new/update/active_state/questions', [ReviewNewController::class, "updateQuestionActiveState"]);

Route::get('/v1.0/review-new/get/questions', [ReviewNewController::class, "getQuestion"]);


Route::get('/review-new/get/questions-all', [ReviewNewController::class, "getQuestionAll"]);


Route::get('/review-new/get/questions-all-report', [ReviewNewController::class, "getQuestionAllReport"]);
Route::get('/review-new/get/questions-all-report/guest', [ReviewNewController::class, "getQuestionAllReportGuest"]);


Route::get('/review-new/get/questions-all-report-by-user/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUser"]);
Route::get('/review-new/get/questions-all-report-by-user-guest/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUserGuest"]);




Route::get('/review-new/get/questions/{id}', [ReviewNewController::class, "getQuestionById"]);
Route::get('/review-new/get/questions/{id}/{businessId}', [ReviewNewController::class, "getQuestionById2"]);
Route::delete('/review-new/delete/questions/{id}', [ReviewNewController::class, "deleteQuestionById"]);

// #################
// tag  Routes
// #################

Route::post('/review-new/create/tags', [ReviewNewController::class, "storeTag"]);

// Route::post('/v1.0/review-new/create/tags/multiple/{businessId}', [ReviewNewController::class, "storeTagMultiple"]);

// Route::put('/review-new/update/tags', [ReviewNewController::class, "updateTag"]);

Route::get('/review-new/get/tags/{id}', [ReviewNewController::class, "getTagById"]);
Route::get('/review-new/get/tags/{id}/{reataurantId}', [ReviewNewController::class, "getTagById2"]);
Route::delete('/review-new/delete/tags/{id}', [ReviewNewController::class, "deleteTagById"]);
// #################
// Star Routes
// #################
Route::post('/review-new/create/stars', [ReviewNewController::class, "storeStar"]);
Route::put('/review-new/update/stars', [ReviewNewController::class, "updateStar"]);
Route::get('/review-new/get/stars', [ReviewNewController::class, "getStar"]);
Route::get('/review-new/get/stars/{id}', [ReviewNewController::class, "getStarById"]);
Route::get('/review-new/get/stars/{id}/{businessId}', [ReviewNewController::class, "getStarById2"]);
Route::delete('/review-new/delete/stars/{id}', [ReviewNewController::class, "deleteStarById"]);





Route::get('/review-new/get/questions-all-report/quantum', [ReviewNewController::class, "getQuestionAllReportQuantum"]);

Route::get('/review-new/get/questions-all-report/guest/quantum', [ReviewNewController::class, "getQuestionAllReportGuestQuantum"]);




// #################
// Star tag question Routes
// #################

Route::post('/star-tag-question', [ReviewNewController::class, "storeStarTag"]);
Route::put('/star-tag-question', [ReviewNewController::class, "updateStarTag"]);
Route::get('/star-tag-question', [ReviewNewController::class, "getStarTag"]);
Route::get('/star-tag-question/{id}', [ReviewNewController::class, "getStarTagById"]);
Route::delete('/star-tag-question/{id}', [ReviewNewController::class, "deleteStarTagById"]);
Route::get('/tag-count/star-tag-question/{businessId}', [ReviewNewController::class, "getSelectedTagCount"]);
Route::get('/tag-count/star-tag-question/by-question/{questionId}', [ReviewNewController::class, "getSelectedTagCountByQuestion"]);



Route::get('/v1.0/customers', [CustomerController::class, "getCustomers"]);



// Overall Business Dashboard
Route::get('/v1.0/reviews/overall-dashboard/{businessId}', [ReviewNewController::class, 'getOverallDashboardData']);
// Voice Review Submission


// Update Business Review Settings
Route::put('/v1.0/businesses/{businessId}/review-settings', [ReviewNewController::class, 'updatedReviewSettings']);




Route::middleware(['superadmin'])->group(function () {




    Route::get('/v1.0/superadmin/dashboard-report/total-business', [SuperAdminReportController::class, "getTotalBusinessReport"]);


    Route::get('/v1.0/superadmin/dashboard-report/total-business-enabled', [SuperAdminReportController::class, "getTotalEnabledBusinessReport"]);

    Route::get('/v1.0/superadmin/dashboard-report/total-business-disabled', [SuperAdminReportController::class, "getTotalDisabledBusinessReport"]);





    Route::get('/v1.0/superadmin/dashboard-report/today-reviews', [SuperAdminReportController::class, "getTodayReviews"]);
    Route::get('/v1.0/superadmin/dashboard-report/review-report', [SuperAdminReportController::class, "getReviewReport"]);
    Route::get('/v1.0/superadmin/customer-list/{perPage}', [UserController::class, "getCustomerReportSuperadmin"]);

   
    Route::delete('superadmin/user-delete/{id}', [UserController::class, "deleteCustomerById"]);
    // EMAIL TEMPLATE WRAPPER MANAGEMENT
    Route::prefix('v1.0/email-template-wrappers')->group(function () {
        Route::put('/{id}', [EmailTemplateWrapperController::class, "updateEmailTemplateWrapper"]);
        Route::get('/{id}', [EmailTemplateWrapperController::class, "getEmailTemplateWrapperById"]);
        Route::get('/', [EmailTemplateWrapperController::class, "getEmailTemplateWrappers"]);
    });


    // EMAIL TEMPLATE MANAGEMENT
    Route::prefix('v1.0/email-templates')->group(function () {
        Route::post('/', [EmailTemplateController::class, "createEmailTemplate"]);
        Route::put('/{id}', [EmailTemplateController::class, "updateEmailTemplate"]);
        Route::get('/', [EmailTemplateController::class, "getEmailTemplates"]);
        Route::get('/{id}', [EmailTemplateController::class, "getEmailTemplateById"]);
        Route::delete('/{ids}', [EmailTemplateController::class, "deleteEmailTemplateById"]);
    });
    Route::get('/v1.0/email-template-types', [EmailTemplateController::class, "getEmailTemplateTypes"]);
});

Route::get('/v1.0/customer-report', [ReportController::class, "customerDashboardReport"]);
Route::get('/v1.0/business-report', [ReportController::class, "businessDashboardReport"]);
Route::get('/v1.0/business-owner-dashboard', [DashboardManagementController::class, "getBusinessOwnerDashboardData"]);
Route::get('/dashboard-report/get/table-report/{businessId}', [ReportController::class, "getTableReport"]);
Route::get('/v1.0/dashboard-report/{businessId}', [ReportController::class, "getDashboardReport"]);
Route::get('/v1.0/dashboard-report/business/get', [ReportController::class, "getBusinessReport"]);

Route::get('/v1.0/reports/staff-comparison/{businessId}', [ReportController::class, 'staffComparison']);

Route::get('/v1.0/reports/staff-performance/{businessId}/{staffId}', [ReportController::class, 'staffPerformance']);

Route::get('/v1.0/reports/staff-dashboard/{businessId}', [ReportController::class, 'staffDashboard']);



// #################
// Review Owner Routes
// #################

Route::post('/review-new/owner/create/questions', [ReviewNewController::class, "storeOwnerQuestion"]);
Route::patch('/review-new/owner/update/questions', [ReviewNewController::class, "updateOwnerQuestion"]);
// #################
// order Routes
// #################


// REVIEW CONTROLLER
Route::get('/v1.0/review-new/{reviewId}', [ReviewNewController::class, "getReviewById"]);

// #################
// forget password Routes
// #################

Route::post('/v1.0/forgot-password', [ForgotPasswordController::class, "storeForgetPassword"]);
Route::patch('/v1.0/forget-password/reset/{token}', [ForgotPasswordController::class, "changePasswordByToken"]);

Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"]);
Route::get('/v1.0/client/businesses', [BusinessController::class, "getBusinessesClients"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// coupon management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::get('/client/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);
Route::get('/v1.0/client/staffs', [StaffController::class, 'getClientAllStaffs']);   // list



Route::get('/client/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetailsClient"]);


// REVIEW NEW CONTROLLER
Route::get('/v1.0/client/review-new/rating-analysis/{businessId}', [ReviewNewController::class, "getAverageRatingClient"]);
Route::get('/v1.0/client/review-new/{businessId}', [ReviewNewController::class, "getReviewByBusinessIdClient"]);


// Make Reviews Private
Route::put('/v1.0/client/reviews/make-private/{ids}', [ReviewNewController::class, 'makeReviewsPrivate']);

// Update Guest User Email by Review IDs
Route::put('/v1.0/client/reviews/update-guest-email/{ids}', [ReviewNewController::class, 'updateGuestEmailsByReviews']);


// Get Questions for Client Business
Route::get('/v1.0/client/questions/{business_id}', [QuestionController::class, 'getAllQuestionClient']);
