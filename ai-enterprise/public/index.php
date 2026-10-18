<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KSR GURU JI · AI Enterprise Platform</title>
  <meta name="description" content="AI-powered multimodal chat and problem-solving platform for students, professionals, developers, and businesses in English & Kannada.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<!-- Background Effects -->
<div class="bg-grid"></div>
<div class="bg-orb orb-1"></div>
<div class="bg-orb orb-2"></div>
<div class="bg-orb orb-3"></div>

<div class="app-wrapper">

  <!-- ═══ Top Bar ═══ -->
  <header class="top-bar">
    <a href="#" class="logo" onclick="showPanel('panel-dashboard')">
      <div class="logo-icon"><i class="fas fa-brain"></i></div>
      <div>
        <div class="logo-text">KSR GURU JI</div>
        <div class="logo-sub" id="logoSubText">AI Enterprise Platform</div>
      </div>
    </a>
    
    <div class="top-bar-actions">
      <!-- Language Dropdown -->
      <div class="nav-select-wrapper">
        <i class="fas fa-language"></i>
        <select id="langSelect" onchange="setLanguage(this.value)">
          <option value="en">English</option>
          <option value="kn">ಕನ್ನಡ (Kannada)</option>
        </select>
      </div>

      <!-- Model Selector -->
      <div class="nav-select-wrapper model-select-wrapper">
        <i class="fas fa-microchip"></i>
        <select id="modelSelect" title="AI Model">
          <option value="gemini-3.5-flash">Gemini 3.5 Flash</option>
          <option value="gemini-3-flash-preview">Gemini 3 Flash</option>
          <option value="gemini-flash-latest">Gemini Flash Latest</option>
          <option value="gemini-flash-lite-latest">Gemini Flash Lite</option>
          <option value="gemini-3.1-pro-preview">Gemini 3.1 Pro</option>
        </select>
      </div>

      <span class="badge-status"><i class="fas fa-circle" style="font-size:.5rem;margin-right:4px"></i> <span id="statusText">AI Online</span></span>
      <button class="btn-icon" onclick="initDatabase()" id="initDbBtn" title="Initialize Database"><i class="fas fa-database"></i></button>
      <button class="btn-icon" onclick="toggleMobileMenu()" title="Menu" style="display:none" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    </div>
  </header>

  <div class="main-content">

    <!-- ═══ Sidebar ═══ -->
    <nav class="sidebar">
      <div class="sidebar-section">
        <div class="sidebar-label" id="labelMain">Main</div>
        <div class="sidebar-item active" data-panel="panel-dashboard" onclick="showPanel('panel-dashboard')">
          <i class="fas fa-th-large"></i> <span id="menuDashboard">Dashboard</span>
        </div>
        <div class="sidebar-item" data-panel="panel-chat" onclick="showPanel('panel-chat')">
          <i class="fas fa-comments"></i> <span id="menuChat">AI Chat</span>
          <span class="badge-count">AI</span>
        </div>
        <div class="sidebar-item" data-panel="panel-search" onclick="showPanel('panel-search')">
          <i class="fas fa-search"></i> <span id="menuSearch">Smart Search</span>
        </div>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-label" id="labelCategories">AI Solutions</div>
        <div class="sidebar-item" data-category="student" onclick="selectCategoryBtn('student')">
          <i class="fas fa-graduation-cap" style="color:var(--accent-purple)"></i> <span id="catStudent">Students</span>
        </div>
        <div class="sidebar-item" data-category="office" onclick="selectCategoryBtn('office')">
          <i class="fas fa-briefcase" style="color:var(--accent-blue)"></i> <span id="catOffice">Office / Work</span>
        </div>
        <div class="sidebar-item" data-category="programming" onclick="selectCategoryBtn('programming')">
          <i class="fas fa-code" style="color:var(--accent-orange)"></i> <span id="catProgramming">Programming</span>
        </div>
        <div class="sidebar-item" data-category="business" onclick="selectCategoryBtn('business')">
          <i class="fas fa-chart-line" style="color:var(--accent-green)"></i> <span id="catBusiness">Business</span>
        </div>
        <div class="sidebar-item" data-category="general" onclick="selectCategoryBtn('general')">
          <i class="fas fa-globe" style="color:var(--accent-cyan)"></i> <span id="catGeneral">General</span>
        </div>
        <div class="sidebar-item" data-category="customer_support" onclick="selectCategoryBtn('customer_support')">
          <i class="fas fa-headset" style="color:#ef4444"></i> <span id="catSupport">Customer Support</span>
        </div>
      </div>

      <!-- Recent Conversations Section to Maintain Chat History -->
      <div class="sidebar-section">
        <div class="sidebar-label sidebar-history-label">
          <span id="labelHistory">Recent Chats</span>
          <button class="btn-small-add" onclick="startNewChatSession()" title="New Chat"><i class="fas fa-plus"></i></button>
        </div>
        <div class="history-list" id="historyList">
          <!-- Populated from AJAX -->
          <div class="empty-history" id="emptyHistoryText">No recent chats</div>
        </div>
      </div>

      <div class="sidebar-footer">
        <div class="sidebar-version">🚀 AI Enterprise v1.1</div>
        <div class="sidebar-powered" id="poweredByText">Powered by KSR GURU JI Intelligence</div>
      </div>
    </nav>

    <!-- ═══ Content Area ═══ -->
    <main class="content-area">

      <!-- ── Dashboard Panel ── -->
      <div class="panel active" id="panel-dashboard">
        <div class="panel-header">
          <h2 id="dashTitle">🧠 AI Command Center</h2>
          <p id="dashSub">Solve academic, official, programming, and business queries with multimodal Gemini AI</p>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
            <div><div class="stat-value" id="statSessions">0</div><div class="stat-label" id="lblStatSessions">Chat Sessions</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
            <div><div class="stat-value" id="statMessages">0</div><div class="stat-label" id="lblStatMessages">AI Messages</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon cyan"><i class="fas fa-search"></i></div>
            <div><div class="stat-value" id="statSearches">0</div><div class="stat-label" id="lblStatSearches">Searches</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-database"></i></div>
            <div><div class="stat-value" id="statKnowledge">20+</div><div class="stat-label" id="lblStatKnowledge">KB Solutions</div></div>
          </div>
        </div>

        <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:14px" id="lblChooseCategory">Select AI Scenario</h3>
        <div class="welcome-grid">
          <!-- Student Card -->
          <div class="welcome-card student" onclick="selectCategoryBtn('student')">
            <div class="welcome-card-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <h3 id="cardStudentTitle">Student Problems</h3>
            <p id="cardStudentDesc">Exams, preparation, careers, and academic assignments guidance.</p>
            <div class="topics">
              <span class="topic-tag">Exam Prep</span>
              <span class="topic-tag">Career Help</span>
              <span class="topic-tag">Study Tips</span>
            </div>
          </div>
          <!-- Office Card -->
          <div class="welcome-card official" onclick="selectCategoryBtn('office')">
            <div class="welcome-card-icon"><i class="fa-solid fa-briefcase"></i></div>
            <h3 id="cardOfficeTitle">Work / Office Problems</h3>
            <p id="cardOfficeDesc">Resume building, professional emails, and workplace conflicts resolution.</p>
            <div class="topics">
              <span class="topic-tag">Emails</span>
              <span class="topic-tag">HR Advice</span>
              <span class="topic-tag">Resume</span>
            </div>
          </div>
          <!-- Programming Card -->
          <div class="welcome-card programming" onclick="selectCategoryBtn('programming')">
            <div class="welcome-card-icon"><i class="fa-solid fa-code"></i></div>
            <h3 id="cardProgrammingTitle">Programming Problems</h3>
            <p id="cardProgrammingDesc">Code explanations, refactoring assistance, debug exceptions, and database index queries.</p>
            <div class="topics">
              <span class="topic-tag">Debugging</span>
              <span class="topic-tag">Refactoring</span>
              <span class="topic-tag">Databases</span>
            </div>
          </div>
          <!-- Business Card -->
          <div class="welcome-card business" onclick="selectCategoryBtn('business')">
            <div class="welcome-card-icon"><i class="fa-solid fa-chart-line"></i></div>
            <h3 id="cardBusinessTitle">Business Problems</h3>
            <p id="cardBusinessDesc">Startup ideation, marketing strategy, accounting basics, and growth metrics.</p>
            <div class="topics">
              <span class="topic-tag">Marketing</span>
              <span class="topic-tag">sales</span>
              <span class="topic-tag">Scaling</span>
            </div>
          </div>
          <!-- General Card -->
          <div class="welcome-card general" onclick="selectCategoryBtn('general')">
            <div class="welcome-card-icon"><i class="fa-solid fa-globe"></i></div>
            <h3 id="cardGeneralTitle">General Questions</h3>
            <p id="cardGeneralDesc">Everyday life hacks, general knowledge trivia, languages translations, and summaries.</p>
            <div class="topics">
              <span class="topic-tag">Q&A</span>
              <span class="topic-tag">Trivia</span>
              <span class="topic-tag">Translation</span>
            </div>
          </div>
          <!-- Customer Support Card -->
          <div class="welcome-card official" style="border-left: 3px solid #ef4444;" onclick="selectCategoryBtn('customer_support')">
            <div class="welcome-card-icon" style="background:#fef2f2;color:#ef4444;"><i class="fa-solid fa-headset"></i></div>
            <h3 id="cardSupportTitle">Customer Support</h3>
            <p id="cardSupportDesc">Help with KSR GURU JI app issues, login, payments, and order tracking.</p>
            <div class="topics">
              <span class="topic-tag">App Issues</span>
              <span class="topic-tag">Refunds</span>
              <span class="topic-tag">Orders</span>
            </div>
          </div>
          <!-- Translation Card -->
          <div class="welcome-card translation" onclick="selectCategoryBtn('general')">
            <div class="welcome-card-icon"><i class="fa-solid fa-language"></i></div>
            <h3 id="cardTranslationTitle">Linguistics & Translation</h3>
            <p id="cardTranslationDesc">Translate text between English, Kannada, and other languages with contextual accuracy.</p>
            <div class="topics">
              <span class="topic-tag">English-Kannada</span>
              <span class="topic-tag">Rephrase</span>
              <span class="topic-tag">Vocabulary</span>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:24px">
          <h3 style="font-size:1.05rem;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-sparkles" style="color:var(--accent-orange)"></i> <span id="quickStartTitle">Feature Overview</span>
          </h3>
          <p style="font-size:.85rem;color:var(--text-secondary);line-height:1.7" id="quickStartDesc">
            <strong>📸 Multimodal Input:</strong> Click the paperclip icon in the chat to attach an image (diagrams, math formulas, screenshots, errors) and ask Gemini to explain it.<br>
            <strong>🗣️ Voice Commands:</strong> Press the microphone icon to type using Google voice input.<br>
            <strong>💬 Chat Threads:</strong> Use the plus button in the sidebar history section to start a new chat thread and maintain previous ones.<br>
            <strong>🌐 Bilingual:</strong> Flip between English and Kannada instantly using the language dropdown.
          </p>
        </div>
      </div>

      <!-- ── Chat Panel ── -->
      <div class="panel" id="panel-chat">
        <div class="chat-container">
          <div class="chat-category-bar">
            <button class="cat-btn active" data-category="general" onclick="setCategory('general')">
              <i class="fas fa-globe"></i> General
            </button>
            <button class="cat-btn" data-category="student" onclick="setCategory('student')">
              <i class="fas fa-graduation-cap"></i> Student
            </button>
            <button class="cat-btn" data-category="office" onclick="setCategory('office')">
              <i class="fas fa-briefcase"></i> Office
            </button>
            <button class="cat-btn" data-category="programming" onclick="setCategory('programming')">
              <i class="fas fa-code"></i> Programming
            </button>
            <button class="cat-btn" data-category="business" onclick="setCategory('business')">
              <i class="fas fa-chart-line"></i> Business
            </button>
            <button class="cat-btn" data-category="customer_support" onclick="setCategory('customer_support')">
              <i class="fas fa-headset"></i> Support
            </button>
          </div>

          <div class="chat-messages" id="chatMessages"></div>

          <!-- Quick prompt chips (hidden once the conversation starts) -->
          <div class="quick-chips" id="quickChips">
            <button class="quick-chip" onclick="sendSuggestion('How to prepare for competitive exams?')">📚 Exam preparation tips</button>
            <button class="quick-chip" onclick="sendSuggestion('How to write a professional email?')">💼 Professional email help</button>
            <button class="quick-chip" onclick="sendSuggestion('Explain SQL vs NoSQL with examples')">💻 SQL vs NoSQL</button>
            <button class="quick-chip" onclick="sendSuggestion('How to manage stress and anxiety?')">🧘 Stress management</button>
          </div>

          <!-- Image Attachment Preview -->
          <div class="image-preview" id="imagePreviewWrapper">
            <img src="" id="imgPreviewThumb" alt="Attachment Thumbnail">
            <button class="btn-clear-preview" onclick="clearAttachedImage()" title="Remove image"><i class="fas fa-times"></i></button>
          </div>

          <div class="chat-input-bar">
            <div class="chat-input-wrapper">
              <!-- Attached File field -->
              <input type="file" id="imageFileInput" accept="image/*" style="display: none;" onchange="handleImageFileSelect(event)">
              <button class="attach-btn" id="attachBtn" onclick="document.getElementById('imageFileInput').click()" title="Upload Image / Screenshot">
                <i class="fas fa-paperclip"></i>
              </button>
              <textarea id="chatInput" rows="1" placeholder="Ask me anything — studies, work, health, life..."></textarea>
              <button class="voice-btn" id="voiceBtn" onclick="toggleVoice()" title="Voice Input">
                <i class="fas fa-microphone"></i>
              </button>
            </div>
            <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()" title="Send">
              <i class="fas fa-paper-plane"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- ── Search Panel ── -->
      <div class="panel" id="panel-search">
        <div class="search-hero">
          <h2>🔍 <span id="searchHeroTitle">Smart AI Search</span></h2>
          <p id="searchHeroDesc">Search across knowledge base, store products, and solutions</p>
        </div>
        <div class="search-box">
          <i class="fas fa-search" style="color:var(--text-muted)"></i>
          <input type="text" id="searchInput" placeholder="Search for solutions, products, advice...">
          <button class="voice-btn" onclick="toggleSearchVoice()" title="Voice Search" style="border:none;width:36px;height:36px">
            <i class="fas fa-microphone"></i>
          </button>
          <button class="search-go" onclick="performSearch()"><i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="trending-bar" id="trendingBar">
          <!-- populated dynamically with trending searches -->
        </div>
        <div class="search-results" id="searchResults"></div>
      </div>

      <!-- ── Analytics Panel ── -->
      <div class="panel" id="panel-analytics">
        <div class="panel-header">
          <h2 id="anTitle">📊 AI Analytics</h2>
          <p id="anDesc">Platform usage statistics and insights</p>
        </div>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
            <div><div class="stat-value" id="statSessions2">0</div><div class="stat-label" id="lblStatSessions2">Total Chat Sessions</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
            <div><div class="stat-value" id="statMessages2">0</div><div class="stat-label" id="lblStatMessages2">Total Messages</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon cyan"><i class="fas fa-search"></i></div>
            <div><div class="stat-value" id="statSearches2">0</div><div class="stat-label" id="lblStatSearches2">Total Searches</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-database"></i></div>
            <div><div class="stat-value" id="statKnowledge2">20+</div><div class="stat-label" id="lblStatKnowledge2">Support Solutions</div></div>
          </div>
        </div>
        <div class="card" style="margin-top:16px">
          <h3 style="font-size:1rem;font-weight:600;margin-bottom:8px" id="anFutureTitle">📈 Business Intelligence</h3>
          <p style="font-size:.85rem;color:var(--text-secondary)" id="anFutureDesc">
            Platform integration logs: Database checks, response latency, semantic matches tracker, bilingual search analysis, and voice conversion statistics are updated live from system entries.
          </p>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="app.js"></script>
<script>
  if (window.innerWidth <= 768) document.getElementById('mobileMenuBtn').style.display = 'flex';
  window.addEventListener('resize', () => {
    document.getElementById('mobileMenuBtn').style.display = window.innerWidth <= 768 ? 'flex' : 'none';
  });
</script>
</body>
</html>
