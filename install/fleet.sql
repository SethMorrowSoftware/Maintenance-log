-- =============================================================================
--  RideLog — Castle Fun Center starting fleet
--
--  Loaded by the installer when "Start with the Castle Fun Center fleet" is
--  chosen, or later from Settings → System on a site that already exists.
--  Real machines, sensible categories, locations, daily checklists and service
--  schedules — and NO made-up history. Every name here is a starting point:
--  rename, re-tag, recategorise or delete anything from the Machines screen.
--  The indoor extras at the end were guesses; trim or add.
--
--  Nothing here names a row id. Machines are matched by tag, categories by
--  slug, locations and checklists by name, so this loads safely next to
--  machines a site already has, and loading it twice adds nothing. A machine
--  whose tag already exists is left exactly as it is; the fleet's schedule for
--  that tag attaches to it.
--
--  Depends on: schema.sql and seed.sql. Records are owned by the first
--  administrator account.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Categories the reference data does not already have (seed.sql provides
-- Go-Kart, Kiddie Ride, Major Ride, …, Facility Equipment)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {asset_categories}
  (name, slug, description, icon, color, default_meter_type, sort_order, is_active)
VALUES
  ('Zip Line',          'zip-line',          'Zip line towers, cables, trolleys and harness sets', 'activity', '#f97316', 'cycles', 25, 1),
  ('Bowling',           'bowling',           'Lanes, pinsetters, ball returns and scoring',        'grid',     '#0ea5e9', 'cycles', 120, 1),
  ('Axe Throwing',      'axe-throwing',      'Throwing lanes, targets, cages and axes',            'tool',     '#b45309', 'none',   130, 1),
  ('Indoor Attraction', 'indoor-attraction', 'Roller rink, climbing wall and other indoor fun',    'activity', '#7c3aed', 'none',   140, 1);


-- -----------------------------------------------------------------------------
-- Locations (seed.sql provides the track, midway, arcade, shop and so on)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {locations} (name, description, building, sort_order, is_active) VALUES
  ('Ride Area',        'Freefall, Dragon Coaster and Swings',   'Outdoor',       35, 1),
  ('Zip Line',         'Launch tower, cable run and landing',   'Outdoor',       36, 1),
  ('Bowling Center',   'Lanes and pinsetter deck',              'Main Building', 45, 1),
  ('Axe Throwing Bay', 'Throwing lanes and cages',              'Main Building', 46, 1),
  ('Roller Rink',      'Skating floor and skate rental',        'Main Building', 47, 1),
  ('Climbing Wall',    'Wall, auto-belays and landing mats',    'Main Building', 48, 1);


-- -----------------------------------------------------------------------------
-- Machines. INSERT IGNORE against the unique tag: one that exists is kept.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO {assets}
  (asset_tag, name, category_id, location_id, status, criticality,
   fuel_type, capacity_passengers, meter_type, meter_reading, description, qr_slug, sort_order, created_by)
