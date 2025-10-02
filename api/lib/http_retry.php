<?php
// HTTP retry wrapper для Airtable API

class HttpException extends RuntimeException {
    public int $status;
    public array $headers;
    
    public function __construct(int $status, string $msg, array $headers = []) {
        parent::__construct($msg, $status);
        $this->status = $status;
        $this->headers = $headers;
    }
}

function http_json(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    $hdrs = [];
    foreach ($headers as $k => $v) {
        $hdrs[] = $k . ': ' . $v;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER    => $hdrs,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER        => true,
        CURLOPT_TIMEOUT       => 30
    ]);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new HttpException(0, curl_error($ch));
    }
    
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    [$rawHeaders, $rawBody] = explode("\r\n\r\n", $resp, 2) + [1 => ''];
    
    $headersArr = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headersArr[trim($k)] = trim($v);
        }
    }
    
    $json = json_decode($rawBody, true);
    if ($status >= 400) {
        throw new HttpException($status, $rawBody, $headersArr);
    }
    
    return ['status' => $status, 'headers' => $headersArr, 'json' => $json, 'raw' => $rawBody];
}

function call_with_retry(callable $fn, int $max = 3): array {
    if (!defined('RETRY_ENABLED') || !RETRY_ENABLED) {
        return $fn();
    }
    
    $attempt = 0;
    $delay = 0.3;
    
    while (true) {
        $attempt++;
        try {
            return $fn();
        } catch (HttpException $e) {
            $s = $e->status;
            if ($s == 429 || $s >= 500 || $s == 0) {
                if ($attempt >= $max) {
                    throw $e;
                }
                
                $ra = (int)($e->headers['Retry-After'] ?? 0);
                $sleep = $ra > 0 ? $ra : $delay + (mt_rand(0, 120) / 1000);
                usleep((int)($sleep * 1_000_000));
                $delay *= 1.8;
                continue;
            }
            throw $e; // 4xx — не ретраим
        }
    }
}
?>
