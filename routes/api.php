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
use App\Http\Controllers\ReviewNewController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SuperAdminReportController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
*/

// ============================================================================
// AuthController – Public auth
// ============================================================================
Route::post('/resend-email-verify-mail', [AuthController::class, "resendEmailVerifyByToken"]);
Route::post('/auth', [AuthController::class, "userLogin"]);
Route::post('/auth/register', [AuthController::class, "userRegister"]);

// ============================================================================
// ForgotPasswordController – Public forgot/reset
// ============================================================================
Route::post('/v1.0/forgot-password', [ForgotPasswordController::class, "storeForgetPassword"]);
Route::patch('/v1.0/forget-password/reset/{token}', [ForgotPasswordController::class, "changePasswordByToken"]);

// ============================================================================
// CustomWebhookController – Webhooks
// ============================================================================
Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"]);

// ============================================================================
// OwnerController – Public owner registration helpers
// ============================================================================
Route::post('/owner/user/registration', [OwnerController::class, "createUser2"]);

// ============================================================================
// AuthController – Auxiliary public auth helpers
// ============================================================================
Route::post('/v1.0/auth/check-user-email', [AuthController::class, "checkUserEmail"]);

// ============================================================================
// Protected Routes (auth:api)
// ============================================================================
Route::middleware(['auth:api'])->group(function () {

    // =====================================================================================
    // SUPER ADMIN ROUTES (no "superadmin" in the URL; all under /v1.0; access via middleware)
    // Controllers: ForgotPasswordController, SuperAdminReportController, UserController,
    //              EmailTemplateWrapperController, EmailTemplateController
    // =====================================================================================
    Route::middleware(['superadmin'])->group(function () {

        // -------------------------------------------------------------------------
        // SuperAdminReportController – Dashboard Reports (Super Admin)
        // -------------------------------------------------------------------------
        Route::get('/v1.0/dashboard-report/total-business', [SuperAdminReportController::class, 'getTotalBusinessReport']);
        Route::get('/v1.0/dashboard-report/total-business-enabled', [SuperAdminReportController::class, 'getTotalEnabledBusinessReport']);
        Route::get('/v1.0/dashboard-report/total-business-disabled', [SuperAdminReportController::class, 'getTotalDisabledBusinessReport']);
        Route::get('/v1.0/dashboard-report/total-reviews', [SuperAdminReportController::class, 'getTotalReviews']);
        Route::get('/v1.0/dashboard-report/today-reviews', [SuperAdminReportController::class, 'getTodayReviews']);
        Route::get('/v1.0/dashboard-report/review-report', [SuperAdminReportController::class, 'getReviewReport']);

        // -------------------------------------------------------------------------
        // UserController – Admin user reporting & destructive ops
        // -------------------------------------------------------------------------
        Route::get('/v1.0/customer-list', [UserController::class, 'getCustomerReportSuperadmin']);
        Route::get('/v1.0/owner-list', [UserController::class, 'getOwnerReport']);
        Route::delete('/v1.0/users/{id}', [UserController::class, 'deleteCustomerById']);

        // -------------------------------------------------------------------------
        // EmailTemplateWrapperController – Wrapper management
        // -------------------------------------------------------------------------
        Route::prefix('/v1.0/email-template-wrappers')->group(function () {
            Route::put('/{id}', [EmailTemplateWrapperController::class, 'updateEmailTemplateWrapper']);
            Route::get('/{id}', [EmailTemplateWrapperController::class, 'getEmailTemplateWrapperById']);
            Route::get('/', [EmailTemplateWrapperController::class, 'getEmailTemplateWrappers']);
        });

        // -------------------------------------------------------------------------
        // EmailTemplateController – Email template management & types
        // -------------------------------------------------------------------------
        Route::prefix('/v1.0/email-templates')->group(function () {
            Route::post('/', [EmailTemplateController::class, 'createEmailTemplate']);
            Route::put('/{id}', [EmailTemplateController::class, 'updateEmailTemplate']);
            Route::get('/', [EmailTemplateController::class, 'getEmailTemplates']);
            Route::get('/{id}', [EmailTemplateController::class, 'getEmailTemplateById']);
            Route::delete('/{ids}', [EmailTemplateController::class, 'deleteEmailTemplateById']);
        });
        Route::get('/v1.0/email-template-types', [EmailTemplateController::class, 'getEmailTemplateTypes']);
    });

    // ============================================================================
    // OwnerController – Owner + business creation (protected)
    // ============================================================================
    Route::post('/v1.0/create-user-with-business', [OwnerController::class, "createUserWithBusiness"]);
    Route::patch('/v1.0/owner/update', [OwnerController::class, "updateUserByUser"]);

    // ============================================================================
    // ReviewNewController – Create review (protected)
    // ============================================================================
    Route::post('/v1.0/review-new/{businessId}', [ReviewNewController::class, "createReview"]);

    // ============================================================================
    // ReportController – v3 dashboard report (protected)
    // ============================================================================
    Route::get('/v3.0/dashboard-report', [ReportController::class, "getDashboardReportV3"]);

    // ============================================================================
    // QuestionController – CRUD & ordering (protected)
    // ============================================================================
    Route::get('/v1.0/questions', [QuestionController::class, 'getAllQuestions']);
    Route::get('/v1.0/questions/{id}', [QuestionController::class, 'questionById']);
    Route::post('/v1.0/questions', [QuestionController::class, 'createQuestion']);
    Route::delete('/v1.0/questions/{ids}', [QuestionController::class, 'deleteQuestion']);
    Route::patch('/v1.0/questions/ordering', [QuestionController::class, 'displayQuestionOrder']);
    Route::patch('/v1.0/questions/set-overall', [QuestionController::class, 'setOverallQuestions']);
    Route::patch('/v1.0/questions/toggle', [QuestionController::class, 'toggleQuestionActivation']);
    Route::patch('/v1.0/questions/{id}', [QuestionController::class, 'updatedQuestion'])->whereNumber('id');

    // ============================================================================
    // LeafletController – CRUD (protected)
    // ============================================================================
    Route::post('/v1.0/leaflet/create', [LeafletController::class, 'insertLeaflet']);
    Route::put('/v1.0/leaflet/update/{id}', [LeafletController::class, 'editLeaflet']);
    Route::get('/v1.0/leaflet/get', [LeafletController::class, 'getAllLeaflet']);
    Route::get('/v1.0/leaflet/get/{id}', [LeafletController::class, 'leafletById']);
    Route::delete('/v1.0/leaflet/{ids}', [LeafletController::class, 'leafletDeleteById']);
    Route::post('/v1.0/leaflet-image', [LeafletController::class, 'insertLeafletImage']);

    // ============================================================================
    // StaffController – CRUD + image upload (protected)
    // ============================================================================
    Route::get('/v1.0/staffs', [StaffController::class, 'getAllStaffs']);
    Route::get('/v1.0/staffs/{id}', [StaffController::class, 'getStaffById']);
    Route::post('/v1.0/staffs', [StaffController::class, 'createStaff']);
    Route::patch('/v1.0/staffs/{id}', [StaffController::class, 'updateStaff']);
    Route::delete('/v1.0/staffs/{id}', [StaffController::class, 'deleteStaff']);
    Route::post('/v1.0/staff-image', [StaffController::class, 'uploadStaffImage']);

    // ============================================================================
    // TagController – Tags CRUD (protected)
    // ============================================================================
    Route::post('/v1.0/tags', [TagController::class, 'createTag']);
    Route::get('/v1.0/tags', [TagController::class, 'getAllTags']);
    Route::get('/v1.0/tags/{id}', [TagController::class, 'getTagById']);
    Route::patch('/v1.0/tags/multiple/{businessId}', [TagController::class, 'createMultipleTags']);
    Route::patch('/v1.0/tags/{id}', [TagController::class, 'updateTag']);
    Route::delete('/v1.0/tags/{ids}', [TagController::class, 'deleteTag']);

    // ============================================================================
    // NotificationController – Notifications CRUD (protected)
    // ============================================================================
    Route::post('/v1.0/notification', [NotificationController::class, "createNotification"]);
    Route::patch('/v1.0/notification/{notificationId}', [NotificationController::class, "updateNotification"]);
    Route::get('/v1.0/notification', [NotificationController::class, "getNotification"]);
    Route::delete('/v1.0/notification/{notificationId}', [NotificationController::class, "deleteNotification"]);

    // ============================================================================
    // AuthController – Authenticated helpers
    // ============================================================================
    Route::get('/v1.0/user', [AuthController::class, "getAllUser"]);
    Route::patch('/v1.0/upload/profile-image', [OwnerController::class, "updateImage"]);
    Route::post('/auth/check-pin/{id}', [AuthController::class, "verifyPin"]);
    Route::get('/auth', [AuthController::class, "getUsersWithRestaurants"]);
    Route::get('/auth/users', [AuthController::class, "getAllUsers"]);

    // ============================================================================
    // SurveyController – CRUD & ordering/toggle (protected)
    // ============================================================================
    Route::post('/v1.0/surveys', [SurveyController::class, "createSurvey"]);
    Route::put('/v1.0/surveys', [SurveyController::class, "updateSurvey"]);
    Route::post('/v1.0/surveys/ordering', [SurveyController::class, "orderSurveys"]);
    Route::get('/v1.0/surveys/{business_id}/{perPage}', [SurveyController::class, "getSurveys"]);
    Route::get('/v1.0/surveys/{business_id}', [SurveyController::class, "getAllSurveys"]);
    Route::patch('/v1.0/surveys/{id}/toggle-active', [SurveyController::class, "toggleSurveyActive"]);
    Route::delete('/v1.0/surveys/{id}', [SurveyController::class, "deleteSurveyById"]);

    // ============================================================================
    // RolesController – Roles/permissions (protected)
    // ============================================================================
    Route::get('/v1.0/initial-role-permissions', [RolesController::class, "getInitialRolePermissions"]);
    Route::get('/v1.0/initial-permissions', [RolesController::class, "getInitialPermissions"]);
    Route::post('/v1.0/roles', [RolesController::class, "createRole"]);
    Route::put('/v1.0/roles', [RolesController::class, "updateRole"]);
    Route::get('/v1.0/roles', [RolesController::class, "getRoles"]);
    Route::get('/v1.0/roles/{id}', [RolesController::class, "getRoleById"]);
    Route::delete('/v1.0/roles/{ids}', [RolesController::class, "deleteRolesByIds"]);

    // ============================================================================
    // BusinessController – Business CRUD & tables (protected)
    // ============================================================================
    Route::post('/v1.0/business', [BusinessController::class, "storeRestaurant"]);
    Route::post('/v1.0/business/by-owner-id', [BusinessController::class, "storeRestaurantByOwnerId"]);
    Route::get('/v1.0/business', [BusinessController::class, "getAllBusinesses"]);
    Route::patch('/v1.0/business/{businessId}', [BusinessController::class, "UpdateBusiness"]);
    Route::delete('/v1.0/business/{id}', [BusinessController::class, "deleteBusinessById"]);
    Route::get('/v1.0/restaurants/tables/{businessId}', [BusinessController::class, "getRestaurantTableByBusinessId"]);
    Route::delete('/v1.0/business/delete/force-delete/{email}', [BusinessController::class, "deleteBusinessByRestaurantIdForceDelete"]);
    Route::patch('/v1.0/business/upload-image/{businessId}', [BusinessController::class, "uploadRestaurantImage"]);

    // ============================================================================
    // BranchController – Branch CRUD (protected, /v1.0/branches)
    // ============================================================================
    Route::prefix('v1.0/branches')->group(function () {
        Route::get('/', [BranchController::class, 'getBranches']);
        Route::post('/', [BranchController::class, 'createBranch']);
        Route::get('/{id}', [BranchController::class, 'getBranchById']);
        Route::patch('/{id}', [BranchController::class, 'updateBranch']);
        Route::patch('/{id}/toggle-active', [BranchController::class, 'toggleBranchActive']);
        Route::delete('/{id}', [BranchController::class, 'deleteBranches']);
    });

    // ============================================================================
    // BusinessDaysController – Business hours/days (protected)
    // ============================================================================
    Route::patch('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "updateBusinessDays"]);
    Route::get('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);

    // ============================================================================
    // StripeController – Restaurant Stripe details (protected)
    // ============================================================================
    Route::patch('/business/UpdateResturantStripeDetails/{restaurentId}', [StripeController::class, "UpdateResturantStripeDetails"]);
    Route::get('/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetails"]);

    // ============================================================================
    // DailyViewsController – Daily views (protected)
    // ============================================================================
    Route::post('/v1.0/daily-views/{businessId}', [DailyViewsController::class, "createDailyView"]);
    Route::patch('/v1.0/daily-views/update/{businessId}', [DailyViewsController::class, "updateDailyView"]);

    // ============================================================================
    // ForgotPasswordController – Authenticated password change (protected)
    // ============================================================================
    Route::patch('/v1.0/auth/change-password', [ForgotPasswordController::class, "changePassword"]);

    // ============================================================================
    // ReviewNewController – Values/filters/tags/stars/reporting (protected)
    // ============================================================================
    Route::get('/v1.0/review-new/values/{businessId}/{rate}', [ReviewNewController::class, "getReviewValue"]);
    Route::post('/review-new/reviewvalue/{businessId}/{rate}', [ReviewNewController::class, "store"]);
    Route::get('/review-new/getvalues/{businessId}', [ReviewNewController::class, "getreviewvalueById"]);
    Route::get('/review-new/getavg/review/{businessId}/{start}/{end}', [ReviewNewController::class, "getAverages"]);
    Route::post('/review-new/addupdatetags/{businessId}', [ReviewNewController::class, "store2"]);
    Route::get('/review-new/getreview/{businessId}/{rate}/{start}/{end}', [ReviewNewController::class, "filterReviews"]);
    Route::get('/review-new/getreviewAll/{businessId}', [ReviewNewController::class, "reviewByBusinessId"]);
    Route::get('/review-new/getcustomerreview/{businessId}/{start}/{end}', [ReviewNewController::class, "getCustommerReview"]);

    // Questions (protected)
    // Route::post('/review-new/create/questions', [QuestionController::class, "storeQuestion"]);
    // Route::put('/review-new/update/questions', [QuestionController::class, "updateQuestion"]);
    // Route::put('/v1.0/review-new/set-overall-question', [QuestionController::class, "setOverallQuestion"]);
    // Route::put('/review-new/update/active_state/questions', [QuestionController::class, "updateQuestionActiveState"]);
    // Route::get('/v1.0/review-new/get/questions', [QuestionController::class, "getQuestion"]);
    // Route::get('/review-new/get/questions-all', [ReviewNewController::class, "getQuestionAll"]);
    // Route::get('/review-new/get/questions-all-report', [ReviewNewController::class, "getQuestionAllReport"]);
    // Route::get('/review-new/get/questions-all-report/guest', [ReviewNewController::class, "getQuestionAllReportGuest"]);
    // Route::get('/review-new/get/questions-all-report-by-user/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUser"]);
    // Route::get('/review-new/get/questions-all-report-by-user-guest/{perPage}', [ReviewNewController::class, "getQuestionAllReportByUserGuest"]);
    // Route::get('/review-new/get/questions/{id}', [ReviewNewController::class, "getQuestionById"]);
    // Route::get('/review-new/get/questions/{id}/{businessId}', [ReviewNewController::class, "getQuestionById2"]);
    // Route::delete('/review-new/delete/questions/{id}', [ReviewNewController::class, "deleteQuestionById"]);

    // Tags (protected)
    // Route::post('/review-new/create/tags', [ReviewNewController::class, "storeTag"]);
    // Route::post('/v1.0/review-new/create/tags/multiple/{businessId}', [ReviewNewController::class, "storeTagMultiple"]);
    // Route::put('/review-new/update/tags', [ReviewNewController::class, "updatedTag"]);
    // Route::get('/review-new/get/tags', [ReviewNewController::class, "getTag"]);
    // Route::get('/review-new/get/tags/{id}', [ReviewNewController::class, "getTagById"]);
    // Route::get('/review-new/get/tags/{id}/{restaurantId}', [ReviewNewController::class, "getTagById2"]);
    // Route::delete('/review-new/delete/tags/{id}', [ReviewNewController::class, "deleteTagById"]);

    // Stars (protected)
    Route::post('/review-new/create/stars', [QuestionController::class, "storeStar"]);
    Route::put('/review-new/update/stars', [QuestionController::class, "updateStar"]);
    Route::get('/review-new/get/stars', [QuestionController::class, "getStar"]);
    Route::get('/review-new/get/stars/{id}', [QuestionController::class, "getStarById"]);
    Route::get('/review-new/get/stars/{id}/{businessId}', [ReviewNewController::class, "getStarById2"]);
    Route::delete('/review-new/delete/stars/{id}', [QuestionController::class, "deleteStarById"]);

    // Quantum reports (protected)
    Route::get('/review-new/get/questions-all-report/quantum', [ReviewNewController::class, "getQuestionAllReportQuantum"]);
    Route::get('/review-new/get/questions-all-report/guest/quantum', [ReviewNewController::class, "getQuestionAllReportGuestQuantum"]);

    // Star-tag-question (protected)
    Route::post('/star-tag-question', [QuestionController::class, "storeStarTag"]);
    Route::put('/star-tag-question', [QuestionController::class, "updateStarTag"]);
    Route::get('/star-tag-question', [QuestionController::class, "getStarTag"]);
    Route::get('/star-tag-question/{id}', [QuestionController::class, "getStarTagById"]);
    Route::delete('/star-tag-question/{id}', [QuestionController::class, "deleteStarTagById"]);
    Route::get('/tag-count/star-tag-question/{businessId}', [QuestionController::class, "getSelectedTagCount"]);
    Route::get('/tag-count/star-tag-question/by-question/{questionId}', [QuestionController::class, "getSelectedTagCountByQuestion"]);

    // ============================================================================
    // CustomerController – Customers (protected)
    // ============================================================================
    Route::get('/v1.0/customers', [CustomerController::class, "getCustomers"]);

    // ============================================================================
    // ReportController & DashboardManagementController – Reports/dashboards (protected)
    // ============================================================================

    Route::get('/v1.0/business-owner-dashboard', [DashboardManagementController::class, "getBusinessOwnerDashboardData"]);
    Route::get('/dashboard-report/get/table-report/{businessId}', [ReportController::class, "getTableReport"]);
    Route::get('/v1.0/dashboard-report/{businessId}', [ReportController::class, "getDashboardReport"]);
    Route::get('/v1.0/dashboard-report/business/get', [ReportController::class, "getBusinessReport"]);
    Route::get('/v1.0/reports/staff-comparison/{businessId}', [ReportController::class, 'staffComparison']);
    Route::get('/v1.0/reports/staff-performance/{businessId}/{staffId}', [ReportController::class, 'staffPerformance']);
    Route::get('/v1.0/reports/staff-dashboard/{businessId}', [ReportController::class, 'staffDashboard']);
    Route::get('/v1.0/reports/review-analytics/{businessId}', [ReportController::class, 'reviewAnalytics']);
    Route::get('/v1.0/branch-dashboard/{branchId}', [ReportController::class, 'getBranchDashboard']);
    Route::get('/v1.0/reports/branch-comparison', [ReportController::class, 'branchComparison']);

    // ============================================================================
    // ReviewNewController – Owner Questions (protected)
    // ============================================================================
    Route::post('/review-new/owner/create/questions', [QuestionController::class, "storeOwnerQuestion"]);
    Route::patch('/review-new/owner/update/questions', [QuestionController::class, "updateOwnerQuestion"]);

    // ============================================================================
    // ReviewNewController – Review by id (protected)
    // ============================================================================
    Route::get('/v1.0/review-new/{reviewId}', [ReviewNewController::class, "getReviewById"]);
});

// ============================================================================
// OwnerController – Public owner routes
// ============================================================================
Route::post('/owner', [OwnerController::class, "createUser"]);
Route::post('/v1.0/register-guest-users', [OwnerController::class, "createGuestUser"]);
Route::post('/owner/staffregister/{businessId}', [OwnerController::class, "createStaffUser"]);
Route::post('/owner/pin/{ownerId}', [OwnerController::class, "updatePin"]);
Route::get('/owner/{ownerId}', [OwnerController::class, "getOwnerById"]);
Route::get('/owner/getAllowner/withourbusiness', [OwnerController::class, "getOwnerNotHaveRestaurent"]);
Route::get('/owner/loaduser/bynumber/{phoneNumber}', [OwnerController::class, "getOwnerByPhoneNumber"]);

// ============================================================================
// BusinessController – Public business read
// ============================================================================
Route::get('/v1.0/business/{businessId}', [BusinessController::class, "getBusinessById"]);

// ============================================================================
// ReviewNewController – Public/unauthorized question sets
// ============================================================================
Route::get('/review-new/get/questions-all/customer', [ReviewNewController::class, "getQuestionAllUnauthorized"]);
Route::get('/review-new/get/questions-all-overall/customer', [ReviewNewController::class, "getQuestionAllUnauthorizedOverall"]);
Route::get('/review-new/get/questions-all-report/unauthorized', [ReviewNewController::class, "getQuestionAllReportUnauthorized"]);
Route::get('/review-new/get/questions-all-report/guest/unauthorized', [ReviewNewController::class, "getQuestionAllReportGuestUnauthorized"]);

// ============================================================================
// ReviewNewController – Public guest review operations
// ============================================================================
Route::post('/v1.0/review-new-guest/{businessId}', [ReviewNewController::class, "storeReviewByGuest"]);
Route::post('/v1.0/reviews/overall/ordering', [ReviewNewController::class, "orderOverallReviews"]);
Route::post('/v1.0/voice/transcribe', [ReviewNewController::class, "transcribeVoice"]);

// ============================================================================
// BusinessController – Client public endpoints
// ============================================================================
Route::get('/v1.0/client/businesses', [BusinessController::class, "getBusinessesClients"]);
Route::post('/v1.0/client/create-user-with-business', [OwnerController::class, "createUserWithBusinessClient"]);

// ============================================================================
// BusinessDaysController & StaffController – Client public endpoints
// ============================================================================
Route::get('/client/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);
Route::get('/v1.0/client/staffs', [StaffController::class, 'getClientAllStaffs']);
Route::get('/client/business/getResturantStripeDetails/{id}', [StripeController::class, "GetResturantStripeDetailsClient"]);

// ============================================================================
// ReviewNewController – Overall business dashboard (public)
// ============================================================================
Route::get('/v1.0/reviews/overall-dashboard/{businessId}', [ReviewNewController::class, 'getOverallDashboardData']);
Route::put('/v1.0/businesses/{businessId}/review-settings', [ReviewNewController::class, 'updatedReviewSettings']);

// ============================================================================
// ReviewNewController – Client review analytics & privacy (public client)
// ============================================================================
Route::get('/v1.0/client/review-new/rating-analysis/{businessId}', [ReviewNewController::class, "getAverageRatingClient"]);
Route::get('/v1.0/client/review-new/{businessId}', [ReviewNewController::class, "getReviewByBusinessIdClient"]);
Route::put('/v1.0/client/reviews/make-private/{ids}', [ReviewNewController::class, 'makeReviewsPrivate']);
Route::put('/v1.0/client/reviews/update-guest-email/{ids}', [ReviewNewController::class, 'updateGuestEmailsByReviews']);

// ============================================================================
// QuestionController – Client business questions (public client)
// ============================================================================
Route::get('/v1.0/client/questions/{business_id}', [QuestionController::class, 'getAllQuestionClient']);
