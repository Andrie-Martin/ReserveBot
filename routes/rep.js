const express = require('express');
const db = require('../db');
const { requireRole } = require('../middleware/auth');

const router = express.Router();
router.use(requireRole('rep'));

router.get('/', async (req, res, next) => {
  try {
    // The rep's block's classes for the current semester
    const [classes] = await db.query(
      `SELECT cs.DayOfWeek,
              TIME_FORMAT(cs.StartTime, '%l:%i %p') AS StartTime,
              TIME_FORMAT(cs.EndTime,   '%l:%i %p') AS EndTime,
              r.Name AS Room, c.Code, c.Title, cs.Instructor
       FROM ClassSchedule cs
       JOIN Term t   ON t.TermID = cs.TermID
       JOIN Room r   ON r.RoomID = cs.RoomID
       JOIN Course c ON c.CourseID = cs.CourseID
       WHERE cs.SectionID = ?
         AND CURDATE() BETWEEN t.StartDate AND t.EndDate
       ORDER BY cs.DayOfWeek, cs.StartTime`,
      [req.session.user.sectionId]
    );

    res.render('home', {
      title: 'Home',
      intro: 'This is where you will book rooms and equipment for your block.',
      stat: null,
      classes,
      comingNext: [
        'Check which rooms are free',
        'Book a session of one of your classes, or a free slot for a make-up class',
        'Request equipment like projectors and HDMI cables',
        'See and cancel your reservations',
      ],
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
