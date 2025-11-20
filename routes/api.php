<?php

use App\Http\Controllers\AuthController;
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
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewNewController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SuperAdminReportController;
use App\Http\Controllers\SurveyController;
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
Route::post('/resend-email-verify-mail', [AuthController::class, "resendEmailVerifyToken"]);
Route::post('/auth', [AuthController::class, "login"]);
Route::post('/auth/register', [AuthController::class, "register"]);




Route::post('/owner/user/registration', [OwnerController::class, "createUser2"]);
Route::post('/owner/user/with/business', [OwnerController::class, "createUserWithBusiness"]);




Route::post('/owner/user/check/email', [OwnerController::class, "checkEmail"]);

Route::post('/owner/super/admin', [OwnerController::class, "createsuperAdmin"]);

Route::post('/owner', [OwnerController::class, "createUser"]);
// #################
// Owner Routes
// Authorization may be hide for some routes I do not know
// #################


// guest user
Route::post('/owner/guestuserregister', [OwnerController::class, "createGuestUser"]);
// end of guest user
Route::post('/owner/staffregister/{businessId}', [OwnerController::class, "createStaffUser"]);

Route::post('/owner/pin/{ownerId}', [OwnerController::class, "updatePin"]);

Route::get('/owner/{ownerId}', [OwnerController::class, "getOwnerById"]);

Route::get('/owner/getAllowner/withourbusiness', [OwnerController::class, "getOwnerNotHaveRestaurent"]);

Route::get('/owner/loaduser/bynumber/{phoneNumber}', [OwnerController::class, "getOwnerByPhoneNumber"]);


Route::get('/business/{businessId}', [BusinessController::class, "getbusinessById"]);


Route::get('/review-new/get/questions-all/customer', [ReviewNewController::class, "getQuestionAllUnauthorized"]);

Route::get('/review-new/get/questions-all-overall/customer', [ReviewNewController::class, "getQuestionAllUnauthorizedOverall"]);




Route::get('/review-new/get/questions-all-report/unauthorized', [ReviewNewController::class, "getQuestionAllReportUnauthorized"]);


Route::get('/review-new/get/questions-all-report/guest/unauthorized', [ReviewNewController::class, "getQuestionAllReportGuestUnauthorized"]);




