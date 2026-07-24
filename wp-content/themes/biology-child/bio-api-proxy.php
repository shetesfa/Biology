<?php
/**
 * BioExplorer — Anthropic API Proxy
 * Placed in the theme folder. Called from the AI Tutor page.
 * This avoids CORS issues on localhost/XAMPP.
 */

// Only allow POST requests
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Only allow logged-in WordPress users
define('ABSPATH', dirname(__FILE__) . '/../../../');
$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if ( file_exists($wp_load) ) {
    require_once $wp_load;
    if ( ! is_user_logged_in() ) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'You must be logged in.']]);
        exit;
    }
}

// Get request body
$body = file_get_contents('php://input');
if ( empty($body) ) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Empty request.']]);
    exit;
}

// ── PUT YOUR ANTHROPIC API KEY HERE ──────────────────────────
$api_key = 'YOUR_ANTHROPIC_API_KEY_HERE';
// ─────────────────────────────────────────────────────────────

// Forward to Anthropic
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error     = curl_error($ch);
curl_close($ch);

if ( $error ) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'cURL error: ' . $error]]);
    exit;
}

header('Content-Type: application/json');
http_response_code($http_code);
echo $response;
