-- Add level enable/disable support to existing ticket installations.
-- Run once in phpMyAdmin against the knowledgebase database.
ALTER TABLE ticket_levels
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER resolve_sla;
