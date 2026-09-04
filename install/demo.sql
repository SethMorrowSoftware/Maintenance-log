-- =============================================================================
--  RideLog — Optional demo data
--
--  Installed ONLY if the "Install sample data" box is ticked in the installer.
--  Gives you a populated dashboard to explore: a kart fleet, several rides,
--  a year of maintenance history, PM schedules, work orders and parts.
--
--  Everything here is fictional. Delete it once you start entering real
--  records — see docs/INSTALL.md for the one-click removal instructions.
--
--  Depends on: schema.sql and seed.sql, plus the administrator account the
--  installer creates as user id 1.
--
--  The extra staff accounts below exist purely so demo history has believable
--  authors. They are created INACTIVE with an unusable password hash and
--  therefore cannot be logged into. Give one a real password from the Users
--  screen if you want to keep it.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Demo staff (inactive — cannot sign in)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {users}
  (id, username, email, password_hash, first_name, last_name, role, job_title,
   employee_number, is_active, must_change_password, created_by)
VALUES
  (2, 'mtorres', 'mtorres@example.com', '$2y$10$DISABLEDACCOUNTNOLOGIN0uJ8xQ0m1rP9sT4vW7yZ2aB5cD8eF12',
   'Mike', 'Torres', 'manager', 'Lead Maintenance Technician', 'EMP-1042', 0, 1, 1),
  (3, 'dgreene', 'dgreene@example.com', '$2y$10$DISABLEDACCOUNTNOLOGIN0uJ8xQ0m1rP9sT4vW7yZ2aB5cD8eF12',
   'Dana', 'Greene', 'technician', 'Ride Technician', 'EMP-1088', 0, 1, 1),
  (4, 'rpatel', 'rpatel@example.com', '$2y$10$DISABLEDACCOUNTNOLOGIN0uJ8xQ0m1rP9sT4vW7yZ2aB5cD8eF12',
   'Ravi', 'Patel', 'technician', 'Small Engine Technician', 'EMP-1113', 0, 1, 1),
  (5, 'swhitfield', 'swhitfield@example.com', '$2y$10$DISABLEDACCOUNTNOLOGIN0uJ8xQ0m1rP9sT4vW7yZ2aB5cD8eF12',
   'Sam', 'Whitfield', 'viewer', 'Operations Manager', 'EMP-1005', 0, 1, 1);


-- -----------------------------------------------------------------------------
-- Assets — the kart fleet and the rides
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {assets}
  (id, asset_tag, name, category_id, location_id, status, criticality,
   manufacturer, model, serial_number, year_manufactured, purchase_date, purchase_cost,
   engine_make, engine_model, fuel_type, tire_size, capacity_passengers,
   meter_type, meter_reading, meter_updated_at, in_service_date, description,
   qr_slug, sort_order, created_by)
VALUES
  (1, 'GK-001', 'Go-Kart #1', 1, 1, 'in_service', 'high',
   'J&J Amusements', 'Sprint 200', 'JJ200-4417', 2021, '2021-04-12', 4850.00,
   'Honda', 'GX200', 'Gasoline', '11x6.00-5', 1, 'hours', 1284.50, '2026-08-29 14:10:00', '2021-05-01',
   'Single-seat adult sprint kart, outside lane fleet.', 'qk1a7f3d92b64c05e', 10, 1),
  (2, 'GK-002', 'Go-Kart #2', 1, 1, 'in_service', 'high',
   'J&J Amusements', 'Sprint 200', 'JJ200-4418', 2021, '2021-04-12', 4850.00,
   'Honda', 'GX200', 'Gasoline', '11x6.00-5', 1, 'hours', 1310.75, '2026-08-29 14:12:00', '2021-05-01',
   'Single-seat adult sprint kart.', 'qk2b8e4a13c75d16f', 20, 1),
  (3, 'GK-003', 'Go-Kart #3', 1, 1, 'maintenance', 'high',
   'J&J Amusements', 'Sprint 200', 'JJ200-4419', 2021, '2021-04-12', 4850.00,
   'Honda', 'GX200', 'Gasoline', '11x6.00-5', 1, 'hours', 1198.25, '2026-08-27 13:45:00', '2021-05-01',
   'Single-seat adult sprint kart. Currently in the shop for a clutch replacement.', 'qk3c9f5b24d86e27a', 30, 1),
  (4, 'GK-004', 'Go-Kart #4', 1, 1, 'in_service', 'high',
   'J&J Amusements', 'Sprint 200', 'JJ200-4420', 2021, '2021-04-12', 4850.00,
   'Honda', 'GX200', 'Gasoline', '11x6.00-5', 1, 'hours', 1265.00, '2026-08-29 14:15:00', '2021-05-01',
   'Single-seat adult sprint kart.', 'qk4d0a6c35e97f38b', 40, 1),
  (5, 'GK-005', 'Go-Kart #5', 1, 1, 'in_service', 'high',
   'J&J Amusements', 'Sprint 200 Duo', 'JJ200D-5102', 2022, '2022-03-28', 6200.00,
   'Honda', 'GX270', 'Gasoline', '11x7.10-5', 2, 'hours', 942.00, '2026-08-29 14:18:00', '2022-04-15',
   'Two-seat kart for adult-and-child pairs.', 'qk5e1b7d46f08a49c', 50, 1),
  (6, 'GK-006', 'Go-Kart #6', 1, 1, 'in_service', 'high',
   'J&J Amusements', 'Sprint 200 Duo', 'JJ200D-5103', 2022, '2022-03-28', 6200.00,
   'Honda', 'GX270', 'Gasoline', '11x7.10-5', 2, 'hours', 968.50, '2026-08-29 14:20:00', '2022-04-15',
   'Two-seat kart for adult-and-child pairs.', 'qk6f2c8e57a19b50d', 60, 1),
  (7, 'GK-007', 'Kiddie Kart #1', 1, 2, 'in_service', 'medium',
   'J&J Amusements', 'Junior 100', 'JJ100-2244', 2023, '2023-05-02', 3100.00,
   'Honda', 'GX120', 'Gasoline', '10x4.50-5', 1, 'hours', 611.25, '2026-08-28 13:30:00', '2023-05-20',
   'Speed-limited junior kart for the kiddie track.', 'qk7a3d9f68b20c61e', 70, 1),
  (8, 'GK-008', 'Kiddie Kart #2', 1, 2, 'out_of_service', 'medium',
   'J&J Amusements', 'Junior 100', 'JJ100-2245', 2023, '2023-05-02', 3100.00,
   'Honda', 'GX120', 'Gasoline', '10x4.50-5', 1, 'hours', 587.00, '2026-08-20 12:15:00', '2023-05-20',
   'Speed-limited junior kart. Awaiting a replacement governor assembly.', 'qk8b4e0a79c31d72f', 80, 1),
  (9, 'RD-001', 'Ferris Wheel', 3, 3, 'in_service', 'critical',
   'Chance Rides', 'Century Wheel 50', 'CR-CW50-8831', 2016, '2016-02-19', 385000.00,
   '', '', 'Electric', '', 48, 'cycles', 48210.00, '2026-08-29 13:05:00', '2016-05-14',
   'Fifty-foot wheel, 24 gondolas. Annual NDT inspection required each March.', 'qr9c5f1b80d42e83a', 90, 1),
  (10, 'RD-002', 'Tilt-A-Whirl', 3, 3, 'in_service', 'critical',
   'Larson International', 'Tilt-A-Whirl Model 12', 'LI-TAW-4417', 2018, '2018-03-05', 210000.00,
   '', '', 'Electric', '', 21, 'cycles', 31544.00, '2026-08-29 13:08:00', '2018-05-01',
   'Seven cars, three riders each.', 'qr0d6a2c91e53f94b', 100, 1),
  (11, 'RD-003', 'Bumper Boats', 5, 5, 'in_service', 'high',
   'J&J Amusements', 'Aqua Blaster', 'JJ-AB-1120', 2019, '2019-04-22', 68000.00,
   'Honda', 'GX160', 'Gasoline', '', 2, 'hours', 3877.50, '2026-08-29 13:12:00', '2019-06-01',
   'Ten-boat fleet plus the pond filtration system.', 'qr1e7b3d02f64a05c', 110, 1),
  (12, 'RD-004', 'Kiddie Coaster', 2, 3, 'in_service', 'critical',
   'Zamperla', 'Mini Mouse', 'ZM-MM-7702', 2020, '2020-02-11', 165000.00,
   '', '', 'Electric', '', 16, 'cycles', 22908.00, '2026-08-29 13:15:00', '2020-06-15',
   'Junior coaster, 36-inch height requirement.', 'qr2f8c4e13a75b16d', 120, 1),
  (13, 'BC-001', 'Batting Cage #1', 8, 7, 'in_service', 'medium',
   'Iron Mike', 'MP-6 Pitching Machine', 'IM-MP6-3390', 2022, '2022-04-08', 8400.00,
   '', '', 'Electric', '', 1, 'cycles', 96420.00, '2026-08-28 15:40:00', '2022-04-25',
   'Arm-style pitching machine, 40-70 mph.', 'qb3a9d5f24b86c27e', 130, 1),
  (14, 'LT-001', 'Laser Tag Arena', 9, 8, 'in_service', 'medium',
   'Laserforce', 'Gen8 Arena System', 'LF-G8-5581', 2021, '2021-09-30', 94000.00,
   '', '', 'Electric', '', 30, 'none', 0.00, NULL, '2021-11-12',
   'Thirty vest-and-phaser sets plus arena scoring and effects.', 'ql4b0e6a35c97d38f', 140, 1);


