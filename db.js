// db.js - one shared connection pool to the MySQL database.
// Every route that needs the database does: const db = require('./db');
const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST,
  port: Number(process.env.DB_PORT) || 3306,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  dateStrings: true, // return dates as plain text like '2026-11-03 09:00:00'
});

// Keep NOW() and CURDATE() in Philippine time, even on a server in another time zone
pool.on('connection', (conn) => {
  conn.query("SET time_zone = '+08:00'");
});

module.exports = pool;