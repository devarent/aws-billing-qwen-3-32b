# Makefile — perintah bantu proyek
# Jalankan `make help`

.PHONY: help check lint validate run docker helm k8s asm clean

help:
	@echo "Targets:"
	@echo "  make check     - cek tool terpasang"
	@echo "  make lint      - cek sintaks semua file PHP"
	@echo "  make validate  - validasi compose, helm, terraform, k8s"
	@echo "  make run       - jalankan dashboard PHP di :8081"
	@echo "  make docker    - build & jalankan docker compose"
	@echo "  make asm       - build contoh assembly"

check:
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check-tools.ps1

lint:
	@for f in $$(find . -name "*.php" -not -path "./venv/*" -not -path "./vendor/*"); do php -l "$$f" || exit 1; done

validate:
	docker compose config --quiet
	helm lint helm/ai-billing-hub
	terraform -chdir=terraform validate
	kubectl apply --dry-run=client -f kubernetes/deployment.yaml

run:
	php -S localhost:8081

docker:
	docker compose up -d --build

asm:
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-assembly.ps1