-- -----------------------------------------------------------------------------
-- Parts inventory
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {parts}
  (id, part_number, name, description, category, manufacturer, supplier,
   unit_cost, unit_of_measure, quantity_on_hand, reorder_level, reorder_quantity,
   location_bin, is_active, created_by)
VALUES
  (1,  'HON-GX200-OIL',  'Engine oil SAE 10W-30 (quart)', 'Small engine oil for GX-series engines.', 'Fluids', 'Honda', 'Northern Tool', 6.75, 'quart', 48.00, 12.00, 24.00, 'A1', 1, 1),
  (2,  'HON-17210-ZE1',  'Air filter element GX160/200', 'Foam and paper air cleaner element.', 'Filters', 'Honda', 'Small Engine Warehouse', 9.40, 'each', 22.00, 8.00, 24.00, 'A2', 1, 1),
  (3,  'HON-98079-55846','Spark plug BPR6ES', 'Standard spark plug for GX-series.', 'Ignition', 'NGK', 'Small Engine Warehouse', 3.85, 'each', 40.00, 15.00, 30.00, 'A3', 1, 1),
  (4,  'JJ-BRK-PAD-STD', 'Kart brake pad set', 'Front and rear pad set for Sprint 200.', 'Brakes', 'J&J Amusements', 'J&J Parts', 42.00, 'set', 9.00, 4.00, 10.00, 'B1', 1, 1),
  (5,  'JJ-BRK-ROTOR',   'Kart brake rotor', 'Vented steel rotor, 8 inch.', 'Brakes', 'J&J Amusements', 'J&J Parts', 88.50, 'each', 4.00, 2.00, 4.00, 'B2', 1, 1),
  (6,  'JJ-CLU-200',     'Centrifugal clutch 200-series', '3/4 inch bore, #35 chain.', 'Drivetrain', 'Hilliard', 'J&J Parts', 132.00, 'each', 3.00, 2.00, 4.00, 'B3', 1, 1),
  (7,  'CHN-35-10FT',    'Roller chain #35 (10 ft)', 'Drive chain stock.', 'Drivetrain', 'Diamond', 'Motion Industries', 34.25, 'each', 6.00, 2.00, 4.00, 'B4', 1, 1),
  (8,  'TIRE-11x600-5',  'Kart tire 11x6.00-5', 'Slick compound, outside lane.', 'Tires', 'Douglas', 'J&J Parts', 46.00, 'each', 14.00, 8.00, 16.00, 'C1', 1, 1),
  (9,  'TIRE-10x450-5',  'Kart tire 10x4.50-5', 'Junior kart tire.', 'Tires', 'Douglas', 'J&J Parts', 38.00, 'each', 8.00, 4.00, 8.00, 'C2', 1, 1),
  (10, 'JJ-SEATBELT-2P', 'Two-point lap belt', 'Replacement kart lap belt with hardware.', 'Safety', 'J&J Amusements', 'J&J Parts', 54.00, 'each', 6.00, 3.00, 6.00, 'C3', 1, 1),
  (11, 'JJ-BUMPER-SIDE', 'Side bumper, molded', 'Impact bumper for Sprint 200.', 'Body', 'J&J Amusements', 'J&J Parts', 76.00, 'each', 5.00, 2.00, 4.00, 'C4', 1, 1),
  (12, 'GRS-EP2-14OZ',   'EP-2 grease cartridge', 'Multi-purpose lithium grease.', 'Fluids', 'Mobil', 'Grainger', 5.60, 'each', 24.00, 10.00, 24.00, 'A4', 1, 1),
  (13, 'BRG-6205-2RS',   'Ball bearing 6205-2RS', 'Sealed bearing, 25mm bore.', 'Bearings', 'SKF', 'Motion Industries', 12.90, 'each', 16.00, 6.00, 12.00, 'D1', 1, 1),
  (14, 'BRG-6305-2RS',   'Ball bearing 6305-2RS', 'Sealed bearing, 25mm bore, heavy series.', 'Bearings', 'SKF', 'Motion Industries', 19.75, 'each', 8.00, 4.00, 8.00, 'D2', 1, 1),
  (15, 'ELE-CONT-40A',   'Contactor 40A 3-pole', 'Ride drive motor contactor.', 'Electrical', 'Schneider', 'Grainger', 68.00, 'each', 3.00, 1.00, 2.00, 'D3', 1, 1),
  (16, 'ELE-LIMIT-SW',   'Heavy duty limit switch', 'Position sensing for ride restraints.', 'Electrical', 'Honeywell', 'Grainger', 44.50, 'each', 5.00, 2.00, 4.00, 'D4', 1, 1),
  (17, 'LED-STRIP-16FT', 'LED lighting strip 16 ft', 'Ride perimeter lighting, 24V.', 'Electrical', 'Generic', 'Amazon Business', 28.00, 'each', 2.00, 4.00, 10.00, 'E1', 1, 1),
  (18, 'HYD-HOSE-3-8',   'Hydraulic hose 3/8 in (per ft)', 'Two-wire hose stock.', 'Hydraulics', 'Parker', 'Motion Industries', 7.20, 'foot', 40.00, 20.00, 50.00, 'E2', 1, 1),
  (19, 'BOAT-IMPELLER',  'Bumper boat impeller', 'Replacement impeller for Aqua Blaster.', 'Marine', 'J&J Amusements', 'J&J Parts', 61.00, 'each', 4.00, 2.00, 4.00, 'E3', 1, 1),
  (20, 'LT-VEST-BATT',   'Laser tag vest battery pack', 'Rechargeable pack for Gen8 vests.', 'Electronics', 'Laserforce', 'Laserforce Direct', 39.00, 'each', 6.00, 4.00, 12.00, 'E4', 1, 1);


