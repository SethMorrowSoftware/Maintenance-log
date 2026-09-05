-- Extra fields on machines (Settings → Fields) are stored as one JSON object
-- per machine, so adding a field never needs a schema change. Fresh installs
-- get this column from schema.sql; this brings an older database up to date.
ALTER TABLE {assets}
    ADD COLUMN custom_data TEXT DEFAULT NULL AFTER notes;
