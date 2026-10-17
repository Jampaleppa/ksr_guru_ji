<?php
// ============================================================
// AI Enterprise — API Gateway
// Handles all AJAX/REST requests with auth + rate limiting
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Bootstrap ────────────────────────────────────────────────
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/AI/ChatEngine.php';
require_once __DIR__ . '/app/AI/SearchEngine.php';

use App\Core\Database;
use App\AI\ChatEngine;
use App\AI\SearchEngine;

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Simple Rate Limiter (file-based) ────────────────────────
function checkRateLimit(string $ip, int $maxRequests = 100, int $window = 60): bool
{
    $cacheDir = __DIR__ . '/storage/cache/';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

    $file = $cacheDir . 'rate_' . md5($ip) . '.json';
    $now = time();

    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }

    // Clean old entries
    $data = array_filter($data, fn($t) => ($now - $t) < $window);
    $data[] = $now;

    file_put_contents($file, json_encode($data));

    return count($data) <= $maxRequests;
}

// ── Request Logging ─────────────────────────────────────────
function logApiRequest(string $endpoint, int $responseCode, float $startTime): void
{
    $elapsed = round((microtime(true) - $startTime) * 1000);
    try {
        $db = Database::getInstance();
        $db->insert(
            "INSERT INTO ai_api_logs (method, endpoint, response_code, response_ms, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $_SERVER['REQUEST_METHOD'],
                $endpoint,
                $responseCode,
                $elapsed,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    } catch (\Exception $e) {
        // Silently fail
    }
}

// ── JSON Response Helper ────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Main Router ─────────────────────────────────────────────
$startTime = microtime(true);
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Rate limit check
if (!checkRateLimit($ip)) {
    logApiRequest('rate_limited', 429, $startTime);
    jsonResponse(['success' => false, 'error' => 'Rate limit exceeded. Please wait.'], 429);
}

// Get the action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    jsonResponse(['success' => false, 'error' => 'No action specified', 'available_actions' => [
        'chat'           => 'POST — Send a message to AI (params: message, category?, session_token?, session_uuid?, language?, model?, image?)',
        'chat_history'   => 'GET  — Get chat history (params: session_token, session_uuid?)',
        'list_sessions'  => 'GET  — List chat sessions (params: session_token)',
        'new_session'    => 'POST — Create a new chat session (params: session_token, category?)',
        'delete_session' => 'POST — Soft-delete a chat session (params: session_token, session_uuid)',
        'rename_session' => 'POST — Rename a chat session (params: session_token, session_uuid, title)',
        'search'         => 'GET  — Search (params: q, type?, category?, limit?)',
        'autocomplete'   => 'GET  — Autocomplete suggestions (params: q, limit?)',
        'trending'       => 'GET  — Get trending searches (params: limit?)',
        'models'         => 'GET  — List available AI models',
        'health'         => 'GET  — API health check',
        'init_db'        => 'POST — Initialize database tables',
        'analytics'      => 'GET  — Get platform analytics',
        'feedback'       => 'POST — Record message feedback (params: message_id, feedback)',
    ]], 400);
}

