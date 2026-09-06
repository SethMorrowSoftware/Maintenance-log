-- =============================================================================
--  RideLog — Reference data
--
--  ALWAYS installed. This is the data the application needs in order to work:
--  default settings, asset categories, park locations, and two ready-to-use
--  inspection checklists.
--
--  Uses INSERT IGNORE throughout so re-running is harmless and never clobbers
--  values the site owner has since changed on the Settings screen.
--
--  Same {table} placeholder convention as schema.sql.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Settings
--   setting_type drives how the Settings screen renders the field:
--   string | text | int | bool | select | password | color | email | hidden
--   (heading = a titled break in the form, not a value)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {settings}
  (setting_key, setting_value, setting_type, setting_group, is_public, label, description, sort_order)
VALUES
  -- General ------------------------------------------------------------------
  ('site_name', 'Castle Fun Center Maintenance', 'string', 'general', 1,
   'Site name', 'Shown in the header, page titles and emails.', 10),
  ('organization_name', 'Castle Fun Center', 'string', 'general', 1,
   'Organization name', 'Used on printed reports and inspection sheets.', 20),
  ('footer_text', '', 'string', 'general', 1,
   'Footer text', 'Optional extra line shown at the bottom of every page.', 30),
  ('items_per_page', '25', 'int', 'general', 0,
   'Rows per page', 'How many records each list shows before paginating.', 40),
  ('dashboard_pm_lookahead_days', '14', 'int', 'general', 0,
   'Upcoming maintenance window', 'How many days ahead the dashboard looks for due maintenance.', 50),
  ('asset_noun_singular', 'Machine', 'string', 'general', 1,
   'What do you call one of the things you look after?', 'Machine, Ride, Asset, Appliance, Vehicle, Unit… Used everywhere one is mentioned, so pick the word your team says.', 60),
  ('asset_noun_plural', 'Machines', 'string', 'general', 1,
   '…and several of them?', 'Machines, Rides, Assets, Appliances… Shown in the menu and on list pages.', 70),

  -- Features (every module is a switch) --------------------------------------
  ('feature_work_orders', '1', 'bool', 'features', 0,
   'Work orders', 'Reporting problems, assigning them, closing them, and the alerts about them.', 10),
  ('feature_schedules', '1', 'bool', 'features', 0,
   'Scheduled service', 'Recurring service by date or by meter, the due list, and the reminders.', 20),
  ('feature_inspections', '1', 'bool', 'features', 0,
   'Checklists and inspections', 'Daily checks, checklist templates, and the "still to check today" list.', 30),
  ('feature_parts', '1', 'bool', 'features', 0,
   'Parts', 'Stock on the shelf, parts used on jobs, and low-stock warnings.', 40),
  ('feature_meters', '1', 'bool', 'features', 0,
   'Meters', 'Hour meters, lap counters, meter readings and meter-based service.', 50),
  ('feature_downtime', '1', 'bool', 'features', 0,
   'Downtime', 'Time out of service on jobs and problems, and the downtime report.', 60),
  ('feature_costs', '1', 'bool', 'features', 0,
   'Money', 'Prices, costs and spend, for the people allowed to see them. Off hides money from everybody, administrators included.', 70),
  ('feature_photos', '1', 'bool', 'features', 0,
   'Photos and files', 'Attachments on machines, jobs, problems and inspections.', 80),
  ('feature_labels', '1', 'bool', 'features', 0,
   'QR labels', 'Printable labels that open a machine on a phone.', 90),
  ('feature_reports', '1', 'bool', 'features', 0,
   'Reports', 'The reports page and CSV exports.', 100),
  ('feature_notifications', '1', 'bool', 'features', 0,
   'In-app notifications', 'The bell in the header and the notifications page. Email and Slack have their own switches.', 110),
  ('feature_audit', '1', 'bool', 'features', 0,
   'Audit log', 'Who changed what, when.', 120),
  ('feature_drafts', '1', 'bool', 'features', 0,
   'Form drafts', 'Keeping a draft of the log and report forms on the device as they are typed.', 130),

  -- Localization -------------------------------------------------------------
  ('timezone', 'America/New_York', 'select', 'localization', 1,
   'Time zone', 'All dates and times are shown in this zone. Stored internally as UTC.', 10),
  ('date_format', 'M j, Y', 'select', 'localization', 1,
   'Date format', 'How dates are displayed.', 20),
  ('time_format', 'g:i A', 'select', 'localization', 1,
   'Time format', 'How times are displayed.', 30),
  ('currency_symbol', '$', 'string', 'localization', 1,
   'Currency symbol', 'Prefixed to every cost figure.', 40),
  ('week_start', '0', 'select', 'localization', 0,
   'First day of the week', '0 = Sunday, 1 = Monday.', 50),

  -- Maintenance --------------------------------------------------------------
  ('notify_pm_due_days', '7', 'int', 'maintenance', 0,
   'Notify before PM is due', 'Days of warning before scheduled maintenance comes due.', 10),
  ('default_labor_rate', '0.00', 'string', 'maintenance', 0,
   'Default labour rate', 'Hourly rate used to pre-fill labour cost. 0 disables it.', 20),
  ('require_meter_on_log', '0', 'bool', 'maintenance', 0,
   'Require a meter reading', 'Force a meter reading on every log for assets that have a meter.', 30),
  ('inspection_signature_required', '1', 'bool', 'maintenance', 0,
   'Require inspection sign-off by default',
   'Starting point for a new checklist. Each checklist can then decide for itself.', 40),
  ('inspection_fail_opens_wo', '1', 'bool', 'maintenance', 0,
   'Failed critical item opens a work order', 'Automatically raise a high-priority work order.', 50),
  ('checks_grace_minutes', '0', 'int', 'maintenance', 0,
   'Grace period for timed checks (minutes)', 'A check finished this long after its due time still counts as on time, and the alert waits this long too.', 52),
  ('checks_notify_managers', '1', 'bool', 'maintenance', 0,
   'Tell whoever manages checklists when a check is not finished on time', 'An in-app notification (and an email, if they have those on) the moment a timed check passes its due time unfinished.', 54),
  ('wo_number_prefix', 'WO-', 'string', 'maintenance', 0,
   'Work order prefix', 'Prefix for generated work order numbers.', 60),
  ('low_stock_alerts', '1', 'bool', 'maintenance', 0,
   'Low stock alerts', 'Warn when a part drops to or below its reorder level.', 70),

  -- Uploads ------------------------------------------------------------------
  ('max_upload_mb', '8', 'int', 'uploads', 0,
   'Maximum attachment size (MB)', 'Cannot exceed what your host allows in upload_max_filesize.', 10),
  ('image_max_dimension', '2000', 'int', 'uploads', 0,
   'Maximum image dimension (px)', 'Uploaded photos are resized down to this and stripped of metadata.', 20),

  -- Email --------------------------------------------------------------------
  ('mail_enabled', '0', 'bool', 'email', 0,
   'Send email', 'Turn off to keep everything in-app only.', 10),
  ('mail_transport', 'mail', 'select', 'email', 0,
   'Mail transport', 'PHP mail() works on most cPanel hosts. Use SMTP for better deliverability.', 20),
  ('mail_from_name', 'Castle Fun Center Maintenance', 'string', 'email', 0,
   'From name', 'Sender name on outgoing mail.', 30),
  ('mail_from_email', '', 'email', 'email', 0,
   'From address', 'Use an address on your own domain or mail will be rejected.', 40),
  ('smtp_host', '', 'string', 'email', 0, 'SMTP host', 'e.g. mail.yourdomain.com', 50),
  ('smtp_port', '587', 'int', 'email', 0, 'SMTP port', '587 for TLS, 465 for SSL, 25 for none.', 60),
  ('smtp_secure', 'tls', 'select', 'email', 0, 'SMTP encryption', 'tls, ssl or none.', 70),
  ('smtp_user', '', 'string', 'email', 0, 'SMTP username', '', 80),
  ('smtp_pass', '', 'password', 'email', 0, 'SMTP password', '', 90),

  -- Slack --------------------------------------------------------------------
  ('slack_enabled', '0', 'bool', 'slack', 0,
   'Post to Slack', 'Turn on once the token and channel below are filled in and the test works.', 10),
  ('slack_bot_token', '', 'password', 'slack', 0,
   'Bot token', 'The Bot User OAuth Token from your Slack app. It starts with xoxb-. The steps are beside this form.', 20),
  ('slack_channel', '#maintenance', 'string', 'slack', 0,
   'Main channel', 'Where alerts go unless one below names its own channel. Use the name with the #, or the channel ID. The bot has to be invited to it.', 30),
  ('slack_mention', '', 'string', 'slack', 0,
   'Who to alert for urgent and safety problems', 'Optional. @here, @channel, or a member ID like U0123ABCD. Added to urgent and safety messages so somebody sees them.', 40),
  ('slack_min_criticality', 'any', 'select', 'slack', 0,
   'Only for machines at least this important', 'Skip alerts about the less important machines. Importance is set on each machine.', 50),
  ('slack_h_events', '', 'heading', 'slack', 0,
   'What to post', 'Each kind of alert can go to its own channel. Leave a channel blank to use the main one.', 60),
  ('slack_on_problem', 'high', 'select', 'slack', 0,
   'Problems reported', 'Which reported problems to post, by how urgent they were marked.', 70),
  ('slack_problem_channel', '', 'string', 'slack', 0,
   'Channel for problems', '', 71),
  ('slack_on_safety', '1', 'bool', 'slack', 0,
   'Always post safety issues', 'A problem flagged as a safety issue is posted whatever its urgency, with the alert mention above.', 72),
  ('slack_on_fixed', '1', 'bool', 'slack', 0,
   'Problems fixed or closed', 'Post when a reported problem is completed or cancelled, to the same channel as problems.', 73),
  ('slack_on_inspection', 'critical', 'select', 'slack', 0,
   'Failed inspections', 'Post when a daily check fails.', 80),
  ('slack_inspection_channel', '', 'string', 'slack', 0,
   'Channel for failed inspections', '', 81),
  ('slack_on_unfinished', '1', 'bool', 'slack', 0,
   'Checks not finished on time', 'Post when a checklist with a due time has not been finished by then. Each checklist can opt out, use its own channel and mention, remind beforehand, and escalate.', 85),
  ('slack_unfinished_channel', '', 'string', 'slack', 0,
   'Channel for unfinished checks', '', 86),
  ('slack_on_status', '1', 'bool', 'slack', 0,
   'Machines going out of or back into service', 'Post every status change: out of service, in the shop, back in service, retired.', 90),
  ('slack_status_channel', '', 'string', 'slack', 0,
   'Channel for status changes', '', 91),
  ('slack_on_job', 'followup', 'select', 'slack', 0,
   'Work logged', 'Post when a job is logged. "Only jobs needing follow-up" keeps the channel quiet.', 100),
  ('slack_job_channel', '', 'string', 'slack', 0,
   'Channel for work logged', '', 101),
  ('slack_on_due', '1', 'bool', 'slack', 0,
   'Service due, once a day', 'A morning list of overdue and upcoming service, posted by the nightly job.', 110),
  ('slack_due_channel', '', 'string', 'slack', 0,
   'Channel for service due', '', 111),
  ('slack_on_stock', '1', 'bool', 'slack', 0,
   'Parts running low', 'Post the moment a part drops to its reorder level.', 120),
  ('slack_stock_channel', '', 'string', 'slack', 0,
   'Channel for parts', '', 121),
  ('slack_daily_summary', '0', 'bool', 'slack', 0,
   'Morning report, once a day', 'What is not running, open problems, service due and parts running low, posted by the nightly job.', 130),
  ('slack_summary_channel', '', 'string', 'slack', 0,
   'Channel for the morning report', '', 131),

  -- Fields (edited on their own screen, not the generic form) -----------------
  ('asset_custom_fields', '', 'hidden', 'fields', 0,
   'Extra fields', 'Fields added to every machine under Settings → Fields.', 10),

  -- Security -----------------------------------------------------------------
  ('session_timeout_minutes', '480', 'int', 'security', 0,
   'Session timeout (minutes)', 'Sign a user out after this much inactivity.', 10),
  ('allow_registration', '0', 'hidden', 'security', 0,
   'Allow self-registration', 'There is no sign-up page: administrators create accounts.', 20),
  ('password_min_length', '8', 'int', 'security', 0,
   'Minimum password length', 'Eight characters or more.', 30),
  ('audit_retention_days', '365', 'int', 'security', 0,
   'Audit log retention (days)', 'Older audit entries are pruned. 0 keeps everything.', 40),
  ('cron_token', '', 'hidden', 'security', 0,
   'Cron token', 'Secret in the scheduled-task URL. Generated during installation.', 50),
  ('role_permissions', '', 'hidden', 'security', 0,
   'Role permissions', 'What each role may do, when changed from the defaults on the Roles page.', 60),

  -- Branding -----------------------------------------------------------------
  ('theme_default', 'system', 'select', 'branding', 1,
   'Default theme', 'system, light or dark. Each person can override this.', 10),
  ('primary_color', '#4f46e5', 'color', 'branding', 1,
   'Accent colour', 'Primary colour used across the interface.', 20),
  ('logo_path', '', 'string', 'branding', 1,
   'Logo', 'Optional logo shown in the sidebar and on printed reports.', 30),

  -- System (not user-editable) -----------------------------------------------
  ('schema_version', '1.0.0', 'hidden', 'system', 0, 'Schema version', '', 10),
  ('app_installed_at', '', 'hidden', 'system', 0, 'Installed at', '', 20),
  ('last_cron_run', '', 'hidden', 'system', 0, 'Last scheduled run', '', 30),
  ('last_checks_run', '', 'hidden', 'system', 0, 'Last checks job run', '', 31),
  ('applied_migrations', '', 'hidden', 'system', 0,
   'Applied migrations', 'Database changes already applied by the installer or the upgrade runner.', 40);


