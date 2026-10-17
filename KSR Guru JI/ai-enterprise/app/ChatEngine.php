<?php
// ============================================================
// AI Chat Engine — Intelligent Problem-Solving AI
// Handles: Student, Office, Programming, Business, General
// ============================================================

namespace App\AI;

use App\Core\Database;

class ChatEngine
{
    private Database $db;
    private array $config;
    private string $sessionToken;

    // Built-in AI knowledge categories
    private array $categories = [
        'student'  => ['exam', 'study', 'homework', 'college', 'school', 'career', 'scholarship', 'marks', 'grade', 'university', 'course', 'teacher', 'class', 'project', 'assignment', 'research', 'thesis', 'internship', 'campus', 'education', 'learn', 'book', 'test', 'quiz', 'lecture', 'degree', 'diploma', 'certificate', 'skill', 'training', 'competitive', 'JEE', 'NEET', 'UPSC', 'CAT', 'GATE', 'GRE', 'IELTS', 'TOEFL'],
        'office'   => ['work', 'office', 'job', 'salary', 'HR', 'meeting', 'email', 'tax', 'legal', 'contract', 'company', 'employee', 'resume', 'interview', 'promotion', 'transfer', 'leave', 'policy', 'deadline', 'report', 'presentation', 'client', 'customer', 'complaint', 'invoice', 'payment', 'vendor', 'official', 'government'],
        'programming'=> ['code', 'bug', 'error', 'programming', 'database', 'developer', 'python', 'javascript', 'php', 'html', 'css', 'java', 'c++', 'git', 'api', 'syntax', 'framework', 'frontend', 'backend', 'fullstack', 'react', 'node', 'django', 'laravel', 'sql', 'nosql', 'exception', 'stacktrace'],
        'business' => ['startup', 'marketing', 'sales', 'business', 'funding', 'product', 'company', 'strategy', 'finance', 'revenue', 'profit', 'customer', 'enterprise', 'equity', 'pitch', 'investor', 'growth', 'competitor', 'niche', 'monetize', 'pricing'],
        'customer_support' => ['ksr guru ji', 'app', 'problem', 'support', 'help', 'login', 'error', 'payment', 'delivery', 'refund', 'account', 'not working', 'issue', 'order', 'website'],
    ];

    // Intelligent response templates for common topics
    private array $smartResponses = [
        // Greetings
        'greeting' => [
            'patterns' => ['hello', 'hi', 'hey', 'namaste', 'good morning', 'good afternoon', 'good evening', 'howdy', 'greetings', 'ಯಾವುದಾದರೂ', 'ಹಲೋ', 'ನಮಸ್ಕಾರ'],
            'responses' => [
                "Hello! 👋 Welcome to KSR GURU JI AI Assistant. I'm here to help you with:\n\n🎓 **Student Problems** — Exam prep, career guidance, study tips\n💼 **Office/Work Problems** — Workplace issues, productivity, goals\n💻 **Programming Problems** — Debugging error, code logic, syntax\n📈 **Business Problems** — Growth strategy, sales, startup advice\n🌐 **General Q&A** — Life hacks, translation, queries\n\nHow can I help you today?",
                "Namaste! 🙏 I'm your AI assistant. Tell me what's on your mind — whether it's about studies, work, programming, business, or general life, I'm here to help!",
            ],
        ],
        // Thanks
        'thanks' => [
            'patterns' => ['thank', 'thanks', 'thank you', 'thx', 'appreciate', 'grateful', 'helpful', 'ಧನ್ಯವಾದ', 'ಧನ್ಯವಾದಗಳು'],
            'responses' => [
                "You're welcome! 😊 Happy I could help. Feel free to ask me anything else anytime!",
                "Glad I could assist! 🌟 Don't hesitate to reach out if you need more help.",
            ],
        ],
        // Goodbye
        'goodbye' => [
            'patterns' => ['bye', 'goodbye', 'see you', 'later', 'exit', 'quit', 'close', 'ಹೋಗಿ ಬರುತ್ತೇನೆ', 'ಹೋಗ್ತೀನಿ'],
            'responses' => [
                "Goodbye! 👋 Take care and remember — you've got this! Feel free to come back anytime.",
                "See you later! 🌟 Wishing you all the best. The AI is always here when you need it!",
            ],
        ],
    ];

    public function __construct(?string $sessionToken = null)
    {
        $this->db = Database::getInstance();
        $allConfig = require __DIR__ . '/../../config/app.php';
        $this->config = $allConfig['ai'];
        $this->sessionToken = $sessionToken ?? bin2hex(random_bytes(32));
    }

