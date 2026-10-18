<?php
// ============================================================
// AI Search Engine — Intelligent Multi-Modal Search
// Supports: Text, Voice, Autocomplete, Fuzzy, Semantic
// ============================================================

namespace App\AI;

use App\Core\Database;

class SearchEngine
{
    private Database $db;
    private array $config;
    private array $aiConfig = [];

    // Synonyms dictionary for better search
    private array $synonyms = [
        'exam'       => ['test', 'examination', 'assessment', 'quiz'],
        'study'      => ['learn', 'education', 'academic', 'school'],
        'career'     => ['job', 'profession', 'work', 'employment', 'occupation'],
        'health'     => ['wellness', 'medical', 'fitness', 'wellbeing'],
        'stress'     => ['anxiety', 'tension', 'pressure', 'worry'],
        'money'      => ['finance', 'salary', 'income', 'budget', 'savings'],
        'tax'        => ['ITR', 'income tax', 'filing', 'deduction'],
        'sleep'      => ['insomnia', 'rest', 'bedtime', 'nap'],
        'exercise'   => ['workout', 'fitness', 'gym', 'physical activity'],
        'diet'       => ['nutrition', 'food', 'eating', 'meal'],
        'resume'     => ['CV', 'biodata', 'curriculum vitae'],
        'interview'  => ['hiring', 'recruitment', 'selection'],
    ];

