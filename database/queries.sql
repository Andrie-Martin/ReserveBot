-- ============================================================
-- ReserveBot: core lookups (optional file, nothing is changed)
-- Paste into phpMyAdmin's SQL tab to try them.
-- In the Node app, each @variable becomes a ? placeholder.
--
-- Rules these queries follow:
--  * A reservation holds its slot while Pending, Confirmed or InUse.
--  * A timetable entry blocks its room on that weekday, but only
--    between the term's StartDate and EndDate.
--  * Rooms are shared, so conflicts are checked across ALL departments.
-- ============================================================
USE reservebot;

-- Example values (next Tuesday 12:00-1:00 PM in CEA300, during the
-- BSCPE 4-5 CMPE 451 class that rep1 has a sample reservation for)
SET @dept_slug := 'bscpe';
SET @next_tue  := CURDATE() + INTERVAL (7 - MOD(WEEKDAY(CURDATE()) + 6, 7)) DAY;
SET @room      := 'CEA300';
SET @start     := TIMESTAMP(@next_tue, '12:00');
SET @end       := TIMESTAMP(@next_tue, '13:00');

-- ------------------------------------------------------------
-- 1. Why is this room busy? (no rows = free)
--    Expected: rep1's reservation and the CMPE 451 class, both 11:30 AM-1:30 PM
--    When a rep books a session of THEIR OWN scheduled class, also add
--    AND cs.ScheduleID <> <that class's ScheduleID>  to the second half.
-- ------------------------------------------------------------
SELECT 'Reservation' AS BlockedBy, r.ReservationID AS ID,
       TIME(r.StartTime) AS FromTime, TIME(r.EndTime) AS ToTime, r.Status AS Detail
FROM Reservation r
JOIN Room rm ON rm.RoomID = r.RoomID
WHERE rm.Name = @room
  AND r.Status IN ('Pending', 'Confirmed', 'InUse')
  AND r.StartTime < @end AND r.EndTime > @start
UNION ALL
SELECT 'Timetable', cs.ScheduleID, cs.StartTime, cs.EndTime,
       COALESCE(CONCAT(c.Code, ' ', COALESCE(s.Name, '')), cs.Note)
