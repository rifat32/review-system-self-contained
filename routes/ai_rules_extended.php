<?php

use App\Http\Controllers\{RuleWizardController, BulkRuleController, AiRuleMetricsController};
use Illuminate\Support\Facades\Route;

// ============================================================================
// AI RULE METRICS & PERFORMANCE - Analytics and verification
// ============================================================================
Route::prefix('/v1.0/ai-rules')->group(function () {



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



    Route::get('/{id}', [RuleWizardController::class, 'getRuleById']);       // Get rule by ID
    Route::put('/{id}', [RuleWizardController::class, 'updateRule']);        // Update rule
    Route::delete('/{id}', [RuleWizardController::class, 'deleteRule']);     // Delete rule
    Route::patch('/{id}/toggle', [RuleWizardController::class, 'toggleEnabled']); // Toggle rule status
});
