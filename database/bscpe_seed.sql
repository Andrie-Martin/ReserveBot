-- ============================================================
-- ReserveBot: File 2 of 2: BSCpE DEPARTMENT DATA
-- Source: BSCpE room schedule (Mon-Sat) and equipment request form.
-- Run AFTER schema.sql.
--
-- Everything is looked up by NAME, never by hard-coded ID, so this file is
-- also the pattern for adding another department.
--
-- Contents: 1 department, 11 rooms, 224 timetable entries
-- (215 classes + 9 blocked), 6 equipment categories,
-- 63 equipment items, 4 test accounts, 2 sample reservations.
--
-- DATA NOTES (please confirm with the department):
--  * Short titles were expanded, e.g. COMP ARCHI -> Computer Architecture.
--  * Tue CEA313 6:00-9:00 PM says "CMPE 305 LOGIC CIRCUITS AND DESIGN".
--    Entered as CMPE 304, the code Logic Circuits uses everywhere else.
--  * CMPE 101 cells say "CPE 1-x"; entered as section "BSCPE 1-x".
--    CMPE 101 has no title on the timetable, so Title is NULL.
--  * Sat "CMPE 401 DATABASE SYSTEMS" (BSCPE 4-1) entered as
--    Database Management Systems, like the other CMPE 401 sections.
--  * CMPE 011 is "COMP PROG" for BSME/BSIE/BSREM but "PROG LOGIC AND
--    DESIGN"/"PLD" for BSCPE 1-6 and 1-7. Kept as two courses.
--  * "BSCOE 4-1P" and "BSCOE 4-3P" kept exactly as written.
--  * Yellow cells (CEA302 "ECE" days, CEA207) and unlabeled light-green
--    cells are entered as Blocked.
--  * PLACEHOLDERS: semester dates, room capacities, equipment stock counts.
-- All test accounts use the password:  Password123!
-- ============================================================

USE reservebot;

-- ------------------------------------------------------------
-- 1. Department and booking rules
-- ------------------------------------------------------------
INSERT INTO Department (Name, Slug) VALUES ('Computer Engineering Department', 'bscpe');
SET @dept := (SELECT DepartmentID FROM Department WHERE Slug = 'bscpe');

-- Defaults fit the timetable: Mon-Sat, 7:30 AM to 9:00 PM, 30-minute slots
INSERT INTO DepartmentConfig (DepartmentID) VALUES (@dept);

-- ------------------------------------------------------------
-- 2. Semester (shared by all departments)
--    PLACEHOLDER DATES: replace with the official first and last day of classes
-- ------------------------------------------------------------
INSERT IGNORE INTO Term (Name, StartDate, EndDate)
VALUES ('1st Semester AY 2026-2027', '2026-09-01', '2027-01-31');
SET @term := (SELECT TermID FROM Term WHERE Name = '1st Semester AY 2026-2027');

-- ------------------------------------------------------------
-- 3. Rooms (INSERT IGNORE: a room another department already added is reused)
--    Capacity left NULL until the real numbers are known
-- ------------------------------------------------------------
INSERT IGNORE INTO Room (Name, Building) VALUES
  ('CEA300', 'CEA Building'),
  ('CEA302', 'CEA Building'),
  ('CEA310', 'CEA Building'),
  ('CEA311', 'CEA Building'),
  ('CEA312', 'CEA Building'),
  ('CEA313', 'CEA Building'),
  ('CEA314', 'CEA Building'),
  ('CEA315', 'CEA Building'),
  ('CEA316', 'CEA Building'),
  ('CEA413', 'CEA Building'),
  ('CEA207', 'CEA Building');

INSERT INTO DepartmentRoom (DepartmentID, RoomID)
SELECT @dept, RoomID FROM Room WHERE Name IN ('CEA300', 'CEA302', 'CEA310', 'CEA311', 'CEA312', 'CEA313', 'CEA314', 'CEA315', 'CEA316', 'CEA413', 'CEA207');

-- ------------------------------------------------------------
-- 4. Timetable: typed in as text first, then turned into IDs by name
-- ------------------------------------------------------------
DROP TABLE IF EXISTS stage_timetable;
CREATE TABLE stage_timetable (
  DayOfWeek  VARCHAR(3),
  Room       VARCHAR(30),
  StartTime  TIME,
  EndTime    TIME,
  Code       VARCHAR(20),     -- NULL = blocked entry
  Title      VARCHAR(100),
  Section    VARCHAR(30),
  Instructor VARCHAR(60),
  Note       VARCHAR(150)
) ENGINE=InnoDB;