    /**
     * Process a user message and generate an AI response
     */
    public function chat(
        string $message, 
        ?string $category = null, 
        ?int $userId = null, 
        ?string $sessionUuid = null, 
        string $language = 'en', 
        ?string $model = null, 
        ?array $imageData = null
    ): array {
        $startTime = microtime(true);
        $message = trim($message);

        if (empty($message) && empty($imageData)) {
            return $this->formatResponse("Please type a message or upload an image to get help! 😊", 'general', 0);
        }

        // Detect category if not provided or set to general
        if ($category === null || $category === 'general') {
            $category = $this->detectCategory($message);
        }

        // Get or create chat session
        $sessionId = $this->getOrCreateSession($userId, $category, $sessionUuid);
        $session = $this->db->fetchOne("SELECT uuid, title FROM ai_chat_sessions WHERE id = ?", [$sessionId]);
        $activeUuid = $session ? $session['uuid'] : $sessionUuid;

        // Process and save local image if uploaded
        $imageUrl = null;
        if (!empty($imageData) && !empty($imageData['data']) && !empty($imageData['mime'])) {
            $imageUrl = $this->saveBase64Image($imageData['data'], $imageData['mime']);
        }

        // Save user message (with image url in metadata if present)
        $this->saveMessage($sessionId, 'user', $message, 'user', 1.0, $imageUrl);

        // Update session title on first message
        $this->updateSessionTitleIfNeeded($sessionId, $message);

        // Build conversation context from prior turns (multi-turn memory)
        $history = $this->getRecentTurns($sessionId);

        // Generate response
        $response = $this->generateResponse($message, $category, $language, $model, $imageData, $history);

        // Save AI response
        $this->saveMessage($sessionId, 'assistant', $response['content'], $response['model'] ?? 'builtin', $response['confidence'] ?? 0.85);

        // Update session message count
        $this->db->execute(
            "UPDATE ai_chat_sessions SET message_count = message_count + 2, updated_at = NOW() WHERE id = ?",
            [$sessionId]
        );

        $elapsed = round((microtime(true) - $startTime) * 1000);

        return [
            'success'       => true,
            'message'       => $response['content'],
            'category'      => $category,
            'confidence'    => $response['confidence'] ?? 0.85,
            'model'         => $response['model'] ?? 'builtin',
            'provider'      => $response['provider'] ?? 'builtin',
            'session_id'    => $sessionId,
            'session_uuid'  => $activeUuid,
            'session_token' => $this->sessionToken,
            'response_ms'   => $elapsed,
            'suggestions'   => $response['suggestions'] ?? [],
        ];
    }

