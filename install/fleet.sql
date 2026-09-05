-- =============================================================================
--  RideLog — Castle Fun Center starting fleet
--
--  Installed when "Start with the Castle Fun Center fleet" is chosen in the
--  installer. Real machines, sensible categories, locations, daily checklists
--  and service schedules — and NO made-up history. Every name here is a
--  starting point: rename, re-tag, recategorise or delete anything from the
--  Machines screen. Counts that were guesses (bowling lanes, the extras at the
--  end) are easy to add to or trim.
--
--  Depends on: schema.sql and seed.sql, plus the administrator account the
--  installer creates as user id 1. Uses INSERT IGNORE throughout.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Categories the reference data does not already have (ids 1–11 come from
-- seed.sql: Go-Kart, Kiddie Ride, Major Ride, …, Facility Equipment)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {asset_categories}
  (id, name, slug, description, icon, color, default_meter_type, sort_order, is_active)
VALUES
  (12, 'Zip Line',          'zip-line',          'Zip line towers, cables, trolleys and harness sets', 'activity', '#f97316', 'cycles', 25, 1),
  (13, 'Bowling',           'bowling',           'Lanes, pinsetters, ball returns and scoring',        'grid',     '#0ea5e9', 'cycles', 120, 1),
  (14, 'Axe Throwing',      'axe-throwing',      'Throwing lanes, targets, cages and axes',            'tool',     '#b45309', 'none',   130, 1),
  (15, 'Indoor Attraction', 'indoor-attraction', 'Roller rink, climbing wall and other indoor fun',    'activity', '#7c3aed', 'none',   140, 1);


-- -----------------------------------------------------------------------------
-- Locations (ids 1–11 come from seed.sql)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {locations} (id, name, description, building, sort_order, is_active) VALUES
  (12, 'Ride Area',        'Freefall, Dragon Coaster and Swings',   'Outdoor',       35, 1),
  (13, 'Zip Line',         'Launch tower, cable run and landing',   'Outdoor',       36, 1),
  (14, 'Bowling Center',   'Lanes and pinsetter deck',              'Main Building', 45, 1),
  (15, 'Axe Throwing Bay', 'Throwing lanes and cages',              'Main Building', 46, 1),
  (16, 'Roller Rink',      'Skating floor and skate rental',        'Main Building', 47, 1),
  (17, 'Climbing Wall',    'Wall, auto-belays and landing mats',    'Main Building', 48, 1);


-- -----------------------------------------------------------------------------
-- Machines
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {assets}
  (id, asset_tag, name, category_id, location_id, status, criticality,
   fuel_type, capacity_passengers, meter_type, meter_reading, description, qr_slug, sort_order, created_by)
