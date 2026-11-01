-- ============================================================
-- ReserveBot: Facility and Equipment Reservation System
-- File 1 of 2: STRUCTURE (tables only, no department data)
--
-- Load order:  1) schema.sql   2) bscpe_seed.sql
-- A new department later = one more seed file (see new_department_template.sql).
--
-- Safe to re-run: drops and recreates every table.
-- WARNING: re-running DELETES all data, including real reservations.
-- Tested on MariaDB 10.11 (same as XAMPP). All tables use InnoDB for row locking.
-- ============================================================

CREATE DATABASE IF NOT EXISTS reservebot
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservebot;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS TimetableView;
DROP TABLE IF EXISTS OverrideLog;
DROP TABLE IF EXISTS ReservationEquipment;
DROP TABLE IF EXISTS Reservation;
DROP TABLE IF EXISTS Equipment;
DROP TABLE IF EXISTS EquipmentCategory;
DROP TABLE IF EXISTS ClassSchedule;
DROP TABLE IF EXISTS DepartmentRoom;
DROP TABLE IF EXISTS Users;
DROP TABLE IF EXISTS Room;
DROP TABLE IF EXISTS Course;
DROP TABLE IF EXISTS Section;
DROP TABLE IF EXISTS Term;
DROP TABLE IF EXISTS DepartmentConfig;
DROP TABLE IF EXISTS Department;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- DEPARTMENTS (the "template" part: one row per department)
-- ------------------------------------------------------------
CREATE TABLE Department (
  DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
  Name         VARCHAR(100) NOT NULL,
  Slug         VARCHAR(30)  NOT NULL UNIQUE,      -- short code, e.g. 'bscpe'
  ContactEmail VARCHAR(150),
  CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Booking rules, one row per department
CREATE TABLE DepartmentConfig (
  DepartmentID        INT PRIMARY KEY,
  RequiresApproval    TINYINT(1) NOT NULL DEFAULT 1,
  OpenDays            SET('Mon','Tue','Wed','Thu','Fri','Sat','Sun')
                        NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat',
  BusinessHoursStart  TIME NOT NULL DEFAULT '07:30:00',
  BusinessHoursEnd    TIME NOT NULL DEFAULT '21:00:00',
  SlotMinutes         INT  NOT NULL DEFAULT 30,
  MinBookingMinutes   INT  NOT NULL DEFAULT 30,
  MaxBookingMinutes   INT  NOT NULL DEFAULT 180,   -- for bookings NOT tied to a scheduled class
  MaxDaysAhead        INT  NOT NULL DEFAULT 14,
  MinNoticeMinutes    INT  NOT NULL DEFAULT 60,
  CancelCutoffMinutes INT  NOT NULL DEFAULT 60,
  NoShowGraceMinutes  INT  NOT NULL DEFAULT 15,
  CONSTRAINT fk_config_department FOREIGN KEY (DepartmentID)
    REFERENCES Department(DepartmentID) ON DELETE CASCADE,
  CONSTRAINT chk_config_hours CHECK (BusinessHoursEnd > BusinessHoursStart)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SHARED CATALOGS (university-wide, reused by every department)
-- ------------------------------------------------------------
-- Semester dates: a class schedule only blocks rooms between these dates
CREATE TABLE Term (
  TermID    INT AUTO_INCREMENT PRIMARY KEY,
  Name      VARCHAR(60) NOT NULL UNIQUE,          -- e.g. '1st Semester AY 2026-2027'
  StartDate DATE NOT NULL,
  EndDate   DATE NOT NULL,
  CONSTRAINT chk_term_dates CHECK (EndDate > StartDate)
) ENGINE=InnoDB;

-- Blocks / class sections, e.g. 'BSCPE 4-2', 'BSME 2-2'
CREATE TABLE Section (
  SectionID INT AUTO_INCREMENT PRIMARY KEY,
  Name      VARCHAR(30) NOT NULL UNIQUE,
  Program   VARCHAR(10) NOT NULL                  -- e.g. 'BSCPE', 'BSME'
) ENGINE=InnoDB;

-- A course is identified by code AND title, because some codes are shared
-- by different courses (e.g. CMPE 453 is both Software Processes and
-- Database Implementation on the BSCpE timetable).
CREATE TABLE Course (
  CourseID INT AUTO_INCREMENT PRIMARY KEY,
  Code     VARCHAR(20)  NOT NULL,                 -- e.g. 'CMPE 453'
  Title    VARCHAR(100),                          -- NULL when the timetable shows no title
  CONSTRAINT uq_course UNIQUE (Code, Title)
) ENGINE=InnoDB;

-- Rooms are building-wide and can be shared by departments (CEA302 is
-- shared by BSCpE and ECE), so they are NOT owned by one department.
CREATE TABLE Room (
  RoomID             INT AUTO_INCREMENT PRIMARY KEY,
  Name               VARCHAR(30) NOT NULL UNIQUE, -- e.g. 'CEA300'
  Building           VARCHAR(60),
  Capacity           INT,                         -- NULL = not recorded yet
  FixedEquipmentNote VARCHAR(200),
  IsActive           TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Which rooms each department can book (many-to-many)
CREATE TABLE DepartmentRoom (
  DepartmentID INT NOT NULL,
  RoomID       INT NOT NULL,
  PRIMARY KEY (DepartmentID, RoomID),
  CONSTRAINT fk_dr_department FOREIGN KEY (DepartmentID)
    REFERENCES Department(DepartmentID) ON DELETE CASCADE,
  CONSTRAINT fk_dr_room FOREIGN KEY (RoomID) REFERENCES Room(RoomID)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE Users (
  UserID       INT AUTO_INCREMENT PRIMARY KEY,
  DepartmentID INT NOT NULL,                      -- department whose rooms/equipment they use
  SectionID    INT,                               -- block a rep represents; NULL for staff/admin
  Email        VARCHAR(150) NOT NULL UNIQUE,
  PasswordHash VARCHAR(255) NOT NULL,             -- bcrypt hash, never the plain password
  Name         VARCHAR(100) NOT NULL,
  Role         ENUM('rep','lab_staff','admin') NOT NULL DEFAULT 'rep',
  IsActive     TINYINT(1) NOT NULL DEFAULT 1,
  CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (DepartmentID) REFERENCES Department(DepartmentID),
  CONSTRAINT fk_users_section    FOREIGN KEY (SectionID)    REFERENCES Section(SectionID)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WEEKLY TIMETABLE (from the department's room schedule)
--   EntryType 'Class'   = a scheduled class; only that section may book the slot
--   EntryType 'Blocked' = room not available (e.g. another department's day)
-- ------------------------------------------------------------
CREATE TABLE ClassSchedule (
  ScheduleID   INT AUTO_INCREMENT PRIMARY KEY,
  TermID       INT NOT NULL,
  DepartmentID INT NOT NULL,                      -- department that manages this entry
  RoomID       INT NOT NULL,
  DayOfWeek    ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  StartTime    TIME NOT NULL,
  EndTime      TIME NOT NULL,
  EntryType    ENUM('Class','Blocked') NOT NULL DEFAULT 'Class',
  CourseID     INT,
  SectionID    INT,
  Instructor   VARCHAR(60),
  Note         VARCHAR(150),
  CONSTRAINT fk_cs_term       FOREIGN KEY (TermID)       REFERENCES Term(TermID),
  CONSTRAINT fk_cs_department FOREIGN KEY (DepartmentID) REFERENCES Department(DepartmentID),
  CONSTRAINT fk_cs_room       FOREIGN KEY (RoomID)       REFERENCES Room(RoomID),
  CONSTRAINT fk_cs_course     FOREIGN KEY (CourseID)     REFERENCES Course(CourseID),
  CONSTRAINT fk_cs_section    FOREIGN KEY (SectionID)    REFERENCES Section(SectionID),
  CONSTRAINT chk_cs_time      CHECK (EndTime > StartTime),
  CONSTRAINT chk_cs_class     CHECK (EntryType = 'Blocked' OR CourseID IS NOT NULL)
) ENGINE=InnoDB;

CREATE INDEX idx_cs_room_day ON ClassSchedule (TermID, RoomID, DayOfWeek, StartTime);

-- ------------------------------------------------------------
-- EQUIPMENT (owned by a department, grouped like the request form)
-- ------------------------------------------------------------
CREATE TABLE EquipmentCategory (
  CategoryID   INT AUTO_INCREMENT PRIMARY KEY,
  DepartmentID INT NOT NULL,
  Name         VARCHAR(60) NOT NULL,
  SortOrder    INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_ec_department FOREIGN KEY (DepartmentID) REFERENCES Department(DepartmentID),
  CONSTRAINT uq_category UNIQUE (DepartmentID, Name)
) ENGINE=InnoDB;

-- TotalQty is the pool; how many are free is computed per time window
CREATE TABLE Equipment (
  EquipmentID   INT AUTO_INCREMENT PRIMARY KEY,
  CategoryID    INT NOT NULL,                     -- department comes from the category
  Name          VARCHAR(60) NOT NULL,
  TotalQty      INT NOT NULL DEFAULT 0,
  MaxPerRequest INT NOT NULL DEFAULT 4,           -- request form allows x1 to x4
  SortOrder     INT NOT NULL DEFAULT 0,
  IsActive      TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_equipment_category FOREIGN KEY (CategoryID) REFERENCES EquipmentCategory(CategoryID),
  CONSTRAINT uq_equipment UNIQUE (CategoryID, Name),
  CONSTRAINT chk_equipment_qty CHECK (TotalQty >= 0 AND MaxPerRequest > 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- RESERVATIONS
--   Slot-blocking statuses: Pending, Confirmed, InUse
--   ScheduleID set   = booking a session of the rep's scheduled class
--   ScheduleID NULL  = extra booking (make-up class, meeting); Purpose required
-- ------------------------------------------------------------
CREATE TABLE Reservation (
  ReservationID INT AUTO_INCREMENT PRIMARY KEY,
  UserID        INT NOT NULL,
  RoomID        INT NOT NULL,
  ScheduleID    INT,
  Purpose       VARCHAR(150),
  StartTime     DATETIME NOT NULL,
  EndTime       DATETIME NOT NULL,
  Status        ENUM('Pending','Confirmed','Rejected','Cancelled',
                     'InUse','Completed','NoShow') NOT NULL DEFAULT 'Pending',
  StatusReason  VARCHAR(255),                     -- rejection / cancellation reason
  OtherItems    TEXT,                             -- unlisted items, e.g. 'Item x2'
  ReviewedBy    INT,                              -- admin who approved/rejected
  CreatedAt     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UpdatedAt     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_user     FOREIGN KEY (UserID)     REFERENCES Users(UserID),
  CONSTRAINT fk_res_room     FOREIGN KEY (RoomID)     REFERENCES Room(RoomID),
  CONSTRAINT fk_res_schedule FOREIGN KEY (ScheduleID) REFERENCES ClassSchedule(ScheduleID),
  CONSTRAINT fk_res_reviewer FOREIGN KEY (ReviewedBy) REFERENCES Users(UserID),
  CONSTRAINT chk_res_time    CHECK (EndTime > StartTime),
  CONSTRAINT chk_res_purpose CHECK (ScheduleID IS NOT NULL OR Purpose IS NOT NULL)
) ENGINE=InnoDB;

CREATE INDEX idx_res_room_time ON Reservation (RoomID, StartTime, EndTime);
CREATE INDEX idx_res_user      ON Reservation (UserID, StartTime);
CREATE INDEX idx_res_status    ON Reservation (Status);

-- Equipment requested per reservation (many-to-many)
CREATE TABLE ReservationEquipment (
  ReservationID INT NOT NULL,
  EquipmentID   INT NOT NULL,
  QtyRequested  INT NOT NULL,
  PRIMARY KEY (ReservationID, EquipmentID),
  CONSTRAINT fk_re_reservation FOREIGN KEY (ReservationID)
    REFERENCES Reservation(ReservationID) ON DELETE CASCADE,
  CONSTRAINT fk_re_equipment FOREIGN KEY (EquipmentID) REFERENCES Equipment(EquipmentID),
  CONSTRAINT chk_re_qty CHECK (QtyRequested > 0)
) ENGINE=InnoDB;

-- Every manual change by lab staff / admin
CREATE TABLE OverrideLog (
  OverrideID      INT AUTO_INCREMENT PRIMARY KEY,
  ReservationID   INT NOT NULL,
  ChangedByUserID INT NOT NULL,
  Action          ENUM('Rescheduled','Cancelled','StatusChanged','EquipmentUpdated') NOT NULL,
  OldValue        JSON,
  NewValue        JSON,
  Reason          VARCHAR(255) NOT NULL,
  ChangedAt       DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_reservation FOREIGN KEY (ReservationID) REFERENCES Reservation(ReservationID),
  CONSTRAINT fk_log_user        FOREIGN KEY (ChangedByUserID) REFERENCES Users(UserID)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VIEW: the timetable in readable form (handy in phpMyAdmin and the app)
-- ------------------------------------------------------------
CREATE VIEW TimetableView AS
SELECT cs.ScheduleID,
       t.Name        AS Term,
       d.Slug        AS Department,
       r.Name        AS Room,
       cs.DayOfWeek,
       cs.StartTime,
       cs.EndTime,
       cs.EntryType,
       c.Code        AS CourseCode,
       c.Title       AS CourseTitle,
       s.Name        AS Section,
       cs.Instructor,
       cs.Note
FROM ClassSchedule cs
JOIN Term t       ON t.TermID = cs.TermID
JOIN Department d ON d.DepartmentID = cs.DepartmentID
JOIN Room r       ON r.RoomID = cs.RoomID
LEFT JOIN Course c  ON c.CourseID = cs.CourseID
LEFT JOIN Section s ON s.SectionID = cs.SectionID;

-- Quick check: should list 14 tables and 1 view
SHOW FULL TABLES;