-- -----------------------------------------------------------------------------
-- Asset categories
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {asset_categories}
  (id, name, slug, description, icon, color, default_meter_type, sort_order, is_active)
VALUES
  (1,  'Go-Kart',            'go-kart',            'Single and double-seat racing karts',        'kart',        '#4f46e5', 'hours',  10, 1),
  (2,  'Kiddie Ride',        'kiddie-ride',        'Small-scale rides for younger guests',       'ride',        '#0ea5e9', 'cycles', 20, 1),
  (3,  'Major Ride',         'major-ride',         'Large mechanical attractions',               'ride',        '#7c3aed', 'cycles', 30, 1),
  (4,  'Water Attraction',   'water-attraction',   'Bumper boats and water play equipment',      'activity',    '#06b6d4', 'hours',  40, 1),
  (5,  'Bumper Boat',        'bumper-boat',        'Powered bumper boats',                       'activity',    '#0891b2', 'hours',  50, 1),
  (6,  'Arcade / Game',      'arcade-game',        'Redemption and video games',                 'grid',        '#db2777', 'none',   60, 1),
  (7,  'Mini Golf',          'mini-golf',          'Course holes, obstacles and features',       'map-pin',     '#16a34a', 'none',   70, 1),
  (8,  'Batting Cage',       'batting-cage',       'Pitching machines and cage equipment',       'activity',    '#ca8a04', 'cycles', 80, 1),
  (9,  'Laser Tag',          'laser-tag',          'Arena equipment, vests and phasers',         'shield',      '#dc2626', 'none',   90, 1),
  (10, 'Support Vehicle',    'support-vehicle',    'Utility vehicles, mowers and tractors',      'truck',       '#65a30d', 'hours', 100, 1),
  (11, 'Facility Equipment', 'facility-equipment', 'Compressors, HVAC and shop equipment',       'tool',        '#64748b', 'hours', 110, 1);


