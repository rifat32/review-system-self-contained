<?php

use App\Http\Controllers\{RuleWizardController, BulkRuleController, AiRuleMetricsController};
use Illuminate\Support\Facades\Route;

// ============================================================================
// AI RULE WIZARD ROUTES - Guided 4-step rule creation
// ============================================================================
Route::prefix('/v1.0/rule-wizard')->group(function () {
    Route::post('/step1', [RuleWizardController::class, 'storeStep1']);
    Route::post('/step2', [RuleWizardController::class, 'storeStep2']);
    Route::post('/step3', [RuleWizardController::class, 'storeStep3']);
    Route::post('/step4/preview', [RuleWizardController::class, 'previewRule']);
    Route::post('/step4/activate', [RuleWizardController::class, 'activateRule']);
    Route::get('/session', [RuleWizardController::class, 'getWizardSession']);
    Route::delete('/session', [RuleWizardController::class, 'clearWizardSession']);
});

// ============================================================================
// BULK RULE OPERATIONS - Cross-branch rule management
// ============================================================================
Route::prefix('/v1.0/ai-rules')->group(function () {
    Route::post('/bulk-apply', [BulkRuleController::class, 'bulkApplyRule']);
    Route::get('/cross-branch', [BulkRuleController::class, 'getCrossBranchRules']);
    Route::post('/bulk-enable', [BulkRuleController::class, 'bulkEnableRules']);
    Route::post('/bulk-disable', [BulkRuleController::class, 'bulkDisableRules']);
    Route::post('/bulk-delete', [BulkRuleController::class, 'bulkDeleteRules']);
    Route::post('/bulk-priority', [BulkRuleController::class, 'bulkUpdatePriority']);
});

// ============================================================================
// AI RULE METRICS & PERFORMANCE - Analytics and verification
// ============================================================================
Route::prefix('/v1.0/ai-rules')->group(function () {
    Route::get('/{ruleId}/metrics', [AiRuleMetricsController::class, 'getRuleMetrics']);
    Route::get('/{ruleId}/trigger-history', [AiRuleMetricsController::class, 'getTriggerHistory']);
    Route::post('/triggers/{triggerId}/verify', [AiRuleMetricsController::class, 'verifyTrigger']);
    Route::post('/validate-conditions', [AiRuleMetricsController::class, 'validateConditions']);
    Route::get('/condition-types', [AiRuleMetricsController::class, 'getConditionTypes']);
    Route::get('/top-performers', [AiRuleMetricsController::class, 'getTopPerformers']);
    Route::get('/needs-attention', [AiRuleMetricsController::class, 'getRulesNeedingAttention']);
});
