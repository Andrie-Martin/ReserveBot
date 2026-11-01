require('dotenv').config();
const path = require('path');
const express = require('express');
const session = require('express-session');
const MySQLStore = require('express-mysql-session')(session);
const db = require('./db');
const { requireLogin, homeFor } = require('./middleware/auth');

if (!process.env.SESSION_SECRET) {
  console.error('Missing SESSION_SECRET in .env. Add a line like: SESSION_SECRET=any-long-random-text');
  process.exit(1);
}

const app = express();
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.static(path.join(__dirname, 'public')));  // style.css
app.use(express.urlencoded({ extended: false }));          // HTML form posts (login)
app.use(express.json());                                    // Messenger webhook

// Messenger webhook (kept for later). Mounted before sessions: it doesn't need one.
app.use('/webhook', require('./routes/webhook'));

// Logins are kept in a "sessions" table in MySQL, so restarting the app doesn't log people out
app.use(session({
  secret: process.env.SESSION_SECRET,
  store: new MySQLStore({}, db),
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, sameSite: 'lax', maxAge: 8 * 60 * 60 * 1000 }, // 8 hours
}));

// Every page can use `user` (null when nobody is logged in)
app.use((req, res, next) => {
  res.locals.user = req.session.user || null;
  next();
});

app.use(require('./routes/auth'));               // /login, /logout
app.use('/rep', require('./routes/rep'));        // block reps
app.use('/staff', require('./routes/staff'));    // lab staff (admins too)
app.use('/admin', require('./routes/admin'));    // admins only

app.get('/', requireLogin, (req, res) => res.redirect(homeFor(req.session.user.role)));

// Database check from Phase 2
app.get('/test-db', async (req, res) => {
  try {
    const [timeRows] = await db.query('SELECT NOW() AS now');
    const [rooms] = await db.query(
      'SELECT RoomID, Name, Building, Capacity FROM Room WHERE IsActive = 1 ORDER BY Name'
    );
    res.json({ databaseTime: timeRows[0].now, rooms });
  } catch (err) {
    console.error(err);
    res.status(500).send('Database error: ' + err.message);
  }
});

// Page not found
app.use((req, res) => {
  res.status(404).render('error', { title: 'Page not found', message: "That page doesn't exist." });
});

// Anything that crashed in a route ends up here
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).render('error', {
    title: 'Something went wrong',
    message: 'Please try again. If it keeps happening, check the terminal running the app.',
  });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Listening on port ${PORT}`));
