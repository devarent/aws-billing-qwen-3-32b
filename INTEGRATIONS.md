# AWS Samples Integration Guide

## Overview

This project has been enhanced with production-grade patterns from the following AWS samples:

1. **sample-flowamp-ai-agent-governance** - Single-table DynamoDB design, cost tracking patterns
2. **sample-governed-generative-ai-inference-solution-on-amazon-bedrock** - Budget enforcement, quota monitoring
3. **sample-agent-interoperability** - JWT identity propagation across providers
4. **DevOps-Core-Course** - Prometheus/Grafana monitoring, Helm best practices

## New Features Implemented

### 1. Hard Budget Enforcement Pattern

**Location:** `middleware/BudgetEnforcement.php`

Based on AWS request interceptor pattern that checks budgets BEFORE API calls are made.

**Key Features:**
- Real-time budget checking (<100ms with Redis)
- Daily and monthly caps
- Hard enforcement mode (blocks requests when budget exceeded)
- Warning thresholds at 80% and 90%
- Provider-specific spend tracking

**Usage:**
```php
$budgetEnforcement = new BudgetEnforcement();
$result = $budgetEnforcement->checkBudget($userId, $estimatedCost);

if (!$result['allowed']) {
    // Return 403 with budget exceeded error
    http_response_code(403);
    echo json_encode(['error' => $result['error']]);
    exit;
}
```

**API Endpoint:**
```
POST /api.php?action=budget_check
{
    "user_id": "user@example.com",
    "estimated_cost": 0.05
}
```

### 2. Multi-Table DynamoDB Schema

**Location:** `terraform/dynamodb_schema.tf`

Three DynamoDB tables for comprehensive cost tracking:

#### Quota Policies Table
Stores budget policies for users, groups, and defaults.
- **PK:** `POLICY#{type}#{identifier}`
- **SK:** `CURRENT`
- **GSI:** PolicyTypeIndex for efficient lookups

#### User Quota Metrics Table
Tracks actual usage per user with TTL for auto-cleanup.
- **PK:** `USER#{email}`
- **SK:** `MONTH#{YYYY-MM}`
- **TTL:** 90 days automatic cleanup

#### Billing Registry Table (Single-Table Design)
Multiple row types using SK prefixes (FlowAMP pattern):
- `INFO` - User profile/budget settings
- `COST#{YYYY-MM-DD}#{requestId}` - Individual API calls
- `AGGREGATE#{YYYY-MM}` - Monthly summaries
- `ALERT#{timestamp}` - Budget alerts
- `REVIEW#{timestamp}` - Review records

**Terraform Deployment:**
```bash
cd terraform
terraform apply
```

### 3. Single-Table Cost Tracking

**Location:** `api/CostTracking.php`

Implements FlowAMP's elegant single-table design with SK prefixes.

**Key Methods:**
- `recordCost()` - Record individual API call cost
- `recordMonthlyAggregate()` - Generate monthly summaries
- `getUserCostHistory()` - Get cost history by date range
- `getUserAlerts()` - Get pending budget alerts
- `acknowledgeAlert()` - Mark alert as acknowledged

**API Endpoints:**
```
POST /api.php?action=record_spend
{
    "user_id": "user@example.com",
    "provider": "groq",
    "model": "qwen/qwen-2.5-coder-32b-instruct",
    "input_tokens": 500,
    "output_tokens": 800,
    "cost": 0.005
}

GET /api.php?action=cost_history&user_id=user@example.com

GET /api.php?action=alerts&user_id=user@example.com

POST /api.php?action=acknowledge_alert
{
    "user_id": "user@example.com",
    "alert_timestamp": 1699999999
}
```

### 4. JWT Identity Propagation

**Location:** `middleware/IdentityPropagation.php`

Based on AWS agent interoperability pattern for multi-provider cost attribution.

**Key Features:**
- JWT token generation with user identity
- Token validation and expiration checking
- Provider-specific header injection
- End-to-end identity propagation across Groq, OpenRouter, Bedrock, Gemini

**Usage:**
```php
$identityPropagation = new IdentityPropagation();

// Generate token
$token = $identityPropagation->generateToken($userId, $email, $teams);

// Validate token
$identity = $identityPropagation->validateToken($token);

// Get provider headers
$headers = $identityPropagation->getProviderHeaders($userId, $email, 'groq');
```