-- -----------------------------------------------------------------------------
-- Preventive maintenance schedules
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {maintenance_schedules}
  (id, asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, assigned_to, priority, instructions,
   last_performed_at, last_meter, next_due_date, next_due_meter, is_active, created_by)
VALUES
  (1, 1, 'Kart #1 — 50 hour service', 'Oil change, air filter, chain and general check.', 'preventive', NULL,
   'meter', 1, 50.00, 3, 1.50, 4, 'normal',
   'Drain oil warm. Replace filter element. Check chain tension and clutch wear. Torque wheel nuts to 25 ft-lb.',
   '2026-08-11 14:30:00', 1250.00, '2026-09-12', 1300.00, 1, 1),
  (2, 2, 'Kart #2 — 50 hour service', 'Oil change, air filter, chain and general check.', 'preventive', NULL,
   'meter', 1, 50.00, 3, 1.50, 4, 'normal',
   'Drain oil warm. Replace filter element. Check chain tension and clutch wear.',
   '2026-08-04 15:00:00', 1275.00, '2026-09-06', 1325.00, 1, 1),
  (3, 3, 'Kart #3 — 50 hour service', 'Oil change, air filter, chain and general check.', 'preventive', NULL,
   'meter', 1, 50.00, 3, 1.50, 4, 'normal',
   'Drain oil warm. Replace filter element. Check chain tension and clutch wear.',
   '2026-07-28 13:20:00', 1150.00, '2026-08-30', 1200.00, 1, 1),
  (4, 5, 'Kart #5 — 50 hour service', 'Duo kart service including GX270 valve check.', 'preventive', NULL,
   'meter', 1, 50.00, 3, 2.00, 4, 'normal',
   'As per single-seat service plus valve lash check every other interval.',
   '2026-08-18 14:00:00', 925.00, '2026-09-20', 975.00, 1, 1),
  (5, 9, 'Ferris Wheel — weekly mechanical', 'Weekly lubrication and fastener check.', 'preventive', 2,
   'weekly', 1, NULL, 2, 3.00, 2, 'high',
   'Grease all points per the Chance lubrication chart. Torque-check gondola pivot fasteners. Log any wear.',
   '2026-08-31 12:00:00', NULL, '2026-09-07', NULL, 1, 1),
  (6, 9, 'Ferris Wheel — annual NDT inspection', 'Non-destructive testing of critical welds by an outside firm.', 'inspection', NULL,
   'annual', 1, NULL, 30, 8.00, 2, 'urgent',
   'Schedule the certified inspector. Retain the report with the ride file. Required before the season opens.',
   '2026-03-16 13:00:00', NULL, '2027-03-16', NULL, 1, 1),
  (7, 10, 'Tilt-A-Whirl — weekly mechanical', 'Weekly lubrication, restraint and brake check.', 'preventive', 2,
   'weekly', 1, NULL, 2, 2.50, 2, 'high',
   'Grease car pivots and platform bearings. Check restraint locks on all seven cars.',
   '2026-08-31 12:30:00', NULL, '2026-09-07', NULL, 1, 1),
  (8, 11, 'Bumper Boats — 100 hour engine service', 'Engine service across the boat fleet.', 'preventive', NULL,
   'meter', 1, 100.00, 5, 6.00, 4, 'normal',
   'Rotate through the fleet. Oil, plug, filter and impeller inspection on each boat.',
   '2026-07-21 13:00:00', 3800.00, '2026-09-08', 3900.00, 1, 1),
  (9, 12, 'Kiddie Coaster — monthly track and restraint', 'Monthly track walk and restraint function test.', 'preventive', 2,
   'monthly', 1, NULL, 5, 2.00, 3, 'high',
   'Walk the full track. Check rail joints, anti-rollbacks and every lap bar.',
   '2026-08-10 12:00:00', NULL, '2026-09-10', NULL, 1, 1),
  (10, 13, 'Batting Cage #1 — quarterly service', 'Pitching machine service and netting inspection.', 'preventive', NULL,
   'quarterly', 1, NULL, 7, 1.50, 4, 'normal',
   'Inspect throwing arm and springs. Check net for holes and secure anchor points.',
   '2026-06-15 14:00:00', NULL, '2026-09-15', NULL, 1, 1);


