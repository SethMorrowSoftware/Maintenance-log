-- =============================================================================
--  RideLog — Database schema
--  Maintenance log & dashboard for go-karts and amusement rides.
--
--  TARGET: MySQL 5.7+ / MariaDB 10.3+ (typical cPanel shared hosting).
--  Deliberately avoids CHECK constraints, JSON columns, generated columns,
--  window functions, functional indexes and utf8mb4_0900_* collations so that
--  it loads on the oldest engine a cPanel host is likely to offer.
--
--  PLACEHOLDERS
--  ------------
--  Every table name is written inside curly braces, e.g. {assets}. Before
--  execution the installer replaces {name} with <prefix>name, so with a prefix
--  of "rl_" the table {assets} becomes rl_assets. Foreign-key constraint names
--  embed a braced table name too (e.g. fk_{assets}_category), because InnoDB
--  requires constraint names to be unique across the whole database — this
--  keeps two RideLog installs in one database from colliding.
--
--  IDEMPOTENT
--  ----------
--  Uses CREATE TABLE IF NOT EXISTS throughout, so re-running is harmless.
--
--  EXECUTION
--  ---------
--  Statements are separated by a semicolon at the end of a line. There are no
--  triggers, procedures, views or DELIMITER blocks, so a simple splitter can
--  execute this file statement by statement.
--
--  To load it by hand for testing:
--    sed -e 's/{\([a-z_][a-z0-9_]*\)}/rl_\1/g' schema.sql | mysql -u user -p dbname
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. users
--    Staff accounts. Soft-deleted so historical maintenance logs keep a valid
--    author reference after someone leaves.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {users} (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username              VARCHAR(64)  NOT NULL,
  email                 VARCHAR(191) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  first_name            VARCHAR(80)  NOT NULL DEFAULT '',
  last_name             VARCHAR(80)  NOT NULL DEFAULT '',
  role                  ENUM('admin','manager','technician','viewer','staff') NOT NULL DEFAULT 'technician',
  phone                 VARCHAR(40)  NOT NULL DEFAULT '',
  job_title             VARCHAR(100) NOT NULL DEFAULT '',
  employee_number       VARCHAR(50)  NOT NULL DEFAULT '',
  avatar_path           VARCHAR(255) DEFAULT NULL,
  timezone              VARCHAR(64)  DEFAULT NULL,
  theme                 ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  is_active             TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password  TINYINT(1)   NOT NULL DEFAULT 0,
  failed_login_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until          DATETIME     DEFAULT NULL,
  last_login_at         DATETIME     DEFAULT NULL,
  last_login_ip         VARCHAR(45)  DEFAULT NULL,
  password_changed_at   DATETIME     DEFAULT NULL,
  notify_email          TINYINT(1)   NOT NULL DEFAULT 1,
  notes                 TEXT         DEFAULT NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by            INT UNSIGNED DEFAULT NULL,
  updated_by            INT UNSIGNED DEFAULT NULL,
  deleted_at            DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_active (is_active, deleted_at),
  KEY idx_users_deleted (deleted_at),
  KEY idx_users_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. remember_tokens
--    "Remember me" cookies, selector/validator pattern. Only a SHA-256 hash of
--    the validator is stored, so a database leak cannot be replayed as a login.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {remember_tokens} (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  selector        VARCHAR(32)  NOT NULL,
  validator_hash  CHAR(64)     NOT NULL,
  expires_at      DATETIME     NOT NULL,
  user_agent      VARCHAR(255) NOT NULL DEFAULT '',
  ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
  last_used_at    DATETIME     DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_user (user_id),
  KEY idx_remember_expires (expires_at),
  CONSTRAINT fk_{remember_tokens}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3. password_resets
--    Single-use, time-limited reset links. Same selector/validator design.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {password_resets} (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  selector    VARCHAR(32)  NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     DEFAULT NULL,
  ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_selector (selector),
  KEY idx_reset_user (user_id),
  KEY idx_reset_expires (expires_at),
  CONSTRAINT fk_{password_resets}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 4. login_attempts
--    Feeds brute-force throttling. No foreign key: attempts are recorded for
--    usernames that may not exist.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {login_attempts} (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username    VARCHAR(191) NOT NULL DEFAULT '',
  ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
  success     TINYINT(1)   NOT NULL DEFAULT 0,
  user_agent  VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_username (username, created_at),
  KEY idx_attempts_ip (ip_address, created_at),
  KEY idx_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 5. settings
--    Key/value application configuration edited from the Settings screen.
--    Columns are named setting_key / setting_value to dodge reserved words.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {settings} (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key    VARCHAR(191) NOT NULL,
  setting_value  LONGTEXT     DEFAULT NULL,
  setting_type   VARCHAR(20)  NOT NULL DEFAULT 'string',
  setting_group  VARCHAR(50)  NOT NULL DEFAULT 'general',
  is_public      TINYINT(1)   NOT NULL DEFAULT 0,
  label          VARCHAR(150) NOT NULL DEFAULT '',
  description    VARCHAR(255) NOT NULL DEFAULT '',
  sort_order     SMALLINT     NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  KEY idx_settings_group (setting_group, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 6. locations
--    Where an asset physically lives: the kart track, the midway, the shop.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {locations} (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  building    VARCHAR(120) NOT NULL DEFAULT '',
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_locations_name (name),
  KEY idx_locations_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 7. asset_categories
--    Go-Kart, Kiddie Ride, Major Ride, Water Attraction, and so on.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {asset_categories} (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name               VARCHAR(120) NOT NULL,
  slug               VARCHAR(120) NOT NULL,
  description        VARCHAR(255) NOT NULL DEFAULT '',
  icon               VARCHAR(60)  NOT NULL DEFAULT 'tool',
  color              VARCHAR(7)   NOT NULL DEFAULT '#4f46e5',
  default_meter_type ENUM('none','hours','miles','cycles','laps') NOT NULL DEFAULT 'none',
  sort_order         SMALLINT     NOT NULL DEFAULT 0,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 8. assets
--    The go-karts, rides and equipment being maintained. Soft-deleted so their
--    maintenance history survives.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {assets} (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_tag           VARCHAR(60)  NOT NULL,
  name                VARCHAR(150) NOT NULL,
  category_id         INT UNSIGNED DEFAULT NULL,
  location_id         INT UNSIGNED DEFAULT NULL,
  status              ENUM('in_service','out_of_service','maintenance','retired') NOT NULL DEFAULT 'in_service',
  criticality         ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  manufacturer        VARCHAR(120) NOT NULL DEFAULT '',
  model               VARCHAR(120) NOT NULL DEFAULT '',
  serial_number       VARCHAR(120) NOT NULL DEFAULT '',
  vin                 VARCHAR(60)  NOT NULL DEFAULT '',
  year_manufactured   SMALLINT UNSIGNED DEFAULT NULL,
  purchase_date       DATE         DEFAULT NULL,
  purchase_cost       DECIMAL(12,2) DEFAULT NULL,
  warranty_expires    DATE         DEFAULT NULL,
  engine_make         VARCHAR(120) NOT NULL DEFAULT '',
  engine_model        VARCHAR(120) NOT NULL DEFAULT '',
  engine_serial       VARCHAR(120) NOT NULL DEFAULT '',
  fuel_type           VARCHAR(40)  NOT NULL DEFAULT '',
  tire_size           VARCHAR(60)  NOT NULL DEFAULT '',
  capacity_passengers SMALLINT UNSIGNED DEFAULT NULL,
  meter_type          ENUM('none','hours','miles','cycles','laps') NOT NULL DEFAULT 'none',
  meter_reading       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  meter_updated_at    DATETIME     DEFAULT NULL,
  in_service_date     DATE         DEFAULT NULL,
  retired_date        DATE         DEFAULT NULL,
  description         TEXT         DEFAULT NULL,
  notes               TEXT         DEFAULT NULL,
  custom_data         TEXT         DEFAULT NULL,
  image_path          VARCHAR(255) DEFAULT NULL,
  qr_slug             VARCHAR(32)  DEFAULT NULL,
  sort_order          SMALLINT     NOT NULL DEFAULT 0,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by          INT UNSIGNED DEFAULT NULL,
  updated_by          INT UNSIGNED DEFAULT NULL,
  deleted_at          DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assets_tag (asset_tag),
  UNIQUE KEY uq_assets_qr (qr_slug),
  KEY idx_assets_category (category_id),
  KEY idx_assets_location (location_id),
  KEY idx_assets_status (status, deleted_at),
  KEY idx_assets_deleted (deleted_at),
  KEY idx_assets_name (name),
  KEY idx_assets_criticality (criticality),
  KEY idx_assets_listing (deleted_at, status, name),
  CONSTRAINT fk_{assets}_category FOREIGN KEY (category_id)
    REFERENCES {asset_categories} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{assets}_location FOREIGN KEY (location_id)
    REFERENCES {locations} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 9. meter_readings
--    Hour-meter / lap-counter history. Every reading is kept so usage can be
--    charted and meter-based PM intervals can be verified.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {meter_readings} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_id          INT UNSIGNED NOT NULL,
  reading           DECIMAL(12,2) NOT NULL,
  previous_reading  DECIMAL(12,2) DEFAULT NULL,
  recorded_at       DATETIME     NOT NULL,
  user_id           INT UNSIGNED DEFAULT NULL,
  source            ENUM('manual','maintenance_log','inspection','import') NOT NULL DEFAULT 'manual',
  reference_id      INT UNSIGNED DEFAULT NULL,
  notes             VARCHAR(255) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_meter_asset (asset_id, recorded_at),
  KEY idx_meter_user (user_id),
  CONSTRAINT fk_{meter_readings}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{meter_readings}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 10. parts
--     Spare-parts catalogue and stock levels.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {parts} (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  part_number           VARCHAR(100) NOT NULL,
  name                  VARCHAR(191) NOT NULL,
  description           TEXT         DEFAULT NULL,
  category              VARCHAR(100) NOT NULL DEFAULT '',
  manufacturer          VARCHAR(120) NOT NULL DEFAULT '',
  supplier              VARCHAR(120) NOT NULL DEFAULT '',
  supplier_part_number  VARCHAR(100) NOT NULL DEFAULT '',
  unit_cost             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  unit_of_measure       VARCHAR(20)  NOT NULL DEFAULT 'each',
  quantity_on_hand      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reorder_level         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reorder_quantity      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  location_bin          VARCHAR(60)  NOT NULL DEFAULT '',
  image_path            VARCHAR(255) DEFAULT NULL,
  notes                 TEXT         DEFAULT NULL,
  is_active             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by            INT UNSIGNED DEFAULT NULL,
  updated_by            INT UNSIGNED DEFAULT NULL,
  deleted_at            DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_parts_number (part_number),
  KEY idx_parts_name (name),
  KEY idx_parts_active (is_active, deleted_at),
  KEY idx_parts_deleted (deleted_at),
  KEY idx_parts_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 11. part_transactions
--     Every stock movement, so quantity_on_hand is always explainable.
--     reference_type/reference_id are polymorphic (maintenance_log, work_order,
--     manual) and therefore intentionally have no foreign key.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {part_transactions} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  part_id           INT UNSIGNED NOT NULL,
  transaction_type  ENUM('in','out','adjust') NOT NULL,
  quantity          DECIMAL(12,2) NOT NULL,
  unit_cost         DECIMAL(12,2) DEFAULT NULL,
  balance_after     DECIMAL(12,2) DEFAULT NULL,
  reference_type    VARCHAR(40)  NOT NULL DEFAULT '',
  reference_id      INT UNSIGNED DEFAULT NULL,
  user_id           INT UNSIGNED DEFAULT NULL,
  notes             VARCHAR(255) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_parttx_part (part_id, created_at),
  KEY idx_parttx_user (user_id),
  KEY idx_parttx_ref (reference_type, reference_id),
  CONSTRAINT fk_{part_transactions}_part FOREIGN KEY (part_id)
    REFERENCES {parts} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{part_transactions}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 12. checklists
--     Inspection templates — e.g. "Daily Go-Kart Pre-Operation Inspection".
-- -----------------------------------------------------------------------------
--     A checklist is for every asset, one category, one asset, or an area (a
--     location: "Bowling opening checks") with no machine involved at all.
--
--     The "when" columns make a checklist a timed check: due_time is the local
--     wall-clock deadline on the days in due_days (ISO weekday digits, 1 = Mon
--     … 7 = Sun). A timed check that is not finished by then can post to Slack
--     (alert_missed, optionally to its own channel and with its own mention),
--     remind people remind_minutes beforehand, and post again with a mention
--     escalate_minutes after the deadline. All optional.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {checklists} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(191) NOT NULL,
  description       TEXT         DEFAULT NULL,
  applies_to        ENUM('all','category','asset','location') NOT NULL DEFAULT 'all',
  category_id       INT UNSIGNED DEFAULT NULL,
  asset_id          INT UNSIGNED DEFAULT NULL,
  location_id       INT UNSIGNED DEFAULT NULL,
  frequency         ENUM('daily','weekly','monthly','quarterly','annual','preseason','adhoc') NOT NULL DEFAULT 'daily',
  estimated_minutes SMALLINT UNSIGNED DEFAULT NULL,
  due_time          TIME         DEFAULT NULL,
  due_days          VARCHAR(7)   NOT NULL DEFAULT '1234567',
  remind_minutes    SMALLINT UNSIGNED DEFAULT NULL,
  alert_missed      TINYINT(1)   NOT NULL DEFAULT 1,
  alert_channel     VARCHAR(80)  NOT NULL DEFAULT '',
  alert_mention     VARCHAR(80)  NOT NULL DEFAULT '',
  escalate_minutes  SMALLINT UNSIGNED DEFAULT NULL,
  require_signature TINYINT(1)   NOT NULL DEFAULT 1,
  require_meter     TINYINT(1)   NOT NULL DEFAULT 0,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        INT UNSIGNED DEFAULT NULL,
  updated_by        INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_checklists_active (is_active),
  KEY idx_checklists_scope (applies_to, category_id, asset_id),
  KEY idx_checklists_location (location_id),
  KEY idx_checklists_frequency (frequency),
  KEY idx_checklists_due (is_active, due_time),
  CONSTRAINT fk_{checklists}_category FOREIGN KEY (category_id)
    REFERENCES {asset_categories} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{checklists}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{checklists}_location FOREIGN KEY (location_id)
    REFERENCES {locations} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 13. checklist_items
--     The individual line items of a template, grouped into sections.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {checklist_items} (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  checklist_id   INT UNSIGNED NOT NULL,
  section        VARCHAR(120) NOT NULL DEFAULT '',
  item_text      VARCHAR(255) NOT NULL,
  description    VARCHAR(500) NOT NULL DEFAULT '',
  response_type  ENUM('pass_fail','pass_fail_na','yes_no','text','number','meter') NOT NULL DEFAULT 'pass_fail_na',
  is_required    TINYINT(1)   NOT NULL DEFAULT 1,
  is_critical    TINYINT(1)   NOT NULL DEFAULT 0,
  allow_photo    TINYINT(1)   NOT NULL DEFAULT 1,
  expected_value VARCHAR(100) NOT NULL DEFAULT '',
  unit           VARCHAR(20)  NOT NULL DEFAULT '',
  min_value      DECIMAL(12,2) DEFAULT NULL,
  max_value      DECIMAL(12,2) DEFAULT NULL,
  sort_order     SMALLINT     NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_items_checklist (checklist_id, sort_order),
  KEY idx_items_critical (is_critical),
  CONSTRAINT fk_{checklist_items}_checklist FOREIGN KEY (checklist_id)
    REFERENCES {checklists} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 13a. checklist_alerts
--      One row per alert the checks job has sent about a timed checklist on a
--      day: the reminder, the "not finished" post, the escalation. It is what
--      stops a job that runs every five minutes saying the same thing twice,
--      and it is the "alerts sent" line on the board.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {checklist_alerts} (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  checklist_id  INT UNSIGNED NOT NULL,
  due_date      DATE         NOT NULL,
  kind          ENUM('reminder','missed','escalation') NOT NULL,
  missing_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  channel       VARCHAR(80)  NOT NULL DEFAULT '',
  ok            TINYINT(1)   NOT NULL DEFAULT 1,
  detail        VARCHAR(255) NOT NULL DEFAULT '',
  sent_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_alert (checklist_id, due_date, kind),
  KEY idx_checklist_alerts_date (due_date),
  CONSTRAINT fk_{checklist_alerts}_checklist FOREIGN KEY (checklist_id)
    REFERENCES {checklists} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 13b. user_areas / user_checklists
--      Where somebody works. A person with any row in either table sees only
--      the checks for those areas and checklists (administrators always see
--      everything). Staff accounts live on these; anybody else can be limited
--      the same way.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {user_areas} (
  user_id      INT UNSIGNED NOT NULL,
  location_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, location_id),
  KEY idx_user_areas_location (location_id),
  CONSTRAINT fk_{user_areas}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{user_areas}_location FOREIGN KEY (location_id)
    REFERENCES {locations} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {user_checklists} (
  user_id       INT UNSIGNED NOT NULL,
  checklist_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, checklist_id),
  KEY idx_user_checklists_checklist (checklist_id),
  CONSTRAINT fk_{user_checklists}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{user_checklists}_checklist FOREIGN KEY (checklist_id)
    REFERENCES {checklists} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 14. maintenance_schedules
--     Preventive-maintenance plans, driven by calendar interval or by meter.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {maintenance_schedules} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_id          INT UNSIGNED NOT NULL,
  name              VARCHAR(191) NOT NULL,
  description       TEXT         DEFAULT NULL,
  log_type          ENUM('preventive','corrective','repair','inspection','daily_check','cleaning','modification','safety','other') NOT NULL DEFAULT 'preventive',
  checklist_id      INT UNSIGNED DEFAULT NULL,
  frequency_type    ENUM('daily','weekly','monthly','quarterly','semiannual','annual','days','weeks','months','meter') NOT NULL DEFAULT 'monthly',
  frequency_value   INT UNSIGNED NOT NULL DEFAULT 1,
  meter_interval    DECIMAL(12,2) DEFAULT NULL,
  lead_time_days    SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  estimated_hours   DECIMAL(8,2) DEFAULT NULL,
  assigned_to       INT UNSIGNED DEFAULT NULL,
  priority          ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  instructions      TEXT         DEFAULT NULL,
  last_performed_at DATETIME     DEFAULT NULL,
  last_meter        DECIMAL(12,2) DEFAULT NULL,
  last_log_id       INT UNSIGNED DEFAULT NULL,
  next_due_date     DATE         DEFAULT NULL,
  next_due_meter    DECIMAL(12,2) DEFAULT NULL,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        INT UNSIGNED DEFAULT NULL,
  updated_by        INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_sched_asset (asset_id, is_active),
  KEY idx_sched_due (next_due_date, is_active),
  KEY idx_sched_assigned (assigned_to),
  KEY idx_sched_checklist (checklist_id),
  CONSTRAINT fk_{maintenance_schedules}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_schedules}_checklist FOREIGN KEY (checklist_id)
    REFERENCES {checklists} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_schedules}_assignee FOREIGN KEY (assigned_to)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 15. work_orders
--     Reported problems, from "operator says kart 4 pulls left" through to
--     completion. inspection_id has no foreign key because {inspections} is
--     created after this table and also points back here.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {work_orders} (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wo_number            VARCHAR(30)  NOT NULL,
  asset_id             INT UNSIGNED DEFAULT NULL,
  title                VARCHAR(191) NOT NULL,
  description          TEXT         DEFAULT NULL,
  priority             ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status               ENUM('open','assigned','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'open',
  source               ENUM('operator_report','inspection','preventive','breakdown','other') NOT NULL DEFAULT 'operator_report',
  reported_by          INT UNSIGNED DEFAULT NULL,
  assigned_to          INT UNSIGNED DEFAULT NULL,
  schedule_id          INT UNSIGNED DEFAULT NULL,
  inspection_id        INT UNSIGNED DEFAULT NULL,
  due_date             DATE         DEFAULT NULL,
  started_at           DATETIME     DEFAULT NULL,
  completed_at         DATETIME     DEFAULT NULL,
  closed_by            INT UNSIGNED DEFAULT NULL,
  resolution           TEXT         DEFAULT NULL,
  downtime_minutes     INT UNSIGNED DEFAULT NULL,
  estimated_hours      DECIMAL(8,2) DEFAULT NULL,
  actual_hours         DECIMAL(8,2) DEFAULT NULL,
  is_safety_issue      TINYINT(1)   NOT NULL DEFAULT 0,
  took_out_of_service  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by           INT UNSIGNED DEFAULT NULL,
  updated_by           INT UNSIGNED DEFAULT NULL,
  deleted_at           DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wo_number (wo_number),
  KEY idx_wo_asset (asset_id),
  KEY idx_wo_status (status, deleted_at),
  KEY idx_wo_assigned (assigned_to, status),
  KEY idx_wo_priority (priority),
  KEY idx_wo_due (due_date),
  KEY idx_wo_deleted (deleted_at),
  KEY idx_wo_inspection (inspection_id),
  KEY idx_wo_created (created_at),
  CONSTRAINT fk_{work_orders}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{work_orders}_reporter FOREIGN KEY (reported_by)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{work_orders}_assignee FOREIGN KEY (assigned_to)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{work_orders}_closer FOREIGN KEY (closed_by)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{work_orders}_schedule FOREIGN KEY (schedule_id)
    REFERENCES {maintenance_schedules} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 16. work_order_comments
--     Discussion thread plus an automatic entry on every status change.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {work_order_comments} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id     INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED DEFAULT NULL,
  comment           TEXT         NOT NULL,
  is_status_change  TINYINT(1)   NOT NULL DEFAULT 0,
  old_status        VARCHAR(30)  NOT NULL DEFAULT '',
  new_status        VARCHAR(30)  NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wocomment_wo (work_order_id, created_at),
  KEY idx_wocomment_user (user_id),
  CONSTRAINT fk_{work_order_comments}_wo FOREIGN KEY (work_order_id)
    REFERENCES {work_orders} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{work_order_comments}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 17. inspections
--     A completed run of a checklist against one asset. log_id has no foreign
--     key because {maintenance_logs} is created after this table.
-- -----------------------------------------------------------------------------
--     asset_id is NULL for a run of an area checklist — the check is of the
--     bowling centre, not of a machine — and location_id says which area.
--     due_at is the deadline the checklist had when the run started (UTC), so
--     was_late stays true to what was asked at the time.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {inspections} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  checklist_id      INT UNSIGNED DEFAULT NULL,
  asset_id          INT UNSIGNED DEFAULT NULL,
  location_id       INT UNSIGNED DEFAULT NULL,
  user_id           INT UNSIGNED DEFAULT NULL,
  schedule_id       INT UNSIGNED DEFAULT NULL,
  work_order_id     INT UNSIGNED DEFAULT NULL,
  log_id            INT UNSIGNED DEFAULT NULL,
  checklist_name    VARCHAR(191) NOT NULL DEFAULT '',
  status            ENUM('in_progress','passed','failed','completed') NOT NULL DEFAULT 'in_progress',
  started_at        DATETIME     NOT NULL,
  completed_at      DATETIME     DEFAULT NULL,
  due_at            DATETIME     DEFAULT NULL,
  was_late          TINYINT(1)   NOT NULL DEFAULT 0,
  duration_minutes  INT UNSIGNED DEFAULT NULL,
  meter_reading     DECIMAL(12,2) DEFAULT NULL,
  passed_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  failed_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  na_count          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  critical_failed   TINYINT(1)   NOT NULL DEFAULT 0,
  took_out_of_service TINYINT(1) NOT NULL DEFAULT 0,
  notes             TEXT         DEFAULT NULL,
  signature_name    VARCHAR(120) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_insp_asset (asset_id, started_at),
  KEY idx_insp_checklist (checklist_id),
  KEY idx_insp_location (location_id),
  KEY idx_insp_user (user_id),
  KEY idx_insp_status (status),
  KEY idx_insp_started (started_at),
  KEY idx_insp_completed (completed_at),
  KEY idx_insp_schedule (schedule_id),
  KEY idx_insp_wo (work_order_id),
  KEY idx_insp_log (log_id),
  CONSTRAINT fk_{inspections}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{inspections}_location FOREIGN KEY (location_id)
    REFERENCES {locations} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{inspections}_checklist FOREIGN KEY (checklist_id)
    REFERENCES {checklists} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{inspections}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{inspections}_schedule FOREIGN KEY (schedule_id)
    REFERENCES {maintenance_schedules} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{inspections}_wo FOREIGN KEY (work_order_id)
    REFERENCES {work_orders} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 18. inspection_items
--     One row per answered checklist line. item_text is snapshotted so editing
--     a template later never rewrites history.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {inspection_items} (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inspection_id      INT UNSIGNED NOT NULL,
  checklist_item_id  INT UNSIGNED DEFAULT NULL,
  section            VARCHAR(120) NOT NULL DEFAULT '',
  item_text          VARCHAR(255) NOT NULL,
  item_description   VARCHAR(500) NOT NULL DEFAULT '',
  response_type      VARCHAR(20)  NOT NULL DEFAULT 'pass_fail_na',
  response           VARCHAR(10)  NOT NULL DEFAULT '',
  value_text         VARCHAR(500) NOT NULL DEFAULT '',
  value_number       DECIMAL(12,2) DEFAULT NULL,
  -- The standard this answer was judged against, as it stood when the check
  -- was started. Copied from the checklist line rather than read back from it,
  -- so editing or deleting that line cannot rewrite a filed inspection. See
  -- install/migrations/003_inspection_item_snapshot.sql.
  unit               VARCHAR(20)  NOT NULL DEFAULT '',
  min_value          DECIMAL(12,2) DEFAULT NULL,
  max_value          DECIMAL(12,2) DEFAULT NULL,
  is_critical        TINYINT(1)   NOT NULL DEFAULT 0,
  notes              VARCHAR(500) NOT NULL DEFAULT '',
  photo_path         VARCHAR(255) DEFAULT NULL,
  sort_order         SMALLINT     NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inspitem_inspection (inspection_id, sort_order),
  KEY idx_inspitem_item (checklist_item_id),
  KEY idx_inspitem_response (response),
  CONSTRAINT fk_{inspection_items}_inspection FOREIGN KEY (inspection_id)
    REFERENCES {inspections} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{inspection_items}_item FOREIGN KEY (checklist_item_id)
    REFERENCES {checklist_items} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 19. maintenance_logs
--     THE CORE TABLE. One row every time somebody works on a kart or a ride.
--     Records who did it, when (date AND time, stored UTC), and what was done.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {maintenance_logs} (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_id          INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED DEFAULT NULL,
  log_type          ENUM('preventive','corrective','repair','inspection','daily_check','cleaning','modification','safety','other') NOT NULL DEFAULT 'corrective',
  title             VARCHAR(191) NOT NULL,
  description       TEXT         DEFAULT NULL,
  work_performed    TEXT         DEFAULT NULL,
  performed_at      DATETIME     NOT NULL,
  completed_at      DATETIME     DEFAULT NULL,
  labor_hours       DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  labor_rate        DECIMAL(12,2) DEFAULT NULL,
  labor_cost        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  parts_cost        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  other_cost        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_cost        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  meter_reading     DECIMAL(12,2) DEFAULT NULL,
  downtime_minutes  INT UNSIGNED DEFAULT NULL,
  status_before     ENUM('in_service','out_of_service','maintenance','retired') DEFAULT NULL,
  status_after      ENUM('in_service','out_of_service','maintenance','retired') DEFAULT NULL,
  schedule_id       INT UNSIGNED DEFAULT NULL,
  work_order_id     INT UNSIGNED DEFAULT NULL,
  inspection_id     INT UNSIGNED DEFAULT NULL,
  is_completed      TINYINT(1)   NOT NULL DEFAULT 1,
  requires_followup TINYINT(1)   NOT NULL DEFAULT 0,
  followup_notes    TEXT         DEFAULT NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        INT UNSIGNED DEFAULT NULL,
  updated_by        INT UNSIGNED DEFAULT NULL,
  deleted_at        DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_logs_asset (asset_id, performed_at),
  KEY idx_logs_user (user_id, performed_at),
  KEY idx_logs_type (log_type),
  KEY idx_logs_performed (performed_at),
  KEY idx_logs_deleted (deleted_at),
  KEY idx_logs_schedule (schedule_id),
  KEY idx_logs_wo (work_order_id),
  KEY idx_logs_inspection (inspection_id),
  KEY idx_logs_followup (requires_followup, deleted_at),
  KEY idx_logs_listing (deleted_at, performed_at),
  CONSTRAINT fk_{maintenance_logs}_asset FOREIGN KEY (asset_id)
    REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_logs}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_logs}_schedule FOREIGN KEY (schedule_id)
    REFERENCES {maintenance_schedules} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_logs}_wo FOREIGN KEY (work_order_id)
    REFERENCES {work_orders} (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_logs}_inspection FOREIGN KEY (inspection_id)
    REFERENCES {inspections} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 20. maintenance_log_parts
--     Parts consumed on a job. part_id is nullable so a technician can record
--     an off-the-shelf part that is not in the inventory catalogue.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {maintenance_log_parts} (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  log_id          INT UNSIGNED NOT NULL,
  part_id         INT UNSIGNED DEFAULT NULL,
  part_number     VARCHAR(100) NOT NULL DEFAULT '',
  part_name       VARCHAR(191) NOT NULL,
  quantity        DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_cost       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_cost      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  from_inventory  TINYINT(1)   NOT NULL DEFAULT 0,
  notes           VARCHAR(255) NOT NULL DEFAULT '',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logparts_log (log_id),
  KEY idx_logparts_part (part_id),
  CONSTRAINT fk_{maintenance_log_parts}_log FOREIGN KEY (log_id)
    REFERENCES {maintenance_logs} (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_{maintenance_log_parts}_part FOREIGN KEY (part_id)
    REFERENCES {parts} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 21. attachments
--     Photos and documents. Polymorphic by design (entity_type/entity_id), so
--     no foreign key on the target.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {attachments} (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type    ENUM('asset','maintenance_log','work_order','inspection','part','user','setting') NOT NULL,
  entity_id      INT UNSIGNED NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  stored_name    VARCHAR(100) NOT NULL,
  file_path      VARCHAR(255) NOT NULL,
  thumb_path     VARCHAR(255) DEFAULT NULL,
  mime_type      VARCHAR(120) NOT NULL DEFAULT '',
  file_size      INT UNSIGNED NOT NULL DEFAULT 0,
  is_image       TINYINT(1)   NOT NULL DEFAULT 0,
  caption        VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by    INT UNSIGNED DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attach_entity (entity_type, entity_id),
  KEY idx_attach_user (uploaded_by),
  CONSTRAINT fk_{attachments}_user FOREIGN KEY (uploaded_by)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 22. audit_log
--     Who changed what, when. user_name is a snapshot so the trail stays
--     readable after an account is deleted.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {audit_log} (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED DEFAULT NULL,
  user_name    VARCHAR(150) NOT NULL DEFAULT '',
  action       VARCHAR(60)  NOT NULL,
  entity_type  VARCHAR(50)  NOT NULL DEFAULT '',
  entity_id    INT UNSIGNED DEFAULT NULL,
  description  VARCHAR(500) NOT NULL DEFAULT '',
  old_values   LONGTEXT     DEFAULT NULL,
  new_values   LONGTEXT     DEFAULT NULL,
  ip_address   VARCHAR(45)  NOT NULL DEFAULT '',
  user_agent   VARCHAR(255) NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_{audit_log}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 23. notifications
--     In-app bell messages: PM due, work order assigned, inspection failed.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {notifications} (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  type         ENUM('pm_due','pm_overdue','wo_assigned','wo_updated','inspection_failed','checklist_missed','low_stock','system') NOT NULL DEFAULT 'system',
  title        VARCHAR(191) NOT NULL,
  message      VARCHAR(500) NOT NULL DEFAULT '',
  link         VARCHAR(255) NOT NULL DEFAULT '',
  entity_type  VARCHAR(50)  NOT NULL DEFAULT '',
  entity_id    INT UNSIGNED DEFAULT NULL,
  is_read      TINYINT(1)   NOT NULL DEFAULT 0,
  read_at      DATETIME     DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at),
  KEY idx_notif_created (created_at),
  KEY idx_notif_dedupe (user_id, type, entity_type, entity_id),
  CONSTRAINT fk_{notifications}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 24. saved_reports
--     A named set of report filters someone wants to re-run.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {saved_reports} (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(150) NOT NULL,
  report_key  VARCHAR(60)  NOT NULL,
  filters     LONGTEXT     DEFAULT NULL,
  is_shared   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_saved_user (user_id, report_key),
  KEY idx_saved_shared (is_shared),
  CONSTRAINT fk_{saved_reports}_user FOREIGN KEY (user_id)
    REFERENCES {users} (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