-- -----------------------------------------------------------------------------
-- Locations
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {locations} (id, name, description, building, sort_order, is_active) VALUES
  (1,  'Go-Kart Track',        'Main outdoor kart track',                   'Outdoor',     10, 1),
  (2,  'Kiddie Track',         'Junior kart track',                         'Outdoor',     20, 1),
  (3,  'Main Midway',          'Central ride midway',                       'Outdoor',     30, 1),
  (4,  'Arcade',               'Indoor arcade floor',                       'Main Building',40, 1),
  (5,  'Water Area',           'Bumper boats and water attractions',        'Outdoor',     50, 1),
  (6,  'Mini Golf Course',     'Eighteen-hole course',                      'Outdoor',     60, 1),
  (7,  'Batting Cages',        'Batting cage bays',                         'Outdoor',     70, 1),
  (8,  'Laser Tag Arena',      'Indoor laser tag arena',                    'Main Building',80, 1),
  (9,  'Maintenance Shop',     'Repair bay and workbenches',                'Shop',        90, 1),
  (10, 'Storage / Off-Season', 'Off-season and long-term storage',          'Shop',       100, 1),
  (11, 'Grounds',              'Parking, walkways and general grounds',     'Outdoor',    110, 1);


-- -----------------------------------------------------------------------------
-- Checklist 1 — Daily Go-Kart Pre-Operation Inspection
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (1, 'Daily Go-Kart Pre-Operation Inspection',
   'Complete on every kart before the track opens to guests. Any failed critical item takes the kart out of service until it is repaired and re-inspected.',
   'category', 1, NULL, 'daily', 10, 1, 1, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (1,  1, 'Brakes & Steering', 'Brake pedal firm, kart stops in a straight line',
       'Roll the kart and apply the brake. No sponginess, no pulling to one side.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10),
  (2,  1, 'Brakes & Steering', 'Brake pads and rotor within service limits',
       'Check pad thickness and look for scoring or glazing on the rotor.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (3,  1, 'Brakes & Steering', 'Steering free of excess play, wheels track straight',
       'No more than slight play at the wheel. Tie rods and heim joints tight.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 30),
  (4,  1, 'Controls', 'Throttle returns fully to idle when released',
       'Snap the pedal and confirm the return spring closes the throttle completely.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40),
  (5,  1, 'Controls', 'Kill switch stops the engine',
       'Run the engine and confirm the kill switch shuts it down immediately.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50),
  (6,  1, 'Safety Equipment', 'Seat belt / harness latches and retracts correctly',
       'Buckle and release. Check webbing for fraying, cuts or UV damage.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 60),
  (7,  1, 'Safety Equipment', 'Roll bar and head restraint secure, no cracks',
       'Check welds and mounting bolts.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 70),
  (8,  1, 'Safety Equipment', 'Bumpers and side pods secure, no sharp edges',
       'All fasteners present and tight. No exposed sharp metal.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 80),
  (9,  1, 'Body & Frame', 'Body panels and floor pan free of cracks or damage',
       'Look underneath as well as on top.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 90),
  (10, 1, 'Body & Frame', 'Seat secure and undamaged',
       'Seat mounts tight, no cracks in the shell.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 100),
  (11, 1, 'Wheels & Tires', 'Tyre condition acceptable, no cords showing',
       'Even wear, adequate tread, no visible damage.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 110),
  (12, 1, 'Wheels & Tires', 'Tyre pressure (front / rear)',
       'Record the measured pressure in PSI.',
       'number', 0, 0, 0, '', 'PSI', 0.00, 60.00, 120),
  (13, 1, 'Wheels & Tires', 'Wheel nuts torqued, no wobble at the hub',
       'Spin each wheel and rock it to check bearings.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 130),
  (14, 1, 'Drivetrain', 'Chain / belt tension and lubrication correct',
       'Correct deflection, adequate lubrication, guard in place.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 140),
  (15, 1, 'Drivetrain', 'Clutch engages smoothly, no slipping',
       'Kart should not creep at idle and should pull cleanly.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 150),
  (16, 1, 'Engine & Fluids', 'Engine oil at correct level',
       'Check on level ground.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 160),
  (17, 1, 'Engine & Fluids', 'No fuel leaks, lines and clamps sound',
       'Inspect the tank, lines, filter and carburettor for weeping or cracking.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 170),
  (18, 1, 'Engine & Fluids', 'Engine runs and idles normally, no unusual noise',
       'Listen for knocking, rattling or exhaust leaks.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 180),
  (19, 1, 'Engine & Fluids', 'Hour meter reading',
       'Record the current hour meter value.',
       'meter', 0, 0, 0, '', 'hours', NULL, NULL, 190),
  (20, 1, 'Final', 'Test lap completed, kart handles normally',
       'Drive one lap. Confirm braking, steering and power delivery are normal.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 200);


-- -----------------------------------------------------------------------------
-- Checklist 2 — Daily Ride Pre-Opening Inspection
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (2, 'Daily Ride Pre-Opening Inspection',
   'Complete on every ride before the park opens. Follow the manufacturer''s manual in addition to this checklist. A failed critical item keeps the ride closed until corrected and re-inspected.',
   'all', NULL, NULL, 'daily', 20, 1, 0, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (21, 2, 'Controls', 'Emergency stop halts the ride immediately',
       'Test the E-stop from the operator position. Ride must stop without delay.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10),
  (22, 2, 'Controls', 'Operator control panel functions correctly',
       'All buttons, keys, indicators and interlocks respond as expected.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (23, 2, 'Controls', 'Ride cycle timer and speed within specification',
       'Compare against the manufacturer''s stated cycle.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 30),
  (24, 2, 'Restraints & Safety', 'Restraints latch, lock and release correctly',
       'Test every seat. Confirm each restraint locks and cannot be released under load.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40),
  (25, 2, 'Restraints & Safety', 'Seats, belts and harnesses free of wear or damage',
       'Check webbing, stitching, buckles and padding.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50),
  (26, 2, 'Restraints & Safety', 'Gates, fencing and queue barriers secure',
       'No gaps, no damage, latches working.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 60),
  (27, 2, 'Restraints & Safety', 'Safety and height-requirement signage in place and legible',
       'Rider requirements posted and readable from the queue.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 70),
  (28, 2, 'Mechanical', 'Drive system operates smoothly, no unusual noise',
       'Listen through a full cycle for grinding, knocking or squealing.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 80),
  (29, 2, 'Mechanical', 'Brakes and stopping devices function correctly',
       'Ride stops within its normal distance and position.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 90),
  (30, 2, 'Mechanical', 'Lubrication points serviced per schedule',
       'Grease points, chains and bearings per the manufacturer''s chart.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 100),
  (31, 2, 'Mechanical', 'Hydraulic / pneumatic systems free of leaks',
       'Check hoses, cylinders and fittings. Note pressures if gauged.',
       'pass_fail_na', 1, 1, 1, '', '', NULL, NULL, 110),
  (32, 2, 'Electrical', 'Wiring, conduit and connections sound',
       'No exposed conductors, chafing, or water ingress in enclosures.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 120),
  (33, 2, 'Electrical', 'Ride and area lighting operational',
       'Including any emergency and egress lighting.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 130),
  (34, 2, 'Structure', 'Structure, welds and fasteners show no cracks or looseness',
       'Walk the structure. Check critical fasteners and safety wire.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 140),
  (35, 2, 'Structure', 'Ground surface, ramps and platforms free of trip hazards',
       'No standing water, no loose decking, no raised edges.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 150),
  (36, 2, 'Test Cycles', 'Empty test cycle completed successfully',
       'Run a full cycle with no riders and observe.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 160),
  (37, 2, 'Test Cycles', 'Loaded / ballast test cycle completed successfully',
       'Run with staff or ballast where the manufacturer requires it.',
       'pass_fail_na', 1, 1, 0, '', '', NULL, NULL, 170),
  (38, 2, 'Test Cycles', 'Cycle count / meter reading',
       'Record the counter value if the ride has one.',
       'meter', 0, 0, 0, '', 'cycles', NULL, NULL, 180),
  (39, 2, 'Final', 'Ride is safe to open to guests',
       'Overall judgement by the inspecting technician.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 190),
  (40, 2, 'Final', 'Notes / observations for the day',
       'Anything the next shift should know.',
       'text', 0, 0, 0, '', '', NULL, NULL, 200);