Route::post('/review-new-guest/{businessId}', [ReviewNewController::class, "storeReviewByGuest"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// @@@@@@@@@@@@@@@@@@@@  Protected Routes      @@@@@@@@@@@@@@@@@
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::middleware(['auth:api'])->group(function () {

    // Leaflet CRUD
    Route::post('/v1.0/leaflet/create',        [LeafletController::class, 'insertLeaflet']);
    Route::put('/v1.0/leaflet/update',        [LeafletController::class, 'editLeaflet']);
    Route::get('/v1.0/leaflet/get',           [LeafletController::class, 'getAllLeaflet']);
    Route::get('/v1.0/leaflet/get/{id}',      [LeafletController::class, 'leafletById']);
    Route::delete('/v1.0/leaflet/{business_id}/{id}', [LeafletController::class, 'leafletDeleteById']);
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

    Route::post('/notification', [NotificationController::class, "storeNotification"]);
    Route::patch('/notification/{notificationId}', [NotificationController::class, "updateNotification"]);
    Route::get('/notification', [NotificationController::class, "getNotification"]);
    Route::delete('/notification/{notificationId}', [NotificationController::class, "deleteNotification"]);




    Route::get('/v1.0/user', [AuthController::class, "getUser"]);





    Route::post('/v1.0/header-image/{business_id}', [OwnerController::class, "createHeaderImage"]);

    Route::post('/v1.0/rating-page-image/{business_id}', [OwnerController::class, "createRatingPageImage"]);

    Route::post('/v1.0/placeholder-image/{business_id}', [OwnerController::class, "createPlaceholderImage"]);




    // #################
    // Auth Routes
    // #################

    Route::patch('/owner/updateuser', [OwnerController::class, "updateUser"]);
    Route::patch('/owner/updateuser/by-user', [OwnerController::class, "updateUserByUser"]);

    Route::patch('/owner/profileimage', [OwnerController::class, "updateImage"]);
    Route::get('/owner/role/getrole', [OwnerController::class, "getRole"]);

    Route::post('/auth/checkpin/{id}', [AuthController::class, "checkPin"]);
    Route::get('/auth', [AuthController::class, "getUserWithRestaurent"]);
    Route::get('/auth/users/{perPage}', [AuthController::class, "getUsers"]);

    // #################
    // Restaurent Routes
    // #################



    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // expense type management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    Route::post('/v1.0/surveys', [SurveyController::class, "createSurvey"]);
    Route::put('/v1.0/surveys', [SurveyController::class, "updateSurvey"]);
    Route::get('/v1.0/surveys/{business_id}/{perPage}', [SurveyController::class, "getSurveyes"]);
    Route::get('/v1.0/surveys/{business_id}', [SurveyController::class, "getAllSurveyes"]);
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





    Route::post('/business', [BusinessController::class, "storeRestaurent"]);
    Route::post('/business/by-owner-id', [BusinessController::class, "storeRestaurentByOwnerId"]);
    Route::delete('/business/delete/{id}', [BusinessController::class, "deleteBusinessByRestaurentId"]);

    Route::delete('/business/delete/force-delete/{email}', [BusinessController::class, "deleteBusinessByRestaurentIdForceDelete"]);


    Route::patch('/business/uploadimage/{restaurentId}', [BusinessController::class, "uploadRestaurentImage"]);
    Route::patch('/business/UpdateResturantDetails/{restaurentId}', [BusinessController::class, "UpdateResturantDetails"]);




    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // business Time Management
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    Route::patch('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "updateBusinessDays"]);
    Route::get('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);



    Route::patch('/business/UpdateResturantStripeDetails/{restaurentId}', [StripeController::class, "UpdateResturantStripeDetails"]);
    Route::get('/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetails"]);






    Route::patch('/business/UpdateResturantDetails/byadmin/{restaurentId}', [BusinessController::class, "UpdateResturantDetailsByAdmin"]);

    Route::get('/business', [BusinessController::class, "getAllBusinesses"]);

    Route::get('/businesses/{perPage}', [BusinessController::class, "getBusinesses"]);

    Route::get('/business/RestuarantbyID/{businessId}', [BusinessController::class, "getbusinessById"]);
    Route::get('/business/Restuarant/tables/{businessId}', [BusinessController::class, "getbusinessTableByBusinessId"]);




    // #################
    // dailyviews Routes

    // #################
    Route::post('/dailyviews/{businessId}', [DailyViewsController::class, "store"]);
    Route::patch('/dailyviews/update/{businessId}', [DailyViewsController::class, "update"]);

    // #################
    // forggor password Routes

    // #################

    Route::patch('/auth/changepassword', [ForgotPasswordController::class, "changePassword"]);



    // #################
    // review  Routes
    // #################

    Route::post('/review/reviewvalue/{businessId}/{rate}', [ReviewController::class, "store"]);
    Route::get('/review/getvalues/{businessId}/{rate}', [ReviewController::class, "getReviewValues"]);
    Route::get('/review/getvalues/{businessId}', [ReviewController::class, "getreviewvalueById"]);
    Route::get('/review/getavg/review/{businessId}/{start}/{end}', [ReviewController::class, "getAverage"]);
    Route::post('/review/addupdatetags/{businessId}', [ReviewController::class, "store2"]);

    Route::get('/review/getreview/{businessId}/{rate}/{start}/{end}', [ReviewController::class, "filterReview"]);
    Route::get('/review/getreviewAll/{businessId}', [ReviewController::class, "getReviewByBusinessId"]);
    Route::get('/review/getcustomerreview/{businessId}/{start}/{end}', [ReviewController::class, "getCustommerReview"]);
    Route::post('/review/{businessId}', [ReviewController::class, "storeReview"]);

    // #################
    // review new  Routes
    // #################

    Route::post('/review-new/reviewvalue/{businessId}/{rate}', [ReviewNewController::class, "store"]);
    Route::get('/review-new/getvalues/{businessId}/{rate}', [ReviewNewController::class, "getReviewValues"]);
    Route::get('/review-new/getvalues/{businessId}', [ReviewNewController::class, "getreviewvalueById"]);
    Route::get('/review-new/getavg/review/{businessId}/{start}/{end}', [ReviewNewController::class, "getAverage"]);
    Route::post('/review-new/addupdatetags/{businessId}', [ReviewNewController::class, "store2"]);

    Route::get('/review-new/getreview/{businessId}/{rate}/{start}/{end}', [ReviewNewController::class, "filterReview"]);
    Route::get('/review-new/getreviewAll/{businessId}', [ReviewNewController::class, "getReviewByBusinessId"]);
    Route::get('/review-new/getcustomerreview/{businessId}/{start}/{end}', [ReviewNewController::class, "getCustommerReview"]);
    Route::post('/review-new/{businessId}', [ReviewNewController::class, "storeReview"]);

    // #################
    // question  Routes
    // #################
    Route::post('/review-new/create/questions', [ReviewNewController::class, "storeQuestion"]);
    Route::put('/review-new/update/questions', [ReviewNewController::class, "updateQuestion"]);


    Route::put('/review-new/set-overall-question', [ReviewNewController::class, "setOverallQuestion"]);




    Route::put('/review-new/update/active_state/questions', [ReviewNewController::class, "updateQuestionActiveState"]);

    Route::get('/review-new/get/questions', [ReviewNewController::class, "getQuestion"]);


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

    Route::post('/review-new/create/tags/multiple/{businessId}', [ReviewNewController::class, "storeTagMultiple"]);

    Route::put('/review-new/update/tags', [ReviewNewController::class, "updateTag"]);
    Route::get('/review-new/get/tags', [ReviewNewController::class, "getTag"]);
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






    Route::middleware(['superadmin'])->group(function () {

        Route::patch('/superadmin/auth/changepassword', [ForgotPasswordController::class, "changePasswordBySuperAdmin"]);

        Route::patch('/superadmin/auth/change-email', [ForgotPasswordController::class, "changeEmailBySuperAdmin"]);


        Route::get('/superadmin/dashboard-report/total-business', [SuperAdminReportController::class, "getTotalBusinessReport"]);


        Route::get('/superadmin/dashboard-report/total-business-enabled', [SuperAdminReportController::class, "getTotalEnabledBusinessReport"]);

        Route::get('/superadmin/dashboard-report/total-business-disabled', [SuperAdminReportController::class, "getTotalDisabledBusinessReport"]);






        Route::get('/superadmin/dashboard-report/total-reviews', [SuperAdminReportController::class, "getTotalReviews"]);
        Route::get('/superadmin/dashboard-report/today-reviews', [SuperAdminReportController::class, "getTodayReviews"]);
        Route::get('/superadmin/dashboard-report/review-report', [SuperAdminReportController::class, "getReviewReport"]);
        Route::get('/superadmin/customer-list/{perPage}', [UserController::class, "getCustomerReportSuperadmin"]);
        Route::get('/superadmin/owner-list/{perPage}', [UserController::class, "getOwnerReport"]);
        Route::delete('/superadmin/user-delete/{id}', [UserController::class, "deleteCustomerById"]);
        Route::put('/v1.0/email-template-wrappers', [EmailTemplateWrapperController::class, "updateEmailTemplateWrapper"]);
        Route::get('/v1.0/email-template-wrappers/{perPage}', [EmailTemplateWrapperController::class, "getEmailTemplateWrappers"]);
        Route::get('/v1.0/email-template-wrappers/single/{id}', [EmailTemplateWrapperController::class, "getEmailTemplateWrapperById"]);
        Route::post('/v1.0/email-templates', [EmailTemplateController::class, "createEmailTemplate"]);
        Route::put('/v1.0/email-templates', [EmailTemplateController::class, "updateEmailTemplate"]);
        Route::get('/v1.0/email-template-types', [EmailTemplateController::class, "getEmailTemplateTypes"]);
        Route::delete('/v1.0/email-templates/{ids}', [EmailTemplateController::class, "deleteEmailTemplateById"]);
        Route::get('/v1.0/email-templates/single/{id}', [EmailTemplateController::class, "getEmailTemplateById"]);
        Route::get('/v1.0/email-templates', [EmailTemplateController::class, "getEmailTemplates"]);
    });

    Route::get('/customer-report', [ReportController::class, "customerDashboardReport"]);
    Route::get('/business-report', [ReportController::class, "businessDashboardReport"]);
    Route::get('/v1.0/business-owner-dashboard', [DashboardManagementController::class, "getBusinessOwnerDashboardData"]);
    Route::get('/dashboard-report/get/table-report/{businessId}', [ReportController::class, "getTableReport"]);
    Route::get('/dashboard-report/{businessId}', [ReportController::class, "getDashboardReport"]);
    Route::get('/dashboard-report3', [ReportController::class, "getDashboardReport3"]);
    Route::get('/dashboard-report/business/get', [ReportController::class, "getBusinessReport"]);

    // #################
    // Review Owner Routes
    // #################

    Route::post('/review-new/owner/create/questions', [ReviewNewController::class, "storeOwnerQuestion"]);
    Route::patch('/review-new/owner/update/questions', [ReviewNewController::class, "updateOwnerQuestion"]);
    // #################
    // order Routes
    // #################



});

// #################
// forget password Routes
// #################

Route::post('/forgetpassword', [ForgotPasswordController::class, "storeForgetPassword"]);
Route::patch('/forgetpassword/reset/{token}', [ForgotPasswordController::class, "changePasswordByToken"]);

Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"]);
Route::get('/client/businesses/{perPage}', [BusinessController::class, "getBusinessesClients"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// coupon management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::get('/client/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);
Route::get('/v1.0/client/staffs', [StaffController::class, 'getClientAllStaffs']);   // list



Route::get('/client/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetailsClient"]);
