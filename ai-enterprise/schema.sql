-- ============================================================
-- KSR GURU JI — AI Enterprise Platform Database Schema
-- Version: 1.0.0 | MySQL 8.0+
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

-- ── ROLES & PERMISSIONS ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_roles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(50)  NOT NULL UNIQUE,
  `label`       VARCHAR(100) NOT NULL,
  `level`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `permissions` JSON         DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ai_roles` (`name`, `label`, `level`) VALUES
('super_admin',      'Super Admin',      1),
('administrator',    'Administrator',    2),
('manager',          'Manager',          3),
('employee',         'Employee',         4),
('delivery_partner', 'Delivery Partner', 5),
('customer',         'Customer',         6),
('guest',            'Guest',            7);

-- ── USERS ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_users` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid`            CHAR(36)     NOT NULL UNIQUE,
  `name`            VARCHAR(150) NOT NULL,
  `email`           VARCHAR(255) DEFAULT NULL UNIQUE,
  `phone`           VARCHAR(20)  DEFAULT NULL UNIQUE,
  `password_hash`   VARCHAR(255) NOT NULL,
  `avatar`          VARCHAR(500) DEFAULT NULL,
  `role_id`         INT UNSIGNED NOT NULL DEFAULT 6,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `is_verified`     TINYINT(1)   NOT NULL DEFAULT 0,
  `otp_code`        VARCHAR(10)  DEFAULT NULL,
  `otp_expires`     TIMESTAMP    NULL DEFAULT NULL,
  `remember_token`  VARCHAR(255) DEFAULT NULL,
  `login_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    TIMESTAMP    NULL DEFAULT NULL,
  `last_login`      TIMESTAMP    NULL DEFAULT NULL,
  `preferences`     JSON         DEFAULT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `ai_roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI CHAT HISTORY ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_chat_sessions` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid`          CHAR(36)      NOT NULL UNIQUE,
  `user_id`       INT UNSIGNED  DEFAULT NULL,
  `session_token` VARCHAR(128)  NOT NULL,
  `category`      ENUM('student','official','personal','general') DEFAULT 'general',
  `title`         VARCHAR(255)  DEFAULT 'New Conversation',
  `status`        ENUM('active','archived','deleted') DEFAULT 'active',
  `message_count` INT UNSIGNED  NOT NULL DEFAULT 0,
  `metadata`      JSON          DEFAULT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `ai_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_status` (`user_id`, `status`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id`  INT UNSIGNED    NOT NULL,
  `role`        ENUM('user','assistant','system') NOT NULL,
  `content`     TEXT            NOT NULL,
  `tokens_used` INT UNSIGNED    DEFAULT 0,
  `model_used`  VARCHAR(50)     DEFAULT 'builtin',
  `confidence`  DECIMAL(5,4)    DEFAULT NULL,
   `feedback`    ENUM('positive','negative') DEFAULT NULL,
  `metadata`    JSON            DEFAULT NULL,
  `created_at`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`session_id`) REFERENCES `ai_chat_sessions`(`id`) ON DELETE CASCADE,
  INDEX `idx_session_role` (`session_id`, `role`),
  FULLTEXT INDEX `idx_content_search` (`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SUPPORT TICKETS ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_support_tickets` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_number`  VARCHAR(20)   NOT NULL UNIQUE,
  `user_id`        INT UNSIGNED  DEFAULT NULL,
  `subject`        VARCHAR(255)  NOT NULL,
  `description`    TEXT          NOT NULL,
  `category`       VARCHAR(50)   DEFAULT 'general',
  `priority`       ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `status`         ENUM('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
  `assigned_to`    INT UNSIGNED  DEFAULT NULL,
  `ai_summary`     TEXT          DEFAULT NULL,
  `ai_suggested`   TEXT          DEFAULT NULL,
  `sla_deadline`   TIMESTAMP     NULL DEFAULT NULL,
  `resolved_at`    TIMESTAMP     NULL DEFAULT NULL,
  `satisfaction`   TINYINT UNSIGNED DEFAULT NULL,
  `tags`           JSON          DEFAULT NULL,
  `created_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)     REFERENCES `ai_users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `ai_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status_priority` (`status`, `priority`),
  INDEX `idx_user_tickets` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_ticket_messages` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`   INT UNSIGNED    NOT NULL,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `is_ai`       TINYINT(1)      NOT NULL DEFAULT 0,
  `content`     TEXT            NOT NULL,
  `attachments` JSON            DEFAULT NULL,
  `created_at`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `ai_support_tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `ai_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEARCH ANALYTICS ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_search_history` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `query`         VARCHAR(500)  NOT NULL,
  `search_type`   ENUM('text','voice','image','barcode','qr','ocr') DEFAULT 'text',
  `category`      VARCHAR(100)  DEFAULT NULL,
  `results_count` INT UNSIGNED  DEFAULT 0,
  `clicked_result`INT UNSIGNED  DEFAULT NULL,
  `response_ms`   INT UNSIGNED  DEFAULT NULL,
  `ip_address`    VARCHAR(45)   DEFAULT NULL,
  `user_agent`    VARCHAR(500)  DEFAULT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `ai_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_query` (`query`(100)),
  INDEX `idx_type` (`search_type`),
  FULLTEXT INDEX `idx_query_fulltext` (`query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_search_keywords` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `keyword`      VARCHAR(255) NOT NULL UNIQUE,
  `search_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_searched`TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_popular` (`search_count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI ANALYTICS ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_analytics` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_type`  VARCHAR(50)  NOT NULL,
  `event_data`  JSON         DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `session_id`  VARCHAR(128) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── UPLOADED FILES ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_uploaded_files` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `filename`     VARCHAR(255) NOT NULL,
  `original_name`VARCHAR(255) NOT NULL,
  `mime_type`    VARCHAR(100) NOT NULL,
  `file_size`    INT UNSIGNED NOT NULL,
  `file_path`    VARCHAR(500) NOT NULL,
  `purpose`      ENUM('chat','ocr','support','avatar','product','document') DEFAULT 'chat',
  `ocr_text`     TEXT         DEFAULT NULL,
  `metadata`     JSON         DEFAULT NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `ai_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_notifications` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED  NOT NULL,
  `title`       VARCHAR(255)  NOT NULL,
  `message`     TEXT          NOT NULL,
  `type`        ENUM('info','success','warning','error','ai') DEFAULT 'info',
  `channel`     ENUM('web','email','sms','whatsapp','push') DEFAULT 'web',
  `is_read`     TINYINT(1)    NOT NULL DEFAULT 0,
  `action_url`  VARCHAR(500)  DEFAULT NULL,
  `metadata`    JSON          DEFAULT NULL,
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `ai_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── API LOGS ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_api_logs` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED  DEFAULT NULL,
  `method`        VARCHAR(10)   NOT NULL,
  `endpoint`      VARCHAR(500)  NOT NULL,
  `request_body`  JSON          DEFAULT NULL,
  `response_code` SMALLINT UNSIGNED NOT NULL,
  `response_ms`   INT UNSIGNED  DEFAULT NULL,
  `ip_address`    VARCHAR(45)   DEFAULT NULL,
  `user_agent`    VARCHAR(500)  DEFAULT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_endpoint` (`endpoint`(100)),
  INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AUDIT LOGS ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_audit_logs` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED  DEFAULT NULL,
  `action`      VARCHAR(100)  NOT NULL,
  `entity_type` VARCHAR(50)   DEFAULT NULL,
  `entity_id`   INT UNSIGNED  DEFAULT NULL,
  `old_values`  JSON          DEFAULT NULL,
  `new_values`  JSON          DEFAULT NULL,
  `ip_address`  VARCHAR(45)   DEFAULT NULL,
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_action` (`user_id`, `action`),
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SYSTEM SETTINGS ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_system_settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name`    VARCHAR(100) NOT NULL UNIQUE,
  `value`       TEXT         DEFAULT NULL,
  `type`        ENUM('string','integer','boolean','json') DEFAULT 'string',
  `group_name`  VARCHAR(50)  DEFAULT 'general',
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ai_system_settings` (`key_name`, `value`, `type`, `group_name`, `description`) VALUES
('site_name',           'KSR GURU JI AI Enterprise', 'string',  'general', 'Platform name'),
('ai_enabled',          '1',                         'boolean', 'ai',      'Enable AI features'),
('ai_model',            'builtin',                   'string',  'ai',      'Default AI model'),
('chat_max_messages',   '100',                       'integer', 'chat',    'Max messages per session'),
('search_min_chars',    '2',                         'integer', 'search',  'Min characters for search'),
('support_auto_reply',  '1',                         'boolean', 'support', 'Auto-reply for tickets'),
('maintenance_mode',    '0',                         'boolean', 'general', 'Maintenance mode flag');

-- ── KNOWLEDGE BASE (for AI responses) ───────────────────────

CREATE TABLE IF NOT EXISTS `ai_knowledge_base` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category`    ENUM('student','official','personal','general','product','faq') NOT NULL,
  `question`    VARCHAR(500) NOT NULL,
  `answer`      TEXT         NOT NULL,
  `keywords`    VARCHAR(500) DEFAULT NULL,
  `language`    VARCHAR(10)  DEFAULT 'en',
  `priority`    TINYINT UNSIGNED DEFAULT 5,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT INDEX `idx_question` (`question`, `keywords`),
  FULLTEXT INDEX `idx_answer` (`answer`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEED KNOWLEDGE BASE ─────────────────────────────────────
INSERT IGNORE INTO `ai_knowledge_base` (`category`, `question`, `answer`, `keywords`, `priority`) VALUES
-- Student
('student', 'How to prepare for competitive exams?',
 'Here are proven strategies for competitive exam preparation:\n\n1. **Create a Study Schedule** - Allocate specific hours daily for each subject\n2. **Previous Year Papers** - Solve at least 10 years of past papers\n3. **Mock Tests** - Take weekly mock tests to build speed\n4. **Revision** - Revise weekly with short notes\n5. **Health** - Sleep 7-8 hours and exercise regularly\n6. **Focus Topics** - Identify high-weightage chapters first\n\nRemember: Consistency beats intensity. Study 4-5 focused hours daily rather than 12 unfocused hours.',
 'exam,preparation,competitive,study,tips,UPSC,JEE,NEET', 9),

('student', 'How to manage time during exams?',
 'Effective time management during exams:\n\n1. **Read all questions first** (5 minutes)\n2. **Start with easy questions** to build confidence\n3. **Allocate time per question** - divide total time by questions\n4. **Don''t get stuck** - move on if stuck for >3 minutes\n5. **Review time** - keep last 15 minutes for checking\n6. **Answer length** - match marks to effort\n\n⏰ Pro tip: Wear a watch. Practice with timed mock tests regularly.',
 'time,management,exam,study,planning,schedule', 8),

('student', 'Help with career guidance',
 'Career guidance framework:\n\n🎯 **Self-Assessment**\n- What subjects excite you?\n- What are your natural strengths?\n- What problems do you want to solve?\n\n📊 **Top Career Paths 2026**\n- AI/ML Engineering\n- Data Science\n- Healthcare & Biotech\n- Renewable Energy\n- Digital Marketing\n- Cybersecurity\n- UX/UI Design\n\n📝 **Action Steps**\n1. Take aptitude tests (free online)\n2. Research 3-5 careers deeply\n3. Find mentors in those fields\n4. Start building skills now (online courses)\n5. Get internships/projects for experience',
 'career,guidance,job,future,course,stream,branch', 9),

('student', 'How to improve concentration while studying?',
 'Boost your study concentration:\n\n🧠 **Environment**\n- Clean, quiet study space\n- Remove phone distractions (use Forest app)\n- Good lighting and comfortable seating\n\n⏱️ **Technique**\n- **Pomodoro Method**: 25 min study + 5 min break\n- Active recall > passive reading\n- Teach what you learn to someone else\n\n🏃 **Lifestyle**\n- Exercise 30 min daily\n- Sleep 7-8 hours\n- Stay hydrated\n- Eat brain foods (nuts, berries, fish)\n\n🎵 **Bonus**: Try lo-fi study music or brown noise',
 'concentration,focus,study,distraction,attention', 8),

-- Official
('official', 'How to write a professional email?',
 'Professional email template:\n\n📧 **Structure**\n```\nSubject: Clear, specific subject line\n\nDear [Name/Title],\n\nParagraph 1: Purpose of the email\nParagraph 2: Details/context\nParagraph 3: Action needed + deadline\n\nBest regards,\n[Your Name]\n[Your Title]\n[Contact Info]\n```\n\n✅ **Tips**\n- Keep it under 200 words\n- Use professional tone\n- Proofread before sending\n- CC only necessary people\n- Reply within 24 hours',
 'email,professional,office,work,writing,communication', 8),

('official', 'How to handle workplace conflict?',
 'Resolving workplace conflict:\n\n🤝 **Step-by-Step Approach**\n1. **Stay Calm** - Don''t react emotionally\n2. **Listen First** - Understand their perspective\n3. **Private Discussion** - Never confront publicly\n4. **Focus on Issues** - Not personalities\n5. **Find Common Ground** - Identify shared goals\n6. **Propose Solutions** - Come with ideas, not just complaints\n7. **Document** - Keep written records\n8. **Escalate if Needed** - Go to HR if unresolved\n\n⚠️ **If harassment/discrimination**: Report to HR immediately. Document everything with dates and witnesses.',
 'conflict,workplace,office,colleague,boss,HR,issue', 8),

('official', 'Help with tax filing',
 'Tax Filing Guide (India):\n\n📋 **Important Dates**\n- ITR filing deadline: July 31 (usually)\n- Advance tax: Quarterly\n\n📑 **Documents Needed**\n- PAN Card\n- Form 16 (from employer)\n- Bank statements\n- Investment proofs (80C, 80D)\n- Aadhaar card\n\n💰 **Tax Saving Options (Section 80C)**\n- PPF, EPF, ELSS\n- Life insurance premium\n- Home loan principal\n- NSC, Tax-saver FD\n- Tuition fees\n\n🖥️ **How to File**\n1. Visit incometax.gov.in\n2. Login with PAN\n3. Choose correct ITR form\n4. Fill income details\n5. Claim deductions\n6. Verify with Aadhaar OTP',
 'tax,filing,ITR,income,deduction,80C,refund,PAN', 9),

-- Personal
('personal', 'How to manage stress and anxiety?',
 'Stress & anxiety management:\n\n🧘 **Immediate Relief (5 minutes)**\n- Box breathing: Inhale 4s → Hold 4s → Exhale 4s → Hold 4s\n- Ground yourself: Name 5 things you see, 4 you hear, 3 you touch\n- Splash cold water on face\n\n📅 **Daily Habits**\n- 30 min exercise (walking counts!)\n- 10 min meditation (try Headspace app)\n- 7-8 hours sleep\n- Limit caffeine after 2 PM\n- Journal your thoughts\n\n🚫 **Avoid**\n- Excessive social media\n- Over-committing\n- Ignoring your feelings\n\n❤️ **Remember**: It''s okay to not be okay. If stress persists, please consult a mental health professional. NIMHANS helpline: 080-46110007',
 'stress,anxiety,mental,health,wellness,meditation,calm', 9),

('personal', 'Tips for better sleep',
 'Better sleep guide:\n\n🌙 **Sleep Hygiene**\n- Same bedtime every day (even weekends)\n- Cool, dark room (18-20°C)\n- No screens 1 hour before bed\n- No caffeine after 2 PM\n\n😴 **Pre-Sleep Routine**\n1. Warm shower/bath\n2. Light stretching or yoga\n3. Read a physical book\n4. Deep breathing exercises\n5. Gratitude journaling (3 things)\n\n🍽️ **Food & Drink**\n- Warm milk with turmeric\n- Chamomile tea\n- Avoid heavy meals 3 hours before bed\n- Banana (natural melatonin)\n\n⏰ **Ideal Sleep Duration**\n- Adults: 7-9 hours\n- Teens: 8-10 hours\n- Children: 9-12 hours',
 'sleep,insomnia,rest,night,health,routine', 8),

('personal', 'How to build healthy habits?',
 'Habit building framework:\n\n🎯 **The 2-Minute Rule**\nStart any new habit with just 2 minutes:\n- \"Read more\" → Read 1 page\n- \"Exercise\" → Put on gym shoes\n- \"Meditate\" → Sit and breathe for 2 min\n\n📆 **21/66 Day Rule**\n- 21 days to form a habit\n- 66 days to make it automatic\n\n🔗 **Habit Stacking**\nAttach new habits to existing ones:\n- \"After I pour my coffee, I will meditate for 2 min\"\n- \"After I sit at my desk, I will write my to-do list\"\n\n📊 **Track Progress**\n- Use a habit tracker app\n- Don''t break the chain\n- Celebrate small wins\n\n💪 **Top healthy habits**: Drink water, walk 10K steps, read daily, sleep 8 hrs, express gratitude',
 'habits,health,routine,discipline,motivation,goals', 8);
