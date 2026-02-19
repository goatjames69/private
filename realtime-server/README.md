# JAMES GAMEROOM – Real-time options

## Recommended: PHP Server-Sent Events (SSE) – no extra server

Real-time is **handled entirely in PHP** via **Server-Sent Events (SSE)**.

- **Endpoint:** `api/realtime_stream.php`
- **Frontend:** Uses `EventSource` in `assets/js/realtime.js` (no WebSocket, no Node).
- **Auth:** Session cookie; no separate auth step.
- **No Node.js or WebSocket server needed** – works with plain Apache/XAMPP.

PHP writes events to `json/realtime_queue.json`; the SSE script processes the queue into `json/realtime_event_log.json` and streams only events that match the connected user/admin. No extra process to run.

---

## Optional: Node.js WebSocket server

If you prefer WebSockets (e.g. for a separate real-time service), you can run the Node server:

```bash
cd realtime-server
npm install
npm start
```

Then switch the frontend back to WebSocket by using a build or a separate `realtime-ws.js` that uses `WebSocket` instead of `EventSource`. **By default the app uses SSE (PHP only).**