VALUES
  ('GK-001', 'Go-Kart #1', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qvjzs6d0ik7euba6h', 10, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-002', 'Go-Kart #2', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qyf9x47udow0xevub', 20, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-003', 'Go-Kart #3', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qo0ghtjtsci51500o', 30, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-004', 'Go-Kart #4', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qbdxzh39kf4943oa3', 40, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-005', 'Go-Kart #5', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qt882ndyqxkxo79wp', 50, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-006', 'Go-Kart #6', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qt4hd4b3k8ex4ksj7', 60, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-007', 'Go-Kart #7', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q5kw3tt8yjki9hs1a', 70, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-008', 'Go-Kart #8', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qqdg8cesw7xad9bvy', 80, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-009', 'Go-Kart #9', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q5pod6uu4enufr0pp', 90, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-010', 'Go-Kart #10', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qs2ibfngskuwrdyb6', 100, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-011', 'Go-Kart #11', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'ql1rd9y2a1urtztfu', 110, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-012', 'Go-Kart #12', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q1lv5lszmxqvlebmd', 120, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-013', 'Go-Kart #13', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qnko1umo06398uxji', 130, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-014', 'Go-Kart #14', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qnqf0wpoi9rximcze', 140, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-015', 'Go-Kart #15', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qg1egibewx2goz868', 150, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-016', 'Go-Kart #16', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qiws0yupt4talzkdq', 160, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-017', 'Go-Kart #17', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qujdep8e62w61ew2a', 170, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-018', 'Go-Kart #18', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qiaq1r9q8hfagmtjj', 180, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-019', 'Go-Kart #19', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'qz4cdnb2582ztpal5', 190, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('GK-020', 'Go-Kart #20', (SELECT id FROM {asset_categories} WHERE slug = 'go-kart' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Go-Kart Track' LIMIT 1), 'in_service', 'high',
   'Gasoline', 1, 'hours', 0.00, 'Go-kart. Daily pre-operation check before the track opens; 50-hour service by the hour meter.', 'q7yhtd6uuws4uobge', 200, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('RD-001', 'Freefall', (SELECT id FROM {asset_categories} WHERE slug = 'major-ride' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Ride Area' LIMIT 1), 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Drop tower. Daily pre-opening inspection, weekly mechanical, and the annual state inspection.', 'q8tjh46qxey70gxgq', 300, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('RD-002', 'Dragon Coaster', (SELECT id FROM {asset_categories} WHERE slug = 'major-ride' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Ride Area' LIMIT 1), 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Roller coaster. Daily pre-opening inspection with a walk of the track; weekly mechanical; annual state inspection.', 'q3qu0wwg8ckc9plqj', 310, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('RD-003', 'Swings', (SELECT id FROM {asset_categories} WHERE slug = 'major-ride' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Ride Area' LIMIT 1), 'in_service', 'critical',
   'Electric', NULL, 'cycles', 0.00, 'Chair swing ride. Daily pre-opening inspection with every chair, chain and hanger checked.', 'q1ziy5aaegbg6lqet', 320, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('ZL-001', 'Zip Line', (SELECT id FROM {asset_categories} WHERE slug = 'zip-line' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Zip Line' LIMIT 1), 'in_service', 'critical',
   '', NULL, 'cycles', 0.00, 'Zip line: tower, cable, trolleys, brake and the harness sets. Daily pre-opening inspection and a monthly cable and hardware inspection.', 'q4l173zdd4fj497c5', 330, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-001', 'Bowling Lane 1', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qrmj788ruceqbmeb9', 410, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-002', 'Bowling Lane 2', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'q2hf91lyquus43s6t', 420, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-003', 'Bowling Lane 3', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qwzau9ufmnq58rzpw', 430, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-004', 'Bowling Lane 4', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qgjuncdon82awffge', 440, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-005', 'Bowling Lane 5', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'q1wjl6x1tne0vw0wb', 450, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-006', 'Bowling Lane 6', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qbfr2kb94ake59g1c', 460, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-007', 'Bowling Lane 7', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qk39yz2c28pnb9yon', 470, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-008', 'Bowling Lane 8', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qb4z9tt65bun8i1zz', 480, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-009', 'Bowling Lane 9', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qm6cgpsa4rt5wg0x7', 490, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-010', 'Bowling Lane 10', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qewcu572l8bxsyqaf', 500, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-011', 'Bowling Lane 11', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qgip7pr209x4g5hrb', 510, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('BL-012', 'Bowling Lane 12', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Bowling Center' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'cycles', 0.00, 'Lane, pinsetter, ball return and scoring for this lane. Weekly lane check; monthly pinsetter service.', 'qup6eog1n63pd3hng', 520, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-001', 'Axe Throw Unit 1', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'q0cw431uqqmqghshn', 510, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-002', 'Axe Throw Unit 2', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qps9hltc7vdkvpey4', 520, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-003', 'Axe Throw Unit 3', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qdu2wvkf6c6f6vi3x', 530, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-004', 'Axe Throw Unit 4', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'q1irmylpex8l2hsjt', 540, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-005', 'Axe Throw Unit 5', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qffxogeabx4gysgni', 550, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AX-006', 'Axe Throw Unit 6', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Axe Throwing Bay' LIMIT 1), 'in_service', 'medium',
   '', 2, 'none', 0.00, 'Throwing lane: target boards, backstop, cage and the axes kept at the lane. Daily lane check.', 'qxksfoheqgf0uh7rl', 560, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('LT-001', 'Laser Tag Arena', (SELECT id FROM {asset_categories} WHERE slug = 'laser-tag' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Laser Tag Arena' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'none', 0.00, 'Arena, vests, phasers and effects.', 'qiqvbb24lhkw5qium', 600, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('RR-001', 'Roller Rink', (SELECT id FROM {asset_categories} WHERE slug = 'indoor-attraction' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Roller Rink' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'none', 0.00, 'Skating floor, barriers, lighting and the rental skate fleet.', 'q8svqzzryfoxrpv9g', 610, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('CW-001', 'Climbing Wall', (SELECT id FROM {asset_categories} WHERE slug = 'indoor-attraction' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Climbing Wall' LIMIT 1), 'in_service', 'high',
   '', NULL, 'none', 0.00, 'Climbing wall with auto-belay devices. Daily check of belays, harnesses and mats; annual belay recertification.', 'qdqzkd01i715hqh5q', 620, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('MG-001', 'Mini Golf Course', (SELECT id FROM {asset_categories} WHERE slug = 'mini-golf' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Mini Golf Course' LIMIT 1), 'in_service', 'low',
   '', NULL, 'none', 0.00, 'Course holes, obstacles, water features and pumps.', 'q1mpc9k3khli4z4la', 630, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('AR-001', 'Arcade', (SELECT id FROM {asset_categories} WHERE slug = 'arcade-game' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Arcade' LIMIT 1), 'in_service', 'low',
   'Electric', NULL, 'none', 0.00, 'Arcade floor and game cabinets. Add individual cabinets as their own machines if you want to track them separately.', 'q5t82y2hxg11o92nd', 640, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)),
  ('FE-001', 'Shop Air Compressor', (SELECT id FROM {asset_categories} WHERE slug = 'facility-equipment' LIMIT 1), (SELECT id FROM {locations} WHERE name = 'Maintenance Shop' LIMIT 1), 'in_service', 'medium',
   'Electric', NULL, 'hours', 0.00, 'Workshop compressor. Quarterly service: drain, filter, belt, safety valve.', 'qmq2v8rkjcqz600xz', 700, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL));


-- -----------------------------------------------------------------------------
-- The daily ride checklist from seed.sql applies to everything by default.
-- With bowling lanes and an arcade in the list that is too broad, so point it
-- at rides only. (Only touched if nobody has changed it already.)
-- -----------------------------------------------------------------------------
UPDATE {checklists} SET applies_to = 'category', category_id = (SELECT id FROM {asset_categories} WHERE slug = 'major-ride' LIMIT 1)
 WHERE name = 'Daily Ride Pre-Opening Inspection' AND applies_to = 'all' AND category_id IS NULL;


-- -----------------------------------------------------------------------------
-- Daily Zip Line Pre-Opening Inspection
-- -----------------------------------------------------------------------------
INSERT INTO {checklists}
  (name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
SELECT 'Daily Zip Line Pre-Opening Inspection',
       'Complete before the first rider of the day. Follow the manufacturer''s and the course builder''s manuals in addition to this list. Any failed critical item keeps the line closed until it is corrected and re-inspected.',
       'category', (SELECT id FROM {asset_categories} WHERE slug = 'zip-line' LIMIT 1), NULL, 'daily', 20, 1, 0, 1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM {checklists} WHERE name = 'Daily Zip Line Pre-Opening Inspection');

INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Cable & Anchors', 'Cable free of broken wires, kinks, corrosion or flattening',
       'Walk the length you can see and check at the terminations with a glove.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Cable free of broken wires, kinks, corrosion or flattening');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Cable & Anchors', 'Anchors, turnbuckles, shackles and cable clamps tight and secured',
       'Seizing wire and mousing in place. No movement at the anchor points.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Anchors, turnbuckles, shackles and cable clamps tight and secured');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Cable & Anchors', 'Tower, platform structure and guardrails sound',
       'No cracks, loose fasteners, rot or missing rails.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 30
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Tower, platform structure and guardrails sound');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Trolleys & Brake', 'Trolleys roll freely, sheaves undamaged, no play in the side plates',
       'Spin each sheave. Check for flat spots and cracked flanges.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Trolleys roll freely, sheaves undamaged, no play in the side plates');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Trolleys & Brake', 'Brake / arrest system stops a test trolley in the normal zone',
       'Send an unloaded trolley (or ballast where the manufacturer requires it) and watch the stop.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 50
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Brake / arrest system stops a test trolley in the normal zone');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Harnesses & Lanyards', 'Every harness, lanyard and carabiner inspected and free of wear',
       'Webbing, stitching, buckles, gates and locking sleeves. Retire anything doubtful.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 60
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Every harness, lanyard and carabiner inspected and free of wear');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Harnesses & Lanyards', 'Helmets and gloves present, clean and undamaged',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 70
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Helmets and gloves present, clean and undamaged');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Launch & Landing', 'Launch and landing platforms, gates and steps clear and dry',
       'No trip hazards, gates latch, landing zone clear of guests.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 80
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Launch and landing platforms, gates and steps clear and dry');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Launch & Landing', 'Radios / communication between launch and landing working',
       '',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 90
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Radios / communication between launch and landing working');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Conditions', 'Weather acceptable: no lightning, high wind or ice on the cable',
       'Follow the operating limits in the manual.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 100
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Weather acceptable: no lightning, high wind or ice on the cable');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Staff test ride completed normally',
       'One qualified staff member rides before the first guest.',
       'pass_fail', 1, 1, 0, '', '', NULL, NULL, 110
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Staff test ride completed normally');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Rider count / cycle reading',
       'Record the counter if there is one.',
       'meter', 0, 0, 0, '', 'cycles', NULL, NULL, 120
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Rider count / cycle reading');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Notes for the day',
       'Anything the next shift should know.',
       'text', 0, 0, 0, '', '', NULL, NULL, 130
  FROM {checklists} c
 WHERE c.name = 'Daily Zip Line Pre-Opening Inspection'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Notes for the day');

-- -----------------------------------------------------------------------------
-- Daily Axe Throwing Lane Check
-- -----------------------------------------------------------------------------
INSERT INTO {checklists}
  (name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
SELECT 'Daily Axe Throwing Lane Check',
       'Quick check of each lane before it opens. A failed critical item closes the lane until it is fixed.',
       'category', (SELECT id FROM {asset_categories} WHERE slug = 'axe-throwing' LIMIT 1), NULL, 'daily', 5, 1, 0, 1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check');

INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Target', 'Target boards solid — no splits, soft spots or loose boards',
       'Replace or rotate boards before they start rejecting axes.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Target boards solid — no splits, soft spots or loose boards');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Target', 'Backstop and side fencing intact, no gaps or protruding fasteners',
       '',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Backstop and side fencing intact, no gaps or protruding fasteners');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Cage', 'Cage door latches and stays shut; throwing line clearly marked',
       '',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 30
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Cage door latches and stays shut; throwing line clearly marked');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Cage', 'Floor clear and dry, no chips or debris in the lane',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 40
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Floor clear and dry, no chips or debris in the lane');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Axes', 'Axe heads tight on the handles, no cracks in handles or heads',
       'Check every axe kept at the lane. Pull the head, flex the handle.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Axe heads tight on the handles, no cracks in handles or heads');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Axes', 'Edges in usable condition, no burrs or chips',
       '',
       'pass_fail', 1, 0, 0, '', '', NULL, NULL, 60
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Edges in usable condition, no burrs or chips');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Safety', 'Rules signage visible and first aid kit at the lane stocked',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 70
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Rules signage visible and first aid kit at the lane stocked');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Notes',
       '',
       'text', 0, 0, 0, '', '', NULL, NULL, 80
  FROM {checklists} c
 WHERE c.name = 'Daily Axe Throwing Lane Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Notes');

-- -----------------------------------------------------------------------------
-- Weekly Bowling Lane & Pinsetter Check
-- -----------------------------------------------------------------------------
INSERT INTO {checklists}
  (name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
SELECT 'Weekly Bowling Lane & Pinsetter Check',
       'One lane at a time, with the pinsetter cycled under observation. Follow the pinsetter manufacturer''s lockout procedure before reaching into the machine.',
       'category', (SELECT id FROM {asset_categories} WHERE slug = 'bowling' LIMIT 1), NULL, 'weekly', 15, 1, 0, 1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check');

INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Pinsetter', 'Pinsetter cycles cleanly — no jams, mis-sets or dropped pins',
       'Watch several full cycles including a strike and a spare.',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 10
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Pinsetter cycles cleanly — no jams, mis-sets or dropped pins');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Pinsetter', 'Guards, covers and interlocks in place and working',
       'The machine must stop when a guard is opened.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Guards, covers and interlocks in place and working');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Pinsetter', 'Pin elevator, distributor and turret free of wear or damage',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 30
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Pin elevator, distributor and turret free of wear or damage');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Lane', 'Ball return delivers to the rack every time',
       '',
       'pass_fail', 1, 0, 0, '', '', NULL, NULL, 40
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Ball return delivers to the rack every time');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Lane', 'Lane surface, gutters and capping undamaged; oil pattern acceptable',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 50
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Lane surface, gutters and capping undamaged; oil pattern acceptable');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Lane', 'Bumpers deploy and retract correctly',
       '',
       'pass_fail_na', 1, 0, 0, '', '', NULL, NULL, 60
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Bumpers deploy and retract correctly');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Lane', 'Foul light and scoring console working',
       '',
       'pass_fail', 1, 0, 0, '', '', NULL, NULL, 70
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Foul light and scoring console working');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Frame counter reading',
       'Record the pinsetter frame counter if it has one.',
       'meter', 0, 0, 0, '', 'cycles', NULL, NULL, 80
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Frame counter reading');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Notes',
       '',
       'text', 0, 0, 0, '', '', NULL, NULL, 90
  FROM {checklists} c
 WHERE c.name = 'Weekly Bowling Lane & Pinsetter Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Notes');

-- -----------------------------------------------------------------------------
-- Daily Climbing Wall Check
-- -----------------------------------------------------------------------------
INSERT INTO {checklists}
  (name, description, applies_to, category_id, asset_id, frequency,
   estimated_minutes, require_signature, require_meter, is_active)
SELECT 'Daily Climbing Wall Check',
       'Before the first climber. Auto-belay devices follow the manufacturer''s daily inspection exactly; this list is the reminder, not the manual.',
       'asset', NULL, (SELECT id FROM {assets} WHERE asset_tag = 'CW-001' LIMIT 1), 'daily', 10, 1, 0, 1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM {checklists} WHERE name = 'Daily Climbing Wall Check');

INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Auto-belays', 'Every auto-belay: webbing undamaged, retracts smoothly, carabiner locks',
       'Pull out and let retract. Check the whole length of webbing for cuts, glazing or fraying.',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 10
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Every auto-belay: webbing undamaged, retracts smoothly, carabiner locks');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Auto-belays', 'Auto-belay mounting and anchors secure',
       '',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 20
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Auto-belay mounting and anchors secure');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Wall', 'Holds tight, no spinning or cracked holds; panels and t-nuts sound',
       '',
       'pass_fail', 1, 0, 1, '', '', NULL, NULL, 30
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Holds tight, no spinning or cracked holds; panels and t-nuts sound');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Landing', 'Landing mats in place, joined, no gaps or damage',
       '',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 40
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Landing mats in place, joined, no gaps or damage');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Harnesses', 'Rental harnesses inspected: webbing, stitching, buckles',
       '',
       'pass_fail', 1, 1, 1, '', '', NULL, NULL, 50
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Rental harnesses inspected: webbing, stitching, buckles');
INSERT INTO {checklist_items}
  (checklist_id, section, item_text, description, response_type,
   is_required, is_critical, allow_photo, expected_value, unit, min_value, max_value, sort_order)
SELECT c.id, 'Final', 'Notes',
       '',
       'text', 0, 0, 0, '', '', NULL, NULL, 60
  FROM {checklists} c
 WHERE c.name = 'Daily Climbing Wall Check'
   AND NOT EXISTS (SELECT 1 FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.item_text = 'Notes');


-- -----------------------------------------------------------------------------
-- Scheduled service, one statement per schedule so each attaches to its
-- machine by tag and is skipped if that machine already has it. next_due is
-- left empty: the loader recomputes it, so everything starts one interval out.
-- -----------------------------------------------------------------------------
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #1 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #1 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #2 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #2 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #3 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #3 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #4 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-004' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #4 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #5 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-005' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #5 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #6 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-006' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #6 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #7 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-007' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #7 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #8 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-008' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #8 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #9 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-009' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #9 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #10 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-010' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #10 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #11 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-011' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #11 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #12 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-012' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #12 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #13 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-013' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #13 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #14 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-014' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #14 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #15 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-015' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #15 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #16 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-016' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #16 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #17 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-017' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #17 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #18 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-018' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #18 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #19 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-019' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #19 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Kart #20 — 50 hour service', 'Oil, air filter, plug check, chain, brakes, tyres and a general look over.', 'preventive', NULL, 'meter', 1,
       50.00, 3, 1.50, 'normal', 'Drain the oil warm and refill. Clean or replace the air filter. Check chain tension and clutch wear. Pads and rotor. Tyre pressures. Torque the wheel nuts. Note anything unusual.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'GK-020' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Kart #20 — 50 hour service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Freefall — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Ride Pre-Opening Inspection' ORDER BY id LIMIT 1), 'weekly', 1,
       NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Freefall — weekly mechanical');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Freefall — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
       NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Freefall — monthly detailed inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Freefall — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
       NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Freefall — annual state inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Dragon Coaster — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Ride Pre-Opening Inspection' ORDER BY id LIMIT 1), 'weekly', 1,
       NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Dragon Coaster — weekly mechanical');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Dragon Coaster — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
       NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Dragon Coaster — monthly detailed inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Dragon Coaster — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
       NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Dragon Coaster — annual state inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Swings — weekly mechanical', 'Lubrication, fasteners, restraints and brakes per the manufacturer''s weekly chart.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Ride Pre-Opening Inspection' ORDER BY id LIMIT 1), 'weekly', 1,
       NULL, 2, 3.00, 'high', 'Grease every point on the lubrication chart. Torque-check the critical fasteners listed in the manual. Function-test every restraint and the brakes. Log any wear found.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Swings — weekly mechanical');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Swings — monthly detailed inspection', 'The manufacturer''s monthly inspection: structure, drive, restraints, electrical.', 'inspection', NULL, 'monthly', 1,
       NULL, 5, 6.00, 'high', 'Work through the monthly section of the manual. Photograph anything marginal. Order parts before they become urgent.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Swings — monthly detailed inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Swings — annual state inspection', 'The annual amusement ride inspection required by New York State, plus the manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
       NULL, 45, 16.00, 'urgent', 'Book the inspector well before the season opens. Complete the manufacturer''s annual service and any NDT they require first. Keep the certificate and the report with the ride file.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RD-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Swings — annual state inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Zip Line — monthly cable and hardware inspection', 'Close inspection of the cable, terminations, trolleys, brake and every harness set.', 'inspection', NULL, 'monthly', 1,
       NULL, 5, 3.00, 'high', 'Inspect the full cable length for broken wires and corrosion. Check anchors, turnbuckles and clamps for movement. Measure trolley sheave wear. Retire any harness or lanyard that fails inspection and record its serial number.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'ZL-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Zip Line — monthly cable and hardware inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Zip Line — annual professional inspection', 'Annual inspection by a qualified inspector, plus manufacturer''s annual service.', 'inspection', NULL, 'annual', 1,
       NULL, 45, 8.00, 'urgent', 'Book the inspector before the season. Replace harnesses and lanyards that have reached the manufacturer''s retirement age. Keep the report with the zip line file.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'ZL-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Zip Line — annual professional inspection');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 1 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 1 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 2 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 2 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 3 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 3 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 4 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-004' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 4 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 5 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-005' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 5 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 6 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-006' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 6 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 7 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-007' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 7 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 8 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-008' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 8 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 9 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-009' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 9 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 10 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-010' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 10 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 11 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-011' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 11 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Lane 12 — monthly pinsetter service', 'Lubrication, belts, chains, adjustments and a clean-out per the pinsetter manual.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Weekly Bowling Lane & Pinsetter Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.00, 'normal', 'Lock out the machine first. Clean and lubricate per the manual. Check belts, chains and springs. Adjust the deck and the pin table. Cycle under observation before releasing the lane.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'BL-012' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Lane 12 — monthly pinsetter service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 1 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 1 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 2 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-002' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 2 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 3 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-003' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 3 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 4 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-004' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 4 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 5 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-005' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 5 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Axe Throw Unit 6 — monthly target and cage service', 'Rotate or replace target boards, tighten the cage and backstop, service the axes.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Axe Throwing Lane Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 0.50, 'normal', 'Replace any board that is soft, split or rejecting axes. Check every fastener on the cage and backstop. Sharpen or retire axes; replace cracked handles.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'AX-006' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Axe Throw Unit 6 — monthly target and cage service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Climbing Wall — annual auto-belay recertification', 'Auto-belay devices returned to the manufacturer or an approved service centre for annual recertification.', 'inspection', NULL, 'annual', 1,
       NULL, 45, 2.00, 'urgent', 'Send the devices in before their certification expires; have spares or plan the closure. Keep the certificates with the wall file.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'CW-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Climbing Wall — annual auto-belay recertification');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Climbing Wall — monthly holds and anchor check', 'Tighten holds, inspect t-nuts, anchors and top-out hardware.', 'preventive', (SELECT id FROM {checklists} WHERE name = 'Daily Climbing Wall Check' ORDER BY id LIMIT 1), 'monthly', 1,
       NULL, 5, 1.50, 'normal', 'Check every hold for spinning. Inspect anchors and auto-belay mounts. Replace worn mats.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'CW-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Climbing Wall — monthly holds and anchor check');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Laser Tag — quarterly vest and phaser service', 'Battery health, straps, sensors and phaser function across the whole set.', 'preventive', NULL, 'quarterly', 1,
       NULL, 7, 3.00, 'normal', 'Test every vest and phaser. Replace weak batteries and frayed straps. Clean sensors. Check arena effects and emergency lighting.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'LT-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Laser Tag — quarterly vest and phaser service');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Roller Rink — monthly floor and skate fleet check', 'Floor surface, barriers, lighting and the rental skates.', 'preventive', NULL, 'monthly', 1,
       NULL, 5, 2.00, 'normal', 'Walk the floor for lifted seams and damage. Check barrier fixings. Inspect rental skates: wheels, bearings, laces, stops.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'RR-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Roller Rink — monthly floor and skate fleet check');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Mini Golf — pre-season course walk', 'Every hole, obstacle, water feature and pump before opening for the season.', 'preventive', NULL, 'annual', 1,
       NULL, 30, 6.00, 'normal', 'Repair surfaces, fix loose obstacles, service the pumps and clean the water features. Check lighting and signage.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'MG-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Mini Golf — pre-season course walk');
INSERT INTO {maintenance_schedules}
  (asset_id, name, description, log_type, checklist_id, frequency_type, frequency_value,
   meter_interval, lead_time_days, estimated_hours, priority, instructions, is_active, created_by)
SELECT a.id, 'Compressor — quarterly service', 'Drain, filters, belt, safety valve and pressure switch.', 'preventive', NULL, 'quarterly', 1,
       NULL, 7, 1.00, 'low', 'Drain the tank. Clean or replace the intake filter. Check belt tension and the safety valve. Note the hour reading.', 1, (SELECT MIN(id) FROM {users} WHERE role = 'admin' AND deleted_at IS NULL)
  FROM {assets} a
 WHERE a.asset_tag = 'FE-001' AND a.deleted_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM {maintenance_schedules} s WHERE s.asset_id = a.id AND s.name = 'Compressor — quarterly service');