**API Endpoints:**
```
POST /api.php?action=generate_token
{
    "user_id": "user@example.com",
    "email": "user@example.com",
    "teams": ["engineering", "data-science"]
}

POST /api.php?action=validate_token
{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}

GET /api.php?action=provider_headers&user_id=user@example.com&provider=groq
```

### 5. FlowAMP-Inspired Dark Theme UI

**Location:** `assets/css/flowamp-theme.css`

GitHub-inspired dark theme optimized for high-density data tables and observability dashboards.

**Key Features:**
- Color palette with semantic accent colors
- High-density data tables with hover states
- Status pills (healthy, warning, error, info)
- Metric cards with delta indicators
- Progress bars with warning thresholds
- Alert cards with severity indicators
- Monospace font for code and numbers

**Usage in index.php:**
```html
<link rel="stylesheet" href="assets/css/flowamp-theme.css">

<div class="billing-table">
  <table>
    <thead>
      <tr>
        <th>Provider</th>
        <th>Model</th>
        <th class="numeric">Cost</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Groq</td>
        <td>Qwen 2.5 32B</td>
        <td class="numeric">$0.005</td>
      </tr>
    </tbody>
  </table>
</div>
```

### 6. Prometheus Metrics Endpoint

**Location:** `api/metrics.php`

Based on DevOps-Core-Course Lab 15 monitoring patterns.

**Metrics Exposed:**
- `ai_billing_total_cost_usd` - Total cost by provider
- `ai_billing_total_requests` - Total API requests
- `ai_billing_total_tokens` - Token consumption (input/output)
- `ai_billing_request_duration_seconds` - Request duration histogram
- `ai_billing_api_errors_total` - API errors by type
- `ai_billing_budget_utilization` - Budget utilization percentage
- `ai_billing_active_users` - Number of active users
- `ai_billing_alerts_total` - Budget alerts triggered
- `ai_billing_up` - Service availability

**Access:**
```
GET /api/metrics.php
```

**Example Output:**
```
# HELP ai_billing_total_cost_usd Total cost in USD across all providers
# TYPE ai_billing_total_cost_usd gauge
ai_billing_total_cost_usd{provider="groq"} 5.23
ai_billing_total_cost_usd{provider="openrouter"} 12.45
ai_billing_total_cost_usd{provider="bedrock"} 8.90
ai_billing_total_cost_usd{provider="gemini"} 3.21
```

### 7. Grafana Dashboard (Helm)

**Location:** `helm/ai-billing-hub/templates/grafana-dashboard.yaml`

Pre-configured Grafana dashboard for real-time monitoring.

**Panels:**
- Total Cost by Provider (stat)
- Total Requests (stat)
- Budget Utilization (gauge with thresholds)
- Active Users (stat)
- Cost Over Time (timeseries)
- Token Consumption (timeseries)
- API Error Rate (timeseries with alert)
- Budget Alerts (stat with color thresholds)

**Helm Deployment:**
```bash
cd helm/ai-billing-hub
helm install ai-billing-hub . -f values.yaml
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    User Interface                      │
│              (index.php + FlowAMP CSS)                  │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│              PHP API (api.php)                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Identity Propagation (JWT)                     │  │
│  │  Budget Enforcement (Redis)                     │  │
│  │  Cost Tracking (DynamoDB)                       │  │
│  └──────────────────────────────────────────────────┘  │
└─────────┬───────────────────────────────────────────────┘
          │
          ├─────────────┬─────────────┬──────────────┐
          ▼             ▼             ▼              ▼
     ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
     │  Groq   │  │OpenRouter│ │ Bedrock │ │ Gemini  │
     └─────────┘  └─────────┘  └─────────┘  └─────────┘
          │             │             │              │
          └─────────────┴─────────────┴──────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│              Cost Tracking Layer                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  DynamoDB (Single-Table Design)                 │  │
│  │  - COST# rows for individual calls              │  │
│  │  - AGGREGATE# rows for monthly summaries         │  │
│  │  - ALERT# rows for budget warnings              │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Redis (Fast Budget Lookups)                    │  │
│  │  - spend:daily:{user}:{date}                    │  │
│  │  - spend:monthly:{user}:{month}                 │  │
│  │  - policy:{user}                                │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│              Monitoring Layer                           │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Prometheus Metrics Endpoint                     │  │
│  │  /api/metrics.php                                │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Grafana Dashboard (Helm)                       │  │
│  │  - Cost visualization                           │  │
│  │  - Budget utilization gauges                    │  │
│  │  - Alert configurations                         │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

## Deployment Instructions

### Local Development (Docker)

1. **Start containers:**
```bash
cd "C:\laragon\www\aws billing qween 3 32b"
docker compose up -d --build
```

2. **Access application:**
```
http://localhost:8081
```

3. **Test budget enforcement:**
```bash
curl -X POST http://localhost:8081/api.php \
  -d "action=budget_check" \
  -d "user_id=test@example.com" \
  -d "estimated_cost=0.05"
