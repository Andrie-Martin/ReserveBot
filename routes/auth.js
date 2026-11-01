const express = require('express');
const bcrypt = require('bcryptjs');
const db = require('../db');
const { homeFor } = require('../middleware/auth');

const router = express.Router();

router.get('/login', (req, res) => {
  if (req.session.user) return res.redirect(homeFor(req.session.user.role));
  res.render('login', { title: 'Log in', error: null, email: '' });
});

router.post('/login', async (req, res, next) => {
  try {
    const email = String(req.body.email || '').trim().toLowerCase();
    const password = String(req.body.password || '');

    const [rows] = await db.query(
      `SELECT u.UserID, u.Name, u.Role, u.PasswordHash, u.IsActive,
              u.DepartmentID, d.Name AS DepartmentName,
              u.SectionID, s.Name AS SectionName
       FROM Users u
       JOIN Department d    ON d.DepartmentID = u.DepartmentID
       LEFT JOIN Section s  ON s.SectionID = u.SectionID
       WHERE u.Email = ?`,
      [email]
    );
    const found = rows[0];
    const passwordOk = found ? await bcrypt.compare(password, found.PasswordHash) : false;

    // Same message whether the email or the password is wrong, so nobody can probe for accounts
    if (!found || !passwordOk || !found.IsActive) {
      return res.status(401).render('login', {
        title: 'Log in',
        error: 'Wrong email or password.',
        email,
      });
    }

    // New session ID on every login (stops "session fixation" attacks)
    req.session.regenerate((err) => {
      if (err) return next(err);
      req.session.user = {
        id: found.UserID,
        name: found.Name,
        role: found.Role,
        departmentId: found.DepartmentID,
        departmentName: found.DepartmentName,
        sectionId: found.SectionID,
        section: found.SectionName,
      };
      req.session.save((saveErr) => {
        if (saveErr) return next(saveErr);
        res.redirect(homeFor(found.Role));
      });
    });
  } catch (err) {
    next(err);
  }
});

router.post('/logout', (req, res, next) => {
  req.session.destroy((err) => {
    if (err) return next(err);
    res.clearCookie('connect.sid');
    res.redirect('/login');
  });
});

module.exports = router;
