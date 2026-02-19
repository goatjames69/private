<?php
/**
 * SSE stream DISABLED to avoid server load (one PHP worker per tab held open).
 * Use api/realtime_poll.php instead – lightweight polling, no long connections.
 */
header('Content-Type: application/json');
http_response_code(503);
echo json_encode([
    'error' => 'SSE disabled',
    'message' => 'Use polling: GET /api/realtime_poll.php?last_id=0'
]);
exit;
