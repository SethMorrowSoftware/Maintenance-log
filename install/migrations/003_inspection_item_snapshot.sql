-- Keep the acceptable range on the inspection, not only on the checklist.
--
-- An inspection item recorded the question but not the standard it was judged
-- against: the acceptable range, the unit and the guidance were read live from
-- {checklist_items} every time. That makes a filed check depend on a template
-- somebody may edit or delete afterwards, and the failure is silent both ways:
--
--   * Delete the line. The foreign key sets checklist_item_id to NULL, the
--     range comes back empty, and a reading outside it is filed with no
--     verdict at all — which the tally counts as a pass.
--   * Retune the line mid-check. The next save re-judges every number on the
--     run against the new range, rewriting answers the technician already
--     gave under the old one.
--
-- Pass and fail counts are written once and never recomputed, so either way a
-- wrong tally is permanent. On a safety record for a ride that is not an
-- acceptable way to lose information.
--
-- These four columns are the standard as it stood when the check was started.
-- Every one has a default, so the other INSERT into this table (the sample
-- data in demo.sql, which lists its columns explicitly) keeps working.
--
-- is_required is deliberately NOT snapshotted. It decides whether a line may
-- be left blank, which is a live question about the run in front of you, not a
-- fact about the record; freezing it would take away an administrator's only
-- way to release a technician stuck on a line that should never have been
-- mandatory. Inspection::items() keeps reading it from the template, and keeps
-- treating a deleted line as required, which is the safe way round.

ALTER TABLE {inspection_items}
    ADD COLUMN item_description VARCHAR(500)  NOT NULL DEFAULT '' AFTER item_text,
    ADD COLUMN unit            VARCHAR(20)    NOT NULL DEFAULT '' AFTER value_number,
    ADD COLUMN min_value       DECIMAL(12,2)  DEFAULT NULL        AFTER unit,
    ADD COLUMN max_value       DECIMAL(12,2)  DEFAULT NULL        AFTER min_value;

-- Give the rows that already exist the standard their template still holds.
-- A row whose checklist line has already been deleted has nothing to recover
-- from and keeps a NULL range: the information is gone, and no migration can
-- bring it back.
UPDATE {inspection_items} ii
  JOIN {checklist_items} ci ON ci.id = ii.checklist_item_id
   SET ii.item_description = ci.description,
       ii.unit            = ci.unit,
       ii.min_value       = ci.min_value,
       ii.max_value       = ci.max_value;
