<?php
/**
 * Additional API Endpoints for Cost Tracking and Alerts
 * Based on FlowAMP and Governed Inference Solution patterns
 */

// Action: budget_check - Check if request is within budget
if ($action === 'budget_check') {
    $userId = $_POST['user_id'] ?? 'anonymous';
    $estimatedCost = (float)($_POST['estimated_cost'] ?? 0.0);

    $result = $budgetEnforcement->checkBudget($userId, $estimatedCost);

    echo json_encode([
        'success' => true,
        'allowed' => $result['allowed'],
        'error' => $result['error'],
        'details' => $result
    ]);
    exit;
}

// Action: record_spend - Record actual spend after API call
if ($action === 'record_spend') {
    $userId = $_POST['user_id'] ?? 'anonymous';
    $provider = $_POST['provider'] ?? 'unknown';
    $model = $_POST['model'] ?? 'unknown';
    $inputTokens = (int)($_POST['input_tokens'] ?? 0);
    $outputTokens = (int)($_POST['output_tokens'] ?? 0);
    $actualCost = (float)($_POST['cost'] ?? 0.0);

    $result = $costTracking->recordCost($userId, $provider, $model, $inputTokens, $outputTokens, $actualCost);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Spend recorded successfully' : 'Failed to record spend'
    ]);
    exit;
}

// Action: budget_utilization - Get current budget utilization
if ($action === 'budget_utilization') {
    $userId = $_GET['user_id'] ?? 'anonymous';

    $utilization = $budgetEnforcement->getBudgetUtilization($userId);

    echo json_encode([
        'success' => true,
        'data' => $utilization
    ]);
    exit;
}

// Action: cost_history - Get user's cost history
if ($action === 'cost_history') {
    $userId = $_GET['user_id'] ?? 'anonymous';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    $history = $costTracking->getUserCostHistory($userId, $startDate, $endDate);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
    exit;
}

// Action: alerts - Get user's pending alerts
if ($action === 'alerts') {
    $userId = $_GET['user_id'] ?? 'anonymous';
    $acknowledged = isset($_GET['acknowledged']) ? filter_var($_GET['acknowledged'], FILTER_VALIDATE_BOOLEAN) : false;

    $alerts = $costTracking->getUserAlerts($userId, $acknowledged);

    echo json_encode([
        'success' => true,
        'data' => $alerts
    ]);
    exit;
}

// Action: acknowledge_alert - Acknowledge an alert
if ($action === 'acknowledge_alert') {
    $userId = $_POST['user_id'] ?? 'anonymous';
    $alertTimestamp = $_POST['alert_timestamp'] ?? 0;

    $result = $costTracking->acknowledgeAlert($userId, $alertTimestamp);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Alert acknowledged' : 'Failed to acknowledge alert'
    ]);
    exit;
}

// Action: monthly_aggregate - Generate monthly aggregate
if ($action === 'monthly_aggregate') {
    $userId = $_POST['user_id'] ?? 'anonymous';
    $month = $_POST['month'] ?? null;

    $result = $costTracking->recordMonthlyAggregate($userId, $month);

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit;
}

// Action: generate_token - Generate JWT token for user
if ($action === 'generate_token') {
    $userId = $_POST['user_id'] ?? 'anonymous';
    $email = $_POST['email'] ?? 'user@example.com';
    $teams = isset($_POST['teams']) ? json_decode($_POST['teams'], true) : [];

    $token = $identityPropagation->generateToken($userId, $email, $teams);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_in' => 3600
    ]);
    exit;
}

// Action: validate_token - Validate JWT token
if ($action === 'validate_token') {
    $token = $_POST['token'] ?? '';

    $identity = $identityPropagation->validateToken($token);

    echo json_encode([
        'success' => $identity !== null,
        'identity' => $identity
    ]);
    exit;
}

// Action: provider_headers - Get provider-specific headers with identity
if ($action === 'provider_headers') {
    $userId = $_GET['user_id'] ?? 'anonymous';
    $email = $_GET['email'] ?? 'user@example.com';
    $provider = $_GET['provider'] ?? 'groq';

    $headers = $identityPropagation->getProviderHeaders($userId, $email, $provider);

    echo json_encode([
        'success' => true,
        'headers' => $headers
    ]);
    exit;
}
