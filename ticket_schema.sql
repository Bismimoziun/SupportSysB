-- ============================================================
-- G&G Support Portal — Module 2: Ticket Management
-- Database Schema
-- Compatible with the existing 'knowledgebase' database
-- ============================================================

USE knowledgebase;

-- ------------------------------------------------------------
-- ticket_levels: configurable admin levels (e.g. L1, L2, L3)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_levels (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    level_name  VARCHAR(50) NOT NULL,          -- e.g. "Level 1"
    level_order INT NOT NULL DEFAULT 1,        -- 1 = first to receive
    attend_sla  INT NOT NULL DEFAULT 60,       -- minutes allowed to attend
    resolve_sla INT NOT NULL DEFAULT 120,      -- minutes allowed to resolve
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- ticket_level_admins: which admin users belong to which level
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_level_admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    level_id    INT NOT NULL,
    user_id     INT NOT NULL,
    FOREIGN KEY (level_id) REFERENCES ticket_levels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_level_user (level_id, user_id)
);

-- ------------------------------------------------------------
-- ticket_extension_reasons: configurable dropdown options
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_extension_reasons (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    reason_text      VARCHAR(100) NOT NULL,
    time_limit_hours DECIMAL(4,1) NOT NULL DEFAULT 1.0,
    is_active        TINYINT(1) DEFAULT 1,
    created_by       INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- tickets: main ticket table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    category_id     INT DEFAULT NULL,               -- reuse existing categories table
    raised_by       INT NOT NULL,                   -- user who raised the ticket
    current_level   INT DEFAULT NULL,               -- FK to ticket_levels
    current_admin   INT DEFAULT NULL,               -- FK to users (assigned admin)
    status          ENUM('open','in_progress','resolved','unresolved','unattended')
                    NOT NULL DEFAULT 'open',
    attend_deadline DATETIME DEFAULT NULL,          -- fixed SLA deadline for attendance
    resolve_deadline DATETIME DEFAULT NULL,         -- fixed SLA deadline for resolution
    attended_at     DATETIME DEFAULT NULL,
    resolved_at     DATETIME DEFAULT NULL,
    resolution_note TEXT DEFAULT NULL,
    add_to_solution TINYINT(1) DEFAULT 0,
    solution_public TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (raised_by)      REFERENCES users(id),
    FOREIGN KEY (current_level)  REFERENCES ticket_levels(id),
    FOREIGN KEY (current_admin)  REFERENCES users(id)
);

-- ------------------------------------------------------------
-- ticket_activity: full audit trail for every ticket event
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_activity (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT NOT NULL,
    actor_id        INT DEFAULT NULL,              -- user who performed the action
    action          VARCHAR(50) NOT NULL,          -- raised/assigned/taken_up/escalated/resolved/unresolved/extended/unattended
    level_id        INT DEFAULT NULL,
    admin_id        INT DEFAULT NULL,
    attend_deadline DATETIME DEFAULT NULL,
    resolve_deadline DATETIME DEFAULT NULL,
    actual_time     DATETIME DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id)  REFERENCES users(id),
    FOREIGN KEY (level_id)  REFERENCES ticket_levels(id),
    FOREIGN KEY (admin_id)  REFERENCES users(id)
);

-- ------------------------------------------------------------
-- ticket_extensions: per-ticket time extension log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_extensions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT NOT NULL,
    admin_id    INT NOT NULL,
    reason_id   INT NOT NULL,
    remarks     TEXT NOT NULL,
    extra_hours INT NOT NULL DEFAULT 1,
    old_deadline DATETIME NOT NULL,
    new_deadline DATETIME NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)  REFERENCES users(id),
    FOREIGN KEY (reason_id) REFERENCES ticket_extension_reasons(id)
);

-- ------------------------------------------------------------
-- round_robin_pointer: tracks who gets next ticket per level
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS round_robin_pointer (
    level_id         INT PRIMARY KEY,
    last_admin_index INT NOT NULL DEFAULT 0,
    FOREIGN KEY (level_id) REFERENCES ticket_levels(id) ON DELETE CASCADE
);

-- ============================================================
-- Seed data — sample levels, admins, and extension reasons
-- (Adjust user IDs to match your actual users table)
-- ============================================================

INSERT IGNORE INTO ticket_levels (level_name, level_order, attend_sla, resolve_sla, created_by) VALUES
('Level 1', 1, 60,  120, 1),
('Level 2', 2, 90,  180, 1),
('Level 3', 3, 120, 240, 1);

INSERT IGNORE INTO ticket_extension_reasons (reason_text, is_active, created_by) VALUES
('OEM Support Required',    1, 1),
('Vendor Dependency',       1, 1),
('Parts Procurement',       1, 1),
('Awaiting User Response',  1, 1),
('Network Issue',           1, 1);

INSERT IGNORE INTO round_robin_pointer (level_id, last_admin_index) VALUES
(1, 0), (2, 0), (3, 0);
