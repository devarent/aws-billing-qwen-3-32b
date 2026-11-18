# Terraform configuration for AI Billing Hub Infrastructure
# Supports AWS EKS, EC2, CloudWatch, DynamoDB, and Prometheus/Grafana monitoring
# Based on AWS samples: FlowAMP, Governed Inference Solution, DevOps-Core-Course

terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.23"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.11"
    }
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = "AI-Billing-Hub"
      Environment = var.environment
      ManagedBy   = "Terraform"
    }
  }
}

# VPC for Kubernetes cluster
module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 5.0"

  name = "${var.project_name}-vpc"
  cidr = var.vpc_cidr

  azs             = var.availability_zones
  private_subnets = var.private_subnet_cidrs
  public_subnets  = var.public_subnet_cidrs

  enable_nat_gateway   = true
  single_nat_gateway   = true
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "${var.project_name}-vpc"
  }
}

# EKS Cluster
module "eks" {
  source  = "terraform-aws-modules/eks/aws"
  version = "~> 19.0"

  cluster_name    = "${var.project_name}-cluster"
  cluster_version = "1.28"

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  cluster_endpoint_public_access  = true
  cluster_endpoint_private_access = true

  eks_managed_node_groups = {
    general = {
      desired_size = 2
      min_size     = 1
      max_size     = 3

      instance_types = ["t3.medium"]
      capacity_type  = "ON_DEMAND"

      labels = {
        role = "general"
      }
    }
  }

  cluster_addons = {
    coredns = {
      most_recent = true
    }
    kube-proxy = {
      most_recent = true
    }
    vpc-cni = {
      most_recent = true
    }
    aws-ebs-csi-driver = {
      most_recent = true
    }
  }

  tags = {
    Environment = var.environment
  }
}

# CloudWatch Log Group for application logs
resource "aws_cloudwatch_log_group" "app_logs" {
  name              = "/aws/eks/${var.project_name}/logs"
  retention_in_days = 7

  tags = {
    Name = "${var.project_name}-logs"
  }
}

# CloudWatch Dashboard for monitoring
resource "aws_cloudwatch_dashboard" "main" {
  dashboard_name = "${var.project_name}-dashboard"

  dashboard_body = jsonencode({
    widgets = [
      {
        type   = "log"
        x      = 0
        y      = 0
        width  = 12
        height = 6

        properties = {
          logs = [
            {
              logGroupName = aws_cloudwatch_log_group.app_logs.name
              region       = var.aws_region
              title        = "Application Logs"
              view         = "table"
            }
          ]
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 6
        width  = 12
        height = 6

        properties = {
          metrics = [
            ["AWS/EKS", "ClusterCPUUtilization", "ClusterName", module.eks.cluster_name]
          ]
          period = 300
          stat   = "Average"
          region = var.aws_region
          title  = "EKS Cluster CPU"
        }
      }
    ]
  })
}

# AWS Budget for cost management
resource "aws_budgets_budget" "monthly" {
  name              = "${var.project_name}-monthly-budget"
  budget_type       = "COST"
  limit_amount      = var.budget_limit
  limit_unit        = "USD"
  time_period_end   = "2087-06-15_00:00"
  time_period_start = "2024-01-01_00:00"
  time_unit         = "MONTHLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    threshold                  = 80
    threshold_type             = "PERCENTAGE_OF_BUDGET"
    notification_type          = "ACTUAL"
    subscriber_email_addresses = [var.budget_notification_email]
  }

  notification {
    comparison_operator        = "GREATER_THAN"
    threshold                  = 100
    threshold_type             = "PERCENTAGE_OF_BUDGET"
    notification_type          = "FORECASTED"
    subscriber_email_addresses = [var.budget_notification_email]
  }
}

# SSM Parameter for storing secrets
resource "aws_ssm_parameter" "groq_api_key" {
  name  = "/${var.project_name}/groq-api-key"
  type  = "SecureString"
  value = var.groq_api_key

  tags = {
    Name = "Groq API Key"
  }
}

resource "aws_ssm_parameter" "openrouter_api_key" {
  name  = "/${var.project_name}/openrouter-api-key"
  type  = "SecureString"
  value = var.openrouter_api_key

  tags = {
    Name = "OpenRouter API Key"
  }
}

# DynamoDB resources didefinisikan langsung di ./dynamodb_schema.tf (root module)

# IAM Role for EKS to access DynamoDB
resource "aws_iam_role_policy_attachment" "eks_dynamodb_access" {
  role       = module.eks.cluster_iam_role_name
  policy_arn = aws_iam_policy.dynamodb_access.arn
}

# Outputs
output "cluster_endpoint" {
  description = "Endpoint for EKS control plane"
  value       = module.eks.cluster_endpoint
}

output "cluster_name" {
  description = "Kubernetes Cluster Name"
  value       = module.eks.cluster_name
}

output "region" {
  description = "AWS region"
  value       = var.aws_region
}

output "dynamodb_tables" {
  description = "DynamoDB table names"
  value = {
    quota_policies   = aws_dynamodb_table.quota_policies.name
    user_metrics     = aws_dynamodb_table.user_quota_metrics.name
    billing_registry = aws_dynamodb_table.billing_registry.name
  }
}
