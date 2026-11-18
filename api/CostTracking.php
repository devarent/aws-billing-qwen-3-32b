<?php
/**
 * Cost Tracking API
 * Based on FlowAMP single-table design with SK prefixes
 * Row types: INFO, COST#, ALERT#, AGGREGATE#
 */

require_once __DIR__ . '/../middleware/BudgetEnforcement.php';

// Composer autoload hanya wajib jika package AWS SDK sudah di-install (composer install).
// Jika belum tersedia, aplikasi tetap berjalan (cost tracking DynamoDB dinonaktifkan).
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;

class CostTracking {
    private $dynamodb;
    private $tableName;
    private $budgetEnforcement;

    public function __construct($region = 'us-east-1') {
        $this->dynamodb = null;
        if (class_exists(\Aws\DynamoDb\DynamoDbClient::class)) {
            $this->dynamodb = new DynamoDbClient([
                'region'  => $region,
                'version' => 'latest'
            ]);
        }

        $this->tableName = getenv('DYNAMODB_TABLE_NAME') ?: 'ai-billing-hub-billing-registry';
        $this->budgetEnforcement = new BudgetEnforcement();
    }

    /**
     * Record individual API call cost
     * SK format: COST#YYYY-MM-DD#requestId
     */
    public function recordCost($userId, $provider, $model, $inputTokens, $outputTokens, $cost, $requestId = null) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak bisa mencatat spend
                return false;
            }
            $date = date('Y-m-d');
            $requestId = $requestId ?: uniqid('req_');
            $sk = "COST#{$date}#{$requestId}";

            $this->dynamodb->putItem([
                'TableName' => $this->tableName,
                'Item' => [
                    'pk' => ['S' => "USER#{$userId}"],
                    'sk' => ['S' => $sk],
                    'user_id' => ['S' => $userId],
                    'provider' => ['S' => $provider],
                    'model' => ['S' => $model],
                    'input_tokens' => ['N' => (string)$inputTokens],
                    'output_tokens' => ['N' => (string)$outputTokens],
                    'total_tokens' => ['N' => (string)($inputTokens + $outputTokens)],
                    'cost_usd' => ['N' => (string)$cost],
                    'request_id' => ['S' => $requestId],
                    'timestamp' => ['N' => (string)time()],
                    'created_at' => ['S' => date('c')]
                ]
            ]);

            // Also record in budget enforcement (Redis)
            $this->budgetEnforcement->recordSpend($userId, $provider, $model, $inputTokens, $outputTokens, $cost);

            // Check for budget warnings
            $warnings = $this->budgetEnforcement->checkWarnings($userId);
            if (!empty($warnings)) {
                $this->createAlert($userId, $warnings);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Failed to record cost: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create monthly aggregate
     * SK format: AGGREGATE#YYYY-MM
     */
    public function recordMonthlyAggregate($userId, $month = null) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak bisa merekam agregat
                return false;
            }
            $month = $month ?: date('Y-m');
            $sk = "AGGREGATE#{$month}";

            // Query all COST# records for this month
            $result = $this->dynamodb->query([
                'TableName' => $this->tableName,
                'KeyConditionExpression' => 'pk = :pk AND begins_with(sk, :sk_prefix)',
                'ExpressionAttributeValues' => [
                    ':pk' => ['S' => "USER#{$userId}"],
                    ':sk_prefix' => ['S' => "COST#{$month}"]
                ]
            ]);

            $totalCost = 0.0;
            $totalTokens = 0;
            $totalRequests = 0;
            $providerBreakdown = [];

            foreach ($result['Items'] as $item) {
                $cost = (float)$item['cost_usd']['N'];
                $tokens = (int)$item['total_tokens']['N'];
                $provider = $item['provider']['S'];

                $totalCost += $cost;
                $totalTokens += $tokens;
                $totalRequests++;

                if (!isset($providerBreakdown[$provider])) {
                    $providerBreakdown[$provider] = ['cost' => 0.0, 'tokens' => 0, 'requests' => 0];
                }
                $providerBreakdown[$provider]['cost'] += $cost;
                $providerBreakdown[$provider]['tokens'] += $tokens;
                $providerBreakdown[$provider]['requests']++;
            }

            // Update or create aggregate record
            $this->dynamodb->putItem([
                'TableName' => $this->tableName,
                'Item' => [
                    'pk' => ['S' => "USER#{$userId}"],
                    'sk' => ['S' => $sk],
                    'user_id' => ['S' => $userId],
                    'month' => ['S' => $month],
                    'total_cost_usd' => ['N' => (string)$totalCost],
                    'total_tokens' => ['N' => (string)$totalTokens],
                    'total_requests' => ['N' => (string)$totalRequests],
                    'provider_breakdown' => ['S' => json_encode($providerBreakdown)],
                    'updated_at' => ['S' => date('c')]
                ]
            ]);

            return [
                'total_cost' => $totalCost,
                'total_tokens' => $totalTokens,
                'total_requests' => $totalRequests,
                'provider_breakdown' => $providerBreakdown
            ];

        } catch (\Exception $e) {
            error_log("Failed to record monthly aggregate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create budget alert
     * SK format: ALERT#timestamp
     */
    private function createAlert($userId, $warnings) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak bisa menyimpan alert
                return false;
            }
            foreach ($warnings as $warning) {
                $timestamp = time();
                $sk = "ALERT#{$timestamp}";

                $this->dynamodb->putItem([
                    'TableName' => $this->tableName,
                    'Item' => [
                        'pk' => ['S' => "USER#{$userId}"],
                        'sk' => ['S' => $sk],
                        'user_id' => ['S' => $userId],
                        'alert_type' => ['S' => $warning['type']],
                        'message' => ['S' => $warning['message']],
                        'severity' => ['S' => $warning['severity']],
                        'timestamp' => ['N' => (string)$timestamp],
                        'created_at' => ['S' => date('c')],
                        'acknowledged' => ['BOOL' => false]
                    ]
                ]);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Failed to create alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's cost history
     */
    public function getUserCostHistory($userId, $startDate = null, $endDate = null) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak ada riwayat
                return [];
            }
            $startDate = $startDate ?: date('Y-m-01'); // First of current month
            $endDate = $endDate ?: date('Y-m-t'); // Last of current month

            // Query aggregates
            $result = $this->dynamodb->query([
                'TableName' => $this->tableName,
                'KeyConditionExpression' => 'pk = :pk AND sk BETWEEN :start AND :end',
                'ExpressionAttributeValues' => [
                    ':pk' => ['S' => "USER#{$userId}"],
                    ':start' => ['S' => "AGGREGATE#{$startDate}"],
                    ':end' => ['S' => "AGGREGATE#{$endDate}"]
                ]
            ]);

            $history = [];
            foreach ($result['Items'] as $item) {
                $history[] = [
                    'month' => $item['month']['S'],
                    'total_cost' => (float)$item['total_cost_usd']['N'],
                    'total_tokens' => (int)$item['total_tokens']['N'],
                    'total_requests' => (int)$item['total_requests']['N'],
                    'provider_breakdown' => json_decode($item['provider_breakdown']['S'], true)
                ];
            }

            return $history;

        } catch (\Exception $e) {
            error_log("Failed to get cost history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's pending alerts
     */
    public function getUserAlerts($userId, $acknowledged = false) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak ada alert
                return [];
            }
            $result = $this->dynamodb->query([
                'TableName' => $this->tableName,
                'KeyConditionExpression' => 'pk = :pk AND begins_with(sk, :sk_prefix)',
                'FilterExpression' => 'acknowledged = :ack',
                'ExpressionAttributeValues' => [
                    ':pk' => ['S' => "USER#{$userId}"],
                    ':sk_prefix' => ['S' => 'ALERT#'],
                    ':ack' => ['BOOL' => $acknowledged]
                ]
            ]);

            $alerts = [];
            foreach ($result['Items'] as $item) {
                $alerts[] = [
                    'alert_type' => $item['alert_type']['S'],
                    'message' => $item['message']['S'],
                    'severity' => $item['severity']['S'],
                    'timestamp' => (int)$item['timestamp']['N'],
                    'acknowledged' => $item['acknowledged']['BOOL']
                ];
            }

            return $alerts;

        } catch (\Exception $e) {
            error_log("Failed to get alerts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert($userId, $alertTimestamp) {
        try {
            if ($this->dynamodb === null) {
                // AWS SDK / DynamoDB tidak tersedia -> tidak bisa mengakui alert
                return false;
            }
            $sk = "ALERT#{$alertTimestamp}";

            $this->dynamodb->updateItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => "USER#{$userId}"],
                    'sk' => ['S' => $sk]
                ],
                'UpdateExpression' => 'SET acknowledged = :ack, acknowledged_at = :time',
                'ExpressionAttributeValues' => [
                    ':ack' => ['BOOL' => true],
                    ':time' => ['S' => date('c')]
                ]
            ]);

            return true;

        } catch (\Exception $e) {
            error_log("Failed to acknowledge alert: " . $e->getMessage());
            return false;
        }
    }
}
