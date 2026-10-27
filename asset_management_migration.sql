-- Additive Asset Management migration for the existing knowledgebase database.
-- Back up the database before applying. This script does not drop or recreate
-- existing tables and may be run again safely.

USE knowledgebase;

CREATE TABLE IF NOT EXISTS asset_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_categories_name (name),
    CONSTRAINT fk_asset_categories_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    department VARCHAR(120) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_locations_name_department (name, department),
    CONSTRAINT fk_asset_locations_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_tag VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    model VARCHAR(120) DEFAULT NULL,
    serial_number VARCHAR(160) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    department VARCHAR(120) DEFAULT NULL,
    location_id INT UNSIGNED DEFAULT NULL,
    vendor VARCHAR(160) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    purchase_cost DECIMAL(12,2) DEFAULT NULL,
    warranty_start DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,
    status ENUM('available','allocated','under_maintenance','retired','disposed')
        NOT NULL DEFAULT 'available',
    condition_label ENUM('new','good','fair','poor','damaged') NOT NULL DEFAULT 'good',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assets_asset_tag (asset_tag),
    UNIQUE KEY uq_assets_serial_number (serial_number),
    KEY idx_assets_category (category_id),
    KEY idx_assets_location (location_id),
    KEY idx_assets_status (status),
    CONSTRAINT fk_assets_category FOREIGN KEY (category_id)
        REFERENCES asset_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_location FOREIGN KEY (location_id)
        REFERENCES asset_locations(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO asset_categories (name) VALUES
    ('Laptop'), ('Desktop'), ('Monitor'), ('Printer'), ('Server'),
    ('Network Device'), ('Software'), ('Other');

CREATE TABLE IF NOT EXISTS asset_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_type ENUM('allocation','return') NOT NULL DEFAULT 'allocation',
    user_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED DEFAULT NULL,
    allocation_id INT UNSIGNED DEFAULT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected','completed','cancelled')
        NOT NULL DEFAULT 'pending',
    remarks TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_requests_user (user_id, created_at),
    KEY idx_asset_requests_status (status, request_type),
    KEY idx_asset_requests_allocation (allocation_id),
    CONSTRAINT fk_asset_requests_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_requests_asset FOREIGN KEY (asset_id)
        REFERENCES assets(id) ON DELETE SET NULL,
    CONSTRAINT fk_asset_requests_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED DEFAULT NULL,
    allocation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expected_return_date DATE DEFAULT NULL,
    allocated_by INT UNSIGNED NOT NULL,
    allocation_notes TEXT DEFAULT NULL,
    return_requested_at DATETIME DEFAULT NULL,
    actual_return_date DATETIME DEFAULT NULL,
    return_condition ENUM('new','good','fair','poor','damaged') DEFAULT NULL,
    return_notes TEXT DEFAULT NULL,
    returned_by INT UNSIGNED DEFAULT NULL,
    active_asset_id INT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN actual_return_date IS NULL THEN asset_id ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_allocations_active_asset (active_asset_id),
    UNIQUE KEY uq_asset_allocations_request (request_id),
    KEY idx_asset_allocations_user (user_id, actual_return_date),
    CONSTRAINT fk_asset_allocations_asset FOREIGN KEY (asset_id)
        REFERENCES assets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_allocations_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_allocations_request FOREIGN KEY (request_id)
        REFERENCES asset_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_asset_allocations_allocator FOREIGN KEY (allocated_by)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_allocations_returner FOREIGN KEY (returned_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_maintenance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    ticket_id INT DEFAULT NULL,
    maintenance_type VARCHAR(100) NOT NULL,
    reported_issue TEXT NOT NULL,
    start_date DATE NOT NULL,
    completion_date DATE DEFAULT NULL,
    technician VARCHAR(160) DEFAULT NULL,
    repair_cost DECIMAL(12,2) DEFAULT NULL,
    work_performed TEXT DEFAULT NULL,
    status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    remarks TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_maintenance_asset (asset_id, start_date),
    KEY idx_asset_maintenance_ticket (ticket_id),
    CONSTRAINT fk_asset_maintenance_asset FOREIGN KEY (asset_id)
        REFERENCES assets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_maintenance_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_asset_maintenance_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_contracts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    contract_type ENUM('warranty','amc') NOT NULL,
    provider VARCHAR(160) NOT NULL,
    reference_number VARCHAR(120) DEFAULT NULL,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    renewal_date DATE DEFAULT NULL,
    cost DECIMAL(12,2) DEFAULT NULL,
    status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    remarks TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_contracts_expiry (contract_type, expiry_date, status),
    KEY idx_asset_contracts_asset (asset_id),
    CONSTRAINT fk_asset_contracts_asset FOREIGN KEY (asset_id)
        REFERENCES assets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_contracts_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_activity_asset (asset_id, created_at),
    CONSTRAINT fk_asset_activity_asset FOREIGN KEY (asset_id)
        REFERENCES assets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_activity_actor FOREIGN KEY (actor_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_asset_settings_updated_by FOREIGN KEY (updated_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO asset_settings (setting_key, setting_value)
VALUES ('contract_warning_days', '30');

-- Add the reverse request-to-allocation reference if this migration is rerun.
SET @asset_request_allocation_fk_exists := (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'asset_requests'
      AND CONSTRAINT_NAME = 'fk_asset_requests_allocation'
);
SET @asset_request_allocation_fk_sql := IF(
    @asset_request_allocation_fk_exists = 0,
    'ALTER TABLE asset_requests ADD CONSTRAINT fk_asset_requests_allocation FOREIGN KEY (allocation_id) REFERENCES asset_allocations(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE asset_request_allocation_fk_stmt FROM @asset_request_allocation_fk_sql;
EXECUTE asset_request_allocation_fk_stmt;
DEALLOCATE PREPARE asset_request_allocation_fk_stmt;

-- Add the optional one-asset-per-ticket link only when it is absent.
-- The new column references assets.id, which is an unsigned INT.
SET @asset_ticket_link_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'asset_id'
);
SET @asset_ticket_link_sql := IF(
    @asset_ticket_link_exists = 0,
    'ALTER TABLE tickets ADD COLUMN asset_id INT UNSIGNED DEFAULT NULL, ADD KEY idx_tickets_asset_id (asset_id), ADD CONSTRAINT fk_tickets_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE asset_ticket_link_stmt FROM @asset_ticket_link_sql;
EXECUTE asset_ticket_link_stmt;
DEALLOCATE PREPARE asset_ticket_link_stmt;