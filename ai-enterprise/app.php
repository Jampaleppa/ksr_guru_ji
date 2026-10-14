<?php
// ============================================================
// AI Enterprise Platform - Configuration
// ============================================================

return [
    // ── Application ──────────────────────────────────────────
    'app' => [
        'name'      => 'KSR GURU JI - AI Enterprise',
        'version'   => '1.2.0',
        'env'       => getenv('APP_ENV') ?: 'production',  // development | production
        'debug'     => (getenv('APP_ENV') ?: 'production') === 'development',
        'timezone'  => 'Asia/Kolkata',
        'locale'    => 'en',
        'base_url'  => getenv('APP_BASE_URL') ?: 'https://ksrguruji.ifree.page/ai-enterprise',
        'parent_url'=> getenv('APP_PARENT_URL') ?: 'https://ksrguruji.ifree.page',
    ],

    // ── Database ─────────────────────────────────────────────
    'database' => [
        'host'    => getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com',
        'port'    => 3306,
        'name'    => getenv('DB_NAME') ?: 'if0_43170510_ksr_guru',
        'user'    => getenv('DB_USER') ?: 'if0_43170510',
        'pass'    => getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],

    // ── AI Services ──────────────────────────────────────────
    'ai' => [
        // OpenAI / GPT
        'openai_api_key'  => '',  // Set your OpenAI API key
        'openai_model'    => 'gpt-4o-mini',
        'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',

        // Google Gemini (primary provider)
        'gemini_api_key'  => 'AIzaSyA7ZeHIhh7k5eHkDK2fPd6i4nkiw8qI08E',
        'gemini_model'    => 'gemini-3.5-flash',
        'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
        // Models are tried top-down; deprecated 2.x models are excluded because
        // Google returned 404 "no longer available to new users" for them.
        'gemini_models'   => [
            'gemini-3.5-flash'     => 'Gemini 3.5 Flash (recommended)',
            'gemini-3-flash-preview' => 'Gemini 3 Flash (preview)',
            'gemini-flash-latest'  => 'Gemini Flash Latest',
            'gemini-flash-lite-latest' => 'Gemini Flash Lite (fastest)',
            'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite (preview)',
            'gemini-3.1-pro-preview'=> 'Gemini 3.1 Pro (most capable)',
        ],
        'gemini_fallback_chain' => [
            'gemini-3.5-flash',
            'gemini-3-flash-preview',
            'gemini-flash-latest',
            'gemini-flash-lite-latest',
        ],
        'gemini_timeout'  => 45,   // seconds — allows multimodal/long answers

        // OCR
        'tesseract_path'  => 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'ocr_languages'   => 'eng+kan',  // English + Kannada

        // Ollama (Open Source AI)
        'ollama_endpoint' => 'http://localhost:11434/api/chat',
        'ollama_model'    => 'llama3', // or mistral, etc.

        // Fallback: use built-in AI when no API key
        'use_builtin'     => true,

        // Conversation memory sent to the model (number of prior turns)
        'context_turns'   => 10,
    ],

    // ── Security ─────────────────────────────────────────────
    'security' => [
        'jwt_secret'         => 'ksr_guru_ai_enterprise_2026_secret_key',
        'jwt_expiry'         => 86400,      // 24 hours
        'csrf_enabled'       => true,
        'cors_origins'       => ['*'],
        'password_min_length'=> 8,
        'max_login_attempts' => 5,
        'lockout_duration'   => 900,        // 15 minutes
        'otp_expiry'         => 300,        // 5 minutes
    ],

    // ── Rate Limiting ────────────────────────────────────────
    'rate_limit' => [
        'enabled'      => true,
        'max_requests' => 100,      // per window
        'window'       => 60,       // seconds
        'ai_requests'  => 30,       // AI-specific limit per window
        'search_limit' => 60,       // search requests per window
    ],

    // ── File Upload ──────────────────────────────────────────
    'upload' => [
        'max_size'       => 10 * 1024 * 1024,  // 10 MB
        'allowed_images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
        'allowed_docs'   => ['pdf', 'doc', 'docx', 'txt', 'csv', 'xlsx'],
        'upload_dir'     => __DIR__ . '/../storage/uploads/',
        'ocr_dir'        => __DIR__ . '/../storage/ocr/',
    ],

    // ── Search ───────────────────────────────────────────────
    'search' => [
        'min_query_length'   => 2,
        'max_results'        => 50,
        'autocomplete_limit' => 8,
        'fuzzy_threshold'    => 0.6,
        'enable_semantic'    => true,
        'enable_voice'       => true,
        'enable_image'       => true,
    ],

    // ── User Roles ───────────────────────────────────────────
    'roles' => [
        'super_admin'      => 1,
        'administrator'    => 2,
        'manager'          => 3,
        'employee'         => 4,
        'delivery_partner' => 5,
        'customer'         => 6,
        'guest'            => 7,
    ],

    // ── Logging ──────────────────────────────────────────────
    'logging' => [
        'enabled'   => true,
        'level'     => 'debug',   // debug | info | warning | error
        'log_dir'   => __DIR__ . '/../storage/logs/',
        'max_files' => 30,
    ],

    // ── Cache ────────────────────────────────────────────────
    'cache' => [
        'driver'    => 'file',     // file | redis
        'cache_dir' => __DIR__ . '/../storage/cache/',
        'ttl'       => 3600,       // 1 hour default
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
        ],
    ],

    // ── Notifications ────────────────────────────────────────
    'notifications' => [
        'whatsapp_enabled' => false,
        'whatsapp_api_key' => '',
        'email_enabled'    => false,
        'smtp_host'        => '',
        'smtp_port'        => 587,
        'smtp_user'        => '',
        'smtp_pass'        => '',
        'sms_enabled'      => false,
        'sms_api_key'      => '',
    ],

    // ── AI Problem Categories ────────────────────────────────
    'problem_categories' => [
        'student' => [
            'label'    => 'Student Problems',
            'icon'     => 'fa-graduation-cap',
            'color'    => '#7c3aed',
            'topics'   => [
                'homework_help', 'exam_preparation', 'career_guidance',
                'study_tips', 'project_ideas', 'subject_doubts',
                'time_management', 'competitive_exams'
            ],
        ],
        'office' => [
            'label'    => 'Office / Work Problems',
            'icon'     => 'fa-briefcase',
            'color'    => '#3b82f6',
            'topics'   => [
                'workplace_issues', 'hr_policies', 'meeting_prep',
                'document_help', 'professional_email', 'career_growth'
            ],
        ],
        'programming' => [
            'label'    => 'Programming Problems',
            'icon'     => 'fa-code',
            'color'    => '#f59e0b',
            'topics'   => [
                'debugging_errors', 'code_refactoring', 'algorithm_design',
                'database_queries', 'api_integration', 'learning_new_framework'
            ],
        ],
        'business' => [
            'label'    => 'Business Problems',
            'icon'     => 'fa-chart-line',
            'color'    => '#10b981',
            'topics'   => [
                'business_planning', 'financial_advice', 'marketing_strategy',
                'startup_ideas', 'customer_acquisition', 'tax_saving'
            ],
        ],
        'general' => [
            'label'    => 'General Problems',
            'icon'     => 'fa-globe',
            'color'    => '#06b6d4',
            'topics'   => [
                'general_qa', 'trivia', 'translation',
                'summarization', 'problem_solving', 'lifestyle_tips'
            ],
        ],
    ],
];
