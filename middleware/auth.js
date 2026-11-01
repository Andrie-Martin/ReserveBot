// Who may open which pages. Checked on the server for every request,
// so hiding a link is never the only protection.

const HOME = { rep: '/rep', lab_staff: '/staff', admin: '/admin' };

// Where each role lands after logging in
function homeFor(role) {
  return HOME[role] || '/login';
}

// Any logged-in user
function requireLogin(req, res, next) {
  if (!req.session.user) return res.redirect('/login');
  next();
}

// Only the listed roles, e.g. requireRole('lab_staff', 'admin')
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.session.user) return res.redirect('/login');
    if (!roles.includes(req.session.user.role)) {
      return res.status(403).render('error', {
        title: 'Not allowed',
        message: "Your account can't open this page.",
      });
    }
    next();
  };
}

module.exports = { homeFor, requireLogin, requireRole };