    // Stop words to skip in search
    private array $stopWords = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for',
        'on', 'with', 'at', 'by', 'from', 'it', 'this', 'that', 'these',
        'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'they',
        'and', 'but', 'or', 'not', 'so', 'if', 'how', 'what', 'which',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $allConfig = require __DIR__ . '/../../config/app.php';
        $this->config = $allConfig['search'];
        $this->aiConfig = $allConfig['ai'];
    }

    /**
     * Main search method — handles all search types
     */
    public function search(string $query, string $type = 'text', string $category = '', int $limit = 20): array
    {
        $startTime = microtime(true);
        $query = trim($query);

        if (mb_strlen($query) < ($this->config['min_query_length'] ?? 2)) {
            return ['success' => false, 'error' => 'Query too short', 'results' => []];
        }

        // Log the search
        $this->logSearch($query, $type, $category);

        // Track keyword popularity
        $this->trackKeyword($query);

        // Perform local structured search
        $results = match ($type) {
            'fuzzy'  => $this->fuzzySearch($query, $category, $limit),
            default  => $this->textSearch($query, $category, $limit),
        };

        // Add an AI-synthesised answer alongside the structured matches
        $aiAnswer = $this->generateAIAnswer($query, $category);

        // If the local index returned nothing but AI did, surface AI as the top result
        if (empty($results) && $aiAnswer) {
            $results[] = [
                'id'        => 'ai-answer',
                'type'      => 'ai',
                'title'     => 'AI Answer for: "' . $query . '"',
                'snippet'   => $aiAnswer['snippet'],
                'category'  => 'ai',
                'relevance' => 0.99,
                'icon'      => 'fa-robot',
            ];
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);

        return [
            'success'      => true,
            'query'        => $query,
            'type'         => $type,
            'category'     => $category,
            'results'      => $results,
            'total'        => count($results),
            'response_ms'  => $elapsed,
            'ai_answer'    => $aiAnswer ? $aiAnswer['full'] : null,
            'suggestions'  => $this->getSearchSuggestions($query),
            'did_you_mean' => $this->didYouMean($query),
        ];
    }

    /**
     * Ask Gemini to answer the search query directly (semantic / conversational search).
     * Returns ['full' => markdown answer, 'snippet' => short preview] or null.
     */
    private function generateAIAnswer(string $query, string $category = ''): ?array
    {
        $key = $this->aiConfig['gemini_api_key'] ?? '';
        if (empty($key)) {
            return null;
        }

        $model = $this->aiConfig['gemini_model'] ?? 'gemini-3.5-flash';
        $prompt = "Answer this user search query concisely and helpfully. "
            . "Use short markdown (headings, bullets, bold). Keep it under 200 words.\n\n"
            . "Query: " . $query;

        if ($category !== '') {
            $prompt .= "\nFocus area: {$category}";
        }

        $payload = json_encode([
            'contents' => [[ 'role' => 'user', 'parts' => [['text' => $prompt]] ]],
            'generationConfig' => ['temperature' => 0.6, 'maxOutputTokens' => 800, 'topP' => 0.95],
        ], JSON_UNESCAPED_UNICODE);

        $endpoint = $this->aiConfig['gemini_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url = $endpoint . $model . ':generateContent?key=' . $key;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => (int) ($this->aiConfig['gemini_timeout'] ?? 45),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        $text = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/([*#`_>-])/', '', $text))));
        $snippet = mb_strlen($plain) > 200 ? mb_substr($plain, 0, 200) . '...' : $plain;

        return ['full' => $text, 'snippet' => $snippet];
    }

    /**
     * Full-text search across knowledge base and products
     */
    private function textSearch(string $query, string $category, int $limit): array
    {
        $results = [];

        // 1. Search knowledge base with full-text
        try {
            $categoryFilter = '';
            $params = [$query, $query];

            if (!empty($category)) {
                $categoryFilter = "AND (category = ? OR category = 'general')";
                $params[] = $category;
            }
            $params[] = $limit;

            $sql = "SELECT id, category, question AS title, answer AS content, keywords, priority,
                           MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
                    FROM ai_knowledge_base 
                    WHERE is_active = 1 
                    AND MATCH(question, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                    {$categoryFilter}
                    ORDER BY relevance DESC, priority DESC
                    LIMIT ?";

            $rows = $this->db->fetchAll($sql, $params);

            foreach ($rows as $row) {
                $results[] = [
                    'id'        => $row['id'],
                    'type'      => 'knowledge',
                    'title'     => $row['title'],
                    'snippet'   => $this->generateSnippet($row['content'], $query),
                    'category'  => $row['category'],
                    'relevance' => round((float)($row['relevance'] ?? 0), 4),
                    'icon'      => $this->getCategoryIcon($row['category']),
                ];
            }
        } catch (\Exception $e) {
            error_log("Full-text search error: " . $e->getMessage());
        }

        // 2. Fallback: LIKE search if no full-text results
        if (empty($results)) {
            $results = $this->likeSearch($query, $category, $limit);
        }

        // 3. Also search products table (from parent platform)
        try {
            $productResults = $this->searchProducts($query, $limit);
            $results = array_merge($results, $productResults);
        } catch (\Exception $e) {
            // Products table might have different schema
        }

        // Sort by relevance
        usort($results, fn($a, $b) => ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0));

        return array_slice($results, 0, $limit);
    }

    /**
     * LIKE-based fallback search
     */
    private function likeSearch(string $query, string $category, int $limit): array
    {
        $words = $this->extractSearchWords($query);
        if (empty($words)) return [];

        $conditions = [];
        $params = [];

        foreach ($words as $word) {
            $conditions[] = "(question LIKE ? OR answer LIKE ? OR keywords LIKE ?)";
            $params[] = "%{$word}%";
            $params[] = "%{$word}%";
            $params[] = "%{$word}%";
        }

        if (!empty($category)) {
            $params[] = $category;
        }
        $params[] = $limit;

        $categoryFilter = !empty($category) ? "AND (category = ? OR category = 'general')" : '';

        $sql = "SELECT id, category, question AS title, answer AS content, keywords, priority
                FROM ai_knowledge_base 
                WHERE is_active = 1 
                AND (" . implode(' OR ', $conditions) . ")
                {$categoryFilter}
                ORDER BY priority DESC
                LIMIT ?";

        try {
            $rows = $this->db->fetchAll($sql, $params);
            return array_map(fn($row) => [
                'id'        => $row['id'],
                'type'      => 'knowledge',
                'title'     => $row['title'],
                'snippet'   => $this->generateSnippet($row['content'], $query),
                'category'  => $row['category'],
                'relevance' => 0.5,
                'icon'      => $this->getCategoryIcon($row['category']),
            ], $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search products from the parent KSR GURU JI store
     */
    private function searchProducts(string $query, int $limit): array
    {
        $sql = "SELECT id, name, kn_name, price, discount, stock, category_id, icon, emoji, images
                FROM products
                WHERE status = 1
                AND (name LIKE ? OR kn_name LIKE ? OR description LIKE ?)
                ORDER BY is_featured DESC, name ASC
                LIMIT ?";

        $term = "%{$query}%";
        $rows = $this->db->fetchAll($sql, [$term, $term, $term, $limit]);

        return array_map(function ($row) {
            $price = (float) $row['price'];
            $discount = (float) ($row['discount'] ?? 0);
            $final = $discount > 0 ? $price - ($price * $discount / 100) : $price;

            $priceText = '₹' . number_format($final, 2);
            if ($discount > 0) {
                $priceText .= ' (was ₹' . number_format($price, 2) . ' · -' . rtrim(rtrim(number_format($discount, 1), '0'), '.') . '%)';
            }
            $stock = (int) ($row['stock'] ?? 0);
            $priceText .= $stock > 0 ? ' · In stock' : ' · Out of stock';

            return [
                'id'        => $row['id'],
                'type'      => 'product',
                'title'     => (string) ($row['name'] ?? ''),
                'snippet'   => $priceText,
                'category'  => 'product',
                'relevance' => 0.75,
                'icon'      => (!empty($row['icon']) ? $row['icon'] : 'fa-box'),
                'emoji'     => $row['emoji'] ?? '',
                'price'     => $final,
                'stock'     => $stock,
                'link'      => '../product.php?id=' . (int) $row['id'],
            ];
        }, $rows);
    }

    /**
     * Fuzzy search using Levenshtein distance
     */
    private function fuzzySearch(string $query, string $category, int $limit): array
    {
        // Expand query with synonyms
        $expandedTerms = $this->expandWithSynonyms($query);
        $allResults = [];

        foreach ($expandedTerms as $term) {
            $results = $this->textSearch($term, $category, $limit);
            foreach ($results as $r) {
                $key = $r['type'] . '_' . $r['id'];
                if (!isset($allResults[$key])) {
                    $allResults[$key] = $r;
                }
            }
        }

        return array_values($allResults);
    }

    /**
     * Autocomplete suggestions
     */
    public function autocomplete(string $prefix, int $limit = 8): array
    {
        $prefix = trim($prefix);
        if (strlen($prefix) < 1) return [];

        $suggestions = [];

        // From popular search keywords
        try {
            $rows = $this->db->fetchAll(
                "SELECT keyword, search_count FROM ai_search_keywords 
                 WHERE keyword LIKE ? 
                 ORDER BY search_count DESC 
                 LIMIT ?",
                [$prefix . '%', $limit]
            );
            foreach ($rows as $row) {
                $suggestions[] = [
                    'text'  => $row['keyword'],
                    'count' => (int) $row['search_count'],
                    'type'  => 'popular',
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        // From knowledge base questions
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT question FROM ai_knowledge_base 
                 WHERE question LIKE ? AND is_active = 1 
                 LIMIT ?",
                ['%' . $prefix . '%', $limit]
            );
            foreach ($rows as $row) {
                $suggestions[] = [
                    'text' => $row['question'],
                    'type' => 'knowledge',
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // From products
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT name FROM products WHERE status = 1 AND (name LIKE ? OR kn_name LIKE ?) LIMIT ?",
                [$prefix . '%', $prefix . '%', $limit]
            );
            foreach ($rows as $row) {
                $suggestions[] = [
                    'text' => $row['name'],
                    'type' => 'product',
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Curated default keywords shown when there is no search history yet.
     */
    private function defaultKeywords(): array
    {
        return [
            ['keyword' => 'exam preparation tips',      'search_count' => 120],
            ['keyword' => 'professional email format',   'search_count' => 98],
            ['keyword' => 'sql vs nosql',                'search_count' => 91],
            ['keyword' => 'workplace conflict resolution','search_count' => 84],
            ['keyword' => 'stress management',           'search_count' => 77],
            ['keyword' => 'career guidance after 12th',  'search_count' => 70],
            ['keyword' => 'startup idea validation',     'search_count' => 63],
            ['keyword' => 'tax saving options 80c',      'search_count' => 58],
        ];
    }

    /**
     * "Did you mean?" suggestions using Levenshtein
     */
    public function didYouMean(string $query): ?string
    {
        $words = $this->extractSearchWords($query);
        $corrected = [];

        try {
            $keywords = $this->db->fetchAll(
                "SELECT keyword FROM ai_search_keywords WHERE search_count > 3 ORDER BY search_count DESC LIMIT 200"
            );

            $knownWords = array_column($keywords, 'keyword');

            foreach ($words as $word) {
                $bestMatch = null;
                $bestDist = PHP_INT_MAX;

                foreach ($knownWords as $known) {
                    $dist = levenshtein(strtolower($word), strtolower($known));
                    if ($dist < $bestDist && $dist <= 2 && $dist > 0) {
                        $bestDist = $dist;
                        $bestMatch = $known;
                    }
                }

                $corrected[] = $bestMatch ?? $word;
            }

            $correctedQuery = implode(' ', $corrected);
            return ($correctedQuery !== $query) ? $correctedQuery : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get trending/popular searches
     */
    public function getTrending(int $limit = 10): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT keyword, search_count FROM ai_search_keywords
                 ORDER BY search_count DESC, last_searched DESC
                 LIMIT ?",
                [$limit]
            );
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Exception $e) {
            // fall through to defaults
        }

        // Fresh install / empty analytics — return curated defaults so the UI is useful
        return array_slice($this->defaultKeywords(), 0, $limit);
    }

    // ── Private Helpers ──────────────────────────────────────

    private function extractSearchWords(string $query): array
    {
        $words = preg_split('/\s+/', strtolower(trim($query)));
        return array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $this->stopWords));
    }

    private function expandWithSynonyms(string $query): array
    {
        $terms = [$query];
        $words = explode(' ', strtolower($query));

        foreach ($words as $word) {
            foreach ($this->synonyms as $key => $syns) {
                if ($word === $key || in_array($word, $syns)) {
                    foreach ($syns as $syn) {
                        $terms[] = str_ireplace($word, $syn, $query);
                    }
                }
            }
        }

        return array_unique($terms);
    }

    private function generateSnippet(string $content, string $query, int $length = 200): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);

        // Try to find query in content and center snippet around it
        $pos = stripos($content, $query);
        if ($pos !== false) {
            $start = max(0, $pos - 50);
            $snippet = substr($content, $start, $length);
            if ($start > 0) $snippet = '...' . $snippet;
            if (strlen($content) > $start + $length) $snippet .= '...';
            return $snippet;
        }

        return strlen($content) > $length ? substr($content, 0, $length) . '...' : $content;
    }

    private function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'student'  => 'fa-graduation-cap',
            'official' => 'fa-briefcase',
            'personal' => 'fa-heart',
            'product'  => 'fa-box',
            'faq'      => 'fa-question-circle',
            default    => 'fa-search',
        };
    }

    private function logSearch(string $query, string $type, ?string $category): void
    {
        try {
            $this->db->insert(
                "INSERT INTO ai_search_history (query, search_type, category, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
                [$query, $type, $category ?: null, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    private function trackKeyword(string $query): void
    {
        try {
            $this->db->execute(
                "INSERT INTO ai_search_keywords (keyword, search_count) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE search_count = search_count + 1, last_searched = NOW()",
                [strtolower(trim($query))]
            );
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get search suggestions based on current query
     */
    private function getSearchSuggestions(string $query): array
    {
        $suggestions = [];
        $words = $this->extractSearchWords($query);

        foreach ($words as $word) {
            if (isset($this->synonyms[$word])) {
                foreach ($this->synonyms[$word] as $syn) {
                    $suggestions[] = str_ireplace($word, $syn, $query);
                }
            }
        }

        return array_unique(array_slice($suggestions, 0, 5));
    }
}
