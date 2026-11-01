-- ============================================================
-- ReserveBot: TEMPLATE for adding another department
--
-- DON'T RUN THIS FILE AS-IS. Copy it (e.g. ece_seed.sql), replace every
-- line marked EXAMPLE with the new department's real data, then run it
-- AFTER schema.sql and the existing department files.
--
-- It only adds rows. It never changes another department's data
-- (except the optional clean-up in step 8, which is commented out).
-- ============================================================
USE reservebot;

-- ------------------------------------------------------------
-- 1. Department and its booking rules
-- ------------------------------------------------------------
INSERT INTO Department (Name, Slug, ContactEmail)
VALUES ('Electronics Engineering Department', 'ece', NULL);               -- EXAMPLE
SET @dept := (SELECT DepartmentID FROM Department WHERE Slug = 'ece');    -- EXAMPLE (same slug)

INSERT INTO DepartmentConfig
  (DepartmentID, RequiresApproval, OpenDays, BusinessHoursStart, BusinessHoursEnd)
VALUES (@dept, 1, 'Mon,Tue,Wed,Thu,Fri,Sat', '07:30', '21:00');           -- EXAMPLE

-- ------------------------------------------------------------
-- 2. Semester. Already there if another department added it (IGNORE skips it).
-- ------------------------------------------------------------
INSERT IGNORE INTO Term (Name, StartDate, EndDate)
VALUES ('1st Semester AY 2026-2027', '2026-09-01', '2027-01-31');
SET @term := (SELECT TermID FROM Term WHERE Name = '1st Semester AY 2026-2027');

-- ------------------------------------------------------------
-- 3. Rooms. List EVERY room the department uses, including shared ones.
--    A room that already exists (like CEA302) is reused, not duplicated,
--    so both departments' bookings are checked against each other.
-- ------------------------------------------------------------
INSERT IGNORE INTO Room (Name, Building, Capacity) VALUES
  ('CEA302', 'CEA Building', NULL),                                        -- EXAMPLE (shared with BSCpE)
  ('CEA401', 'CEA Building', 40);                                          -- EXAMPLE (new room)

INSERT INTO DepartmentRoom (DepartmentID, RoomID)
SELECT @dept, RoomID FROM Room WHERE Name IN ('CEA302', 'CEA401');         -- EXAMPLE

-- ------------------------------------------------------------
-- 4. Timetable: one row per block on the department's room schedule.
--    Blocked entry = Code, Title, Section and Instructor all NULL, reason in Note.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS stage_timetable;
CREATE TABLE stage_timetable (
  DayOfWeek  VARCHAR(3),
  Room       VARCHAR(30),
  StartTime  TIME,
  EndTime    TIME,
  Code       VARCHAR(20),
  Title      VARCHAR(100),
  Section    VARCHAR(30),
  Instructor VARCHAR(60),
  Note       VARCHAR(150)
) ENGINE=InnoDB;

INSERT INTO stage_timetable
  (DayOfWeek, Room, StartTime, EndTime, Code, Title, Section, Instructor, Note) VALUES
  ('Mon', 'CEA302', '07:30', '10:30', 'ECEN 101', 'Example Course A', 'BSECE 1-1', 'A. Example', NULL),  -- EXAMPLE
  ('Mon', 'CEA302', '10:30', '13:30', 'ECEN 102', 'Example Course B', 'BSECE 1-2', 'B. Example', NULL),  -- EXAMPLE
  ('Tue', 'CEA401', '07:30', '10:30', 'ECEN 101', 'Example Course A', 'BSECE 1-1', 'A. Example', NULL),  -- EXAMPLE
  ('Sat', 'CEA401', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Closed on Saturdays');                    -- EXAMPLE

-- The next three statements are the same for every department: don't edit them
INSERT INTO Section (Name, Program)
SELECT DISTINCT t.Section, SUBSTRING_INDEX(t.Section, ' ', 1)
FROM stage_timetable t
WHERE t.Section IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Section s WHERE s.Name = t.Section);

INSERT INTO Course (Code, Title)
SELECT DISTINCT t.Code, t.Title
FROM stage_timetable t
WHERE t.Code IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Course c WHERE c.Code = t.Code AND c.Title <=> t.Title);

