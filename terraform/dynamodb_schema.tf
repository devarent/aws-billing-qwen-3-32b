# DynamoDB Schema for Multi-Table Cost Tracking
# Based on AWS sample-governed-generative-ai-inference-solution-on-amazon-bedrock
# and sample-flowamp-ai-agent-governance patterns

# ========================================================
# Quota Policies Table
# Stores budget policies for users, groups, and defaults
# ========================================================
resource "aws_dynamodb_table" "quota_policies" {
  name         = "${var.project_name}-quota-policies"
  billing_mode = "PAY_PER_REQUEST"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  attribute {
    name = "policy_type"
    type = "S"
  }

  attribute {
    name = "identifier"
    type = "S"
  }

  hash_key  = "pk"
  range_key = "sk"

  global_secondary_index {
    name            = "PolicyTypeIndex"
    hash_key        = "policy_type"
    range_key       = "identifier"
    projection_type = "ALL"
  }

  point_in_time_recovery {
    enabled = true
  }

  tags = {
    Name        = "${var.project_name}-quota-policies"
    Environment = var.environment
  }
}

# ========================================================
# User Quota Metrics Table
# Tracks actual usage per user with TTL for auto-cleanup
# ========================================================
resource "aws_dynamodb_table" "user_quota_metrics" {
  name         = "${var.project_name}-user-metrics"
  billing_mode = "PAY_PER_REQUEST"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  hash_key  = "pk"
  range_key = "sk"

  ttl {
    attribute_name = "ttl"
    enabled        = true
  }

  point_in_time_recovery {
    enabled = true
  }

  tags = {
    Name        = "${var.project_name}-user-metrics"
    Environment = var.environment
  }
}

# ========================================================
# Single Table Design (FlowAMP Pattern)
# Multiple row types using SK prefixes
# ========================================================
resource "aws_dynamodb_table" "billing_registry" {
  name         = "${var.project_name}-billing-registry"
  billing_mode = "PAY_PER_REQUEST"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  hash_key  = "pk"
  range_key = "sk"

  # GSI for querying by row type
  global_secondary_index {
    name            = "RowTypeIndex"
    hash_key        = "pk"
    range_key       = "sk"
    projection_type = "ALL"
  }

  point_in_time_recovery {
    enabled = true
  }

  tags = {
    Name        = "${var.project_name}-billing-registry"
    Environment = var.environment
  }
}

# ========================================================
# Seed default policies
# ========================================================
resource "aws_dynamodb_table_item" "default_policy" {
  table_name = aws_dynamodb_table.quota_policies.name
  hash_key   = "pk"
  range_key  = "sk"

  item = jsonencode({
    pk                   = "POLICY#default"
    sk                   = "CURRENT"
    policy_type          = "default"
    identifier           = "default"
    monthly_token_limit  = 1000000
    daily_token_limit    = 50000
    monthly_usd_limit    = 100.0
    daily_usd_limit      = 10.0
    warning_threshold_80 = true
    warning_threshold_90 = true
    enforcement_mode     = "hard"
    created_at           = timestamp()
    updated_at           = timestamp()
  })
}

# ========================================================
# IAM Policy for Lambda/EC2 to access DynamoDB
# ========================================================
resource "aws_iam_policy" "dynamodb_access" {
  name        = "${var.project_name}-dynamodb-access"
  description = "Policy for accessing billing DynamoDB tables"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:GetItem",
          "dynamodb:PutItem",
          "dynamodb:UpdateItem",
          "dynamodb:Query",
          "dynamodb:Scan",
          "dynamodb:BatchWriteItem"
        ]
        Resource = [
          aws_dynamodb_table.quota_policies.arn,
          aws_dynamodb_table.user_quota_metrics.arn,
          aws_dynamodb_table.billing_registry.arn,
          "${aws_dynamodb_table.quota_policies.arn}/index/PolicyTypeIndex",
          "${aws_dynamodb_table.billing_registry.arn}/index/RowTypeIndex"
        ]
      }
    ]
  })
}