-- -----------------------------------------------------------------------------
-- Work orders
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {work_orders}
  (id, wo_number, asset_id, title, description, priority, status, source,
   reported_by, assigned_to, due_date, started_at, completed_at, closed_by, resolution,
   downtime_minutes, estimated_hours, actual_hours, is_safety_issue, took_out_of_service,
   created_at, created_by)
VALUES
  (1, 'WO-000001', 3, 'Kart #3 clutch slipping under load',
   'Operator reports kart #3 will not pull away cleanly from the start line and the engine revs without the kart accelerating. Pulled from the fleet at 2:15 pm.',
   'high', 'in_progress', 'operator_report', 5, 4, '2026-09-05', '2026-08-27 17:30:00', NULL, NULL, NULL,
   NULL, 2.00, NULL, 0, 1, '2026-08-27 18:20:00', 5),
  (2, 'WO-000002', 8, 'Kiddie Kart #2 governor not limiting speed',
   'Kart is reaching noticeably higher speed than the rest of the junior fleet. Removed from service immediately as a safety precaution.',
   'urgent', 'on_hold', 'operator_report', 5, 4, '2026-09-02', '2026-08-20 16:00:00', NULL, NULL, NULL,
   NULL, 1.50, NULL, 1, 1, '2026-08-20 15:45:00', 5),
  (3, 'WO-000003', 9, 'Ferris Wheel gondola 14 door latch sticking',
   'Ride operator reports the latch on gondola 14 needs an unusual amount of force to close.',
   'high', 'completed', 'inspection', 2, 2, '2026-08-16', '2026-08-15 12:40:00', '2026-08-15 14:10:00', 2,
   'Disassembled the latch, cleaned out accumulated grit and corrosion, replaced the return spring and re-lubricated. Cycled fifty times, latch now operates normally. Gondola returned to service.',
   90, 1.50, 1.50, 1, 0, '2026-08-15 12:05:00', 2),
  (4, 'WO-000004', 12, 'Kiddie Coaster lap bar 3 slow to release',
   'Lap bar on car 3 releases slowly at the unload platform, holding up the queue.',
   'normal', 'assigned', 'operator_report', 5, 3, '2026-09-08', NULL, NULL, NULL, NULL,
   NULL, 1.00, NULL, 0, 0, '2026-08-30 15:10:00', 5),
  (5, 'WO-000005', 11, 'Bumper boat 6 losing power intermittently',
   'Boat 6 cuts out intermittently mid-session. Suspect fuel delivery.',
   'normal', 'open', 'operator_report', 5, NULL, '2026-09-10', NULL, NULL, NULL, NULL,
   NULL, 2.00, NULL, 0, 0, '2026-09-01 14:25:00', 5),
  (6, 'WO-000006', 14, 'Laser tag vests 7 and 12 not holding charge',
   'Two vests drop out partway through a session even after a full overnight charge.',
   'low', 'completed', 'operator_report', 5, 3, '2026-08-25', '2026-08-22 18:00:00', '2026-08-22 19:15:00', 3,
   'Replaced the battery packs in both vests and updated the vest service log. Both now run a full three-hour session.',
   0, 1.00, 1.25, 0, 0, '2026-08-21 20:30:00', 5);


-- -----------------------------------------------------------------------------
-- Inspections
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {inspections}
  (id, checklist_id, asset_id, user_id, checklist_name, status, started_at, completed_at,
   duration_minutes, meter_reading, passed_count, failed_count, na_count, critical_failed,
   took_out_of_service, notes, signature_name)
VALUES
  (1, 1, 1, 4, 'Daily Go-Kart Pre-Operation Inspection', 'passed',
   '2026-09-03 12:05:00', '2026-09-03 12:16:00', 11, 1284.50, 18, 0, 0, 0, 0,
   'All good. Tyre pressures set to 18 PSI front, 20 rear.', 'Ravi Patel'),
  (2, 1, 2, 4, 'Daily Go-Kart Pre-Operation Inspection', 'passed',
   '2026-09-03 12:18:00', '2026-09-03 12:28:00', 10, 1310.75, 18, 0, 0, 0, 0,
   'Chain slightly loose, adjusted on the spot.', 'Ravi Patel'),
  (3, 2, 9, 2, 'Daily Ride Pre-Opening Inspection', 'passed',
   '2026-09-03 11:30:00', '2026-09-03 11:58:00', 28, 48210.00, 17, 0, 2, 0, 0,
   'Ride cleared for opening. Hydraulic section not applicable on this unit.', 'Mike Torres'),
  (4, 1, 8, 4, 'Daily Go-Kart Pre-Operation Inspection', 'failed',
   '2026-08-20 12:10:00', '2026-08-20 12:26:00', 16, 587.00, 17, 1, 0, 1, 1,
   'Kart reached noticeably higher speed than the rest of the junior fleet on the test lap. Governor not limiting correctly. Removed from service and raised a work order.',
   'Ravi Patel');

INSERT IGNORE INTO {inspection_items}
  (id, inspection_id, checklist_item_id, section, item_text, response_type, response,
   value_text, value_number, is_critical, notes, sort_order)
VALUES
  (1, 4, 1,  'Brakes & Steering', 'Brake pedal firm, kart stops in a straight line', 'pass_fail', 'pass', '', NULL, 1, '', 10),
  (2, 4, 4,  'Controls', 'Throttle returns fully to idle when released', 'pass_fail', 'pass', '', NULL, 1, '', 40),
  (3, 4, 5,  'Controls', 'Kill switch stops the engine', 'pass_fail', 'pass', '', NULL, 1, '', 50),
  (4, 4, 12, 'Wheels & Tires', 'Tyre pressure (front / rear)', 'number', '', '', 18.00, 0, 'Front 18, rear 20.', 120),
  (5, 4, 19, 'Engine & Fluids', 'Hour meter reading', 'meter', '', '', 587.00, 0, '', 190),
  (6, 4, 20, 'Final', 'Test lap completed, kart handles normally', 'pass_fail', 'fail', '', NULL, 1,
   'Kart exceeded the junior speed limit on the back straight. Governor linkage appears not to be restricting travel.', 200);