VALUES
  (1, 'GK-001', 'Go-Kart #1', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qvjzs6d0ik7euba6h', 10, 1),
  (2, 'GK-002', 'Go-Kart #2', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qyf9x47udow0xevub', 20, 1),
  (3, 'GK-003', 'Go-Kart #3', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qo0ghtjtsci51500o', 30, 1),
  (4, 'GK-004', 'Go-Kart #4', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qbdxzh39kf4943oa3', 40, 1),
  (5, 'GK-005', 'Go-Kart #5', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qt882ndyqxkxo79wp', 50, 1),
  (6, 'GK-006', 'Go-Kart #6', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qt4hd4b3k8ex4ksj7', 60, 1),
  (7, 'GK-007', 'Go-Kart #7', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q5kw3tt8yjki9hs1a', 70, 1),
  (8, 'GK-008', 'Go-Kart #8', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qqdg8cesw7xad9bvy', 80, 1),
  (9, 'GK-009', 'Go-Kart #9', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q5pod6uu4enufr0pp', 90, 1),
  (10, 'GK-010', 'Go-Kart #10', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qs2ibfngskuwrdyb6', 100, 1),
  (11, 'GK-011', 'Go-Kart #11', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'ql1rd9y2a1urtztfu', 110, 1),
  (12, 'GK-012', 'Go-Kart #12', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q1lv5lszmxqvlebmd', 120, 1),
  (13, 'GK-013', 'Go-Kart #13', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qnko1umo06398uxji', 130, 1),
  (14, 'GK-014', 'Go-Kart #14', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qnqf0wpoi9rximcze', 140, 1),
  (15, 'GK-015', 'Go-Kart #15', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qg1egibewx2goz868', 150, 1),
  (16, 'GK-016', 'Go-Kart #16', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qiws0yupt4talzkdq', 160, 1),
  (17, 'GK-017', 'Go-Kart #17', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qujdep8e62w61ew2a', 170, 1),
  (18, 'GK-018', 'Go-Kart #18', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qiaq1r9q8hfagmtjj', 180, 1),
  (19, 'GK-019', 'Go-Kart #19', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qz4cdnb2582ztpal5', 190, 1),
  (20, 'GK-020', 'Go-Kart #20', 1, 1, 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q7yhtd6uuws4uobge', 200, 1),
  (21, 'RD-001', 'Freefall', 3, 12, 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Drop tower. Daily pre-opening inspection, weekly mechanical, and the annual state inspection.', 'q8tjh46qxey70gxgq', 300, 1),
  (22, 'RD-002', 'Dragon Coaster', 3, 12, 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Roller coaster. Daily pre-opening inspection with a walk of the track; weekly mechanical; annual state inspection.', 'q3qu0wwg8ckc9plqj', 310, 1),
  (23, 'RD-003', 'Swings', 3, 12, 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Chair swing ride. Daily pre-opening inspection with every chair, chain and hanger checked.', 'q1ziy5aaegbg6lqet', 320, 1),
  (24, 'ZL-001', 'Zip Line', 12, 13, 'in_service', 'critical',
   '', NULL, 'cycles', 0.00, 'Zip line: tower, cable, trolleys, brake and the harness sets. Daily pre-opening inspection and a monthly cable and hardware inspection.', 'q4l173zdd4fj497c5', 330, 1),
  (25, 'BL-001', 'Bowling Lane 1', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qrmj788ruceqbmeb9', 410, 1),
  (26, 'BL-002', 'Bowling Lane 2', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'q2hf91lyquus43s6t', 420, 1),
  (27, 'BL-003', 'Bowling Lane 3', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qwzau9ufmnq58rzpw', 430, 1),
  (28, 'BL-004', 'Bowling Lane 4', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qgjuncdon82awffge', 440, 1),
  (29, 'BL-005', 'Bowling Lane 5', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'q1wjl6x1tne0vw0wb', 450, 1),
  (30, 'BL-006', 'Bowling Lane 6', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qbfr2kb94ake59g1c', 460, 1),
  (31, 'BL-007', 'Bowling Lane 7', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qk39yz2c28pnb9yon', 470, 1),
  (32, 'BL-008', 'Bowling Lane 8', 13, 14, 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qb4z9tt65bun8i1zz', 480, 1),
  (33, 'AX-001', 'Axe Throw Unit 1', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qm6cgpsa4rt5wg0x7', 510, 1),
  (34, 'AX-002', 'Axe Throw Unit 2', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qewcu572l8bxsyqaf', 520, 1),
  (35, 'AX-003', 'Axe Throw Unit 3', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qgip7pr209x4g5hrb', 530, 1),
  (36, 'AX-004', 'Axe Throw Unit 4', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qup6eog1n63pd3hng', 540, 1),
  (37, 'AX-005', 'Axe Throw Unit 5', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'q0cw431uqqmqghshn', 550, 1),
  (38, 'AX-006', 'Axe Throw Unit 6', 14, 15, 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qps9hltc7vdkvpey4', 560, 1),
  (39, 'LT-001', 'Laser Tag Arena', 9, 8, 'in_service', 'medium',
   'Electric', NULL, 'none', 0.00, 'Arena, vests, phasers and effects.', 'qdu2wvkf6c6f6vi3x', 600, 1),
  (40, 'RR-001', 'Roller Rink', 15, 16, 'in_service', 'medium',
   'Electric', NULL, 'none', 0.00, 'Skating floor, barriers, lighting and the rental skate fleet.', 'q1irmylpex8l2hsjt', 610, 1),
  (41, 'CW-001', 'Climbing Wall', 15, 17, 'in_service', 'high',
   '', NULL, 'none', 0.00, 'Climbing wall with auto-belay devices. Daily check of belays, harnesses and mats; annual belay recertification.', 'qffxogeabx4gysgni', 620, 1),
  (42, 'MG-001', 'Mini Golf Course', 7, 6, 'in_service', 'low',
   '', NULL, 'none', 0.00, 'Course holes, obstacles, water features and pumps.', 'qxksfoheqgf0uh7rl', 630, 1),
  (43, 'AR-001', 'Arcade', 6, 4, 'in_service', 'low',
   'Electric', NULL, 'none', 0.00, 'Arcade floor and game cabinets. Add individual cabinets as their own machines if you want to track them separately.', 'qiqvbb24lhkw5qium', 640, 1),
  (44, 'FE-001', 'Shop Air Compressor', 11, 9, 'in_service', 'medium',
   'Electric', NULL, 'hours', 0.00, 'Workshop compressor. Quarterly service: drain, filter, belt, safety valve.', 'q8svqzzryfoxrpv9g', 700, 1);


-- -----------------------------------------------------------------------------
-- The daily ride checklist from seed.sql applies to everything by default.
-- With bowling lanes and an arcade in the list that is too broad, so point it
-- at rides only. (Only touched if nobody has changed it already.)
-- -----------------------------------------------------------------------------
UPDATE {checklists} SET applies_to = 'category', category_id = 3
 WHERE id = 2 AND applies_to = 'all' AND category_id IS NULL;


-- -----------------------------------------------------------------------------
-- Checklist 3 — Daily Zip Line Pre-Opening Inspection
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (3, 'Daily Zip Line Pre-Opening Inspection',
   'Complete before the first rider of the day. Follow the manufacturer''s and the course builder''s manuals in addition to this list. Any failed critical item keeps the line closed until it is corrected and re-inspected.',
   'category', 12, NULL, 'daily', 20, 1, 0, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (41, 3, 'Cable & Anchors', 'Cable free of broken wires, kinks, corrosion or flattening',
       'Walk the length you can see and check at the terminations with a glove.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10),
  (42, 3, 'Cable & Anchors', 'Anchors, turnbuckles, shackles and cable clamps tight and secured',
       'Seizing wire and mousing in place. No movement at the anchor points.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (43, 3, 'Cable & Anchors', 'Tower, platform structure and guardrails sound',
       'No cracks, loose fasteners, rot or missing rails.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 30),
  (44, 3, 'Trolleys & Brake', 'Trolleys roll freely, sheaves undamaged, no play in the side plates',
       'Spin each sheave. Check for flat spots and cracked flanges.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40),
  (45, 3, 'Trolleys & Brake', 'Brake / arrest system stops a test trolley in the normal zone',
       'Send an unloaded trolley (or ballast where the manufacturer requires it) and watch the stop.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 50),
  (46, 3, 'Harnesses & Lanyards', 'Every harness, lanyard and carabiner inspected and free of wear',
       'Webbing, stitching, buckles, gates and locking sleeves. Retire anything doubtful.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 60),
  (47, 3, 'Harnesses & Lanyards', 'Helmets and gloves present, clean and undamaged',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 70),
  (48, 3, 'Launch & Landing', 'Launch and landing platforms, gates and steps clear and dry',
       'No trip hazards, gates latch, landing zone clear of guests.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 80),
  (49, 3, 'Launch & Landing', 'Radios / communication between launch and landing working',
       '', 'pass_fail', 1, 1, 0, '', '', NULL, NULL, 90),
  (50, 3, 'Conditions', 'Weather acceptable: no lightning, high wind or ice on the cable',
       'Follow the operating limits in the manual.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 100),
  (51, 3, 'Final', 'Staff test ride completed normally',
       'One qualified staff member rides before the first guest.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 110),
  (52, 3, 'Final', 'Rider count / cycle reading',
       'Record the counter if there is one.',
       'meter', 0, 0, 0, '', 'cycles', NULL, NULL, 120),
  (53, 3, 'Final', 'Notes for the day',
       'Anything the next shift should know.',
       'text', 0, 0, 0, '', '', NULL, NULL, 130);


-- -----------------------------------------------------------------------------
-- Checklist 4 — Daily Axe Throwing Lane Check
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (4, 'Daily Axe Throwing Lane Check',
   'Quick check of each lane before it opens. A failed critical item closes the lane until it is fixed.',
   'category', 14, NULL, 'daily', 5, 1, 0, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (61, 4, 'Target', 'Target boards solid — no splits, soft spots or loose boards',
       'Replace or rotate boards before they start rejecting axes.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10),
  (62, 4, 'Target', 'Backstop and side fencing intact, no gaps or protruding fasteners',
       '', 'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (63, 4, 'Cage', 'Cage door latches and stays shut; throwing line clearly marked',
       '', 'pass_fail', 1, 1, 1, '', '', NULL, NULL, 30),
  (64, 4, 'Cage', 'Floor clear and dry, no chips or debris in the lane',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 40),
  (65, 4, 'Axes', 'Axe heads tight on the handles, no cracks in handles or heads',
       'Check every axe kept at the lane. Pull the head, flex the handle.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50),
  (66, 4, 'Axes', 'Edges in usable condition, no burrs or chips',
       '', 'pass_fail', 1, 0, 0, '', '', NULL, NULL, 60),
  (67, 4, 'Safety', 'Rules signage visible and first aid kit at the lane stocked',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 70),
  (68, 4, 'Final', 'Notes',
       '', 'text', 0, 0, 0, '', '', NULL, NULL, 80);


-- -----------------------------------------------------------------------------
-- Checklist 5 — Weekly Bowling Lane & Pinsetter Check
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (5, 'Weekly Bowling Lane & Pinsetter Check',
   'One lane at a time, with the pinsetter cycled under observation. Follow the pinsetter manufacturer''s lockout procedure before reaching into the machine.',
   'category', 13, NULL, 'weekly', 15, 1, 0, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (71, 5, 'Pinsetter', 'Pinsetter cycles cleanly — no jams, mis-sets or dropped pins',
       'Watch several full cycles including a strike and a spare.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 10),
  (72, 5, 'Pinsetter', 'Guards, covers and interlocks in place and working',
       'The machine must stop when a guard is opened.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (73, 5, 'Pinsetter', 'Pin elevator, distributor and turret free of wear or damage',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 30),
  (74, 5, 'Lane', 'Ball return delivers to the rack every time',
       '', 'pass_fail', 1, 0, 0, '', '', NULL, NULL, 40),
  (75, 5, 'Lane', 'Lane surface, gutters and capping undamaged; oil pattern acceptable',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 50),
  (76, 5, 'Lane', 'Bumpers deploy and retract correctly',
       '', 'pass_fail_na', 1, 0, 0, '', '', NULL, NULL, 60),
  (77, 5, 'Lane', 'Foul light and scoring console working',
       '', 'pass_fail', 1, 0, 0, '', '', NULL, NULL, 70),
  (78, 5, 'Final', 'Frame counter reading',
       'Record the pinsetter frame counter if it has one.',
       'meter', 0, 0, 0, '', 'cycles', NULL, NULL, 80),
  (79, 5, 'Final', 'Notes',
       '', 'text', 0, 0, 0, '', '', NULL, NULL, 90);


-- -----------------------------------------------------------------------------
-- Checklist 6 — Daily Climbing Wall Check
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {checklists}
  (id, name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
VALUES
  (6, 'Daily Climbing Wall Check',
   'Before the first climber. Auto-belay devices follow the manufacturer''s daily inspection exactly; this list is the reminder, not the manual.',
   'asset', NULL, 41, 'daily', 10, 1, 0, 1);

INSERT IGNORE INTO {checklist_items}
  (id, checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
VALUES
  (81, 6, 'Auto-belays', 'Every auto-belay: webbing undamaged, retracts smoothly, carabiner locks',
       'Pull out and let retract. Check the whole length of webbing for cuts, glazing or fraying.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10),
  (82, 6, 'Auto-belays', 'Auto-belay mounting and anchors secure',
       '', 'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20),
  (83, 6, 'Wall', 'Holds tight, no spinning or cracked holds; panels and t-nuts sound',
       '', 'pass_fail', 1, 0, 1, '', '', NULL, NULL, 30),
  (84, 6, 'Landing', 'Landing mats in place, joined, no gaps or damage',
       '', 'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40),
  (85, 6, 'Harnesses', 'Rental harnesses inspected: webbing, stitching, buckles',
       '', 'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50),
  (86, 6, 'Final', 'Notes',
       '', 'text', 0, 0, 0, '', '', NULL, NULL, 60);


-- -----------------------------------------------------------------------------
-- Scheduled service. next_due is left empty: the installer recomputes it from
-- today, so a fresh install starts with everything one interval out.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {maintenance_schedules}
  (id, asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
VALUES
  (1, 1, 'Kart #1 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (2, 2, 'Kart #2 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (3, 3, 'Kart #3 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (4, 4, 'Kart #4 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (5, 5, 'Kart #5 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (6, 6, 'Kart #6 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (7, 7, 'Kart #7 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (8, 8, 'Kart #8 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (9, 9, 'Kart #9 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (10, 10, 'Kart #10 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (11, 11, 'Kart #11 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (12, 12, 'Kart #12 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (13, 13, 'Kart #13 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (14, 14, 'Kart #14 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (15, 15, 'Kart #15 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (16, 16, 'Kart #16 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (17, 17, 'Kart #17 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (18, 18, 'Kart #18 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (19, 19, 'Kart #19 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (20, 20, 'Kart #20 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
   50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, 1),
  (21, 21, 'Freefall — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', 2, 'weekly', 1,
   NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, 1),
  (22, 21, 'Freefall — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
   NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, 1),
  (23, 21, 'Freefall — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
   NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, 1),
  (24, 22, 'Dragon Coaster — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', 2, 'weekly', 1,
   NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, 1),
  (25, 22, 'Dragon Coaster — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
   NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, 1),
  (26, 22, 'Dragon Coaster — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
   NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, 1),
  (27, 23, 'Swings — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', 2, 'weekly', 1,
   NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, 1),
  (28, 23, 'Swings — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
   NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, 1),
  (29, 23, 'Swings — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
   NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, 1),
  (30, 24, 'Zip Line — monthly cable and hardware inspection', 'Close inspection of the cable, terminations, trolleys, brake and every harness set.', 'inspection', NULL, 'monthly', 1,
   NULL, 5, 3.00, 'high', 'Inspect the full cable length for broken wires and corrosion. Check anchors, turnbuckles and clamps for movement. Measure trolley sheave wear. Retire any harness or lanyard that fails inspection and record its serial number.', 1, 1),
  (31, 24, 'Zip Line — annual professional inspection', 'Annual inspection by a qualified inspector, plus manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
   NULL, 45, 8.00, 'urgent', 'Book the inspector before the season. Replace harnesses and lanyards that have reached the manufacturer''s retirement age. Keep the report with the zip line file.', 1, 1),
  (32, 25, 'Lane 1 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (33, 26, 'Lane 2 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (34, 27, 'Lane 3 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (35, 28, 'Lane 4 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (36, 29, 'Lane 5 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (37, 30, 'Lane 6 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (38, 31, 'Lane 7 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (39, 32, 'Lane 8 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', 5, 'monthly', 1,
   NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, 1),
  (40, 33, 'Axe Throw Unit 1 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (41, 34, 'Axe Throw Unit 2 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (42, 35, 'Axe Throw Unit 3 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (43, 36, 'Axe Throw Unit 4 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (44, 37, 'Axe Throw Unit 5 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (45, 38, 'Axe Throw Unit 6 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', 4, 'monthly', 1,
   NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, 1),
  (46, 41, 'Climbing Wall — annual auto-belay recertification', 'Auto-belay devices returned to the manufacturer or an approved service centre for annual recertification.', 'inspection', NULL, 'annual', 1,
   NULL, 45, 2.00, 'urgent', 'Send the devices in before their certification expires; have spares or plan the closure. Keep the certificates with the wall file.', 1, 1),
  (47, 41, 'Climbing Wall — monthly holds and anchor check', 'Tighten holds, inspect t-nuts, anchors and top-out hardware.', 'preventive', 6, 'monthly', 1,
   NULL, 5, 1.50, 'normal', 'Check every hold for spinning. Inspect anchors and auto-belay mounts. Replace worn mats.', 1, 1),
  (48, 39, 'Laser Tag — quarterly vest and phaser service', 'Battery health, straps, sensors and phaser function across the whole set.', 'preventive', NULL, 'quarterly', 1,
   NULL, 7, 3.00, 'normal', 'Test every vest and phaser. Replace weak batteries and frayed straps. Clean sensors. Check arena effects and emergency lighting.', 1, 1),
  (49, 40, 'Roller Rink — monthly floor and skate fleet check', 'Floor surface, barriers, lighting and the rental skates.', 'preventive', NULL, 'monthly', 1,
   NULL, 5, 2.00, 'normal', 'Walk the floor for lifted seams and damage. Check barrier fixings. Inspect rental skates: wheels, bearings, laces, stops.', 1, 1),
  (50, 42, 'Mini Golf — pre-season course walk', 'Every hole, obstacle, water feature and pump before opening for the season.', 'preventive', NULL, 'annual', 1,
   NULL, 30, 6.00, 'normal', 'Repair surfaces, fix loose obstacles, service the pumps and clean the water features. Check lighting and signage.', 1, 1),
  (51, 44, 'Compressor — quarterly service', 'Drain, filters, belt, safety valve and pressure switch.', 'preventive', NULL, 'quarterly', 1,
   NULL, 7, 1.00, 'low', 'Drain the tank. Clean or replace the intake filter. Check belt tension and the safety valve. Note the hour reading.', 1, 1);
