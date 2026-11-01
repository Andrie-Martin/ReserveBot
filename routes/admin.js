const express = require('express');
const db = require('../db');
const { requireRole } = require('../middleware/auth');

const router = express.Router();
router.use(requireRole('admin'));

router.get('/', async (req, res, next) => {
  try {
    // Requests from this department waiting for approval
    const [[pending]] = await db.query(
      `SELECT COUNT(*) AS n
       FROM Reservation r
       JOIN Users u ON u.UserID = r.UserID
       WHERE u.DepartmentID = ? AND r.Status = 'Pending'`,
      [req.session.user.departmentId]
    );

    res.render('home', {
      title: 'Admin',
      intro: 'This is where you will approve requests and manage the department.',
      stat: { value: pending.n, label: pending.n === 1 ? 'request waiting for approval' : 'requests waiting for approval' },
      classes: null,
      comingNext: [
        'Approve or reject pending requests',
        'Manage rooms, equipment and stock counts',
        'Create accounts for reps and lab staff',
        'Department settings and reports',
      ],
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
