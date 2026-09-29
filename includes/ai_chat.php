<?php
// ============================================================
// G&G Support Portal — includes/ai_chat.php
// Secure PHP proxy between the browser and FastAPI :8000
// ============================================================

require_once __DIR__ . '/auth_guard.php';

guard_require_login();

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// CSRF check — read token from POST body
$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(200); // return 200 so JS can read the JSON
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page and try again.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
define('AI_BASE', 'http://localhost:8000'); 
switch ($action) {

    // ── User asks a question ─────────────────────────────────
    case 'chat':
        $question = trim($_POST['question'] ?? '');
        if ($question === '') {
            echo json_encode(['success' => false, 'message' => 'Please type a question.']);
            exit;
        }
        if (mb_strlen($question) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Question too long (max 1000 characters).']);
            exit;
        }

        $rawHistory = $_POST['history'] ?? '[]';
        $history    = json_decode($rawHistory, true);
        if (!is_array($history)) $history = [];
        $history = array_slice($history, -6);

        $payload = json_encode(['question' => $question, 'history' => $history]);
        echo ai_post('/chat', $payload);
        break;

    // ── Admin: re-index solutions ─────────────────────────────
    case 'ingest':
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['admin', 'system_admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Admin access required.']);
            exit;
        }

        $solutions = $pdo->query("
            SELECT id, question, answer
            FROM solutions
            WHERE status = 'approved'
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($solutions)) {
            echo json_encode(['success' => false, 'message' => 'No approved solutions found to index.']);
            exit;
        }

        echo ai_post('/ingest', json_encode(['solutions' => $solutions]));
        break;

    // ── Admin: health check ───────────────────────────────────
    case 'health':
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['admin', 'system_admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Admin access required.']);
            exit;
        }
        echo ai_get('/health');
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

// ── cURL helpers ──────────────────────────────────────────────

function ai_post(string $path, string $jsonBody): string {
    $ch = curl_init(AI_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $body === false) {
        return json_encode([
            'success' => false,
            'message' => 'AI service is unreachable. Make sure the Python server is running (py -3.11 main.py).',
        ]);
    }
    return $body;
}

function ai_get(string $path): string {
    $ch = curl_init(AI_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: json_encode(['status' => 'unreachable']);
}
