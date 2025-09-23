<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$baseId = $_GET['base'] ?? 'apppwhjFN82N9zNqm';
$tableId = $_GET['table'] ?? 'tblbSajWkzI8X7M4U';

// Load config if present
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }

// Resolve PAT from multiple locations
$pat = $_GET['pat'] ?? '';
if (!$pat) { $pat = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY') ?: ''; }
if (!$pat) { $pat = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? '')); }
if (!$pat) { $pat = $cfg['airtable_registry']['api_key'] ?? ($cfg['airtable_registry']['token'] ?? ''); }

if (!$pat) {
  out(['ok'=>false,'error'=>'PAT not configured (try ?pat=...)','hint'=>'Set AIRTABLE_PAT env or store in api/config.php'], 400);
}

$url = 'https://api.airtable.com/v0/'.rawurlencode($baseId).'/'.rawurlencode($tableId).'?maxRecords=3';
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [ 'Authorization: Bearer '.$pat ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

if ($err) { out(['ok'=>false,'http_code'=>0,'error'=>$err], 502); }
$decoded = json_decode($resp, true);
if ($code>=200 && $code<300){ out(['ok'=>true,'http_code'=>$code,'records'=>$decoded['records'] ?? [], 'raw'=>$decoded]); }
$errMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Upstream error';
out(['ok'=>false,'http_code'=>$code,'error'=>$errMsg,'raw'=>$decoded], $code ?: 502);
