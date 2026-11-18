<?php
/**
 * Prometheus Metrics Endpoint
 * Based on DevOps-Core-Course Lab 15 monitoring patterns
 * Exposes billing metrics in Prometheus format
 */

header('Content-Type: text/plain; version=0.0.4');

// Metric definitions
$metrics = [];

// ========================================================
# HELP ai_billing_total_cost_usd Total cost in USD across all providers
# TYPE ai_billing_total_cost_usd gauge
$metrics[] = 'ai_billing_total_cost_usd{provider="groq"} ' . (getMetric('groq_total_cost') ?: 0);
$metrics[] = 'ai_billing_total_cost_usd{provider="openrouter"} ' . (getMetric('openrouter_total_cost') ?: 0);
$metrics[] = 'ai_billing_total_cost_usd{provider="bedrock"} ' . (getMetric('bedrock_total_cost') ?: 0);
$metrics[] = 'ai_billing_total_cost_usd{provider="gemini"} ' . (getMetric('gemini_total_cost') ?: 0);

# HELP ai_billing_total_requests Total number of API requests
# TYPE ai_billing_total_requests counter
$metrics[] = 'ai_billing_total_requests{provider="groq"} ' . (getMetric('groq_requests') ?: 0);
$metrics[] = 'ai_billing_total_requests{provider="openrouter"} ' . (getMetric('openrouter_requests') ?: 0);
$metrics[] = 'ai_billing_total_requests{provider="bedrock"} ' . (getMetric('bedrock_requests') ?: 0);
$metrics[] = 'ai_billing_total_requests{provider="gemini"} ' . (getMetric('gemini_requests') ?: 0);

# HELP ai_billing_total_tokens Total tokens consumed
# TYPE ai_billing_total_tokens counter
$metrics[] = 'ai_billing_total_tokens{provider="groq",type="input"} ' . (getMetric('groq_input_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="groq",type="output"} ' . (getMetric('groq_output_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="openrouter",type="input"} ' . (getMetric('openrouter_input_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="openrouter",type="output"} ' . (getMetric('openrouter_output_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="bedrock",type="input"} ' . (getMetric('bedrock_input_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="bedrock",type="output"} ' . (getMetric('bedrock_output_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="gemini",type="input"} ' . (getMetric('gemini_input_tokens') ?: 0);
$metrics[] = 'ai_billing_total_tokens{provider="gemini",type="output"} ' . (getMetric('gemini_output_tokens') ?: 0);

# HELP ai_billing_request_duration_seconds Request duration in seconds
# TYPE ai_billing_request_duration_seconds histogram
$metrics[] = 'ai_billing_request_duration_seconds_bucket{provider="groq",le="0.1"} ' . (getMetric('groq_duration_0_1') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_bucket{provider="groq",le="0.5"} ' . (getMetric('groq_duration_0_5') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_bucket{provider="groq",le="1.0"} ' . (getMetric('groq_duration_1_0') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_bucket{provider="groq",le="5.0"} ' . (getMetric('groq_duration_5_0') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_bucket{provider="groq",le="+Inf"} ' . (getMetric('groq_duration_inf') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_sum{provider="groq"} ' . (getMetric('groq_duration_sum') ?: 0);
$metrics[] = 'ai_billing_request_duration_seconds_count{provider="groq"} ' . (getMetric('groq_duration_count') ?: 0);

# HELP ai_billing_api_errors_total Total API errors
# TYPE ai_billing_api_errors_total counter
$metrics[] = 'ai_billing_api_errors_total{provider="groq",error_type="timeout"} ' . (getMetric('groq_errors_timeout') ?: 0);
$metrics[] = 'ai_billing_api_errors_total{provider="groq",error_type="rate_limit"} ' . (getMetric('groq_errors_rate_limit') ?: 0);
$metrics[] = 'ai_billing_api_errors_total{provider="groq",error_type="auth"} ' . (getMetric('groq_errors_auth') ?: 0);
$metrics[] = 'ai_billing_api_errors_total{provider="openrouter",error_type="timeout"} ' . (getMetric('openrouter_errors_timeout') ?: 0);
$metrics[] = 'ai_billing_api_errors_total{provider="openrouter",error_type="rate_limit"} ' . (getMetric('openrouter_errors_rate_limit') ?: 0);
$metrics[] = 'ai_billing_api_errors_total{provider="openrouter",error_type="auth"} ' . (getMetric('openrouter_errors_auth') ?: 0);

# HELP ai_billing_budget_utilization Budget utilization percentage
# TYPE ai_billing_budget_utilization gauge
$metrics[] = 'ai_billing_budget_utilization{type="daily"} ' . (getMetric('budget_daily_utilization') ?: 0);
$metrics[] = 'ai_billing_budget_utilization{type="monthly"} ' . (getMetric('budget_monthly_utilization') ?: 0);

# HELP ai_billing_active_users Number of active users
# TYPE ai_billing_active_users gauge
$metrics[] = 'ai_billing_active_users ' . (getMetric('active_users') ?: 0);

# HELP ai_billing_alerts_total Total budget alerts triggered
# TYPE ai_billing_alerts_total counter
$metrics[] = 'ai_billing_alerts_total{severity="warning"} ' . (getMetric('alerts_warning') ?: 0);
$metrics[] = 'ai_billing_alerts_total{severity="critical"} ' . (getMetric('alerts_critical') ?: 0);

# HELP ai_billing_up Service availability
# TYPE ai_billing_up gauge
$metrics[] = 'ai_billing_up 1';

echo implode("\n", $metrics);

/**
 * Helper function to get metric value from Redis or database
 * In production, this would query your metrics storage
 */
function getMetric($key) {
    // For development, return mock data
    // In production, query Redis or your metrics database
    $mockMetrics = [
        'groq_total_cost' => 5.23,
        'openrouter_total_cost' => 12.45,
        'bedrock_total_cost' => 8.90,
        'gemini_total_cost' => 3.21,
        'groq_requests' => 150,
        'openrouter_requests' => 320,
        'bedrock_requests' => 85,
        'gemini_requests' => 65,
        'groq_input_tokens' => 45000,
        'groq_output_tokens' => 120000,
        'openrouter_input_tokens' => 95000,
        'openrouter_output_tokens' => 280000,
        'budget_daily_utilization' => 45.2,
        'budget_monthly_utilization' => 23.8,
        'active_users' => 12,
        'alerts_warning' => 3,
        'alerts_critical' => 0
    ];

    return $mockMetrics[$key] ?? 0;
}
