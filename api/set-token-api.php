<?php
// api/set-token-api.php
// API endpoint for setting Airtable tokens via web interface

require_once __DIR__ . '/secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])){
  header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
  header('Vary: Origin');
} else {
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
  header('Access-Control-Allow-Origin: '.$scheme.'://'.$_SERVER['HTTP_HOST']);
}
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
  http_response_code(204);
  exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Check admin token
$adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
$expectedToken = getenv('ADMIN_TOKEN') ?: '';

if (!$adminToken || !hash_equals($expectedToken, $adminToken)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Forbidden - Invalid admin token']);
  exit;
}

try {
  $body = json_decode(file_get_contents('php://input') ?: '[]', true);
  
  if (!isset($body['token']) || empty($body['token'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Token is required']);
    exit;
  }
  
  $token = $body['token'];
  $slot = $body['slot'] ?? 'current';
  
  // Validate token format
  if (!preg_match('~^pat\\.[A-Za-z0-9_\\-]{20,}$~', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid PAT format. Must start with "pat." and be at least 20 characters.']);
    exit;
  }
  
  // Validate slot
  if (!in_array($slot, ['current', 'next'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid slot. Must be "current" or "next".']);
    exit;
  }
  
  // Test token with Airtable
  $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 10,
  ]);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode !== 200) {
    http_response_code(400);
    echo json_encode([
      'ok' => false, 
      'error' => 'Token validation failed', 
      'details' => "Airtable returned HTTP $httpCode"
    ]);
    exit;
  }
  
  // Store token
  store_airtable_token($slot, $token);
  
  // Log the action
  error_log("[SET-TOKEN-API] Token set in slot '$slot' successfully");
  
  echo json_encode([
    'ok' => true, 
    'message' => "Token successfully set in '$slot' slot",
    'slot' => $slot
  ]);
  
} catch (Throwable $e) {
  error_log("[SET-TOKEN-API] Error: " . $e->getMessage());
  
  http_response_code(500);
  echo json_encode([
    'ok' => false, 
    'error' => 'Internal server error',
    'details' => $e->getMessage()
  ]);
}
?>