INSERT INTO ClassSchedule
  (TermID, DepartmentID, RoomID, DayOfWeek, StartTime, EndTime,
   EntryType, CourseID, SectionID, Instructor, Note)
SELECT @term, @dept, r.RoomID, t.DayOfWeek, t.StartTime, t.EndTime,
       IF(t.Code IS NULL, 'Blocked', 'Class'), c.CourseID, s.SectionID, t.Instructor, t.Note
FROM stage_timetable t
JOIN Room r         ON r.Name = t.Room
LEFT JOIN Course c  ON c.Code = t.Code AND c.Title <=> t.Title
LEFT JOIN Section s ON s.Name = t.Section;

DROP TABLE stage_timetable;

-- ------------------------------------------------------------
-- 5. Equipment, grouped the way the department's request form groups it
-- ------------------------------------------------------------
DROP TABLE IF EXISTS stage_equipment;
CREATE TABLE stage_equipment (
  Category  VARCHAR(60),
  CatOrder  INT,
  Item      VARCHAR(60),
  ItemOrder INT,
  TotalQty  INT
) ENGINE=InnoDB;

INSERT INTO stage_equipment (Category, CatOrder, Item, ItemOrder, TotalQty) VALUES
  ('Audiovisual Equipment', 1, 'Projector', 1, 3),                         -- EXAMPLE
  ('Audiovisual Equipment', 1, 'HDMI Cable', 2, 5),                        -- EXAMPLE
  ('Lab Instruments',       2, 'Function Generator', 1, 8);                -- EXAMPLE

INSERT INTO EquipmentCategory (DepartmentID, Name, SortOrder)
SELECT DISTINCT @dept, Category, CatOrder FROM stage_equipment;

INSERT INTO Equipment (CategoryID, Name, TotalQty, MaxPerRequest, SortOrder)
SELECT ec.CategoryID, s.Item, s.TotalQty, 4, s.ItemOrder
FROM stage_equipment s
JOIN EquipmentCategory ec ON ec.DepartmentID = @dept AND ec.Name = s.Category;

DROP TABLE stage_equipment;

-- ------------------------------------------------------------
-- 6. First accounts: at least one admin and one lab staff.
--    The hash below is for Password123! (change it after first login).
-- ------------------------------------------------------------
INSERT INTO Users (DepartmentID, SectionID, Email, PasswordHash, Name, Role) VALUES
  (@dept, NULL, 'admin.ece@reservebot.test',                               -- EXAMPLE
   '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'ECE Office Admin', 'admin'),
  (@dept, NULL, 'staff.ece@reservebot.test',                               -- EXAMPLE
   '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'ECE Lab Assistant', 'lab_staff');

-- ------------------------------------------------------------
-- 7. Check: compare these counts with what you typed above
-- ------------------------------------------------------------
SELECT d.Name,
  (SELECT COUNT(*) FROM DepartmentRoom dr WHERE dr.DepartmentID = d.DepartmentID) AS Rooms,
  (SELECT COUNT(*) FROM ClassSchedule cs  WHERE cs.DepartmentID = d.DepartmentID) AS TimetableRows,
  (SELECT COUNT(*) FROM Equipment e JOIN EquipmentCategory ec ON ec.CategoryID = e.CategoryID
     WHERE ec.DepartmentID = d.DepartmentID)                                    AS EquipmentItems,
  (SELECT COUNT(*) FROM Users u WHERE u.DepartmentID = d.DepartmentID)          AS Accounts
FROM Department d WHERE d.DepartmentID = @dept;

-- ------------------------------------------------------------
-- 8. OPTIONAL clean-up for shared rooms.
--    BSCpE's timetable marks CEA302 as "Used by ECE" on Mon, Wed and Fri.
--    Once ECE's real classes are in, those placeholders can go, but ONLY if
--    ECE's own timetable covers all the time ECE needs the room. Otherwise
--    the uncovered hours become bookable by BSCpE.
-- ------------------------------------------------------------
-- DELETE FROM ClassSchedule
-- WHERE EntryType = 'Blocked'
--   AND Note LIKE 'Used by ECE%'
--   AND RoomID = (SELECT RoomID FROM Room WHERE Name = 'CEA302');
