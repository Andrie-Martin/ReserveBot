require('dotenv').config();
const express = require('express');
const app = express();
app.use(express.json());

// Verification endpoint — Meta calls this once to confirm you control the URL
app.get('/webhook', (req, res) => {
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

// Actual message-receiving endpoint
app.post('/webhook', (req, res) => {
  const body = req.body;
  console.log(JSON.stringify(body, null, 2)); // just log for now, so you can see events arrive

  if (body.object === 'page') {
    res.sendStatus(200);
  } else {
    res.sendStatus(404);
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Listening on port ${PORT}`));