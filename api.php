<?php
// Jangan pernah biarkan notice/deprecation PHP merusak body JSON.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// CORS untuk local dev & container
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Provider, X-Provider-Api-Key, X-User-Id, X-User-Email');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Action dari query string (harus didefinisikan sebelum file yang memakai $action diproses)
$action = $_GET['action'] ?? 'chat';

// Load middleware classes
require_once __DIR__ . '/middleware/BudgetEnforcement.php';
require_once __DIR__ . '/middleware/IdentityPropagation.php';
require_once __DIR__ . '/api/CostTracking.php';

// Initialize middleware
$budgetEnforcement = new BudgetEnforcement();
$identityPropagation = new IdentityPropagation();
$costTracking = new CostTracking();

require_once __DIR__ . '/api/endpoints.php';

// 1. Fungsi Resolusi Secret yang Aman (Docker Secret -> Environment -> .env)
function getSecret($name) {
    // A. Cek Docker Secret mount path (/run/secrets/...)
    $secretPath = '/run/secrets/' . strtolower($name);
    if (file_exists($secretPath) && is_readable($secretPath)) {
        return trim(file_get_contents($secretPath));
    }

    // B. Cek System Environment Variable (Docker Compose / Cloud Run / ECS)
    $val = getenv($name);
    if ($val !== false && trim($val) !== '') {
        return trim($val);
    }

    if (!empty($_ENV[$name])) {
        return trim($_ENV[$name]);
    }

    if (!empty($_SERVER[$name])) {
        return trim($_SERVER[$name]);
    }

    // C. Cek file .env lokal jika ada
    static $localEnv = null;
    if ($localEnv === null) {
        $localEnv = [];
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    list($k, $v) = explode('=', $line, 2);
                    $localEnv[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }
    }

    return $localEnv[$name] ?? '';
}

/**
 * Cek apakah nilai secret benar-benar terisi (bukan placeholder).
 */
function isConfigured($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return false;
    }

    $lower = strtolower($value);
    $placeholders = [
        'your_', 'your-', 'replace_with', 'replace-', 'akia_your',
        'changeme', 'change_me', 'example', 'xxx', 'todo'
    ];

    foreach ($placeholders as $placeholder) {
        if (str_starts_with($lower, $placeholder)) {
            return false;
        }
    }

    return true;
}

// Status kredensial yang terdeteksi
if ($action === 'status') {
    echo json_encode([
        'status' => 'ok',
        'providers' => [
            'groq' => isConfigured(getSecret('GROQ_API_KEY')),
            'openrouter' => isConfigured(getSecret('OPENROUTER_API_KEY')),
            'gemini' => isConfigured(getSecret('GEMINI_API_KEY')),
            'aws' => isConfigured(getSecret('AWS_ACCESS_KEY_ID'))
        ],
        'is_docker' => file_exists('/.dockerenv'),
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Cek Saldo OpenRouter (jika key tersedia)
if ($action === 'openrouter_balance') {
    $apiKey = trim($_SERVER['HTTP_X_PROVIDER_API_KEY'] ?? '');
    if ($apiKey === '') {
        $apiKey = getSecret('OPENROUTER_API_KEY');
    }
    if (!isConfigured($apiKey)) {
        echo json_encode(['error' => 'OPENROUTER_API_KEY belum tersedia']);
        exit;
    }

    $ch = curl_init('https://openrouter.ai/api/v1/auth/key');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $json = json_decode($res, true);
    if ($httpCode === 200 && isset($json['data'])) {
        echo json_encode([
            'status' => 'ok',
            'data' => $json['data']
        ]);
    } else {
        echo json_encode(['error' => 'Gagal membaca saldo OpenRouter', 'raw' => $json]);
    }
    exit;
}

// Handler Chat Multi-Provider (Groq, OpenRouter, Google Gemini/GCP)
if ($action === 'chat') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $provider = $data['provider'] ?? ($_SERVER['HTTP_X_PROVIDER'] ?? 'groq');
    $messages = $data['messages'] ?? [];
    $model = $data['model'] ?? '';
    $temperature = floatval($data['temperature'] ?? 0.6);
    $maxTokens = intval($data['max_completion_tokens'] ?? 2048);

    if (empty($messages)) {
        http_response_code(400);
        echo json_encode(['error' => 'Pesan tidak boleh kosong']);
        exit;
    }

    $clientKey = $_SERVER['HTTP_X_PROVIDER_API_KEY'] ?? ($data['api_key'] ?? '');

    $targetUrl = '';
    $headers = ['Content-Type: application/json'];
    $apiKey = '';

    if ($provider === 'groq') {
        $apiKey = !empty($clientKey) ? $clientKey : getSecret('GROQ_API_KEY');
        if (!isConfigured($apiKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'GROQ_API_KEY belum diisi di environment / UI']);
            exit;
        }
        $targetUrl = 'https://api.groq.com/openai/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        if (empty($model)) $model = 'qwen/qwen3.8-27b';

    } elseif ($provider === 'openrouter') {
        $apiKey = !empty($clientKey) ? $clientKey : getSecret('OPENROUTER_API_KEY');
        if (!isConfigured($apiKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'OPENROUTER_API_KEY belum diisi di environment / UI']);
            exit;
        }
        $targetUrl = 'https://openrouter.ai/api/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'HTTP-Referer: http://localhost:8081';
        $headers[] = 'X-Title: AI Billing Hub';
        if (empty($model)) $model = 'qwen/qwen-2.5-coder-32b-instruct';

    } elseif ($provider === 'gemini') {
        $apiKey = !empty($clientKey) ? $clientKey : getSecret('GEMINI_API_KEY');
        if (!isConfigured($apiKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'GEMINI_API_KEY belum diisi di environment / UI']);
            exit;
        }
        // Endpoint OpenAI-compatible dari Google Gemini v1beta
        $targetUrl = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        if (empty($model)) $model = 'gemini-2.5-flash';

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Provider tidak didukung: ' . htmlspecialchars($provider)]);
        exit;
    }

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ];

    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 70,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = round(microtime(true) - $startTime, 3);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => 'cURL Error: ' . $curlError, 'provider' => $provider]);
        exit;
    }

    // Validate response is not empty
    if (empty($response)) {
        http_response_code(500);
        echo json_encode(['error' => 'Empty response from API', 'provider' => $provider, 'http_code' => $httpCode]);
        exit;
    }

    // Log response for debugging
    error_log("API Response ({$provider}): HTTP {$httpCode}, Length: " . strlen($response));

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Invalid JSON response from API',
            'json_error' => json_last_error_msg(),
            'provider' => $provider,
            'http_code' => $httpCode,
            'raw_response' => substr($response, 0, 500) // First 500 chars for debugging
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($httpCode >= 400) {
        http_response_code($httpCode);
        $errMsg = $result['error']['message'] ?? ($result['error'] ?? 'API error with HTTP ' . $httpCode);
        if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        echo json_encode(['error' => $errMsg, 'raw' => $result, 'provider' => $provider]);
        exit;
    }

    $content = $result['choices'][0]['message']['content'] ?? '';
    $usage = $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

    echo json_encode([
        'status' => 'success',
        'provider' => $provider,
        'model' => $result['model'] ?? $model,
        'content' => $content,
        'usage' => $usage,
        'duration_seconds' => $duration
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Action tidak dikenal']);
