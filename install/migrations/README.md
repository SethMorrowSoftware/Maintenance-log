# Migrations

Database changes for versions after 1.0.0 live here as plain `.sql` files.

## Naming

    NNN_short_description.sql

for example `002_add_tire_pressure_to_assets.sql`. Files are applied in
filename order, so keep the number sequential and zero-padded.

## Writing one

Use the same `{table}` placeholders as `schema.sql` — the runner substitutes
the installed table prefix:

```sql
ALTER TABLE {assets}
    ADD COLUMN tire_pressure_front DECIMAL(6,2) DEFAULT NULL AFTER tire_size;
```

Make each file safe to run twice where you can. MySQL has no
`ADD COLUMN IF NOT EXISTS`, so a re-run of a migration that adds a column will
error — the runner records every applied file in the `applied_migrations`
setting and skips it next time, so this only matters if someone edits that
setting by hand.

Never edit a migration that has already shipped. Add a new one instead.

## Applying them

An administrator opens `install/upgrade.php` in a browser after uploading the
new files. The runner:

1. re-applies `schema.sql` (all `CREATE TABLE IF NOT EXISTS`, so it only adds
   tables a new version introduced),
2. re-applies `seed.sql` (all `INSERT IGNORE`, so it only adds new settings
   rows and never overwrites a value the site owner has changed),
3. applies each pending migration in order, stopping at the first failure,
4. updates the recorded `schema_version`.
