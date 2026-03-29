<?php
/**
 * Support India Initiative — Server Storage API
 * Place this file in the same folder as index.html on your server.
 * It stores all donations in donations.json (same folder).
 * No database needed. Works on any PHP hosting.
 */

// Allow cross-origin if needed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$FILE = __DIR__ . '/donations.json';

// ── Initialize file if it doesn't exist ──────────────────────────────────────
if (!file_exists($FILE)) {
    file_put_contents($FILE, json_encode(['donations' => [], 'updated' => time()], JSON_PRETTY_PRINT));
}

// ── GET — return all donations ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = json_decode(file_get_contents($FILE), true);
    if (!$data || !isset($data['donations'])) {
        $data = ['donations' => [], 'updated' => time()];
    }
    echo json_encode($data);
    exit;
}

// ── POST — save a new donation ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['amount'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Read existing
    $data = json_decode(file_get_contents($FILE), true);
    if (!$data || !isset($data['donations'])) {
        $data = ['donations' => [], 'updated' => time()];
    }

    // Build donation entry
    $entry = [
        'id'       => 'd' . time() . rand(100, 999),
        'name'     => substr(strip_tags($input['name']), 0, 60),
        'amount'   => floatval($input['amount']),
        'camp'     => isset($input['camp']) ? substr($input['camp'], 0, 30) : 'house',
        'state'    => isset($input['state']) ? substr(strip_tags($input['state']), 0, 40) : 'India',
        'city'     => isset($input['city']) ? substr(strip_tags($input['city']), 0, 40) : '',
        'time'     => time() * 1000, // JS timestamp (ms)
    ];

    // Prepend newest first, cap at 300
    array_unshift($data['donations'], $entry);
    $data['donations'] = array_slice($data['donations'], 0, 300);
    $data['updated'] = time();

    // Write back with file lock
    $fp = fopen($FILE, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    echo json_encode(['ok' => true, 'entry' => $entry]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

