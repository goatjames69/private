/**
 * WebSocket server for JAMES GAMEROOM real-time updates.
 * Polls PHP-written queue file and broadcasts events to connected clients.
 * Run: node server.js   (from realtime-server folder)
 */
const WebSocket = require('ws');
const fs = require('fs');
const path = require('path');

const PORT = process.env.WS_PORT || 3080;
const QUEUE_FILE = path.resolve(__dirname, '..', 'json', 'realtime_queue.json');
const POLL_MS = 150;

const wss = new WebSocket.Server({ port: PORT });

// Map: clientId -> { ws, user_id, role }
const clients = new Map();
let clientIdNext = 1;

function broadcast(data, filter) {
  const msg = JSON.stringify(data);
  clients.forEach((info, id) => {
    if (filter && !filter(info)) return;
    if (info.ws.readyState === WebSocket.OPEN) {
      try { info.ws.send(msg); } catch (e) { /* ignore */ }
    }
  });
}

function broadcastToUser(userId, data) {
  broadcast(data, (info) => info.user_id === userId);
}

function broadcastToAdmins(data) {
  broadcast(data, (info) => info.role === 'admin' || info.role === 'staff');
}

function broadcastToAll(data) {
  broadcast(data);
}

function processQueue() {
  let queue = [];
  try {
    if (fs.existsSync(QUEUE_FILE)) {
      const raw = fs.readFileSync(QUEUE_FILE, 'utf8');
      queue = JSON.parse(raw || '[]');
    }
  } catch (e) {
    return;
  }
  if (queue.length === 0) return;

  queue.forEach((item) => {
    const event = item.event || '';
    const payload = item.payload || {};
    switch (event) {
      case 'user_balance_updated':
        broadcastToUser(payload.user_id, { type: 'user_balance_updated', payload });
        broadcastToAdmins({ type: 'user_balance_updated', payload });
        break;
      case 'user_withdrawal_requested':
        broadcastToAdmins({ type: 'user_withdrawal_requested', payload });
        broadcastToUser(payload.user_id, { type: 'user_withdrawal_requested', payload });
        break;
      case 'support_message':
        broadcastToUser(payload.user_id, { type: 'support_message', payload });
        broadcastToAdmins({ type: 'support_message', payload });
        break;
      case 'support_chat_status':
        broadcastToUser(payload.user_id, { type: 'support_chat_status', payload });
        broadcastToAdmins({ type: 'support_chat_status', payload });
        break;
      case 'admin_user_updated':
        broadcastToUser(payload.user_id, { type: 'admin_user_updated', payload });
        broadcastToAdmins({ type: 'admin_user_updated', payload });
        break;
      case 'notification':
        if (payload.user_id) broadcastToUser(payload.user_id, { type: 'notification', payload });
        else if (payload.admin) broadcastToAdmins({ type: 'notification', payload });
        else broadcastToAll({ type: 'notification', payload });
        break;
      default:
        broadcastToAll({ type: event, payload });
    }
  });

  try {
    fs.writeFileSync(QUEUE_FILE, '[]', 'utf8');
  } catch (e) { /* ignore */ }
}

// Poll queue file (PHP writes here)
setInterval(processQueue, POLL_MS);

wss.on('connection', (ws, req) => {
  const id = clientIdNext++;
  const info = { ws, user_id: null, role: null };
  clients.set(id, info);

  ws.on('message', (raw) => {
    try {
      const data = JSON.parse(raw);
      if (data.type === 'auth') {
        info.user_id = data.user_id || null;
        info.role = data.role || null;
        ws.send(JSON.stringify({ type: 'auth_ok', payload: {} }));
      }
    } catch (e) { /* ignore */ }
  });

  ws.on('close', () => { clients.delete(id); });
  ws.on('error', () => { clients.delete(id); });
});

wss.on('listening', () => {
  console.log('JAMES GAMEROOM WebSocket server listening on port', PORT);
});