-- -----------------------------------------------------------------------------
-- Maintenance logs — roughly a year of history
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {maintenance_logs}
  (id, asset_id, user_id, log_type, title, description, work_performed, performed_at, completed_at,
   labor_hours, labor_cost, parts_cost, other_cost, total_cost, meter_reading, downtime_minutes,
   status_before, status_after, schedule_id, work_order_id, inspection_id,
   is_completed, requires_followup, followup_notes, created_by)
VALUES
  (1, 1, 4, 'preventive', '50 hour service — Kart #1',
   'Routine interval service.', 'Changed engine oil, cleaned and re-oiled the air filter element, checked chain tension and lubricated, inspected brake pads (60% remaining), torqued wheel nuts.',
   '2025-09-14 14:00:00', '2025-09-14 15:30:00', 1.50, 0.00, 16.15, 0.00, 16.15, 980.00, 90, 'in_service', 'in_service', 1, NULL, NULL, 1, 0, NULL, 4),
  (2, 2, 4, 'preventive', '50 hour service — Kart #2',
   'Routine interval service.', 'Oil change, air filter cleaned, chain adjusted and lubricated, brake inspection.',
   '2025-09-14 15:45:00', '2025-09-14 17:00:00', 1.25, 0.00, 16.15, 0.00, 16.15, 1005.00, 75, 'in_service', 'in_service', 2, NULL, NULL, 1, 0, NULL, 4),
  (3, 9, 2, 'preventive', 'Weekly mechanical — Ferris Wheel',
   'Weekly lubrication and fastener check.', 'Greased all 24 gondola pivots and both main bearings per the lubrication chart. Torque-checked hub fasteners. No wear noted.',
   '2025-09-22 12:00:00', '2025-09-22 15:00:00', 3.00, 0.00, 11.20, 0.00, 11.20, 44120.00, 0, 'in_service', 'in_service', 5, NULL, NULL, 1, 0, NULL, 2),
  (4, 11, 4, 'repair', 'Bumper boat 3 — impeller replacement',
   'Boat 3 losing thrust. Found the impeller badly worn with two vanes chipped.', 'Pulled the drive unit, replaced the impeller and the wear ring, reassembled and water-tested for twenty minutes. Thrust restored to normal.',
   '2025-10-06 13:30:00', '2025-10-06 16:00:00', 2.50, 0.00, 61.00, 0.00, 61.00, 3410.00, 150, 'maintenance', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (5, 3, 4, 'corrective', 'Kart #3 — front end alignment',
   'Kart pulling left under braking.', 'Checked tie rod ends, found the left one worn. Replaced and re-aligned the front end to spec. Test lap confirmed straight tracking.',
   '2025-10-19 14:15:00', '2025-10-19 16:00:00', 1.75, 0.00, 28.00, 0.00, 28.00, 890.00, 105, 'maintenance', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (6, 10, 2, 'preventive', 'Weekly mechanical — Tilt-A-Whirl',
   'Weekly lubrication and restraint check.', 'Greased car pivots and platform bearings. Function-tested all seven car restraints. Checked platform drive belt tension.',
   '2025-10-27 12:30:00', '2025-10-27 15:00:00', 2.50, 0.00, 5.60, 0.00, 5.60, 28900.00, 0, 'in_service', 'in_service', 7, NULL, NULL, 1, 0, NULL, 2),
  (7, 9, 2, 'preventive', 'End of season shutdown — Ferris Wheel',
   'Winterisation.', 'Drained and greased all points, covered the drive motor and control cabinet, removed and stored all 24 gondola cushions, applied corrosion inhibitor to exposed hardware.',
   '2025-11-10 13:00:00', '2025-11-10 19:00:00', 6.00, 0.00, 42.30, 0.00, 42.30, 45880.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 2),
  (8, 1, 4, 'preventive', 'End of season storage prep — Kart #1',
   'Winter storage preparation for the kart fleet.', 'Stabiliser added to fuel, ran engine to circulate. Oil changed. Battery removed and put on a maintainer. Tyres inflated and kart raised off the ground.',
   '2025-11-17 14:00:00', '2025-11-17 15:00:00', 1.00, 0.00, 12.35, 0.00, 12.35, 1042.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (9, 2, 4, 'preventive', 'End of season storage prep — Kart #2',
   'Winter storage preparation.', 'Fuel stabilised, oil changed, battery on maintainer, kart raised.',
   '2025-11-17 15:15:00', '2025-11-17 16:00:00', 0.75, 0.00, 12.35, 0.00, 12.35, 1068.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (10, 14, 3, 'repair', 'Laser tag — arena effect lighting repair',
   'Two overhead effect fixtures dark in the north half of the arena.', 'Traced the fault to a failed 24V driver. Replaced the driver and re-terminated two corroded connections. Both fixtures back in service.',
   '2025-12-08 19:00:00', '2025-12-08 21:00:00', 2.00, 0.00, 28.00, 0.00, 28.00, NULL, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 3),
  (11, 12, 3, 'inspection', 'Off-season structural inspection — Kiddie Coaster',
   'Annual off-season inspection of the track and structure.', 'Walked the full circuit. Checked all rail joints, anti-rollback dogs and support fasteners. Two support bolts found below torque and retightened. Track weld inspection showed no cracking.',
   '2026-01-19 14:00:00', '2026-01-19 18:00:00', 4.00, 0.00, 0.00, 0.00, 0.00, 21440.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 3),
  (12, 10, 2, 'modification', 'Tilt-A-Whirl — LED lighting upgrade',
   'Replaced the incandescent perimeter lighting with LED strip.', 'Removed the original festoon lighting, installed 160 feet of 24V LED strip with new drivers, re-terminated the control feed and tested all effects.',
   '2026-02-16 14:00:00', '2026-02-16 21:00:00', 7.00, 0.00, 280.00, 120.00, 400.00, 29650.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 2),
  (13, 9, 2, 'inspection', 'Annual NDT inspection — Ferris Wheel',
   'Certified third-party non-destructive testing of critical welds and pins.', 'Outside inspector performed magnetic particle testing on the hub, spoke terminations and gondola hangers. No indications found. Report filed with the ride records. Certificate valid twelve months.',
   '2026-03-16 13:00:00', '2026-03-16 21:00:00', 8.00, 0.00, 0.00, 1850.00, 1850.00, 46020.00, 0, 'in_service', 'in_service', 6, NULL, NULL, 1, 0, NULL, 2),
  (14, 1, 4, 'preventive', 'Season opening service — Kart #1',
   'Bringing the fleet out of winter storage.', 'Fresh fuel, oil change, new spark plug, air filter cleaned, battery refitted and tested, chain lubricated, tyre pressures set, full function check and test laps.',
   '2026-04-06 13:00:00', '2026-04-06 15:00:00', 2.00, 0.00, 20.00, 0.00, 20.00, 1042.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (15, 2, 4, 'preventive', 'Season opening service — Kart #2',
   'Bringing the fleet out of winter storage.', 'Fresh fuel, oil change, new plug, air filter, battery, chain, tyres, function check and test laps.',
   '2026-04-06 15:15:00', '2026-04-06 17:00:00', 1.75, 0.00, 20.00, 0.00, 20.00, 1068.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (16, 3, 4, 'preventive', 'Season opening service — Kart #3',
   'Bringing the fleet out of winter storage.', 'Fresh fuel, oil change, new plug, air filter, battery, chain, tyres, function check and test laps.',
   '2026-04-07 13:00:00', '2026-04-07 14:45:00', 1.75, 0.00, 20.00, 0.00, 20.00, 962.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (17, 5, 4, 'preventive', 'Season opening service — Kart #5',
   'Duo kart season prep including a valve lash check.', 'Standard season service plus valve clearance checked and set on the GX270. Both within spec after adjustment.',
   '2026-04-07 15:00:00', '2026-04-07 17:30:00', 2.50, 0.00, 20.00, 0.00, 20.00, 742.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (18, 9, 2, 'preventive', 'Season opening — Ferris Wheel',
   'De-winterisation and commissioning.', 'Removed covers, refitted all gondola cushions, greased every point, meggered the drive motor, ran twenty empty cycles then a ballasted cycle. All within normal parameters.',
   '2026-04-13 12:00:00', '2026-04-13 20:00:00', 8.00, 0.00, 58.40, 0.00, 58.40, 46020.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 2),
  (19, 11, 4, 'preventive', 'Season opening — Bumper Boats',
   'Pond and fleet commissioning.', 'Filled and treated the pond, started and tuned all ten boats, replaced two impellers showing wear, checked every bumper ring and tether.',
   '2026-04-20 13:00:00', '2026-04-20 21:00:00', 8.00, 0.00, 122.00, 0.00, 122.00, 3520.00, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (20, 4, 4, 'corrective', 'Kart #4 — brake pad replacement',
   'Pads found at minimum thickness during the daily inspection.', 'Replaced the front and rear pad set, cleaned and inspected the rotor (within limits), bled and adjusted the brake, confirmed straight stopping on a test lap.',
   '2026-05-04 14:30:00', '2026-05-04 16:00:00', 1.50, 0.00, 42.00, 0.00, 42.00, 1108.00, 90, 'maintenance', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (21, 13, 4, 'preventive', 'Quarterly service — Batting Cage #1',
   'Pitching machine service and netting inspection.', 'Inspected the throwing arm and springs, lubricated pivots, replaced a worn feed wheel, inspected the netting and repaired two small holes, checked all anchors.',
   '2026-06-15 14:00:00', '2026-06-15 15:30:00', 1.50, 0.00, 34.00, 0.00, 34.00, 88400.00, 0, 'in_service', 'in_service', 10, NULL, NULL, 1, 0, NULL, 4),
  (22, 6, 4, 'repair', 'Kart #6 — clutch replacement',
   'Clutch slipping under acceleration.', 'Removed the clutch, found the shoes worn past the wear line and the drum blued from heat. Replaced the clutch assembly, fitted a new chain, set tension and test-drove ten laps.',
   '2026-06-22 13:45:00', '2026-06-22 16:15:00', 2.50, 0.00, 166.25, 0.00, 166.25, 812.00, 150, 'maintenance', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4),
  (23, 12, 3, 'preventive', 'Monthly track and restraint — Kiddie Coaster',
   'Monthly inspection.', 'Full track walk, rail joints checked, anti-rollbacks function tested, all sixteen lap bars cycled and verified locking. One bar slow to release, noted for follow-up.',
   '2026-07-13 12:00:00', '2026-07-13 14:00:00', 2.00, 0.00, 5.60, 0.00, 5.60, 22310.00, 0, 'in_service', 'in_service', 9, NULL, NULL, 1, 1,
   'Car 3 lap bar releases slowly. Monitor and service if it worsens.', 3),
  (24, 11, 4, 'preventive', '100 hour engine service — Bumper Boats',
   'Fleet engine service.', 'Oil, plug and air filter on all ten boats. Inspected impellers, two replaced. Checked all fuel lines and tank vents.',
   '2026-07-21 13:00:00', '2026-07-21 19:00:00', 6.00, 0.00, 244.00, 0.00, 244.00, 3800.00, 0, 'in_service', 'in_service', 8, NULL, NULL, 1, 0, NULL, 4),
  (25, 3, 4, 'preventive', '50 hour service — Kart #3',
   'Routine interval service.', 'Oil change, air filter cleaned, chain adjusted, brakes inspected. Noted the clutch sounding slightly rough under load.',
   '2026-07-28 13:20:00', '2026-07-28 14:45:00', 1.42, 0.00, 16.15, 0.00, 16.15, 1150.00, 85, 'in_service', 'in_service', 3, NULL, NULL, 1, 1,
   'Clutch noise developing. Watch closely over the next few weeks.', 4),
  (26, 2, 4, 'preventive', '50 hour service — Kart #2',
   'Routine interval service.', 'Oil change, air filter, chain and brake check. All within spec.',
   '2026-08-04 15:00:00', '2026-08-04 16:20:00', 1.33, 0.00, 16.15, 0.00, 16.15, 1275.00, 80, 'in_service', 'in_service', 2, NULL, NULL, 1, 0, NULL, 4),
  (27, 1, 4, 'preventive', '50 hour service — Kart #1',
   'Routine interval service.', 'Oil change, air filter, chain adjusted and lubricated, brake pads at 45%, wheel nuts torqued.',
   '2026-08-11 14:30:00', '2026-08-11 16:00:00', 1.50, 0.00, 16.15, 0.00, 16.15, 1250.00, 90, 'in_service', 'in_service', 1, NULL, NULL, 1, 0, NULL, 4),
  (28, 9, 2, 'repair', 'Ferris Wheel — gondola 14 latch repair',
   'Latch on gondola 14 stiff to close, reported by the ride operator.', 'Disassembled the latch, cleaned out grit and corrosion, replaced the return spring, re-lubricated and reassembled. Cycled fifty times to confirm smooth operation.',
   '2026-08-15 12:40:00', '2026-08-15 14:10:00', 1.50, 0.00, 8.40, 0.00, 8.40, 47820.00, 90, 'in_service', 'in_service', NULL, 3, NULL, 1, 0, NULL, 2),
  (29, 5, 4, 'preventive', '50 hour service — Kart #5',
   'Duo kart interval service.', 'Oil change, air filter, chain, brakes. Valve lash checked this interval, both within spec.',
   '2026-08-18 14:00:00', '2026-08-18 16:00:00', 2.00, 0.00, 20.00, 0.00, 20.00, 925.00, 120, 'in_service', 'in_service', 4, NULL, NULL, 1, 0, NULL, 4),
  (30, 8, 4, 'safety', 'Kiddie Kart #2 — removed from service',
   'Failed the daily pre-operation inspection: kart exceeded the junior speed limit on the test lap.', 'Kart taken off the track immediately and moved to the shop. Governor linkage inspected, appears not to restrict throttle travel fully. Replacement governor assembly ordered. Kart tagged out of service.',
   '2026-08-20 12:30:00', '2026-08-20 13:15:00', 0.75, 0.00, 0.00, 0.00, 0.00, 587.00, NULL, 'in_service', 'out_of_service', NULL, 2, 4, 1, 1,
   'Awaiting the governor assembly from J&J. Kart stays out of service until fitted and re-inspected.', 4),
  (31, 14, 3, 'repair', 'Laser tag — vest battery replacement',
   'Vests 7 and 12 dropping out mid-session.', 'Replaced the battery packs in both vests, cleaned the charging contacts, updated the vest service log. Both ran a full three-hour session on test.',
   '2026-08-22 18:00:00', '2026-08-22 19:15:00', 1.25, 0.00, 78.00, 0.00, 78.00, NULL, 0, 'in_service', 'in_service', NULL, 6, NULL, 1, 0, NULL, 3),
  (32, 3, 4, 'corrective', 'Kart #3 — pulled for clutch diagnosis',
   'Operator reports the kart revs without accelerating from the start line.', 'Pulled the kart from the fleet and moved it to the shop. Confirmed the clutch is slipping badly under load. Clutch and chain ordered.',
   '2026-08-27 17:30:00', '2026-08-27 18:15:00', 0.75, 0.00, 0.00, 0.00, 0.00, 1198.25, NULL, 'in_service', 'maintenance', NULL, 1, NULL, 1, 1,
   'Waiting on the clutch assembly. Fit and test-drive before returning to the fleet.', 4),
  (33, 10, 2, 'preventive', 'Weekly mechanical — Tilt-A-Whirl',
   'Weekly lubrication and restraint check.', 'Greased car pivots and platform bearings, function-tested all seven restraints, checked drive belt tension and the brake pad on the platform drive.',
   '2026-08-31 12:30:00', '2026-08-31 15:00:00', 2.50, 0.00, 5.60, 0.00, 5.60, 31490.00, 0, 'in_service', 'in_service', 7, NULL, NULL, 1, 0, NULL, 2),
  (34, 9, 2, 'preventive', 'Weekly mechanical — Ferris Wheel',
   'Weekly lubrication and fastener check.', 'Greased all gondola pivots and main bearings, torque-checked hub fasteners, inspected the drive belt. No issues found.',
   '2026-08-31 12:00:00', '2026-08-31 15:00:00', 3.00, 0.00, 11.20, 0.00, 11.20, 48180.00, 0, 'in_service', 'in_service', 5, NULL, NULL, 1, 0, NULL, 2),
  (35, 7, 4, 'cleaning', 'Kiddie Kart #1 — deep clean and detail',
   'Scheduled cosmetic refresh.', 'Pressure washed the body and floor pan, degreased the chain and drivetrain, cleaned the seat and belt webbing, touched up scuffed paint on the nose cone.',
   '2026-09-01 13:00:00', '2026-09-01 14:30:00', 1.50, 0.00, 8.00, 0.00, 8.00, 611.25, 0, 'in_service', 'in_service', NULL, NULL, NULL, 1, 0, NULL, 4);


-- -----------------------------------------------------------------------------
-- Parts consumed on those jobs
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {maintenance_log_parts}
  (id, log_id, part_id, part_number, part_name, quantity, unit_cost, total_cost, from_inventory, notes)
VALUES
  (1,  1,  1, 'HON-GX200-OIL',  'Engine oil SAE 10W-30 (quart)', 1.00, 6.75,  6.75,  1, ''),
  (2,  1,  2, 'HON-17210-ZE1',  'Air filter element GX160/200',  1.00, 9.40,  9.40,  1, ''),
  (3,  4, 19, 'BOAT-IMPELLER',  'Bumper boat impeller',          1.00, 61.00, 61.00, 1, 'Boat 3'),
  (4, 12, 17, 'LED-STRIP-16FT', 'LED lighting strip 16 ft',     10.00, 28.00, 280.00, 1, '160 ft total'),
  (5, 20,  4, 'JJ-BRK-PAD-STD', 'Kart brake pad set',            1.00, 42.00, 42.00, 1, ''),
  (6, 22,  6, 'JJ-CLU-200',     'Centrifugal clutch 200-series', 1.00, 132.00,132.00,1, ''),
  (7, 22,  7, 'CHN-35-10FT',    'Roller chain #35 (10 ft)',      1.00, 34.25, 34.25, 1, ''),
  (8, 24,  1, 'HON-GX200-OIL',  'Engine oil SAE 10W-30 (quart)',10.00, 6.75,  67.50, 1, 'Ten boats'),
  (9, 24, 19, 'BOAT-IMPELLER',  'Bumper boat impeller',          2.00, 61.00, 122.00,1, 'Boats 4 and 9'),
  (10,31, 20, 'LT-VEST-BATT',   'Laser tag vest battery pack',   2.00, 39.00, 78.00, 1, 'Vests 7 and 12'),
  (11,27,  1, 'HON-GX200-OIL',  'Engine oil SAE 10W-30 (quart)', 1.00, 6.75,  6.75,  1, ''),
  (12,27,  2, 'HON-17210-ZE1',  'Air filter element GX160/200',  1.00, 9.40,  9.40,  1, '');


-- -----------------------------------------------------------------------------
-- Meter reading history
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {meter_readings}
  (id, asset_id, reading, previous_reading, recorded_at, user_id, source, reference_id, notes)
VALUES
  (1,  1, 1042.00, 980.00,  '2025-11-17 14:00:00', 4, 'maintenance_log', 8,  'End of season'),
  (2,  1, 1250.00, 1042.00, '2026-08-11 14:30:00', 4, 'maintenance_log', 27, '50 hour service'),
  (3,  1, 1284.50, 1250.00, '2026-08-29 14:10:00', 4, 'manual',          NULL, 'End of week reading'),
  (4,  2, 1275.00, 1068.00, '2026-08-04 15:00:00', 4, 'maintenance_log', 26, '50 hour service'),
  (5,  2, 1310.75, 1275.00, '2026-08-29 14:12:00', 4, 'manual',          NULL, 'End of week reading'),
  (6,  3, 1150.00, 962.00,  '2026-07-28 13:20:00', 4, 'maintenance_log', 25, '50 hour service'),
  (7,  3, 1198.25, 1150.00, '2026-08-27 13:45:00', 4, 'manual',          NULL, 'Reading taken when pulled from service'),
  (8,  5, 925.00,  742.00,  '2026-08-18 14:00:00', 4, 'maintenance_log', 29, '50 hour service'),
  (9,  9, 48180.00,47820.00,'2026-08-31 12:00:00', 2, 'maintenance_log', 34, 'Weekly service'),
  (10, 9, 48210.00,48180.00,'2026-09-03 11:30:00', 2, 'inspection',      3,  'Daily pre-opening'),
  (11,11, 3800.00, 3520.00, '2026-07-21 13:00:00', 4, 'maintenance_log', 24, '100 hour service'),
  (12,12, 22310.00,21440.00,'2026-07-13 12:00:00', 3, 'maintenance_log', 23, 'Monthly inspection'),
  (13, 8, 587.00,  560.00,  '2026-08-20 12:10:00', 4, 'inspection',      4,  'Reading at failed inspection');


-- -----------------------------------------------------------------------------
-- Stock movements matching the parts consumed above
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {part_transactions}
  (id, part_id, transaction_type, quantity, unit_cost, balance_after, reference_type, reference_id, user_id, notes, created_at)
VALUES
  (1,  1, 'in',  120.00, 6.75,  120.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (2,  2, 'in',   48.00, 9.40,   48.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (3,  4, 'in',   12.00, 42.00,  12.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (4,  6, 'in',    4.00, 132.00,  4.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (5, 19, 'in',    8.00, 61.00,   8.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (6, 20, 'in',   12.00, 39.00,  12.00, 'purchase',        NULL, 1, 'Opening stock', '2025-09-01 13:00:00'),
  (7, 19, 'out',   1.00, 61.00,   7.00, 'maintenance_log', 4,  4, 'Boat 3 impeller', '2025-10-06 13:30:00'),
  (8,  4, 'out',   1.00, 42.00,  11.00, 'maintenance_log', 20, 4, 'Kart #4 brake pads', '2026-05-04 14:30:00'),
  (9,  6, 'out',   1.00, 132.00,  3.00, 'maintenance_log', 22, 4, 'Kart #6 clutch', '2026-06-22 13:45:00'),
  (10,19, 'out',   2.00, 61.00,   5.00, 'maintenance_log', 24, 4, 'Boats 4 and 9', '2026-07-21 13:00:00'),
  (11,20, 'out',   2.00, 39.00,   6.00, 'maintenance_log', 31, 3, 'Vests 7 and 12', '2026-08-22 18:00:00'),
  (12, 1, 'out',  10.00, 6.75,   58.00, 'maintenance_log', 24, 4, 'Bumper boat fleet service', '2026-07-21 13:00:00'),
  (13, 1, 'out',   1.00, 6.75,   48.00, 'maintenance_log', 27, 4, 'Kart #1 50 hour', '2026-08-11 14:30:00'),
  (14, 2, 'out',   1.00, 9.40,   22.00, 'maintenance_log', 27, 4, 'Kart #1 50 hour', '2026-08-11 14:30:00'),
  (15,19, 'out',   1.00, 61.00,   4.00, 'maintenance_log', 19, 4, 'Season opening', '2026-04-20 13:00:00');


-- -----------------------------------------------------------------------------
-- Work order discussion
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {work_order_comments}
  (id, work_order_id, user_id, comment, is_status_change, old_status, new_status, created_at)
VALUES
  (1, 1, 5, 'Kart pulled from the fleet at 2:15 pm. Guests moved to kart 7.', 0, '', '', '2026-08-27 18:20:00'),
  (2, 1, 4, 'Confirmed the clutch is slipping badly under load. Shoes are worn past the line and the drum is blued. Ordering a clutch and chain.', 0, '', '', '2026-08-27 19:05:00'),
  (3, 1, 4, 'Status changed from Open to In Progress.', 1, 'open', 'in_progress', '2026-08-27 19:06:00'),
  (4, 1, 4, 'Parts due in Wednesday. Kart stays in the shop until then.', 0, '', '', '2026-08-28 13:30:00'),
  (5, 2, 4, 'Governor linkage inspected. It is not restricting throttle travel fully. Replacement assembly ordered from J&J.', 0, '', '', '2026-08-20 17:10:00'),
  (6, 2, 4, 'Status changed from In Progress to On Hold — waiting on the part.', 1, 'in_progress', 'on_hold', '2026-08-21 14:00:00'),
  (7, 3, 2, 'Latch cleaned, new return spring fitted, cycled fifty times. Operating normally.', 0, '', '', '2026-08-15 14:05:00'),
  (8, 3, 2, 'Status changed from In Progress to Completed.', 1, 'in_progress', 'completed', '2026-08-15 14:10:00'),
  (9, 4, 3, 'Scheduled for Monday morning before opening.', 0, '', '', '2026-08-30 16:00:00'),
  (10,6, 3, 'Both packs replaced and contacts cleaned. Ran a full session on test with no dropouts.', 0, '', '', '2026-08-22 19:14:00');


-- -----------------------------------------------------------------------------
-- Link the completed work orders back to their maintenance logs
-- -----------------------------------------------------------------------------
UPDATE {work_orders} SET inspection_id = 4 WHERE id = 2;
UPDATE {inspections} SET work_order_id = 2, log_id = 30 WHERE id = 4;
