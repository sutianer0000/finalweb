-- Bug reports — user-submitted issues routed to the dev/admin queue.
-- Run once. Idempotent (uses CREATE TABLE IF NOT EXISTS).
-- Keep database.sql synced so fresh imports already include this.

USE ewallet;

CREATE TABLE IF NOT EXISTS bug_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Reporter context. user_id is nullable so a future "anonymous report"
    -- form on a public page could still write here. user_email is a
    -- snapshot at submit time so the report stays meaningful even if the
    -- account is later deleted.
    user_id INT DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,

    -- The actual report
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,

    -- Diagnostic context auto-captured by the form
    page_url VARCHAR(500) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    -- Triage workflow
    status ENUM('open','triaged','resolved','wont_fix') DEFAULT 'open',
    dev_notes TEXT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    resolved_by INT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_bug_status_created (status, created_at),
    KEY idx_bug_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
