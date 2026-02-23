<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::whereHas('roles', function($q){ $q->where('name', 'business_owner'); })->first();
if (!$user) { echo "No owner\n"; exit; }

$request = \Illuminate\Http\Request::create('/api/v1.0/question-categories/with-sub-category', 'POST', [
    'title' => 'Test',
    'sub_category' => [
        ['title' => 'Test']
    ]
]);
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(function() use ($user) { return $user; });

$controller = app(\App\Http\Controllers\QuestionCategoryController::class);
try {
    $response = $controller->createQuestionCategoryWithSubCategory($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo $response->getContent() . "\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "ValidationException:\n";
    print_r($e->errors());
} catch (\Exception $e) {
    echo "Exception:\n";
    echo $e->getMessage() . "\n";
}
