<?php
// Receive JSON data from the HTML form
$jsonData = file_get_contents('php://input');

if ($jsonData) {
    // 1. Create the 'json' folder if it doesn't exist
    if (!file_exists('json')) {
        mkdir('json', 0777, true);
    }

    $filePath = 'json/paygatetx.json';

    // 2. Load existing transactions or start a new list
    $currentData = [];
    if (file_exists($filePath)) {
        $currentData = json_decode(file_get_contents($filePath), true);
    }

    // 3. Append the new data with timestamp, id, and status
    $newData = json_decode($jsonData, true);
    $newData['server_time'] = date('Y-m-d H:i:s');
    $newData['id'] = 'pg_' . uniqid();
    $newData['status'] = 'pending';
    $currentData[] = $newData;

    // 4. Save back to the folder
    file_put_contents($filePath, json_encode($currentData, JSON_PRETTY_PRINT));
    
    echo json_encode(["status" => "success"]);
}
?>