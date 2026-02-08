<?php

use App\Http\Controllers\{RuleWizardController, BulkRuleController, AiRuleMetricsController};
use Illuminate\Support\Facades\Route;

// ============================================================================
// AI RULE METRICS & PERFORMANCE - Analytics and verification
// ============================================================================
Route::prefix('/v1.0/ai-rules')->group(function () {
    Route::get('/dashboard-boxes', [AiRuleMetricsController::class, 'getDashboardBoxes']);
    Route::post('/triggers/{triggerId}/verify', [AiRuleMetricsController::class, 'verifyTrigger']);
    Route::post('/validate-conditions', [AiRuleMetricsController::class, 'validateConditions']);
    Route::get('/condition-types', [AiRuleMetricsController::class, 'getConditionTypes']);
    Route::get('/top-performers', [AiRuleMetricsController::class, 'getTopPerformers']);
    Route::get('/needs-attention', [AiRuleMetricsController::class, 'getRulesNeedingAttention']);

    // Parameterized routes (keep specific params before generic ones if needed, but these are fairly specific)
    Route::get('/{ruleId}/metrics', [AiRuleMetricsController::class, 'getRuleMetrics']);
    Route::get('/{ruleId}/trigger-history', [AiRuleMetricsController::class, 'getTriggerHistory']);
});

// ============================================================================
// AI RULES CRUD - Standard create, read, update, delete operations
// ============================================================================
Route::prefix('/v1.0/ai-rules')->group(function () {
    // CRUD Operations
    Route::post('/', [RuleWizardController::class, 'createRule']);       // Create rule
    Route::get('/', [RuleWizardController::class, 'getAllRules']);            // Get all rules

    // Preview - Needs to be before {id}
    Route::post('/preview', [RuleWizardController::class, 'previewRule']);   // Preview rule

    Route::get('/{id}', [RuleWizardController::class, 'getRuleById']);       // Get rule by ID
    Route::put('/{id}', [RuleWizardController::class, 'updateRule']);        // Update rule
    Route::delete('/{id}', [RuleWizardController::class, 'deleteRule']);     // Delete rule
    Route::patch('/{id}/toggle', [RuleWizardController::class, 'toggleEnabled']); // Toggle rule status
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
