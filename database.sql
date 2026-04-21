

USE railway;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(15) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    address TEXT NOT NULL,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    role ENUM('user', 'admin') DEFAULT 'user',
    -- Account status: pending, verified, disabled, waiting_for_updates
    status ENUM('pending', 'verified', 'disabled', 'waiting_for_updates') DEFAULT 'pending',
    -- First login tracking: user must change password on first login
    first_login TINYINT(1) DEFAULT 1,
    -- ID card photos
    id_card_front VARCHAR(255) DEFAULT NULL,
    id_card_back VARCHAR(255) DEFAULT NULL,
    -- Account lock fields
    failed_login_attempts INT DEFAULT 0,
    has_abnormal_login TINYINT(1) DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    permanently_locked TINYINT(1) DEFAULT 0,
    permanently_locked_at DATETIME DEFAULT NULL,
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- OTP CODES TABLE (for password reset & transfer verification)
-- =====================================================
CREATE TABLE otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('password_reset', 'transfer_verification') NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- CREDIT CARDS TABLE (simulated cards for deposit/withdraw)
-- =====================================================
CREATE TABLE credit_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_number VARCHAR(6) NOT NULL UNIQUE,
    expiration_date VARCHAR(10) NOT NULL,
    cvv VARCHAR(3) NOT NULL,
    card_type ENUM('deposit', 'withdraw', 'both') DEFAULT 'deposit',
    max_amount_per_deposit DECIMAL(15, 2) DEFAULT NULL,  -- NULL means unlimited
    always_fail TINYINT(1) DEFAULT 0,  -- For card #3 that always says "out of money"
    note TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- =====================================================
-- TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    -- Type of transaction
    type ENUM('deposit', 'withdraw', 'transfer_out', 'transfer_in', 'phone_card') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    fee DECIMAL(15, 2) DEFAULT 0.00,
    total_amount DECIMAL(15, 2) NOT NULL,  -- amount + fee (total deducted or credited)
    -- Status
    status ENUM('completed', 'pending', 'approved', 'rejected', 'cancelled') DEFAULT 'completed',
    -- For transfers: who pays the fee
    fee_payer ENUM('sender', 'receiver') DEFAULT NULL,
    -- Related user (recipient for transfer_out, sender for transfer_in)
    related_user_id INT DEFAULT NULL,
    -- Card info (for deposit/withdraw)
    card_number VARCHAR(6) DEFAULT NULL,
    -- Note/message
    note TEXT DEFAULT NULL,
    -- Balance after transaction
    balance_after DECIMAL(15, 2) DEFAULT NULL,
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- PHONE CARDS TABLE (purchased scratch cards)
-- =====================================================
CREATE TABLE phone_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    user_id INT NOT NULL,
    carrier ENUM('Viettel', 'Mobifone', 'Vinaphone') NOT NULL,
    carrier_code VARCHAR(5) NOT NULL,
    denomination DECIMAL(10, 2) NOT NULL,
    card_code VARCHAR(10) NOT NULL,  -- 10-digit code: 5 carrier digits + 5 random digits
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- LOGIN HISTORY TABLE (for tracking abnormal logins)
-- =====================================================
CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    status ENUM('success', 'failed', 'locked') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Admin account (password: admin123 - hashed with PHP password_hash)
-- You should regenerate this hash in PHP. This is bcrypt hash for 'admin123'
INSERT INTO users (phone_number, email, full_name, date_of_birth, address, password, balance, role, status, first_login)
VALUES (
    '0000000000',
    'admin@ewallet.com',
    'System Administrator',
    '1990-01-01',
    'Ho Chi Minh City, Vietnam',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: 'password' - CHANGE THIS
    0.00,
    'admin',
    'verified',
    0
);

-- Credit cards (simulated)
INSERT INTO credit_cards (card_number, expiration_date, cvv, max_amount_per_deposit, always_fail, note) VALUES
('111111', '10/10/2022', '411', NULL, 0, 'No limit on recharges or amount per deposit. Also used for withdrawals.'),
('222222', '11/11/2022', '443', 1000000.00, 0, 'No limit on recharges but max 1,000,000 VND per deposit.'),
('333333', '12/12/2022', '577', NULL, 1, 'Always returns "card is out of money".');

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_status ON transactions(status);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_created ON transactions(created_at);
CREATE INDEX idx_otp_user ON otp_codes(user_id);
CREATE INDEX idx_phone_cards_transaction ON phone_cards(transaction_id);
CREATE INDEX idx_login_history_user ON login_history(user_id);