FROM ClassSchedule cs
JOIN Room rm ON rm.RoomID = cs.RoomID
JOIN Term t  ON t.TermID = cs.TermID
LEFT JOIN Course c  ON c.CourseID = cs.CourseID
LEFT JOIN Section s ON s.SectionID = cs.SectionID
WHERE rm.Name = @room
  AND DATE(@start) BETWEEN t.StartDate AND t.EndDate
  AND cs.DayOfWeek = ELT(WEEKDAY(@start) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
  AND cs.StartTime < TIME(@end) AND cs.EndTime > TIME(@start);

-- ------------------------------------------------------------
-- 2. Which of the department's rooms are free for the whole window?
--    Next Tuesday 8:00-10:00 AM -> expected: CEA300, CEA302, CEA315
--    (at 3:00-4:00 PM every room is taken, so the result would be empty)
-- ------------------------------------------------------------
SET @start := TIMESTAMP(@next_tue, '08:00');
SET @end   := TIMESTAMP(@next_tue, '10:00');

SELECT rm.Name AS Room, rm.Capacity
FROM Room rm
JOIN DepartmentRoom dr ON dr.RoomID = rm.RoomID
JOIN Department d      ON d.DepartmentID = dr.DepartmentID
WHERE d.Slug = @dept_slug
  AND rm.IsActive = 1
  AND NOT EXISTS (
        SELECT 1 FROM Reservation r
        WHERE r.RoomID = rm.RoomID
          AND r.Status IN ('Pending', 'Confirmed', 'InUse')
          AND r.StartTime < @end AND r.EndTime > @start)
  AND NOT EXISTS (
        SELECT 1 FROM ClassSchedule cs
        JOIN Term t ON t.TermID = cs.TermID
        WHERE cs.RoomID = rm.RoomID
          AND DATE(@start) BETWEEN t.StartDate AND t.EndDate
          AND cs.DayOfWeek = ELT(WEEKDAY(@start) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
          AND cs.StartTime < TIME(@end) AND cs.EndTime > TIME(@start))
ORDER BY rm.Name;

-- ------------------------------------------------------------
-- 3. A rep's scheduled classes on a date (what they can book)
-- ------------------------------------------------------------
SET @email := 'rep1@reservebot.test';
SET @date  := @next_tue;

SELECT cs.ScheduleID, rm.Name AS Room, cs.StartTime, cs.EndTime,
       c.Code, c.Title, cs.Instructor
FROM Users u
JOIN ClassSchedule cs ON cs.SectionID = u.SectionID
JOIN Term t           ON t.TermID = cs.TermID
JOIN Room rm          ON rm.RoomID = cs.RoomID
JOIN Course c         ON c.CourseID = cs.CourseID
WHERE u.Email = @email
  AND @date BETWEEN t.StartDate AND t.EndDate
  AND cs.DayOfWeek = ELT(WEEKDAY(@date) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
ORDER BY cs.StartTime;

-- ------------------------------------------------------------
-- 4. Equipment still available in the window
--    Subtracts every overlapping booking, so it never over-promises.
--    Next Tuesday 12:00-1:00 PM -> Projector, Projector Power Cord and
--    HDMI Cable show 9 of 10 because of rep1's sample reservation.
-- ------------------------------------------------------------
SET @start := TIMESTAMP(@next_tue, '12:00');
SET @end   := TIMESTAMP(@next_tue, '13:00');

SELECT ec.Name AS Category, e.Name AS Item, e.TotalQty,
       e.TotalQty - COALESCE((
         SELECT SUM(re.QtyRequested)
         FROM ReservationEquipment re
         JOIN Reservation r ON r.ReservationID = re.ReservationID
         WHERE re.EquipmentID = e.EquipmentID
           AND r.Status IN ('Pending', 'Confirmed', 'InUse')
           AND r.StartTime < @end AND r.EndTime > @start), 0) AS Available,
       e.MaxPerRequest
FROM Equipment e
JOIN EquipmentCategory ec ON ec.CategoryID = e.CategoryID
JOIN Department d         ON d.DepartmentID = ec.DepartmentID
WHERE d.Slug = @dept_slug AND e.IsActive = 1
ORDER BY ec.SortOrder, e.SortOrder;

-- ------------------------------------------------------------
-- 5. One room's full day (timetable + reservations), e.g. for the staff dashboard
-- ------------------------------------------------------------
SET @day := @next_tue;

SELECT cs.StartTime, cs.EndTime, 'Timetable' AS Source,
       COALESCE(CONCAT(c.Code, ' ', COALESCE(c.Title, ''), ' / ', COALESCE(s.Name, '')), cs.Note) AS What
FROM ClassSchedule cs
JOIN Room rm ON rm.RoomID = cs.RoomID
JOIN Term t  ON t.TermID = cs.TermID
LEFT JOIN Course c  ON c.CourseID = cs.CourseID
LEFT JOIN Section s ON s.SectionID = cs.SectionID
WHERE rm.Name = @room
  AND @day BETWEEN t.StartDate AND t.EndDate
  AND cs.DayOfWeek = ELT(WEEKDAY(@day) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
UNION ALL
SELECT TIME(r.StartTime), TIME(r.EndTime), CONCAT('Reservation #', r.ReservationID),
       CONCAT(r.Status, ' - ', COALESCE(r.Purpose, 'scheduled class'), ' - ', u.Name)
FROM Reservation r
JOIN Room rm ON rm.RoomID = r.RoomID
JOIN Users u ON u.UserID = r.UserID
WHERE rm.Name = @room AND DATE(r.StartTime) = @day
ORDER BY 1;