    /**
     * Fetch the last N conversation turns for context (multi-turn memory).
     * Returns Gemini-style [{role, parts:[{text}]}] entries, oldest first.
     */
    private function getRecentTurns(int $sessionId, ?int $limit = null): array
    {
        $limit = $limit ?? (int) ($this->config['context_turns'] ?? 10);
        if ($sessionId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT role, content FROM ai_chat_messages
                 WHERE session_id = ? AND role IN ('user','assistant') AND content <> ''
                 ORDER BY id DESC LIMIT " . ($limit * 2),
                [$sessionId]
            );
            $rows = array_reverse($rows);

            $turns = [];
            foreach ($rows as $row) {
                // The current user message was already saved, so drop the very last one
                // to avoid duplicating it (it is sent explicitly as the live turn).
                $turns[] = [
                    'role'  => $row['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) $row['content']]],
                ];
            }

            // Remove trailing (current) user turn — replaced by the live message
            if (!empty($turns) && end($turns)['role'] === 'user') {
                array_pop($turns);
            }

            return $turns;
        } catch (\Exception $e) {
            error_log("Context build error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Decode and save base64 image data to local uploads folder
     */
    private function saveBase64Image(string $base64Data, string $mimeType): ?string
    {
        try {
            $extension = 'png';
            if (strpos($mimeType, 'jpeg') !== false || strpos($mimeType, 'jpg') !== false) {
                $extension = 'jpg';
            } elseif (strpos($mimeType, 'webp') !== false) {
                $extension = 'webp';
            } elseif (strpos($mimeType, 'gif') !== false) {
                $extension = 'gif';
            }

            // Target directory: root/ai-enterprise/storage/uploads/
            $uploadDir = __DIR__ . '/../../storage/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $filename = 'img_' . uniqid() . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Strip prefix if the frontend sent the whole data URI
            $cleanBase64Data = $base64Data;
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64Data, $matches)) {
                $cleanBase64Data = $matches[2];
            }

            $decoded = base64_decode($cleanBase64Data);
            if ($decoded === false) {
                return null;
            }

            file_put_contents($filepath, $decoded);
            return 'storage/uploads/' . $filename;
        } catch (\Exception $e) {
            error_log("Failed to save base64 image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update title of chat session based on first user message
     */
    private function updateSessionTitleIfNeeded(int $sessionId, string $message): void
    {
        try {
            $chatCount = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ?", [$sessionId]);
            // Counts 1 since user's message has already been written
            if ($chatCount == 1 && !empty($message)) {
                $title = trim(preg_replace('/\s+/', ' ', $message));
                if (mb_strlen($title) > 30) {
                    $title = mb_substr($title, 0, 27) . '...';
                }
                $this->db->execute(
                    "UPDATE ai_chat_sessions SET title = ? WHERE id = ?",
                    [$title ?: "New Chat", $sessionId]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to update session title: " . $e->getMessage());
        }
    }

    /**
     * Detect problem category from message content
     */
    private function detectCategory(string $message): string
    {
        $messageLower = strtolower($message);
        $scores = ['student' => 0, 'office' => 0, 'programming' => 0, 'business' => 0, 'customer_support' => 0];

        foreach ($this->categories as $cat => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($messageLower, strtolower($keyword)) !== false) {
                    $scores[$cat] += 2; // Keyword match weight
                }
            }
        }

        $maxScore = max($scores);
        if ($maxScore === 0) {
            return 'general';
        }

        return array_search($maxScore, $scores);
    }

    /**
     * Generate an intelligent response
     */
    private function generateResponse(string $message, string $category, string $language = 'en', ?string $model = null, ?array $imageData = null, array $history = []): array
    {
        // 1. If Gemini key is available, run Gemini API first for intelligent completion (supports images + context)
        if (!empty($this->config['gemini_api_key'])) {
            $apiResponse = $this->callGeminiAPI($message, $category, $language, $model, $imageData, $history);
            if ($apiResponse !== null) {
                return $apiResponse;
            }
        }

        // 2. Fallback to OpenAI API if configured
        if (!empty($this->config['openai_api_key'])) {
            $apiResponse = $this->callOpenAIAPI($message, $category, $language, $model, $history);
            if ($apiResponse !== null) {
                return $apiResponse;
            }
        }

        // 3. Fallback to Open Source AI Tools (Ollama) if running
        if (!empty($this->config['ollama_endpoint'])) {
            $apiResponse = $this->callOllamaAPI($message, $category, $language, $model, $history);
            if ($apiResponse !== null) {
                return $apiResponse;
            }
        }

        // 4. Fallback to Greetings patterns and local KB if offline / key expired
        $messageLower = strtolower($message);
        foreach ($this->smartResponses as $type => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (stripos($messageLower, $pattern) !== false) {
                    $content = $data['responses'][array_rand($data['responses'])];
                    if ($language === 'kn') {
                        $content = "ಹಲೋ! 👋 KSR GURU JI AI ಅಸಿಸ್ಟೆಂಟ್‌ಗೆ ಸುಸ್ವಾಗತ. ನಾನು ನಿಮಗೆ ಕೆಳಗಿನ ಸಮಸ್ಯೆಗಳಿಗೆ ಸಹಾಯ ಮಾಡಲು ಇಲ್ಲಿದ್ದೇನೆ:\n\n🎓 **ವಿದ್ಯಾರ್ಥಿ ಸಮಸ್ಯೆಗಳು (Students)**\n💼 **ಕಚೇರಿ/ಉದ್ಯೋಗ ಸಮಸ್ಯೆಗಳು (Office)**\n💻 **ಪ್ರೋಗ್ರಾಮಿಂಗ್ ತೊಂದರೆಗಳು (Programming)**\n📈 **ವ್ಯಾಪಾರ ತಂತ್ರಗಳು (Business)**\n🌐 **ಸಾಮಾನ್ಯ ಪ್ರಶ್ನೆಗಳು (General)**\n\nಇಂದು ನಾನು ನಿಮಗೆ ಹೇಗೆ ಸಹಾಯ ಮಾಡಬಹುದು?";
                    }
                    return [
                        'content'    => $content,
                        'model'      => 'builtin-pattern',
                        'provider'   => 'builtin',
                        'confidence' => 0.95,
                        'suggestions'=> $this->getSuggestions($category),
                    ];
                }
            }
        }

        // 5. Search knowledge base
        $kbResponse = $this->searchKnowledgeBase($message, $category);
        if ($kbResponse !== null) {
            return [
                'content'    => $kbResponse['answer'],
                'model'      => 'builtin-kb',
                'provider'   => 'knowledge-base',
                'confidence' => 0.90,
                'suggestions'=> $this->getSuggestions($category),
            ];
        }

        // 6. Intelligent local fallback system
        return $this->generateSmartFallback($message, $category, $language);
    }

    /**
     * Search the knowledge base for relevant answers
     */
    private function searchKnowledgeBase(string $query, string $category): ?array
    {
        try {
            // Try full-text search first
            $sql = "SELECT *, MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
                    FROM ai_knowledge_base
                    WHERE is_active = 1
                    AND (category = ? OR category = 'general')
                    AND MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC, priority DESC
                    LIMIT 1";
            $result = $this->db->fetchOne($sql, [$query, $category, $query]);

            if ($result && ($result['relevance'] ?? 0) > 0.5) {
                // Increment usage count
                $this->db->execute(
                    "UPDATE ai_knowledge_base SET usage_count = usage_count + 1 WHERE id = ?",
                    [$result['id']]
                );
                return $result;
            }
        } catch (\Exception $e) {
            error_log("KB search error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Call Google Gemini API (multimodal, multi-turn, with model fallback)
     */
    private function callGeminiAPI(string $message, string $category, string $language = 'en', ?string $model = null, ?array $imageData = null, array $history = []): ?array
    {
        $systemPrompt = $this->getSystemPrompt($category, $language);

        // Build the user turn (text + optional inline image)
        $parts = [];
        if (!empty($message)) {
            $parts[] = ['text' => $message];
        } else {
            $parts[] = ['text' => 'Analyze this image and provide helpful insights for this category: ' . $category];
        }

        if (is_array($imageData) && !empty($imageData['data']) && !empty($imageData['mime'])) {
            $base64 = $imageData['data'];
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64, $m)) {
                $base64 = $m[2];
            }
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $imageData['mime'],
                    'data'     => $base64,
                ],
            ];
        }

        // Assemble the conversation: history first, then the live user turn
        $contents = [];
        foreach ($history as $turn) {
            if (!empty($turn['parts'])) {
                $contents[] = $turn;
            }
        }
        $contents[] = ['role' => 'user', 'parts' => $parts];

        $payload = json_encode([
            'contents'          => $contents,
            'generationConfig'  => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 2048,
                'topP'            => 0.95,
                'topK'            => 40,
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        // Build the ordered list of models to attempt
        $models = array_values(array_unique(array_filter(array_merge(
            [$model],
            [$this->config['gemini_model'] ?? null],
            $this->config['gemini_fallback_chain'] ?? [],
            array_keys($this->config['gemini_models'] ?? [])
        ))));

        $lastError = null;
        foreach ($models as $m) {
            [$code, $body] = $this->rawGeminiRequest($m, $payload);

            if ($code === 200 && $body) {
                $data = json_decode($body, true);
                $text = $this->extractGeminiText($data);
                if (!empty($text)) {
                    return [
                        'content'    => $text,
                        'model'      => $m,
                        'provider'   => 'gemini',
                        'confidence' => 0.95,
                        'usage'      => $data['usageMetadata'] ?? null,
                        'suggestions'=> $this->getSuggestions($category),
                    ];
                }
                $lastError = 'empty response';
            }

            // If a specific model was explicitly requested by the client, do not
            // silently swap it — let the caller see the error instead.
            if ($model !== null && $m === $model && $code !== 200) {
                error_log("Gemini explicit model '{$m}' failed HTTP {$code}: " . substr((string) $body, 0, 300));
            }

            // Stop the chain on hard auth failures (bad/expired key) — no point retrying
            if (in_array($code, [400, 401, 403], true)) {
                error_log("Gemini auth/payload error HTTP {$code}: " . substr((string) $body, 0, 300));
                break;
            }

            $lastError = "HTTP {$code}";
            // Small backoff before trying the next model on transient errors
            usleep(120000);
        }

        if ($lastError !== null) {
            error_log("Gemini API exhausted all models. Last error: {$lastError}");
        }
        return null;
    }

    /**
     * Perform the raw HTTPS request to Gemini and return [httpCode, body].
     * Retries once on 429/500/503 (rate limit / transient overload).
     */
    private function rawGeminiRequest(string $model, string $payload, int $attempts = 2): array
    {
        $url = $this->config['gemini_endpoint'] . $model . ':generateContent?key=' . $this->config['gemini_api_key'];
        $timeout = (int) ($this->config['gemini_timeout'] ?? 45);

        for ($try = 1; $try <= $attempts; $try++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

            if ($curlErr) {
                error_log("Gemini curl error for {$model}: {$curlErr}");
            }

            if ($code !== 200 && in_array($code, [429, 500, 502, 503, 504], true) && $try < $attempts) {
                usleep(400000); // 0.4s backoff then retry same model
                continue;
            }

            return [$code, $response];
        }

        return [0, null];
    }

    /**
     * Extract text from a Gemini response, tolerating multi-part answers.
     */
    private function extractGeminiText(?array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $candidate = $data['candidates'][0] ?? null;
        if (!$candidate) {
            return '';
        }

        $text = '';
        foreach (($candidate['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        // Blocked by safety filters — surface a helpful message instead of silence
        if (trim($text) === '' && !empty($candidate['finishReason']) && $candidate['finishReason'] === 'SAFETY') {
            return "I'm unable to answer that request due to safety guidelines. Please rephrase your question and I'll gladly help.";
        }

        return trim($text);
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAIAPI(string $message, string $category, string $language = 'en', ?string $model = null, array $history = []): ?array
    {
        $systemPrompt = $this->getSystemPrompt($category, $language);
        $model = $model ?? $this->config['openai_model'] ?? 'gpt-4o-mini';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'model' ? 'assistant' : 'user';
            $text = $turn['parts'][0]['text'] ?? '';
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = json_encode([
            'model'    => $model,
            'messages' => $messages,
            'max_tokens'  => 2048,
            'temperature' => 0.7,
        ]);

        $ch = curl_init($this->config['openai_endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['openai_api_key'],
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['choices'][0]['message']['content'] ?? null;
            if ($text) {
                return [
                    'content'    => $text,
                    'model'      => $model,
                    'provider'   => 'openai',
                    'confidence' => 0.93,
                    'suggestions'=> $this->getSuggestions($category),
                ];
            }
        }

        return null;
    }

    /**
     * Call Ollama (Open Source Local AI API)
     */
    private function callOllamaAPI(string $message, string $category, string $language = 'en', ?string $model = null, array $history = []): ?array
    {
        $systemPrompt = $this->getSystemPrompt($category, $language);
        $model = $model ?? $this->config['ollama_model'] ?? 'llama3';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'model' ? 'assistant' : 'user';
            $text = $turn['parts'][0]['text'] ?? '';
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = json_encode([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ]);

        $ch = curl_init($this->config['ollama_endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30, // Local AI inference could take a bit longer
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['message']['content'] ?? null;
            if ($text) {
                return [
                    'content'    => $text,
                    'model'      => $model . ' (Open Source/Ollama)',
                    'provider'   => 'ollama',
                    'confidence' => 0.92,
                    'suggestions'=> $this->getSuggestions($category),
                ];
            }
        }

        return null;
    }

    /**
     * Generate smart fallback when offline / no API key is available
     */
    private function generateSmartFallback(string $message, string $category, string $language = 'en'): array
    {
        $categoryLabels = [
            'student'     => 'Student & Education',
            'office'      => 'Office & Work',
            'programming' => 'Programming & Development',
            'business'    => 'Business & Startups',
            'customer_support' => 'Customer Support - KSR GURU JI App',
            'general'     => 'General Query',
        ];

        $catLabel = $categoryLabels[$category] ?? 'General';

        if ($language === 'kn') {
            $prefix = "ನಾನು ನಿಮ್ಮ ಪ್ರಶ್ನೆಯನ್ನು **{$catLabel}** ಗೆ ಸಂಬಂಧಿಸಿದ್ದು ಎಂದು ಗುರುತಿಸಿದ್ದೇನೆ.\n\n";
            $tips = match ($category) {
                'student' => "📚 **ವಿದ್ಯಾರ್ಥಿ ಸಂಪನ್ಮೂಲಗಳು**\n- Khan Academy (ವಿಷಯಗಳ ವಿವರಣೆ)\n- NPTEL (ಭಾರತೀಯ ವಿಶ್ವವಿದ್ಯಾಲಯ ಶಿಕ್ಷಣ)\n- ವಾರಕ್ಕೊಮ್ಮೆ ವೇಳಾಪಟ್ಟಿ ತಯಾರಿಸಿ ಅಧ್ಯಯನ ಮಾಡಿ.\n- ಗೊಂದಲವಿರುವ ಸೂತ್ರಗಳನ್ನು ದಿನವೂ ಬರೆದು ಅಭ್ಯಾಸ ಮಾಡಿ.",
                'office' => "💼 **ಕಚೇರಿ ಸಲಹೆಗಳು**\n- ವೃತ್ತಿಪರ ಇಮೇಲ್ ಇತಿಹಾಸ ಕಾಪಾಡಿ.\n- ಸಹೋದ್ಯೋಗಿಗಳೊಂದಿಗೆ ಸ್ಪಷ್ಟ ಸಂವಹನ ನಡೆಸಿ.\n- ಯಾವುದೇ ಕಚೇರಿ ವಹಿವಾಟುಗಳಿಗೆ ಲಿಖಿತ ದಾಖಲೆಗಳನ್ನು ಇಟ್ಟುಕೊಳ್ಳಿ.",
                'programming' => "💻 **ಪ್ರೋಗ್ರಾಮಿಂಗ್ ಮತ್ತು ಕೋಡಿಂಗ್**\n- StackOverflow ಮತ್ತು ಅಧಿಕೃತ ಡಾಕ್ಯುಮೆಂಟ್ಸ್ ನೋಡಿ.\n- ದೋಷಗಳನ್ನು ಹುಡುಕಲು 'Stack Trace' ಗಮನಿಸಿ.\n- ಕೋಡಿಂಗ್ ಮಾಡುವಾಗ ಸಣ್ಣ ಸಣ್ಣ ಫಂಕ್ಷನ್‌ಗಳಾಗಿ ವಿಂಗಡಿಸಿ.",
                'business' => "📈 **ವ್ಯಾಪಾರ ಮತ್ತು ಸ್ಟಾರ್ಟ್ಅಪ್**\n- ಗ್ರಾಹಕರ ಆಸಕ್ತಿಗಳನ್ನು ಮೊದಲು ವಿಶ್ಲೇಷಿಸಿ.\n- ಬಂಡವಾಳ ಮತ್ತು ಮಾಸಿಕ ವೆಚ್ಚಗಳ ಲೆಕ್ಕಾಚಾರ ಸ್ಪಷ್ಟವಾಗಿರಲಿ.\n- ಡಿಜಿಟಲ್ ಮಾರ್ಕೆಟಿಂಗ್ ಬಳಕೆ ಆರಂಭಿಸಿ.",
                'customer_support' => "🎧 **KSR GURU JI ಗ್ರಾಹಕ ಸೇವೆ**\n- ನಿಮ್ಮ ಖಾತೆಯ ಲಾಗಿನ್ ತೊಂದರೆಗೆ 'ಪಾಸ್ವರ್ಡ್ ಮರೆತಿದ್ದೀರಾ' ಲಿಂಕ್ ಬಳಸಿ.\n- ಆರ್ಡರ್ ಅಥವಾ ಡೆಲಿವರಿ ಸಂಬಂಧಿತ ಸಮಸ್ಯೆಗಳಿದ್ದರೆ ಆರ್ಡರ್ ಪುಟದಿಂದ ದೂರು ನೀಡಿ.\n- ನಾವು ಯಾವುದೇ ಸಮಸ್ಯೆಗೆ ಶೀಘ್ರ ಪರಿಹಾರ ಒದಗಿಸುತ್ತೇವೆ.",
                default => "🌟 **ನಾನು ಕೆಳಗಿನ ವಿಭಾಗಗಳಲ್ಲಿ ಸಹಾಯ ಮಾಡಬಲ್ಲೆ:**\n- 🎓 ವಿದ್ಯಾರ್ಥಿ ಪ್ರಶ್ನೆಗಳು\n- 💼 ಕಚೇರಿ ಇಮೇಲ್ ಮತ್ತು ಸಲಹೆಗಳು\n- 💻 ಸಾಫ್ಟ್‌ವೇರ್ ಮತ್ತು ಪ್ರೋಗ್ರಾಮಿಂಗ್ ದೋಷಗಳು\n- 📈 ವ್ಯಾಪಾರ ಮತ್ತು ಮಾರ್ಕೆಟಿಂಗ್ ತಂತ್ರಗಳು\n- 🎧 KSR GURU JI ಆಪ್ ಗ್ರಾಹಕ ಸೇವೆ",
            };
            $content = $prefix . $tips . "\n\n---\n💬 *Open Source AI ಹಾಗೂ Gemini API ಕೀಲಿಯನ್ನು ಸೆಟಪ್ ಮಾಡಿದ ನಂತರ ಇನ್ನಷ್ಟು ನಿಖರವಾದ ಉತ್ತರಗಳು ಲಭ್ಯವಾಗುತ್ತವೆ.*";
        } else {
            $prefix = "I understand you have a **{$catLabel}** related question.\n\n";
            $tips = match ($category) {
                'student' => "📚 **Study Resources**\n- YouTube Academic Explanations, Khan Academy, and NPTEL.\n- Create short study blocks (Pomodoro technique: 25 min focus, 5 min break).\n- Solve mock test papers weekly.",
                'office' => "💼 **Professional Resources**\n- LinkedIn Learning & HR Help portals.\n- Double check emails before hitting send.\n- Maintain absolute clarity on task deadlines & status templates.",
                'programming' => "💻 **Developer Tips & Resources**\n- Keep functions modular and write unit tests.\n- Use developer debug tools / print logs to narrow down anomalies.\n- Search official docs, stackoverflow, or developer forums for library usage.",
                'business' => "📈 **Startup & Business Core**\n- Validate ideas using Customer Discovery (interviews).\n- Create a simple page MVP to check user traction before build.\n- Ensure strict accounting of monthly cash burns.",
                'customer_support' => "🎧 **KSR GURU JI Customer Support**\n- For login issues, please use the 'Forgot Password' flow.\n- For pending orders or delivery problems, visit the 'Orders' dashboard to track or report.\n- We are committed to solving your KSR GURU JI app issues as quickly as possible.",
                default => "🌟 **I am here to solve problems across:**\n- 🎓 Student Challenges\n- 💼 Corporate Workflows\n- 💻 Programming Diagnostics\n- 📈 Marketing & Business Strategy\n- 🎧 KSR GURU JI Customer Support",
            };
            $content = $prefix . $tips . "\n\n---\n💬 *Please connect an open-source AI endpoint via Ollama or set your Google Gemini API Key in `config/app.php` to access advanced, dynamic explanations!*";
        }

        return [
            'content'    => $content,
            'model'      => 'builtin-smart',
            'provider'   => 'builtin',
            'confidence' => 0.70,
            'suggestions'=> $this->getSuggestions($category),
        ];
    }

    /**
     * Get system prompt for AI API calls
     */
    private function getSystemPrompt(string $category, string $language = 'en'): string
    {
        $base = "You are KSR GURU JI AI Assistant, an elite, empathetic, and highly knowledgeable AI for India and Kannada-speaking users. ";
        $base .= "You help users solve real problems across education, work/office, programming, business, health and general life. ";
        $base .= "Always give practical, step-by-step, actionable advice with concrete examples. ";
        $base .= "Use clean markdown: headings (##), bold (**text**), numbered and bulleted lists, and fenced code blocks for code. ";
        $base .= "Keep answers well-structured and complete — do not cut off mid-explanation. ";
        if ($language == 'kn') {
            $base .= "IMPORTANT: You MUST respond strictly in the KANNADA language (ಕನ್ನಡ). If the user asks for code, keep code keywords/syntax in English but write explanations, line-by-line breakdowns and code comments in Kannada. Be polite. ";
        } else {
            $base .= "You respond in clear, well-formatted English. Be polite, encouraging and thorough. ";
        }

        return match ($category) {
            'student'  => $base . "The user has a STUDENT/EDUCATION related question. Focus on study techniques, exam strategy, career guidance, university preparation, scholarships and assignments.",
            'office'   => $base . "The user has an OFFICE/WORK related question. Focus on workplace issues, professional communication, resumes, interviews, HR policies and productivity.",
            'programming' => $base . "The user has a PROGRAMMING/CODING query. Provide working code samples in fenced blocks, explain the reasoning, debug errors from stack traces, and suggest refactoring and database query improvements.",
            'business' => $base . "The user has a BUSINESS/STARTUP/E-COMMERCE query. Focus on marketing channels, sales funnels, accounting, idea validation, pricing, growth and customer discovery.",
            'customer_support' => $base . "The user has a CUSTOMER SUPPORT query strictly regarding the 'KSR GURU JI App'. Provide precise, empathetic support steps for app crashes, login problems, payment issues, refunds, delivery tracking, order status and account settings. Always reassure them their issue will be resolved and, where relevant, tell them to use the app's Support/Track Order pages.",
            default    => $base . "Detect their concern and provide the best possible multi-faceted advice. Answer any question as a helpful, knowledgeable assistant.",
        };
    }

    /**
     * Get suggested follow-up questions
     */
    private function getSuggestions(string $category): array
    {
        $suggestions = match ($category) {
            'student' => [
                "How to prepare for competitive exams?",
                "Best time management tips for students",
                "How to improve concentration?",
                "Career guidance for computer science",
            ],
            'office' => [
                "How to write a professional email?",
                "Workplace conflict resolution tips",
                "How to prepare for a performance review?",
                "Tips for speaking confidently in meetings",
            ],
            'programming' => [
                "What is the difference between SQL and NoSQL?",
                "Help me debug 'NullPointerException' in Java",
                "How to structure a clean REST API?",
                "Best practices for web application security",
            ],
            'business' => [
                "How to validate a startup idea?",
                "Growth marketing strategies for e-commerce",
                "What is a cash flow statement?",
                "How to pitch my product to investors?",
            ],
            'customer_support' => [
                "My payment got stuck, refund help?",
                "How to track my recent order in the KSR app?",
                "I am facing login issues in the app.",
                "How do I update my profile details?",
            ],
            default => [
                "I have a problem with the KSR GURU JI app.",
                "Can you solve a complex math problem?",
                "Give me tips for time management.",
                "What can you help me with?",
            ],
        };

        shuffle($suggestions);
        return array_slice($suggestions, 0, 3);
    }

    /**
     * Get or create a chat session
     */
    private function getOrCreateSession(?int $userId, string $category, ?string $sessionUuid = null): int
    {
        try {
            if (!empty($sessionUuid)) {
                // Seek active session by UUID
                $session = $this->db->fetchOne(
                    "SELECT id FROM ai_chat_sessions WHERE uuid = ? AND status = 'active' LIMIT 1",
                    [$sessionUuid]
                );
                if ($session) {
                    return (int) $session['id'];
                }
            }

            // Create new session UUID
            $uuid = $sessionUuid ?? sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            // Default safe starting title
            $title = "New Chat (" . ucfirst($category) . ")";

            $id = $this->db->insert(
                "INSERT INTO ai_chat_sessions (uuid, user_id, session_token, category, title) VALUES (?, ?, ?, ?, ?)",
                [$uuid, $userId, $this->sessionToken, $category, $title]
            );

            return (int) $id;
        } catch (\Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Save a message to the database
     */
    private function saveMessage(int $sessionId, string $role, string $content, string $model = 'builtin', float $confidence = 0.85, ?string $imageUrl = null): void
    {
        if ($sessionId <= 0) return;

        try {
            $metadata = null;
            if ($imageUrl) {
                $metadata = json_encode(['image_url' => $imageUrl]);
            }

            $this->db->insert(
                "INSERT INTO ai_chat_messages (session_id, role, content, model_used, confidence, metadata) VALUES (?, ?, ?, ?, ?, ?)",
                [$sessionId, $role, $content, $model, $confidence, $metadata]
            );
        } catch (\Exception $e) {
            error_log("Save message error: " . $e->getMessage());
        }
    }

    /**
     * Whether a real AI provider is configured (Gemini / OpenAI).
     */
    public function isAIConfigured(): bool
    {
        return !empty($this->config['gemini_api_key']) || !empty($this->config['openai_api_key']);
    }

    /**
     * List the active chat sessions for a browser token.
     */
    public function listSessions(string $token, int $limit = 30): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT uuid, category, title, message_count, updated_at, created_at
                 FROM ai_chat_sessions
                 WHERE session_token = ? AND status = 'active'
                 ORDER BY updated_at DESC LIMIT " . max(1, min($limit, 100)),
                [$token]
            );
        } catch (\Exception $e) {
            error_log("listSessions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Rename a chat session (owner-scoped by session token).
     */
    public function renameSession(string $token, string $uuid, string $title): bool
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if ($uuid === '' || $title === '') {
            return false;
        }
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57) . '...';
        }

        try {
            $affected = $this->db->execute(
                "UPDATE ai_chat_sessions SET title = ? WHERE uuid = ? AND session_token = ? AND status = 'active'",
                [$title, $uuid, $token]
            );
            return $affected > 0;
        } catch (\Exception $e) {
            error_log("renameSession error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get chat history for a session (returns latest session by default if uuid is omitted)
     */
    public function getHistory(?string $token = null, ?string $sessionUuid = null): array
    {
        $token = $token ?? $this->sessionToken;
        try {
            if (!empty($sessionUuid)) {
                return $this->db->fetchAll(
                    "SELECT m.* FROM ai_chat_messages m
                     JOIN ai_chat_sessions s ON m.session_id = s.id
                     WHERE s.uuid = ? AND s.status = 'active'
                     ORDER BY m.created_at ASC
                     LIMIT 100",
                    [$sessionUuid]
                );
            } else {
                return $this->db->fetchAll(
                    "SELECT m.* FROM ai_chat_messages m
                     JOIN ai_chat_sessions s ON m.session_id = s.id
                     WHERE s.session_token = ? AND s.status = 'active'
                     AND s.id = (SELECT id FROM ai_chat_sessions WHERE session_token = ? AND status = 'active' ORDER BY updated_at DESC LIMIT 1)
                     ORDER BY m.created_at ASC
                     LIMIT 100",
                    [$token, $token]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to fetch history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format response
     */
    private function formatResponse(string $content, string $category, float $confidence): array
    {
        return [
            'success'    => true,
            'message'    => $content,
            'category'   => $category,
            'confidence' => $confidence,
            'model'      => 'builtin',
            'provider'   => 'builtin',
            'session_token' => $this->sessionToken,
            'suggestions'=> $this->getSuggestions($category),
        ];
    }
}
