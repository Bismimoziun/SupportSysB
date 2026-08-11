-- ============================================================
-- Run this once in phpMyAdmin or MySQL to add the SLA
-- auto-trigger throttle table.
-- ============================================================
CREATE TABLE IF NOT EXISTS sla_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed with a past time so SLA check runs immediately on first page load
INSERT IGNORE INTO sla_settings (setting_key, setting_value)
VALUES ('last_sla_run', '2000-01-01 00:00:00');
