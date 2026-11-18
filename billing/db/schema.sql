-- =====================================================================
-- Qween 3 32B — AWS Billing Manager
-- Skema database MySQL 8.x (utf8mb4)
--
-- File ini dipakai dua cara:
--   1. Di-mount ke /docker-entrypoint-initdb.d (dijalankan MySQL saat
--      volume pertama kali dibuat).
--   2. Dibaca oleh aplikasi PHP (billing/lib/db.php) jika tabel belum ada,
--      sehingga `php -S` di luar Docker juga tetap bisa bootstrap sendiri.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'finance', 'viewer') NOT NULL DEFAULT 'viewer',
    last_login_at DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aws_accounts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id     CHAR(12) NOT NULL,
    alias          VARCHAR(120) NOT NULL,
    environment    ENUM('production', 'staging', 'development', 'sandbox') NOT NULL DEFAULT 'production',
    owner_email    VARCHAR(190) NULL,
    region         VARCHAR(32) NOT NULL DEFAULT 'us-east-1',
    status         ENUM('active', 'suspended', 'closed') NOT NULL DEFAULT 'active',
    credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    monthly_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounts_account_id (account_id),
    KEY idx_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(40) NOT NULL,
    name       VARCHAR(120) NOT NULL,
    category   VARCHAR(60) NOT NULL DEFAULT 'Compute',
    unit       VARCHAR(40) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_code (code),
    KEY idx_services_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cost_records (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id     INT UNSIGNED NOT NULL,
    service_id     INT UNSIGNED NOT NULL,
    usage_date     DATE NOT NULL,
    region         VARCHAR(32) NOT NULL DEFAULT 'us-east-1',
    usage_quantity DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    cost_usd       DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    source         ENUM('aws_ce', 'manual', 'import') NOT NULL DEFAULT 'manual',
    note           VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_costs_date (usage_date),
    KEY idx_costs_account_date (account_id, usage_date),
    KEY idx_costs_service (service_id),
    CONSTRAINT fk_costs_account FOREIGN KEY (account_id) REFERENCES aws_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_costs_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    period          ENUM('monthly', 'quarterly', 'annual') NOT NULL DEFAULT 'monthly',
    limit_usd       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    alert_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80,
    hard_stop       TINYINT(1) NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_budgets_account (account_id),
    CONSTRAINT fk_budgets_account FOREIGN KEY (account_id) REFERENCES aws_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id     INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(40) NOT NULL,
    period_start   DATE NOT NULL,
    period_end     DATE NOT NULL,
    amount_usd     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status         ENUM('draft', 'issued', 'paid', 'overdue', 'void') NOT NULL DEFAULT 'issued',
    due_date       DATE NULL,
    paid_at        DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (invoice_number),
    KEY idx_invoices_account (account_id),
    KEY idx_invoices_status (status),
    CONSTRAINT fk_invoices_account FOREIGN KEY (account_id) REFERENCES aws_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id      INT UNSIGNED NULL,
    budget_id       INT UNSIGNED NULL,
    type            ENUM('budget_threshold', 'anomaly', 'credit_low', 'invoice_overdue') NOT NULL,
    severity        ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    message         VARCHAR(255) NOT NULL,
    threshold_pct   TINYINT UNSIGNED NULL,
    dedupe_key      VARCHAR(160) NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_at DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alerts_dedupe (dedupe_key),
    KEY idx_alerts_read (is_read),
    KEY idx_alerts_created (created_at),
    CONSTRAINT fk_alerts_account FOREIGN KEY (account_id) REFERENCES aws_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_alerts_budget FOREIGN KEY (budget_id) REFERENCES budgets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    skey       VARCHAR(60) NOT NULL,
    svalue     VARCHAR(255) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename      VARCHAR(190) NOT NULL,
    rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped  INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('success', 'partial', 'failed') NOT NULL DEFAULT 'success',
    message       VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