```

### Production (Terraform + EKS)

1. **Configure Terraform:**
```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with your values
```

2. **Deploy infrastructure:**
```bash
terraform init
terraform plan
terraform apply
```

3. **Configure kubectl:**
```bash
aws eks update-kubeconfig --region us-east-1 --name ai-billing-hub-cluster
```

4. **Deploy with Helm:**
```bash
cd ../helm/ai-billing-hub
helm install ai-billing-hub . -f values.yaml
```

### WSL Ubuntu

1. **Setup Python environment:**
```bash
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
bash setup_wsl.sh
```

2. **Run CLI scripts:**
```bash
source venv/bin/activate
python3 chat_groq.py
python3 chat_openrouter.py
python3 chat_bedrock.py
```

## Security Considerations

### API Keys
- **Never** commit `.env` file
- Use Docker secrets for production
- Use Kubernetes secrets for K8s deployments
- Use AWS Secrets Manager for cloud deployments

### JWT Tokens
- Set strong `JWT_SECRET` in environment
- Tokens expire after 1 hour (configurable)
- Always validate tokens on each request

### Budget Enforcement
- Enable `BUDGET_ENFORCEMENT_ENABLED=true` in production
- Set appropriate `DEFAULT_DAILY_CAP` and `DEFAULT_MONTHLY_CAP`
- Configure warning thresholds (80%, 90%)

### DynamoDB
- Enable point-in-time recovery (enabled by default)
- Set appropriate TTL for old data
- Use IAM policies for least-privilege access

## Monitoring

### Prometheus Metrics
Access metrics at:
```
http://your-domain/api/metrics.php
```

### Grafana Dashboard
After Helm deployment, access Grafana at:
```
http://your-domain/grafana
```

Default credentials (update in values.yaml):
- Username: `admin`
- Password: `prom-operator`

### CloudWatch Logs
Application logs are sent to CloudWatch Log Group:
```
/aws/eks/ai-billing-hub/application
```

## Troubleshooting

### Budget Enforcement Not Working
1. Check Redis connection: `docker logs <container> | grep redis`
2. Verify `BUDGET_ENFORCEMENT_ENABLED=true` in environment
3. Check user policy exists in Redis

### DynamoDB Write Failures
1. Verify IAM role has DynamoDB permissions
2. Check table names match Terraform outputs
3. Enable CloudWatch logs for Lambda (if using Lambda)

### JWT Token Invalid
1. Verify `JWT_SECRET` matches between generation and validation
2. Check token hasn't expired (default 1 hour)
3. Ensure token hasn't been tampered with

### Prometheus Metrics Not Showing
1. Verify `/api/metrics.php` is accessible
2. Check Prometheus is scraping the endpoint
3. Review ServiceMonitor configuration in Helm

## Next Steps

1. **Configure actual provider API keys** in `.env` or Docker secrets
2. **Set appropriate budget limits** for your use case
3. **Enable monitoring alerts** in Grafana
4. **Review and adjust** budget enforcement thresholds
5. **Implement authentication** (Cognito, Auth0, etc.) for production
6. **Add additional providers** as needed (Anthropic, Cohere, etc.)

## References

- [FlowAMP AI Agent Governance](https://github.com/aws-samples/sample-flowamp-ai-agent-governance)
- [Governed Inference Solution](https://github.com/aws-samples/sample-governed-generative-ai-inference-solution-on-amazon-bedrock)
- [Agent Interoperability](https://github.com/aws-samples/sample-agent-interoperability)
- [DevOps Core Course](https://github.com/inno-devops-labs/DevOps-Core-Course)
- [Qwen3-Next on Bedrock](https://github.com/aws-samples/sample-qwen3-next-on-amazon-bedrock)