try {
    switch ($action) {
        // ── AI Chat ──────────────────────────────────────────
        case 'chat':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            // Get input from POST or JSON body
            $input = json_decode(file_get_contents('php://input'), true);
            $message = $input['message'] ?? $_POST['message'] ?? '';
            $category = $input['category'] ?? $_POST['category'] ?? null;
            $token = $input['session_token'] ?? $_POST['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? null;
            $sessionUuid = $input['session_uuid'] ?? $_POST['session_uuid'] ?? null;
            $language = $input['language'] ?? $_POST['language'] ?? 'en';
            $model = $input['model'] ?? $_POST['model'] ?? null;
            $image = $input['image'] ?? $_POST['image'] ?? null; // Contains ['data' => ..., 'mime' => ...]

            if (empty($message) && empty($image)) {
                jsonResponse(['success' => false, 'error' => 'Message or image is required'], 400);
            }

            $chat = new ChatEngine($token);
            $response = $chat->chat($message, $category, null, $sessionUuid, $language, $model, $image);

            logApiRequest('chat', 200, $startTime);
            jsonResponse($response);
            break;

        // ── Chat History ─────────────────────────────────────
        case 'chat_history':
            $token = $_GET['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
            $sessionUuid = $_GET['session_uuid'] ?? null;
            if (empty($token)) {
                jsonResponse(['success' => false, 'error' => 'session_token required'], 400);
            }

            $chat = new ChatEngine($token);
            $history = $chat->getHistory($token, $sessionUuid);

            jsonResponse(['success' => true, 'messages' => $history, 'count' => count($history)]);
            break;

        // ── List Chat Sessions ───────────────────────────────
        case 'list_sessions':
            $token = $_GET['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
            if (empty($token)) {
                jsonResponse(['success' => false, 'error' => 'session_token required'], 400);
            }

            try {
                $chat = new ChatEngine($token);
                $sessions = $chat->listSessions($token, (int) ($_GET['limit'] ?? 30));
                jsonResponse(['success' => true, 'sessions' => $sessions, 'count' => count($sessions)]);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Rename Chat Session ──────────────────────────────
        case 'rename_session':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $token = $input['session_token'] ?? $_POST['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
            $sessionUuid = $input['session_uuid'] ?? $_POST['session_uuid'] ?? '';
            $title = trim($input['title'] ?? $_POST['title'] ?? '');

            if (empty($token) || empty($sessionUuid) || $title === '') {
                jsonResponse(['success' => false, 'error' => 'session_token, session_uuid and title are required'], 400);
            }

            try {
                $chat = new ChatEngine($token);
                if ($chat->renameSession($token, $sessionUuid, $title)) {
                    jsonResponse(['success' => true, 'message' => 'Session renamed']);
                }
                jsonResponse(['success' => false, 'error' => 'Session not found or unchanged'], 404);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Create New Chat Session ──────────────────────────
        case 'new_session':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['session_token'] ?? $_POST['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
            $category = $input['category'] ?? $_POST['category'] ?? 'general';

            if (empty($token)) {
                jsonResponse(['success' => false, 'error' => 'session_token required'], 400);
            }

            try {
                $uuid = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );

                $db = Database::getInstance();
                $db->insert(
                    "INSERT INTO ai_chat_sessions (uuid, session_token, category, title, status) VALUES (?, ?, ?, ?, 'active')",
                    [$uuid, $token, $category, "New Chat (" . ucfirst($category) . ")"]
                );

                jsonResponse(['success' => true, 'session_uuid' => $uuid]);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Delete Chat Session ──────────────────────────────
        case 'delete_session':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['session_token'] ?? $_POST['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
            $sessionUuid = $input['session_uuid'] ?? $_POST['session_uuid'] ?? '';

            if (empty($token) || empty($sessionUuid)) {
                jsonResponse(['success' => false, 'error' => 'session_token and session_uuid are required'], 400);
            }

            try {
                $db = Database::getInstance();
                $db->execute(
                    "UPDATE ai_chat_sessions SET status = 'deleted' WHERE uuid = ? AND session_token = ?",
                    [$sessionUuid, $token]
                );
                jsonResponse(['success' => true, 'message' => 'Session deleted']);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Search ───────────────────────────────────────────
        case 'search':
            $query = $_GET['q'] ?? '';
            $type = $_GET['type'] ?? 'text';
            $category = $_GET['category'] ?? '';
            $limit = min((int) ($_GET['limit'] ?? 20), 50);

            if (empty($query)) {
                jsonResponse(['success' => false, 'error' => 'Query (q) is required'], 400);
            }

            $search = new SearchEngine();
            $results = $search->search($query, $type, $category, $limit);

            logApiRequest('search', 200, $startTime);
            jsonResponse($results);
            break;

        // ── Autocomplete ─────────────────────────────────────
        case 'autocomplete':
            $query = $_GET['q'] ?? '';
            $limit = min((int) ($_GET['limit'] ?? 8), 20);

            $search = new SearchEngine();
            $suggestions = $search->autocomplete($query, $limit);

            jsonResponse(['success' => true, 'suggestions' => $suggestions]);
            break;

        // ── Trending Searches ────────────────────────────────
        case 'trending':
            $limit = min((int) ($_GET['limit'] ?? 10), 30);

            $search = new SearchEngine();
            $trending = $search->getTrending($limit);

            jsonResponse(['success' => true, 'trending' => $trending]);
            break;

        // ── Health Check ─────────────────────────────────────
        case 'health':
            $dbOk = false;
            try {
                $db = Database::getInstance();
                $db->fetchOne("SELECT 1 as ok");
                $dbOk = true;
            } catch (\Exception $e) {
                // DB not available
            }

            $aiConfig = (require __DIR__ . '/config/app.php')['ai'] ?? [];

            jsonResponse([
                'success'   => true,
                'status'    => 'operational',
                'version'   => '1.2.0',
                'php'       => PHP_VERSION,
                'database'  => $dbOk ? 'connected' : 'disconnected',
                'ai'        => [
                    'gemini_configured' => !empty($aiConfig['gemini_api_key']),
                    'openai_configured' => !empty($aiConfig['openai_api_key']),
                    'default_model'     => $aiConfig['gemini_model'] ?? null,
                    'mode'              => !empty($aiConfig['gemini_api_key']) ? 'live-ai' : 'builtin-fallback',
                ],
                'timestamp' => date('c'),
                'uptime'    => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . 's',
            ]);
            break;

        // ── List AI Models ───────────────────────────────────
        case 'models':
            $aiConfig = (require __DIR__ . '/config/app.php')['ai'] ?? [];
            $models = [];
            foreach (($aiConfig['gemini_models'] ?? []) as $id => $label) {
                $models[] = ['id' => $id, 'label' => $label, 'provider' => 'gemini'];
            }
            jsonResponse([
                'success' => true,
                'default' => $aiConfig['gemini_model'] ?? null,
                'configured' => !empty($aiConfig['gemini_api_key']),
                'models'  => $models,
            ]);
            break;

        // ── Initialize Database ──────────────────────────────
        case 'init_db':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            try {
                $db = Database::getInstance();
                $schema = file_get_contents(__DIR__ . '/database/schema.sql');

                // Split by semicolons and execute each statement
                $statements = array_filter(
                    array_map('trim', explode(';', $schema)),
                    fn($s) => !empty($s) && strlen($s) > 5
                );

                $executed = 0;
                $errors = [];

                foreach ($statements as $sql) {
                    try {
                        $db->pdo()->exec($sql);
                        $executed++;
                    } catch (\Exception $e) {
                        // Skip duplicate errors for INSERT IGNORE etc.
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $errors[] = substr($e->getMessage(), 0, 100);
                        }
                        $executed++;
                    }
                }

                jsonResponse([
                    'success'  => true,
                    'message'  => "Database initialized! Executed {$executed} statements.",
                    'executed' => $executed,
                    'warnings' => $errors,
                ]);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Analytics Overview ───────────────────────────────
        case 'analytics':
            try {
                $db = Database::getInstance();

                $stats = [];

                // Total chat sessions
                try { $stats['chat_sessions'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ai_chat_sessions"); } catch (\Exception $e) { $stats['chat_sessions'] = 0; }
                // Total messages
                try { $stats['total_messages'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ai_chat_messages"); } catch (\Exception $e) { $stats['total_messages'] = 0; }
                // Total searches
                try { $stats['total_searches'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ai_search_history"); } catch (\Exception $e) { $stats['total_searches'] = 0; }
                // Total tickets
                try { $stats['total_tickets'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ai_support_tickets"); } catch (\Exception $e) { $stats['total_tickets'] = 0; }
                // Popular searches
                try {
                    $stats['popular_searches'] = $db->fetchAll(
                        "SELECT keyword, search_count FROM ai_search_keywords ORDER BY search_count DESC LIMIT 10"
                    );
                } catch (\Exception $e) { $stats['popular_searches'] = []; }
                // Chat by category
                try {
                    $stats['chat_by_category'] = $db->fetchAll(
                        "SELECT category, COUNT(*) as count FROM ai_chat_sessions GROUP BY category"
                    );
                } catch (\Exception $e) { $stats['chat_by_category'] = []; }
                // Recent activity
                try {
                    $stats['recent_chats'] = $db->fetchAll(
                        "SELECT uuid, category, title, message_count, created_at FROM ai_chat_sessions ORDER BY updated_at DESC LIMIT 5"
                    );
                } catch (\Exception $e) { $stats['recent_chats'] = []; }

                jsonResponse(['success' => true, 'analytics' => $stats]);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            break;

        // ── Feedback ─────────────────────────────────────────
        case 'feedback':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $messageId = (int) ($input['message_id'] ?? 0);
            $feedback = $input['feedback'] ?? '';
            $token = $input['session_token'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';

            if ($messageId && in_array($feedback, ['positive', 'negative'])) {
                try {
                    $db = Database::getInstance();

                    // Only allow feedback on messages that belong to this browser's sessions
                    $owns = $db->fetchOne(
                        "SELECT m.id FROM ai_chat_messages m
                         JOIN ai_chat_sessions s ON m.session_id = s.id
                         WHERE m.id = ? AND s.session_token = ?",
                        [$messageId, $token]
                    );

                    if (!$owns) {
                        jsonResponse(['success' => false, 'error' => 'Message not found for this session'], 404);
                    }

                    $db->execute(
                        "UPDATE ai_chat_messages SET feedback = ? WHERE id = ?",
                        [$feedback, $messageId]
                    );
                    jsonResponse(['success' => true, 'message' => 'Feedback recorded']);
                } catch (\Exception $e) {
                    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
                }
            } else {
                jsonResponse(['success' => false, 'error' => 'Invalid feedback'], 400);
            }
            break;

        default:
            jsonResponse(['success' => false, 'error' => "Unknown action: {$action}"], 404);
    }
} catch (\Exception $e) {
    logApiRequest($action, 500, $startTime);
    jsonResponse([
        'success' => false,
        'error'   => 'Internal server error',
        'details' => $e->getMessage(),
    ], 500);
}
