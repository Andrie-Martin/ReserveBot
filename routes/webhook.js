// Messenger webhook from the first setup (kept for a possible Messenger version later)
const express = require('express');

const router = express.Router();

// Verification endpoint: Meta calls this once to confirm you control the URL
router.get('/', (req, res) => {
  const VERIFY_TOKEN = process.env.VERIFY_TOKEN;
  const mode = req.query['hub.mode'];
  const token = req.query['hub.verify_token'];
  const challenge = req.query['hub.challenge'];

  if (mode === 'subscribe' && token === VERIFY_TOKEN) {
    console.log('Webhook verified!');
    return res.status(200).send(challenge);
  }
  return res.sendStatus(403);
});

// Message-receiving endpoint
router.post('/', (req, res) => {
  const body = req.body;
  console.log(JSON.stringify(body, null, 2));

  if (body.object === 'page') {
    res.sendStatus(200);
  } else {
    res.sendStatus(404);
  }
});

module.exports = router;
