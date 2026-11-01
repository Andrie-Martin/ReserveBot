const express = require('express');
const db = require('../db');
const { requireRole } = require('../middleware/auth');

const router = express.Router();
router.use(requireRole('lab_staff', 'admin'));

router.get('/', async (req, res, next) => {
  try {
    // Reservations happening today in this department
    const [[today]] = await db.query(
      `SELECT COUNT(*) AS n
       FROM Reservation r
       JOIN Users u ON u.UserID = r.UserID
       WHERE u.DepartmentID = ?
         AND DATE(r.StartTime) = CURDATE()
         AND r.Status IN ('Confirmed', 'InUse')`,
      [req.session.user.departmentId]
    );

    res.render('home', {
      title: 'Lab staff',
      intro: "This is where you will run the day's room schedule.",
      stat: { value: today.n, label: today.n === 1 ? 'reservation today' : 'reservations today' },
      classes: null,
      comingNext: [
        "Today's schedule for every room",
        'Verify a reservation and issue the key and equipment',
        'Mark items returned, or mark a no-show',
        'Reschedule or cancel a reservation, with a reason',
      ],
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
