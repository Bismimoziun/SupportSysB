-- ============================================================
-- G&G Support Portal — AI Chat Logs Migration
-- Run this once to add the optional chat logging table.
-- Logs are useful for reviewing what users are asking and
-- improving the solution base over time.
-- ============================================================

CREATE TABLE IF NOT EXISTS ai_chat_logs (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    question    TEXT            NOT NULL,
    answer      TEXT            NOT NULL,
    sources     JSON            DEFAULT NULL,   -- solution IDs/titles retrieved
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