INSERT INTO stage_timetable
  (DayOfWeek, Room, StartTime, EndTime, Code, Title, Section, Instructor, Note) VALUES
  ('Mon', 'CEA300', '10:30', '13:30', 'CMPE 402', 'Project Management', 'BSCPE 4-1', 'L. Reyes', NULL),
  ('Mon', 'CEA300', '15:00', '18:00', 'CMPE 354', 'Enterprise Software', 'BSCPE 3-7', 'J. Tena', NULL),
  ('Mon', 'CEA300', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-4', 'L. Reyes', NULL),
  ('Mon', 'CEA302', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Used by ECE (yellow on timetable)'),
  ('Mon', 'CEA310', '10:30', '13:30', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-4', 'J. Tena', NULL),
  ('Mon', 'CEA310', '15:00', '18:00', 'CMPE 402', 'Project Management', 'BSCPE 4-6', 'L. Reyes', NULL),
  ('Mon', 'CEA310', '18:00', '21:00', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-2', 'J. Tena', NULL),
  ('Mon', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay', NULL),
  ('Mon', 'CEA311', '11:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-4', 'O. Pajabera', NULL),
  ('Mon', 'CEA311', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSME 2-2', 'A. Racusa', NULL),
  ('Mon', 'CEA311', '17:00', '20:00', 'CMPE 011', 'Computer Programming', 'BSME 2-2', 'A. Racusa', NULL),
  ('Mon', 'CEA312', '11:30', '14:30', 'CMPE 310', 'Computer Networks', 'BSCPE 1-2P', 'J. Avila', NULL),
  ('Mon', 'CEA312', '14:30', '17:30', 'CMPE 310', 'Computer Networks', 'BSCPE 1-2P', 'J. Avila', NULL),
  ('Mon', 'CEA312', '18:00', '21:00', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-5', 'T. Velardo', NULL),
  ('Mon', 'CEA313', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-6', 'J. Lorico', NULL),
  ('Mon', 'CEA313', '15:00', '18:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-7', 'J. Lorico', NULL),
  ('Mon', 'CEA313', '18:00', '21:00', 'CMPE 402', 'Project Management', 'BSCPE 4-7', 'J. Lorico', NULL),
  ('Mon', 'CEA314', '10:30', '13:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-3', 'A. Racusa', NULL),
  ('Mon', 'CEA314', '13:30', '16:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-1', 'M. Malbog', NULL),
  ('Mon', 'CEA314', '16:30', '21:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-6', 'M. Malbog', NULL),
  ('Mon', 'CEA315', '11:30', '13:30', 'CMPE 453', 'Software Processes', 'BSCPE 4-6', 'A. Delacruz', NULL),
  ('Mon', 'CEA315', '14:30', '16:30', 'CMPE 453', 'Software Processes', 'BSCPE 4-7', 'A. Delacruz', NULL),
  ('Mon', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-5', 'J. Delacruz', NULL),
  ('Mon', 'CEA316', '10:30', '13:30', 'MATH 029', 'Differential Equations', 'BSCPE 2-7', NULL, NULL),
  ('Mon', 'CEA316', '13:30', '16:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-6', 'F. Natividad', NULL),
  ('Mon', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-1', 'J. Avila', NULL),
  ('Mon', 'CEA413', '07:30', '10:30', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Mon', 'CEA413', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-1', NULL, NULL),
  ('Mon', 'CEA413', '13:30', '16:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-4', NULL, NULL),
  ('Mon', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Mon', 'CEA207', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Not available (yellow on timetable, no label)'),
  ('Tue', 'CEA300', '11:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-5', 'O. Pajabera', NULL),
  ('Tue', 'CEA300', '15:00', '18:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-3', 'L. Reyes', NULL),
  ('Tue', 'CEA300', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 1-1P', 'J. Binuya', NULL),
  ('Tue', 'CEA302', '11:00', '14:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-3', 'L. Reyes', NULL),
  ('Tue', 'CEA302', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-6', 'J. Binuya', NULL),
  ('Tue', 'CEA302', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-7', 'R. Fernando', NULL),
  ('Tue', 'CEA310', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera', NULL),
  ('Tue', 'CEA310', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-7', 'J. Binuya', NULL),
  ('Tue', 'CEA310', '13:30', '16:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-4', 'J. Rodriguez', NULL),
  ('Tue', 'CEA310', '18:00', '20:00', 'CMPE 452', 'Digital Image Processing', 'BSCPE 4-1', 'C. Casuat', NULL),
  ('Tue', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay', NULL),
  ('Tue', 'CEA311', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-6', 'R. Mahaguay', NULL),
  ('Tue', 'CEA311', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-2', 'A. Racusa', NULL),
  ('Tue', 'CEA311', '17:00', '20:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-2', 'A. Racusa', NULL),
  ('Tue', 'CEA312', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-1', 'G. Avena', NULL),
  ('Tue', 'CEA312', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-3', 'G. Avena', NULL),
  ('Tue', 'CEA312', '14:30', '16:30', 'CMPE 453', 'Database Implementation', 'BSCPE 4-2', 'A. Delacruz', NULL),
  ('Tue', 'CEA312', '18:00', '21:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-1P', 'O. Pajabera', NULL),
  ('Tue', 'CEA313', '08:00', '09:00', 'CMPE 101', NULL, 'BSCPE 1-2', 'P. Tenerife', NULL),
  ('Tue', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'T. Velardo', NULL),
  ('Tue', 'CEA313', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-4', 'M. Bautista', NULL),
  ('Tue', 'CEA313', '16:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-4', 'M. Bautista', NULL),
  ('Tue', 'CEA313', '18:00', '21:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-3', 'J. Cansino', NULL),
  ('Tue', 'CEA314', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-4', 'C. Casuat', NULL),
  ('Tue', 'CEA314', '11:00', '14:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-5', 'J. Lorico', NULL),
  ('Tue', 'CEA314', '14:00', '17:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-5', 'J. Lorico', NULL),
  ('Tue', 'CEA314', '18:00', '21:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-2', 'M. Ello', NULL),
  ('Tue', 'CEA315', '10:30', '13:30', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-8', 'R. Ado', NULL),
  ('Tue', 'CEA315', '14:30', '16:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-3', 'O. Pajabera', NULL),
  ('Tue', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-3', 'J. Delacruz', NULL),
  ('Tue', 'CEA316', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo', NULL),
  ('Tue', 'CEA316', '10:30', '13:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-4', 'F. Natividad', NULL),
  ('Tue', 'CEA316', '13:30', '16:30', 'CMPE 452', 'Digital Image Processing', 'BSCPE 4-1', 'C. Casuat', NULL),
  ('Tue', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-5', 'F. Natividad', NULL),
  ('Tue', 'CEA413', '07:30', '10:30', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Tue', 'CEA413', '10:30', '13:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-4', 'A. Racusa', NULL),
  ('Tue', 'CEA413', '13:30', '16:30', 'MATH 101', 'Calculus 1', 'BSCPE 1-4', NULL, NULL),
  ('Tue', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Tue', 'CEA207', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Not available (yellow on timetable, no label)'),
  ('Wed', 'CEA300', '07:30', '10:30', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-1P', 'R. Marasigan', NULL),
  ('Wed', 'CEA300', '10:30', '13:30', 'CMPE 402', 'Project Management', 'BSCPE 4-2', 'J. Lorico', NULL),
  ('Wed', 'CEA300', '15:00', '18:00', 'CMPE 402', 'Project Management', 'BSCPE 4-3', 'L. Reyes', NULL),
  ('Wed', 'CEA300', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-4', 'L. Reyes', NULL),
  ('Wed', 'CEA302', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Used by ECE (yellow on timetable)'),
  ('Wed', 'CEA310', '10:00', '12:00', 'CMPE 353', 'Introduction to Big Data', 'BSCPE 3-3', 'M. Malbog', NULL),
  ('Wed', 'CEA310', '12:00', '14:00', 'CMPE 354', 'Enterprise Software', 'BSCPE 3-7', 'J. Tena', NULL),
  ('Wed', 'CEA310', '15:00', '18:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-2', 'J. Avila', NULL),
  ('Wed', 'CEA310', '18:00', '21:00', 'CMPE 309', 'Feedback and Control Systems', 'BSCPE 4-1P', 'J. Delacruz', NULL),
  ('Wed', 'CEA311', '08:00', '09:00', 'CMPE 101', NULL, 'BSCPE 1-1', 'R. Mahaguay', NULL),
  ('Wed', 'CEA311', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'P. Tenerife', NULL),
  ('Wed', 'CEA311', '13:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'J. Rodriguez', NULL),
  ('Wed', 'CEA311', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-6', 'M. Malbog', NULL),
  ('Wed', 'CEA312', '07:30', '10:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-7', 'A. Racusa', NULL),
  ('Wed', 'CEA312', '12:00', '15:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-3', 'A. Racusa', NULL),
  ('Wed', 'CEA312', '15:00', '18:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-5', 'M. Bautista', NULL),
  ('Wed', 'CEA312', '18:00', '21:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-1', 'M. Bautista', NULL),
  ('Wed', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'P. Tenerife', NULL),
  ('Wed', 'CEA313', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-1', 'J. Rodriguez', NULL),
  ('Wed', 'CEA313', '13:30', '16:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-6', 'K. Tancinco', NULL),
  ('Wed', 'CEA313', '16:30', '18:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-7', 'M. Malbog', NULL),
  ('Wed', 'CEA313', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 3-5', 'J. Binuya', NULL),
  ('Wed', 'CEA314', '07:30', '10:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'L. Lacatan', NULL),
  ('Wed', 'CEA314', '10:30', '13:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-2', 'M. Bautista', NULL),
  ('Wed', 'CEA314', '15:00', '18:00', 'CMPE 351', 'Network Administration', 'BSCPE 3-4', 'J. Binuya', NULL),
  ('Wed', 'CEA314', '18:00', '21:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-3', 'M. Ello', NULL),
  ('Wed', 'CEA315', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-2', 'C. Casuat', NULL),
  ('Wed', 'CEA315', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-2', 'C. Casuat', NULL),
  ('Wed', 'CEA315', '13:30', '16:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-1', 'M. Malbog', NULL),
  ('Wed', 'CEA315', '18:00', '21:00', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-2', 'K. Tancinco', NULL),
  ('Wed', 'CEA316', '11:00', '14:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-4', 'F. Natividad', NULL),
  ('Wed', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 1-2P', 'J. Avila', NULL),
  ('Wed', 'CEA413', '12:00', '15:00', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Wed', 'CEA413', '15:00', '18:00', 'MATH 029', 'Differential Equations', 'BSCPE 2-2', NULL, NULL),
  ('Wed', 'CEA413', '18:00', '21:00', 'CMPE 453', 'Software Processes', 'BSCPE 4-7', 'C. Casuat', NULL),
  ('Wed', 'CEA207', '10:30', '13:30', NULL, NULL, NULL, NULL, 'Reserved (light green on timetable, no label)'),
  ('Wed', 'CEA207', '13:30', '16:30', 'ENGL 013', 'Technical Report Writing', 'BSCPE 1-2P', NULL, NULL),
  ('Wed', 'CEA207', '18:00', '21:00', 'MATH 101', 'Calculus 1', 'BSCPE 1-7', NULL, NULL),
  ('Thu', 'CEA300', '07:30', '10:30', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-3P', 'R. Marasigan', NULL),
  ('Thu', 'CEA300', '11:00', '14:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-4', 'J. Binuya', NULL),
  ('Thu', 'CEA300', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-4', 'J. Binuya', NULL),
  ('Thu', 'CEA300', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 1-2P', 'J. Binuya', NULL),
  ('Thu', 'CEA302', '13:30', '16:30', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-7', 'R. Marasigan', NULL),
  ('Thu', 'CEA302', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-7', 'R. Fernando', NULL),
  ('Thu', 'CEA310', '10:30', '13:30', 'CMPE 302', 'Introduction to HDL', 'BSCPE 1-1P', 'J. Tena', NULL),
  ('Thu', 'CEA310', '15:00', '18:00', 'MATH 029', 'Differential Equations', 'BSCPE 2-5', NULL, NULL),
  ('Thu', 'CEA310', '18:00', '21:00', 'MATH 101', 'Calculus 1', 'BSCPE 1-6', NULL, NULL),
  ('Thu', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay', NULL),
  ('Thu', 'CEA311', '10:30', '15:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-2', 'M. Malbog', NULL),
  ('Thu', 'CEA311', '15:00', '18:00', 'CMPE 011', 'Computer Programming', 'BSREM 1-2', 'M. Bautista', NULL),
  ('Thu', 'CEA311', '18:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSREM 1-2', 'M. Bautista', NULL),
  ('Thu', 'CEA312', '10:30', '13:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-6', 'M. Bautista', NULL),
  ('Thu', 'CEA312', '15:00', '18:00', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-5', 'A. Racusa', NULL),
  ('Thu', 'CEA312', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-5', 'M. Malbog', NULL),
  ('Thu', 'CEA313', '07:30', '10:30', 'CMPE 011', 'Computer Programming', 'BSREM 1-1', 'A. Racusa', NULL),
  ('Thu', 'CEA313', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSREM 1-1', 'A. Racusa', NULL),
  ('Thu', 'CEA313', '15:00', '18:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-7', 'J. Lorico', NULL),
  ('Thu', 'CEA313', '18:00', '21:00', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-5', 'T. Velardo', NULL),
  ('Thu', 'CEA314', '07:30', '10:30', 'CMPE 402', 'Project Management', 'BSCPE 1-1P', 'L. Lacatan', NULL),
  ('Thu', 'CEA314', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-1', 'L. Lacatan', NULL),
  ('Thu', 'CEA314', '15:00', '18:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-7', 'J. Rodriguez', NULL),
  ('Thu', 'CEA314', '18:00', '21:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-7', 'J. Rodriguez', NULL),
  ('Thu', 'CEA315', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-6', 'O. Pajabera', NULL),
  ('Thu', 'CEA315', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-6', 'J. Lorico', NULL),
  ('Thu', 'CEA315', '13:30', '16:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-5', 'O. Pajabera', NULL),
  ('Thu', 'CEA315', '16:30', '18:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-6', 'M. Malbog', NULL),
  ('Thu', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-5', 'J. Delacruz', NULL),
  ('Thu', 'CEA316', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-7', NULL, NULL),
  ('Thu', 'CEA316', '13:30', '16:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-6', 'F. Natividad', NULL),
  ('Thu', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-5', 'F. Natividad', NULL),
  ('Thu', 'CEA413', '16:30', '19:30', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Thu', 'CEA207', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Not available (yellow on timetable, no label)'),
  ('Fri', 'CEA300', '07:30', '10:30', 'ENSC 029', 'Technopreneurship 101', 'BSEE 4-4', 'F. Oquindo', NULL),
  ('Fri', 'CEA300', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-7', 'J. Binuya', NULL),
  ('Fri', 'CEA300', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-6', 'J. Binuya', NULL),
  ('Fri', 'CEA300', '18:00', '20:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-1P', 'O. Pajabera', NULL),
  ('Fri', 'CEA302', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Used by ECE (yellow on timetable)'),
  ('Fri', 'CEA310', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-1', 'G. Avena', NULL),
  ('Fri', 'CEA310', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-3', 'G. Avena', NULL),
  ('Fri', 'CEA310', '15:00', '18:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-7', 'L. Reyes', NULL),
  ('Fri', 'CEA310', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-7', 'L. Reyes', NULL),
  ('Fri', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay', NULL),
  ('Fri', 'CEA311', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-6', 'R. Mahaguay', NULL),
  ('Fri', 'CEA311', '15:00', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'M. Bautista', NULL),
  ('Fri', 'CEA311', '18:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'M. Bautista', NULL),
  ('Fri', 'CEA312', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera', NULL),
  ('Fri', 'CEA312', '12:00', '15:00', 'CMPE 353', 'Introduction to Big Data', 'BSCPE 3-3', 'M. Malbog', NULL),
  ('Fri', 'CEA312', '15:00', '18:00', 'CMPE 310', 'Computer Networks', 'BSCPE 1-1P', 'J. Avila', NULL),
  ('Fri', 'CEA312', '18:00', '21:00', 'CMPE 310', 'Computer Networks', 'BSCPE 1-1P', 'J. Avila', NULL),
  ('Fri', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'P. Tenerife', NULL),
  ('Fri', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'P. Tenerife', NULL),
  ('Fri', 'CEA313', '13:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-1', 'J. Tena', NULL),
  ('Fri', 'CEA313', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-2', 'K. Tancinco', NULL),
  ('Fri', 'CEA314', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-4', 'C. Casuat', NULL),
  ('Fri', 'CEA314', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'T. Velardo', NULL),
  ('Fri', 'CEA314', '13:30', '16:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-4', 'J. Rodriguez', NULL),
  ('Fri', 'CEA314', '16:30', '21:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-7', 'M. Malbog', NULL),
  ('Fri', 'CEA315', '07:30', '10:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-3', 'J. Cansino', NULL),
  ('Fri', 'CEA315', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-1', 'J. Cansino', NULL),
  ('Fri', 'CEA315', '13:30', '16:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-5', 'K. Tancinco', NULL),
  ('Fri', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-3', 'J. Delacruz', NULL),
  ('Fri', 'CEA316', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo', NULL),
  ('Fri', 'CEA316', '10:30', '13:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-4', 'F. Natividad', NULL),
  ('Fri', 'CEA316', '15:00', '18:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-3', 'F. Natividad', NULL),
  ('Fri', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-3', 'F. Natividad', NULL),
  ('Fri', 'CEA413', '07:30', '10:30', NULL, NULL, NULL, NULL, 'Reserved (light green on timetable, no label)'),
  ('Fri', 'CEA413', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-5', NULL, NULL),
  ('Fri', 'CEA413', '13:30', '16:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-1', NULL, NULL),
  ('Fri', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', NULL, NULL, NULL),
  ('Fri', 'CEA207', '07:30', '21:00', NULL, NULL, NULL, NULL, 'Not available (yellow on timetable, no label)'),
  ('Sat', 'CEA300', '07:30', '10:30', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-7', 'P. Tenerife', NULL),
  ('Sat', 'CEA300', '10:30', '13:30', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-7', 'P. Tenerife', NULL),
  ('Sat', 'CEA300', '14:00', '17:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera', NULL),
  ('Sat', 'CEA300', '18:00', '21:00', 'CMPE 40223', 'Trends in Software Development', 'BSCPE 4-1P', 'M. Legarda', NULL),
  ('Sat', 'CEA302', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-4', 'T. Velardo', NULL),
  ('Sat', 'CEA302', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-4', 'T. Velardo', NULL),
  ('Sat', 'CEA302', '13:30', '16:30', 'CMPE 40223', 'Trends in Software Development', 'BSCPE 4-3P', 'M. Legarda', NULL),
  ('Sat', 'CEA302', '18:00', '21:00', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-3P', 'R. Marasigan', NULL),
  ('Sat', 'CEA310', '07:30', '10:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'L. Lacatan', NULL),
  ('Sat', 'CEA310', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-1', 'L. Lacatan', NULL),
  ('Sat', 'CEA310', '14:00', '17:00', 'CMPE 402', 'Project Management', 'BSCPE 1-2P', 'L. Lacatan', NULL),
  ('Sat', 'CEA310', '17:00', '20:00', 'CMPE 402', 'Project Management', 'BSCPE 4-5', 'L. Lacatan', NULL),
  ('Sat', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay', NULL),
  ('Sat', 'CEA311', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-6', 'R. Mahaguay', NULL),
  ('Sat', 'CEA311', '14:00', '17:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-6', 'R. Ado', NULL),
  ('Sat', 'CEA311', '17:00', '18:00', 'CMPE 101', NULL, 'BSCPE 1-3', 'M. Legarda', NULL),
  ('Sat', 'CEA311', '18:00', '21:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-4', 'O. Pajabera', NULL),
  ('Sat', 'CEA312', '07:30', '10:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-3', 'R. Marasigan', NULL),
  ('Sat', 'CEA312', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-3', 'R. Marasigan', NULL),
  ('Sat', 'CEA312', '14:00', '17:00', 'CMPE 402', 'Project Management', 'BSCPE 4-2', 'P. Tenerife', NULL),
  ('Sat', 'CEA312', '17:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-5', 'J. Tena', NULL),
  ('Sat', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-4', 'J. Cansino', NULL),
  ('Sat', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-4', 'J. Cansino', NULL),
  ('Sat', 'CEA313', '14:00', '20:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-3', 'J. Cansino', NULL),
  ('Sat', 'CEA314', '07:30', '12:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-5', 'F. Oquindo', NULL),
  ('Sat', 'CEA314', '12:30', '13:30', 'CMPE 101', NULL, 'BSCPE 1-7', 'F. Oquindo', NULL),
  ('Sat', 'CEA314', '14:00', '15:00', 'CMPE 101', NULL, 'BSCPE 1-6', 'F. Oquindo', NULL),
  ('Sat', 'CEA314', '15:00', '16:00', 'CMPE 101', NULL, 'BSCPE 1-4', 'F. Oquindo', NULL),
  ('Sat', 'CEA314', '16:30', '21:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-6', 'F. Oquindo', NULL),
  ('Sat', 'CEA315', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-6', 'O. Pajabera', NULL),
  ('Sat', 'CEA315', '10:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-3', 'O. Pajabera', NULL),
  ('Sat', 'CEA315', '14:00', '17:00', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-1P', 'R. Marasigan', NULL),
  ('Sat', 'CEA315', '17:00', '21:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'D. Madrigalejos', NULL),
  ('Sat', 'CEA316', '07:30', '10:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-5', 'D. Madrigalejos', NULL),
  ('Sat', 'CEA316', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-5', 'D. Madrigalejos', NULL),
  ('Sat', 'CEA316', '15:00', '18:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-3', 'J. Avila', NULL),
  ('Sat', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-7', 'J. Avila', NULL),
  ('Sat', 'CEA413', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-3', 'M. Ello', NULL),
  ('Sat', 'CEA413', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-2', 'M. Ello', NULL),
  ('Sat', 'CEA413', '15:00', '17:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'D. Madrigalejos', NULL),
  ('Sat', 'CEA413', '17:00', '20:00', 'CHEM', 'Chemistry for Engineers', NULL, 'F. Lacsa', NULL),
  ('Sat', 'CEA207', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-3', NULL, NULL),
  ('Sat', 'CEA207', '14:00', '17:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo', NULL),
  ('Sat', 'CEA207', '18:00', '21:00', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-6', NULL, NULL);

-- New sections and courses only (<=> is NULL-safe "equals", for CMPE 101's NULL title)
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
-- 5. Equipment, grouped like the request form
--    PLACEHOLDER STOCK: every item starts at TotalQty = 10. Update with real counts.
--    MaxPerRequest = 4 because the form only offers x1 to x4.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS stage_equipment;
CREATE TABLE stage_equipment (
  Category  VARCHAR(60),
  CatOrder  INT,
  Item      VARCHAR(60),
  ItemOrder INT
) ENGINE=InnoDB;

INSERT INTO stage_equipment (Category, CatOrder, Item, ItemOrder) VALUES
  ('Audiovisual Equipment', 1, 'Projector', 1),
  ('Audiovisual Equipment', 1, 'Projector Power Cord', 2),
  ('Audiovisual Equipment', 1, 'TV Remote', 3),
  ('Audiovisual Equipment', 1, 'TV Power Cord', 4),
  ('Audiovisual Equipment', 1, 'Extension Cord', 5),
  ('Audiovisual Equipment', 1, 'HDMI Cable', 6),
  ('Audiovisual Equipment', 1, 'USB Cable (Type A to Type B)', 7),
  ('Classroom Essentials', 2, 'Armed Chair', 1),
  ('Classroom Essentials', 2, 'Monoblock Chair', 2),
  ('Classroom Essentials', 2, 'Eraser', 3),
  ('Classroom Essentials', 2, 'Whiteboard Marker', 4),
  ('Classroom Essentials', 2, 'Electric Fan', 5),
  ('Classroom Essentials', 2, 'Scientific Calculator', 6),
  ('Electrical & Electronic Equipment', 3, 'Analog Multi-Tester', 1),
  ('Electrical & Electronic Equipment', 3, 'Digital Multi-Tester', 2),
  ('Electrical & Electronic Equipment', 3, 'Power Supply', 3),
  ('Electrical & Electronic Equipment', 3, 'Alligator Clip Wire', 4),
  ('Electrical & Electronic Equipment', 3, 'FPGA Kit', 5),
  ('Electrical & Electronic Equipment', 3, 'Analog Oscilloscope', 6),
  ('Electrical & Electronic Equipment', 3, 'Digital Oscilloscope', 7),
  ('Electrical & Electronic Equipment', 3, 'Oscilloscope Probe', 8),
  ('Electrical & Electronic Equipment', 3, 'Soldering Iron', 9),
  ('Electrical & Electronic Equipment', 3, 'Soldering Iron Holder', 10),
  ('Hardware Equipment', 4, 'System Unit', 1),
  ('Hardware Equipment', 4, 'Monitor', 2),
  ('Hardware Equipment', 4, 'Automatic Voltage Regulator (AVR)', 3),
  ('Hardware Equipment', 4, 'Power Cord', 4),
  ('Hardware Equipment', 4, 'VGA Cable', 5),
  ('Hardware Equipment', 4, 'Keyboard', 6),
  ('Hardware Equipment', 4, 'Mouse', 7),
  ('Hardware Equipment', 4, 'Laptop', 8),
  ('Hardware Equipment', 4, 'Laptop Charger', 9),
  ('Hardware Equipment', 4, 'Screwdriver Set', 10),
  ('Hardware Equipment', 4, 'Phillips Screwdriver', 11),
  ('Hardware Equipment', 4, 'Flathead Screwdriver', 12),
  ('Hardware Equipment', 4, 'Pliers', 13),
  ('Hardware Equipment', 4, 'Outlet Adapter', 14),
  ('Network Equipment', 5, 'LAN Cable', 1),
  ('Network Equipment', 5, 'RJ45 Connector', 2),
  ('Network Equipment', 5, 'Wire Stripper', 3),
  ('Network Equipment', 5, 'Crimping Tool', 4),
  ('Network Equipment', 5, 'LAN Tester', 5),
  ('Network Equipment', 5, 'Access Point', 6),
  ('Network Equipment', 5, 'Network Switch', 7),
  ('Breadboarding Components', 6, '7-Segment Display', 1),
  ('Breadboarding Components', 6, '9 V Battery', 2),
  ('Breadboarding Components', 6, 'AA Battery', 3),
  ('Breadboarding Components', 6, 'AAA Battery', 4),
  ('Breadboarding Components', 6, 'Breadboard', 5),
  ('Breadboarding Components', 6, 'Button', 6),
  ('Breadboarding Components', 6, 'Capacitor', 7),
  ('Breadboarding Components', 6, 'Connecting Wire', 8),
  ('Breadboarding Components', 6, 'Diode', 9),
  ('Breadboarding Components', 6, 'DIP Switch', 10),
  ('Breadboarding Components', 6, 'Integrated Circuit (IC)', 11),
  ('Breadboarding Components', 6, 'Jump Wire', 12),
  ('Breadboarding Components', 6, 'LED', 13),
  ('Breadboarding Components', 6, 'Potentiometer', 14),
  ('Breadboarding Components', 6, 'Relay', 15),
  ('Breadboarding Components', 6, 'Resistor', 16),
  ('Breadboarding Components', 6, 'Solid Wire', 17),
  ('Breadboarding Components', 6, 'Switch', 18),
  ('Breadboarding Components', 6, 'Transistor', 19);

INSERT INTO EquipmentCategory (DepartmentID, Name, SortOrder)
SELECT DISTINCT @dept, Category, CatOrder FROM stage_equipment;

INSERT INTO Equipment (CategoryID, Name, TotalQty, MaxPerRequest, SortOrder)
SELECT ec.CategoryID, s.Item, 10, 4, s.ItemOrder
FROM stage_equipment s
JOIN EquipmentCategory ec ON ec.DepartmentID = @dept AND ec.Name = s.Category;

DROP TABLE stage_equipment;

-- ------------------------------------------------------------
-- 6. Test accounts (password: Password123!). Replace before real use.
-- ------------------------------------------------------------
INSERT INTO Users (DepartmentID, SectionID, Email, PasswordHash, Name, Role) VALUES
  (@dept, NULL, 'admin@reservebot.test', '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'BSCpE Office Admin', 'admin'),
  (@dept, NULL, 'staff@reservebot.test', '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'Lab Assistant', 'lab_staff'),
  (@dept, (SELECT SectionID FROM Section WHERE Name = 'BSCPE 4-5'),
          'rep1@reservebot.test', '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'Bea Prieto', 'rep'),
  (@dept, (SELECT SectionID FROM Section WHERE Name = 'BSCPE 3-1'),
          'rep2@reservebot.test', '$2b$10$T5LTYtgAG0B2un3psfft5.N9WH/IFzYohnea0NSAJMVMAXvJzxvY2', 'Maria Santos', 'rep');

-- ------------------------------------------------------------
-- 7. Sample reservations, dated relative to today so they stay useful
-- ------------------------------------------------------------
SET @admin    := (SELECT UserID FROM Users WHERE Email = 'admin@reservebot.test');
SET @rep1     := (SELECT UserID FROM Users WHERE Email = 'rep1@reservebot.test');
SET @rep2     := (SELECT UserID FROM Users WHERE Email = 'rep2@reservebot.test');
SET @next_tue := CURDATE() + INTERVAL (7 - MOD(WEEKDAY(CURDATE()) + 6, 7)) DAY;
SET @next_mon := CURDATE() + INTERVAL (7 - WEEKDAY(CURDATE())) DAY;

-- (a) rep1 (BSCPE 4-5) books next Tuesday's CMPE 451 Introduction to Cybersecurity class
INSERT INTO Reservation (UserID, RoomID, ScheduleID, StartTime, EndTime, Status, ReviewedBy)
SELECT @rep1, cs.RoomID, cs.ScheduleID,
       TIMESTAMP(@next_tue, cs.StartTime), TIMESTAMP(@next_tue, cs.EndTime), 'Confirmed', @admin
FROM ClassSchedule cs
JOIN Section s ON s.SectionID = cs.SectionID
JOIN Course c  ON c.CourseID = cs.CourseID
WHERE s.Name = 'BSCPE 4-5' AND c.Code = 'CMPE 451' AND cs.DayOfWeek = 'Tue';
SET @res1 := LAST_INSERT_ID();

INSERT INTO ReservationEquipment (ReservationID, EquipmentID, QtyRequested)
SELECT @res1, e.EquipmentID, 1
FROM Equipment e JOIN EquipmentCategory ec ON ec.CategoryID = e.CategoryID
WHERE ec.DepartmentID = @dept AND e.Name IN ('Projector', 'Projector Power Cord', 'HDMI Cable');

-- (b) rep2 (BSCPE 3-1) asks for a free slot for a make-up class, waiting for approval
INSERT INTO Reservation (UserID, RoomID, Purpose, StartTime, EndTime, Status, OtherItems)
SELECT @rep2, RoomID, 'Make-up class',
       TIMESTAMP(@next_mon, '08:00'), TIMESTAMP(@next_mon, '10:00'), 'Pending', 'Bluetooth speaker x1'
FROM Room WHERE Name = 'CEA316';
SET @res2 := LAST_INSERT_ID();

INSERT INTO ReservationEquipment (ReservationID, EquipmentID, QtyRequested)
SELECT @res2, e.EquipmentID, 2
FROM Equipment e JOIN EquipmentCategory ec ON ec.CategoryID = e.CategoryID
WHERE ec.DepartmentID = @dept AND e.Name = 'Extension Cord';

-- Quick check: expected 224 timetable rows, 63 equipment items
SELECT
  (SELECT COUNT(*) FROM ClassSchedule cs WHERE cs.DepartmentID = @dept) AS TimetableRows,
  (SELECT COUNT(*) FROM Section)                                        AS Sections,
  (SELECT COUNT(*) FROM Course)                                         AS Courses,
  (SELECT COUNT(*) FROM Equipment e JOIN EquipmentCategory ec ON ec.CategoryID = e.CategoryID
     WHERE ec.DepartmentID = @dept)                                     AS EquipmentItems,
  (SELECT COUNT(*) FROM Reservation)                                    AS Reservations;
