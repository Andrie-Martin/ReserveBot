-- ============================================================
-- ReserveBot: core lookups (optional file, nothing is changed)
-- Paste into phpMyAdmin's SQL tab to try them.
-- In Laravel, each @variable becomes a ? placeholder or a query builder call.
--
-- Rules these queries follow:
--  * A reservation holds its slot while Pending, Confirmed or InUse.
--  * A timetable entry blocks its room on that weekday, but only
--    between the term's start_date and end_date.
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
--    AND cs.id <> <that class's id>  to the second half.
-- ------------------------------------------------------------
SELECT 'Reservation' AS blocked_by, r.id,
       TIME(r.start_time) AS from_time, TIME(r.end_time) AS to_time, r.status AS detail
FROM reservations r
JOIN rooms rm ON rm.id = r.room_id
WHERE rm.name = @room
  AND r.status IN ('Pending', 'Confirmed', 'InUse')
  AND r.start_time < @end AND r.end_time > @start
UNION ALL
SELECT 'Timetable', cs.id, cs.start_time, cs.end_time,
       COALESCE(CONCAT(c.code, ' ', COALESCE(s.name, '')), cs.note)
FROM class_schedules cs
JOIN rooms rm ON rm.id = cs.room_id
JOIN terms t  ON t.id = cs.term_id
LEFT JOIN courses c  ON c.id = cs.course_id
LEFT JOIN sections s ON s.id = cs.section_id
WHERE rm.name = @room
  AND DATE(@start) BETWEEN t.start_date AND t.end_date
  AND cs.day_of_week = ELT(WEEKDAY(@start) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
  AND cs.start_time < TIME(@end) AND cs.end_time > TIME(@start);

-- ------------------------------------------------------------
-- 2. Which of the department's rooms are free for the whole window?
--    Next Tuesday 8:00-10:00 AM -> expected: CEA300, CEA302, CEA315
--    (at 3:00-4:00 PM every room is taken, so the result would be empty)
-- ------------------------------------------------------------
SET @start := TIMESTAMP(@next_tue, '08:00');
SET @end   := TIMESTAMP(@next_tue, '10:00');

SELECT rm.name AS room, rm.capacity
FROM rooms rm
JOIN department_room dr ON dr.room_id = rm.id
JOIN departments d      ON d.id = dr.department_id
WHERE d.slug = @dept_slug
  AND rm.is_active = 1
  AND NOT EXISTS (
        SELECT 1 FROM reservations r
        WHERE r.room_id = rm.id
          AND r.status IN ('Pending', 'Confirmed', 'InUse')
          AND r.start_time < @end AND r.end_time > @start)
  AND NOT EXISTS (
        SELECT 1 FROM class_schedules cs
        JOIN terms t ON t.id = cs.term_id
        WHERE cs.room_id = rm.id
          AND DATE(@start) BETWEEN t.start_date AND t.end_date
          AND cs.day_of_week = ELT(WEEKDAY(@start) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
          AND cs.start_time < TIME(@end) AND cs.end_time > TIME(@start))
ORDER BY rm.name;

-- ------------------------------------------------------------
-- 3. A rep's scheduled classes on a date (what they can book)
-- ------------------------------------------------------------
SET @email := 'rep1@reservebot.test';
SET @date  := @next_tue;

SELECT cs.id AS schedule_id, rm.name AS room, cs.start_time, cs.end_time,
       c.code, c.title, cs.instructor
FROM users u
JOIN class_schedules cs ON cs.section_id = u.section_id
JOIN terms t            ON t.id = cs.term_id
JOIN rooms rm           ON rm.id = cs.room_id
JOIN courses c          ON c.id = cs.course_id
WHERE u.email = @email
  AND @date BETWEEN t.start_date AND t.end_date
  AND cs.day_of_week = ELT(WEEKDAY(@date) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
ORDER BY cs.start_time;

-- ------------------------------------------------------------
-- 4. Equipment still available in the window
--    Subtracts every overlapping booking, so it never over-promises.
--    Next Tuesday 12:00-1:00 PM -> Projector, Projector Power Cord and
--    HDMI Cable show 9 of 10 because of rep1's sample reservation.
-- ------------------------------------------------------------
SET @start := TIMESTAMP(@next_tue, '12:00');
SET @end   := TIMESTAMP(@next_tue, '13:00');

SELECT ec.name AS category, e.name AS item, e.total_qty,
       e.total_qty - COALESCE((
         SELECT SUM(re.qty_requested)
         FROM reservation_equipment re
         JOIN reservations r ON r.id = re.reservation_id
         WHERE re.equipment_id = e.id
           AND r.status IN ('Pending', 'Confirmed', 'InUse')
           AND r.start_time < @end AND r.end_time > @start), 0) AS available,
       e.max_per_request
FROM equipment e
JOIN equipment_categories ec ON ec.id = e.category_id
JOIN departments d           ON d.id = ec.department_id
WHERE d.slug = @dept_slug AND e.is_active = 1
ORDER BY ec.sort_order, e.sort_order;

-- ------------------------------------------------------------
-- 5. One room's full day (timetable + reservations), e.g. for the staff dashboard
-- ------------------------------------------------------------
SET @day := @next_tue;

SELECT cs.start_time, cs.end_time, 'Timetable' AS source,
       COALESCE(CONCAT(c.code, ' ', COALESCE(c.title, ''), ' / ', COALESCE(s.name, '')), cs.note) AS what
FROM class_schedules cs
JOIN rooms rm ON rm.id = cs.room_id
JOIN terms t  ON t.id = cs.term_id
LEFT JOIN courses c  ON c.id = cs.course_id
LEFT JOIN sections s ON s.id = cs.section_id
WHERE rm.name = @room
  AND @day BETWEEN t.start_date AND t.end_date
  AND cs.day_of_week = ELT(WEEKDAY(@day) + 1, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun')
UNION ALL
SELECT TIME(r.start_time), TIME(r.end_time), CONCAT('Reservation #', r.id),
       CONCAT(r.status, ' - ', COALESCE(r.purpose, 'scheduled class'), ' - ', u.name)
FROM reservations r
JOIN rooms rm ON rm.id = r.room_id
JOIN users u  ON u.id = r.user_id
WHERE rm.name = @room AND DATE(r.start_time) = @day
ORDER BY 1;
