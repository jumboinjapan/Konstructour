<?php
header('Content-Type: application/json; charset=utf-8');

function mask($s){ if($s==='') return ''; return substr($s,0,3).'...'.substr($s,-6); }

$env = trim(getenv('AIRTABLE_API_KEY') ?: '');
$filePath = __DIR__ . '/../.secrets/airtable_pat.txt';
$fileOk = is_readable($filePath);
$fileRaw = $fileOk ? file_get_contents($filePath) : '';
$file = $fileRaw===false ? '' : trim($fileRaw);

echo json_encode([
  'env_present' => $env !== '',
  'env_preview' => $env !== '' ? mask($env) : null,
  'file_path'   => realpath($filePath) ?: $filePath,
  'file_exists' => file_exists($filePath),
  'file_readable' => $fileOk,
  'file_size'   => ($fileOk && $fileRaw!==false) ? strlen($fileRaw) : 0,
  'file_preview'=> $file !== '' ? mask($file) : null,
], JSON_UNESCAPED_UNICODE);
?>
