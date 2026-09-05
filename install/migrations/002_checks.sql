-- Timed checks, area checklists and the Staff role.
--
-- Fresh installs get all of this from schema.sql. This brings an older
-- database up to date. The three new tables (checklist_alerts, user_areas,
-- user_checklists) are created by re-running schema.sql, which the upgrade
-- runner does before it gets here, so only the changes to existing tables
-- live in this file.

-- A fifth role: staff who run the checks for their area and nothing else.
ALTER TABLE {users}
    MODIFY role ENUM('admin','manager','technician','viewer','staff') NOT NULL DEFAULT 'technician';

-- A checklist can be for an area, and can have a deadline and alerts.
ALTER TABLE {checklists}
    MODIFY applies_to ENUM('all','category','asset','location') NOT NULL DEFAULT 'all';

ALTER TABLE {checklists}
    ADD COLUMN location_id      INT UNSIGNED DEFAULT NULL AFTER asset_id,
    ADD COLUMN due_time         TIME         DEFAULT NULL AFTER estimated_minutes,
    ADD COLUMN due_days         VARCHAR(7)   NOT NULL DEFAULT '1234567' AFTER due_time,
    ADD COLUMN remind_minutes   SMALLINT UNSIGNED DEFAULT NULL AFTER due_days,
    ADD COLUMN alert_missed     TINYINT(1)   NOT NULL DEFAULT 1 AFTER remind_minutes,
    ADD COLUMN alert_channel    VARCHAR(80)  NOT NULL DEFAULT '' AFTER alert_missed,
    ADD COLUMN alert_mention    VARCHAR(80)  NOT NULL DEFAULT '' AFTER alert_channel,
    ADD COLUMN escalate_minutes SMALLINT UNSIGNED DEFAULT NULL AFTER alert_mention,
    ADD KEY idx_checklists_location (location_id),
    ADD KEY idx_checklists_due (is_active, due_time),
    ADD CONSTRAINT fk_{checklists}_location FOREIGN KEY (location_id)
        REFERENCES {locations} (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- An inspection of an area has no machine. The foreign key is dropped and
-- re-added around the change because some MySQL versions refuse to alter a
-- column while a constraint points at it.
ALTER TABLE {inspections} DROP FOREIGN KEY fk_{inspections}_asset;

ALTER TABLE {inspections}
    MODIFY asset_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN location_id INT UNSIGNED DEFAULT NULL AFTER asset_id,
    ADD COLUMN due_at      DATETIME     DEFAULT NULL AFTER completed_at,
    ADD COLUMN was_late    TINYINT(1)   NOT NULL DEFAULT 0 AFTER due_at,
    ADD KEY idx_insp_location (location_id),
    ADD KEY idx_insp_completed (completed_at),
    ADD CONSTRAINT fk_{inspections}_asset FOREIGN KEY (asset_id)
        REFERENCES {assets} (id) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_{inspections}_location FOREIGN KEY (location_id)
        REFERENCES {locations} (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- The bell can say a check was missed.
ALTER TABLE {notifications}
    MODIFY type ENUM('pm_due','pm_overdue','wo_assigned','wo_updated','inspection_failed','checklist_missed','low_stock','system') NOT NULL DEFAULT 'system';
