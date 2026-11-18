<?php
/**
 * Budget Enforcement Middleware
 * Based on AWS sample-agentcore-gateway-usage-interceptor pattern
 * Enforces budget limits BEFORE API calls are made
 */

class BudgetEnforcement {
    private $redis;
    private $enforcementEnabled;
    private $defaultDailyCap;
    private $defaultMonthlyCap;

    public function __construct($redisConfig = null) {
        // Redis for fast budget lookups (<100ms)
        // Redis client hanya bisa dipakai jika ekstensi PHP Redis (phpredis) sudah terinstall.
        // Jika belum tersedia, anggap budget enforcement dinonaktifkan (fail-open).
        $this->redis = null;
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $host = $redisConfig['host'] ?? (getenv('REDIS_HOST') ?: '127.0.0.1');
                $port = (int)($redisConfig['port'] ?? (getenv('REDIS_PORT') ?: 6379));
                $timeout = (float)(getenv('REDIS_TIMEOUT') ?: 1.5);

                if ($redis->connect($host, $port, $timeout)) {
                    $this->redis = $redis;
                }
            } catch (\Throwable $e) {
                // Redis tidak tersedia -> fail-open, jangan sampai aplikasi crash.
                error_log('Redis unavailable: ' . $e->getMessage());
                $this->redis = null;
            }
        }

        $this->enforcementEnabled = getenv('BUDGET_ENFORCEMENT_ENABLED') === 'true';
        $this->defaultDailyCap = (float)(getenv('DEFAULT_DAILY_CAP') ?: 10.0);
        $this->defaultMonthlyCap = (float)(getenv('DEFAULT_MONTHLY_CAP') ?: 100.0);
    }

    /**
     * Check if user's budget allows the request
     * Returns: array ['allowed' => bool, 'error' => string|null]
     */
    public function checkBudget($userId, $estimatedCost = 0.0) {
        if (!$this->enforcementEnabled || $this->redis === null) {
            // Enforcement aktif tapi Redis belum tersedia -> fail open (izinkan request)
            return ['allowed' => true, 'error' => null];
        }

        try {
            // Get user's budget policy
            $policy = $this->getUserPolicy($userId);

            // Get current spend state
            $spend = $this->getUserSpend($userId);

            // Check daily cap
            if ($spend['daily_spend'] + $estimatedCost > $policy['daily_cap']) {
                return [
                    'allowed' => false,
                    'error' => 'DAILY_BUDGET_EXCEEDED',
                    'daily_spend' => $spend['daily_spend'],
                    'daily_cap' => $policy['daily_cap']
                ];
            }

            // Check monthly cap
            if ($spend['monthly_spend'] + $estimatedCost > $policy['monthly_cap']) {
                return [
                    'allowed' => false,
                    'error' => 'MONTHLY_BUDGET_EXCEEDED',
                    'monthly_spend' => $spend['monthly_spend'],
                    'monthly_cap' => $policy['monthly_cap']
                ];
            }

            return ['allowed' => true, 'error' => null];

        } catch (Exception $e) {
            // Fail open if budget check fails (log error)
            error_log("Budget check failed for user $userId: " . $e->getMessage());
            return ['allowed' => true, 'error' => null];
        }
    }

    /**
     * Record actual spend after API call
     */
    public function recordSpend($userId, $provider, $model, $inputTokens, $outputTokens, $actualCost) {
        try {
            if ($this->redis === null) {
                // Redis belum tersedia -> tidak bisa mencatat spend
                return true;
            }
            $today = date('Y-m-d');
            $month = date('Y-m');

            // Update daily spend
            $dailyKey = "spend:daily:{$userId}:{$today}";
            $this->redis->hIncrByFloat($dailyKey, 'total', $actualCost);
            $this->redis->hIncrBy($dailyKey, 'input_tokens', $inputTokens);
            $this->redis->hIncrBy($dailyKey, 'output_tokens', $outputTokens);
            $this->redis->hSet($dailyKey, 'last_updated', time());
            $this->redis->expire($dailyKey, 86400 * 7); // Keep 7 days

            // Update monthly spend
            $monthlyKey = "spend:monthly:{$userId}:{$month}";
            $this->redis->hIncrByFloat($monthlyKey, 'total', $actualCost);
            $this->redis->hIncrBy($monthlyKey, 'input_tokens', $inputTokens);
            $this->redis->hIncrBy($monthlyKey, 'output_tokens', $outputTokens);
            $this->redis->hSet($monthlyKey, 'last_updated', time());
            $this->redis->expire($monthlyKey, 86400 * 90); // Keep 90 days

            // Track per-provider/model spend
            $providerKey = "spend:provider:{$provider}:{$model}:{$month}";
            $this->redis->hIncrByFloat($providerKey, 'total', $actualCost);
            $this->redis->hIncrBy($providerKey, 'requests', 1);
            $this->redis->expire($providerKey, 86400 * 90);

            return true;

        } catch (Exception $e) {
            error_log("Failed to record spend for user $userId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's budget policy (could be from DB or defaults)
     */
    private function getUserPolicy($userId) {
        // Default policy (dipakai jika Redis cache belum tersedia)
        $defaultPolicy = [
            'daily_cap' => $this->defaultDailyCap,
            'monthly_cap' => $this->defaultMonthlyCap,
            'warning_threshold_80' => $this->defaultDailyCap * 0.8,
            'warning_threshold_90' => $this->defaultDailyCap * 0.9,
            'enforcement_mode' => 'hard' // or 'soft' for warnings only
        ];

        if ($this->redis === null) {
            return $defaultPolicy;
        }

        // Check Redis cache first
        $policyKey = "policy:{$userId}";
        $cached = $this->redis->get($policyKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        // Cache for 5 minutes
        $this->redis->setex($policyKey, 300, json_encode($defaultPolicy));

        return $defaultPolicy;
    }

    /**
     * Get user's current spend state
     */
    private function getUserSpend($userId) {
        if ($this->redis === null) {
            // Redis belum tersedia -> anggap spend 0
            return [
                'daily_spend' => 0.0,
                'monthly_spend' => 0.0,
                'daily_date' => date('Y-m-d'),
                'monthly_date' => date('Y-m')
            ];
        }

        $today = date('Y-m-d');
        $month = date('Y-m');

        $dailyKey = "spend:daily:{$userId}:{$today}";
        $monthlyKey = "spend:monthly:{$userId}:{$month}";

        $dailySpend = $this->redis->hGet($dailyKey, 'total') ?: 0.0;
        $monthlySpend = $this->redis->hGet($monthlyKey, 'total') ?: 0.0;

        return [
            'daily_spend' => (float)$dailySpend,
            'monthly_spend' => (float)$monthlySpend,
            'daily_date' => $today,
            'monthly_date' => $month
        ];
    }

    /**
     * Get budget utilization percentage
     */
    public function getBudgetUtilization($userId) {
        $policy = $this->getUserPolicy($userId);
        $spend = $this->getUserSpend($userId);

        return [
            'daily_utilization' => ($spend['daily_spend'] / $policy['daily_cap']) * 100,
            'monthly_utilization' => ($spend['monthly_spend'] / $policy['monthly_cap']) * 100,
            'daily_remaining' => max(0, $policy['daily_cap'] - $spend['daily_spend']),
            'monthly_remaining' => max(0, $policy['monthly_cap'] - $spend['monthly_spend'])
        ];
    }

    /**
     * Check if warning thresholds are triggered
     */
    public function checkWarnings($userId) {
        $utilization = $this->getBudgetUtilization($userId);
        $warnings = [];

        if ($utilization['daily_utilization'] >= 90) {
            $warnings[] = [
                'type' => 'daily_90',
                'message' => 'Daily budget at 90% utilization',
                'severity' => 'critical'
            ];
        } elseif ($utilization['daily_utilization'] >= 80) {
            $warnings[] = [
                'type' => 'daily_80',
                'message' => 'Daily budget at 80% utilization',
                'severity' => 'warning'
            ];
        }

        if ($utilization['monthly_utilization'] >= 90) {
            $warnings[] = [
                'type' => 'monthly_90',
                'message' => 'Monthly budget at 90% utilization',
                'severity' => 'critical'
            ];
        } elseif ($utilization['monthly_utilization'] >= 80) {
            $warnings[] = [
                'type' => 'monthly_80',
                'message' => 'Monthly budget at 80% utilization',
                'severity' => 'warning'
            ];
        }

        return $warnings;
    }
}
