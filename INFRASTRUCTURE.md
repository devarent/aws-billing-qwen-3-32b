# Infrastructure Deployment Guide

This guide covers deployment using Terraform, Kubernetes, and Helm for production-grade infrastructure.

## 🏗️ Terraform Deployment (AWS EKS)

### Prerequisites

- Terraform >= 1.0
- AWS CLI configured with credentials
- AWS account with EKS permissions

### Setup

```bash
cd terraform

# Copy example variables
cp terraform.tfvars.example terraform.tfvars

# Edit terraform.tfvars with your values
nano terraform.tfvars
```

### Deploy Infrastructure

```bash
# Initialize Terraform
terraform init

# Plan deployment
terraform plan

# Apply changes
terraform apply
```

### Configure kubectl

```bash
# Update kubeconfig
aws eks update-kubeconfig --region us-east-1 --name ai-billing-hub-cluster

# Verify connection
kubectl get nodes
```

### Destroy Infrastructure

```bash
terraform destroy
```

## 🚀 Kubernetes Deployment

### Option 1: Using Manifests

```bash
cd kubernetes

# Apply secrets (edit secrets.yaml first)
kubectl apply -f secrets.yaml

# Apply deployment
kubectl apply -f deployment.yaml

# Check status
kubectl get pods
kubectl get svc
```

### Option 2: Using Helm

```bash
cd helm/ai-billing-hub

# Create custom values
cp values.yaml custom-values.yaml
# Edit custom-values.yaml with your API keys

# Install chart
helm install ai-billing-hub . -f custom-values.yaml

# Upgrade
helm upgrade ai-billing-hub . -f custom-values.yaml

# Uninstall
helm uninstall ai-billing-hub
```

### Access Application

```bash
# Get service URL
kubectl get svc ai-billing-hub-service

# Port forward for local testing
kubectl port-forward svc/ai-billing-hub-service 8081:80
```

## 📊 Monitoring

### CloudWatch Metrics

Terraform automatically creates:
- CloudWatch Log Group for application logs
- CloudWatch Dashboard for monitoring
- AWS Budget for cost management

### Viewing Logs

```bash
# CloudWatch logs
aws logs tail /aws/eks/ai-billing-hub/logs --follow

# Kubernetes logs
kubectl logs -f deployment/ai-billing-hub
```

### CloudWatch Dashboard

Access dashboard via AWS Console: CloudWatch → Dashboards → ai-billing-hub-dashboard

## 🔒 Security Best Practices

### Secrets Management

1. **Never commit API keys** to version control
2. Use AWS SSM Parameter Store for production (Terraform)
3. Use Kubernetes Secrets for Kubernetes deployments
4. Rotate API keys regularly

### Network Security

- VPC with private subnets for EKS nodes
- Security groups limiting inbound/outbound traffic
- NLB with SSL/TLS termination (add HTTPS)

### Cost Management

- AWS Budget alerts configured at 80% and 100%
- Resource limits set in Kubernetes
- Auto-scaling to optimize costs

## 🎯 Production Checklist

- [ ] Terraform infrastructure deployed
- [ ] EKS cluster running
- [ ] kubectl configured
- [ ] API keys stored in secrets
- [ ] Application deployed via Helm
- [ ] HPA configured for auto-scaling
- [ ] CloudWatch dashboard active
- [ ] AWS Budget alerts configured
- [ ] SSL/TLS configured for HTTPS
- [ ] Monitoring and logging verified

## 🛠️ Troubleshooting

### Terraform Issues

```bash
# State lock issues
terraform force-unlock <LOCK_ID>

# Reinitialize after provider changes
terraform init -upgrade
```

### Kubernetes Issues

```bash
# Check pod status
kubectl describe pod <pod-name>

# Check logs
kubectl logs <pod-name>

# Restart deployment
kubectl rollout restart deployment/ai-billing-hub
```

### Helm Issues

```bash
# List releases
helm list

# Get values
helm get values ai-billing-hub

# Debug
helm install --debug --dry-run ai-billing-hub . -f custom-values.yaml
```

## 📈 Scaling

### Horizontal Pod Autoscaler

Automatically scales based on CPU/memory:
- Min replicas: 2
- Max replicas: 5
- Target CPU: 70%
- Target Memory: 80%

### Manual Scaling

```bash
# Scale deployment
kubectl scale deployment ai-billing-hub --replicas=5

# Update HPA
kubectl edit hpa ai-billing-hub-hpa
```

## 🔄 CI/CD Integration

### GitHub Actions Example

```yaml
name: Deploy to EKS

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v1
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: us-east-1
      - name: Deploy with Helm
        run: |
          helm upgrade ai-billing-hub ./helm/ai-billing-hub \
            --install \
            --set secrets.groqApiKey=${{ secrets.GROQ_API_KEY }} \
            --set secrets.openrouterApiKey=${{ secrets.OPENROUTER_API_KEY }}
```

## 📚 Additional Resources

- [Terraform AWS Provider](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- [EKS Best Practices](https://aws.github.io/aws-eks-best-practices/)
- [Helm Documentation](https://helm.sh/docs/)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
