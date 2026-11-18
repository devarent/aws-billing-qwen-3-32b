<?php
/**
 * Smoke test budget enforcement (butuh Redis).
 * Jalankan di container:
 *   docker exec -e BUDGET_ENFORCEMENT_ENABLED=true -e REDIS_HOST=redis \
 *     qwen-ai-billing-hub php /var/www/html/scripts/budget-smoke.php
 */
require __DIR__ . '/../middleware/BudgetEnforcement.php';

$userId = 'budget-smoke-' . bin2hex(random_bytes(4));
$budget = new BudgetEnforcement();

// Rekam spend melebihi daily cap default (10.0)
$budget->recordSpend($userId, 'groq', 'smoke-test', 1000, 1000, 11.0);

$result = $budget->checkBudget($userId, 1.0);

echo json_encode([
    'user_id' => $userId,
    'allowed' => $result['allowed'],
    'error' => $result['error'],
], JSON_PRETTY_PRINT), PHP_EOL;

exit($result['allowed'] ? 1 : 0);
