-- NovaBank Demo Banking System
-- Import this file into phpMyAdmin BEFORE running the application.
-- All monetary values in this system are FICTIONAL DEMO FUNDS ONLY.

CREATE DATABASE IF NOT EXISTS novabank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE novabank;

-- ---------------------------------------------------------------
-- Roles
-- ---------------------------------------------------------------
CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO roles (id, name) VALUES (1, 'customer'), (2, 'admin');

-- ---------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    address VARCHAR(255) NOT NULL,
    status ENUM('active','locked','suspended') NOT NULL DEFAULT 'active',
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------
CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(5) NOT NULL DEFAULT 'NOVA',
    status ENUM('active','locked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_balance_nonnegative CHECK (balance >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Transaction types
-- ---------------------------------------------------------------
CREATE TABLE transaction_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO transaction_types (code) VALUES
('deposit'), ('withdrawal'), ('transfer_out'), ('transfer_in'),
('loan_disbursement'), ('loan_repayment');

-- ---------------------------------------------------------------
-- Transactions (ledger)
-- ---------------------------------------------------------------
CREATE TABLE transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(30) NOT NULL UNIQUE,
    account_id INT UNSIGNED NOT NULL,
    type_id TINYINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    counterparty_account_id INT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    status ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (type_id) REFERENCES transaction_types(id),
    FOREIGN KEY (counterparty_account_id) REFERENCES accounts(id),
    INDEX idx_account_created (account_id, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Beneficiaries
-- ---------------------------------------------------------------
CREATE TABLE beneficiaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    beneficiary_account_id INT UNSIGNED NOT NULL,
    nickname VARCHAR(60) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (beneficiary_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_beneficiary (user_id, beneficiary_account_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Loans
-- ---------------------------------------------------------------
CREATE TABLE loans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_ref VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    principal DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term_months SMALLINT UNSIGNED NOT NULL,
    monthly_payment DECIMAL(15,2) NOT NULL,
    total_repayment DECIMAL(15,2) NOT NULL,
    remaining_balance DECIMAL(15,2) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('pending','under_review','approved','rejected','active','fully_paid','defaulted') NOT NULL DEFAULT 'pending',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    decided_by INT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Loan payments
-- ---------------------------------------------------------------
CREATE TABLE loan_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    remaining_balance DECIMAL(15,2) NOT NULL,
    transaction_id INT UNSIGNED NOT NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Notifications
-- ---------------------------------------------------------------
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Login attempts (rate limiting / lockout)
-- ---------------------------------------------------------------
CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username_attempted VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username_attempted, created_at),
    INDEX idx_ip_time (ip_address, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Sessions (DB-tracked, in addition to native PHP sessions)
-- ---------------------------------------------------------------
CREATE TABLE user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Security logs
-- ---------------------------------------------------------------
CREATE TABLE security_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_time (user_id, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- System settings (admin-manageable, key-value)
-- ---------------------------------------------------------------
CREATE TABLE system_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('registration_enabled', '1'),
('default_language', 'en'),
('maintenance_mode', '0');

-- ---------------------------------------------------------------
-- Admin logs
-- ---------------------------------------------------------------
CREATE TABLE admin_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id INT UNSIGNED NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =================================================================
-- SAMPLE / DEMO DATA
-- All passwords below are: Password123!
-- Hash generated with PHP password_hash() (bcrypt)
-- =================================================================

INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, mobile_number, date_of_birth, address, status)
VALUES
(2, 'admin', 'admin@novabank.demo', '$2b$10$NREiYLc3JBu1p0tiWHse0eLAWjmMbhvjIBqy7BnapxYng/deZSY7a', 'Nova', 'Admin', '+639170000000', '1990-01-01', 'NovaBank HQ, Makati City', 'active'),
(1, 'juan.delacruz', 'juan.delacruz@example.demo', '$2b$10$NREiYLc3JBu1p0tiWHse0eLAWjmMbhvjIBqy7BnapxYng/deZSY7a', 'Juan', 'Dela Cruz', '+639171234567', '1995-05-14', '123 Rizal St, Quezon City', 'active'),
(1, 'maria.santos', 'maria.santos@example.demo', '$2b$10$NREiYLc3JBu1p0tiWHse0eLAWjmMbhvjIBqy7BnapxYng/deZSY7a', 'Maria', 'Santos', '+639179876543', '1992-11-02', '456 Bonifacio Ave, Pasig City', 'active');

INSERT INTO accounts (user_id, account_number, balance) VALUES
(2, 'NOVA-1000-0000-0001', 25000.00),
(3, 'NOVA-1000-0000-0002', 8500.50);

INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, description, status)
VALUES
('TXN-DEMO-000001', 1, 1, 25000.00, 25000.00, 'Initial demo deposit', 'completed'),
('TXN-DEMO-000002', 2, 1, 10000.00, 10000.00, 'Initial demo deposit', 'completed'),
('TXN-DEMO-000003', 2, 2, 1500.00, 8500.00, 'Demo ATM withdrawal', 'completed');

UPDATE accounts SET balance = 8500.50 WHERE id = 2;
UPDATE transactions SET balance_after = 8500.50 WHERE transaction_ref = 'TXN-DEMO-000003';

INSERT INTO loans (loan_ref, user_id, principal, interest_rate, term_months, monthly_payment, total_repayment, remaining_balance, purpose, status)
VALUES
('LOAN-DEMO-0001', 2, 5000.00, 5.00, 6, 875.00, 5250.00, 5250.00, 'Demo home improvement', 'active');

INSERT INTO notifications (user_id, type, title, message) VALUES
(2, 'welcome', 'Welcome to NovaBank', 'Your demo account is ready. All funds are fictional.'),
(3, 'welcome', 'Welcome to NovaBank', 'Your demo account is ready. All funds are fictional.');
