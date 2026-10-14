// ============================================================
// KSR GURU JI — AI Enterprise Dashboard JavaScript
// Supporting: Bilingual (EN/KN), Multimodality, Chat History
// ============================================================

const API_BASE = "../api.php";

// Unique client browser token (corresponds to session_token column in SQL)
let clientToken = localStorage.getItem("ai_client_token");
if (!clientToken) {
  clientToken =
    "ct_" + Math.random().toString(36).substring(2, 15) + "_" + Date.now();
  localStorage.setItem("ai_client_token", clientToken);
}

// Global Application State
let activeSessionUuid = localStorage.getItem("active_session_uuid") || "";
let selectedLanguage = localStorage.getItem("ai_language") || "en";
let currentCategory = "general";
let isRecording = false;
let recognition = null;
let attachedImage = null; // { data: 'base64_string', mime: 'type' }
let searchTimeout = null;
let lastUserMessage = ""; // used by regenerateResponse()

// Translation resources for absolute Bilingual Support (English & Kannada)
const translations = {
  en: {
    logoSub: "AI Enterprise Platform",
    statusOnline: "AI Online",
    lblMain: "Main",
    menuDashboard: "Dashboard",
    menuChat: "AI Chat",
    menuSearch: "Smart Search",
    lblCategories: "AI Solutions",
    catStudent: "Students",
    catOffice: "Office / Work",
    catProgramming: "Programming",
    catBusiness: "Business",
    catGeneral: "General",
    lblHistory: "Recent Chats",
    emptyHistory: "No recent chats",
    dashTitle: "🧠 AI Command Center",
    dashSub:
      "Solve academic, official, programming, and business queries with multimodal Gemini AI",
    lblStatSessions: "Chat Sessions",
    lblStatMessages: "AI Messages",
    lblStatSearches: "Searches",
    lblStatKnowledge: "KB Solutions",
    lblChooseCategory: "Select AI Scenario",
    cardStudentTitle: "Student Problems",
    cardStudentDesc:
      "Exams, preparation, careers, and academic assignments guidance.",
    cardOfficeTitle: "Work / Office Problems",
    cardOfficeDesc:
      "Resume building, professional emails, and workplace conflicts resolution.",
    cardProgrammingTitle: "Programming Problems",
    cardProgrammingDesc:
      "Code explanations, refactoring assistance, debug exceptions, and database index queries.",
    cardBusinessTitle: "Business Problems",
    cardBusinessDesc:
      "Startup ideation, marketing strategy, accounting basics, and growth metrics.",
    cardGeneralTitle: "General Questions",
    cardGeneralDesc:
      "Everyday life hacks, general knowledge trivia, languages translations, and summaries.",
    cardTranslationTitle: "Linguistics & Translation",
    cardTranslationDesc:
      "Translate text between English, Kannada, and other languages with contextual accuracy.",
    quickStartTitle: "Feature Overview",
    quickStartDesc:
      "<strong>📸 Multimodal Input:</strong> Click the paperclip icon in the chat to attach an image (diagrams, math formulas, screenshots, errors) and ask Gemini to explain it.<br><strong>🗣️ Voice Commands:</strong> Press the microphone icon to type using Google voice input.<br><strong>💬 Chat Threads:</strong> Use the plus button in the sidebar history section to start a new chat thread and maintain previous ones.<br><strong>🌐 Bilingual:</strong> Flip between English and Kannada instantly using the language dropdown.",
    chatPlaceholder:
      "Ask me anything — studies, work, programming, business...",
    searchHeroTitle: "Smart AI Search",
    searchHeroDesc:
      "Search across knowledge base, store products, and solutions",
    searchInputPlaceholder: "Search for solutions, products, advice...",
    anTitle: "📊 AI Analytics",
    anDesc: "Platform usage statistics and insights",
    lblStatSessions2: "Total Chat Sessions",
    lblStatMessages2: "Total Messages",
    lblStatSearches2: "Total Searches",
    lblStatKnowledge2: "Support Solutions",
    anFutureTitle: "📈 Business Intelligence",
    anFutureDesc:
      "Platform integration logs: Database checks, response latency, semantic matches tracker, bilingual search analysis, and voice conversion statistics are updated live from system entries.",
    poweredBy: "Powered by KSR GURU JI Intelligence",
    welcomeTitle:
      "Welcome to **KSR GURU JI AI** 🧠✨\n\nI'm your intelligent assistant ready to help with:\n\n🎓 **Student Problems** — Exam prep, career guidance\n💼 **Office Problems** — Workplace documents, HR rules\n💻 **Programming Problems** — Debugging errors, code logic\n📈 **Business Problems** — Growth strategy, startup tips\n\nType your question below or click a suggestion! You can also attach image diagrams or screenshots.",
    welcomeSug0: "How to prepare for competitive exams?",
    welcomeSug1: "Help with tax saving tips",
    welcomeSug2: "Explain the difference between SQL and NoSQL",
  },
  kn: {
    logoSub: "ಎಐ ಎಂಟರ್‌ಪ್ರೈಸ್ ವೇದಿಕೆ",
    statusOnline: "ಎಐ ಆನ್‌ಲೈನ್",
    lblMain: "ಮುಖ್ಯ",
    menuDashboard: "ಡ್ಯಾಶ್‌ಬೋರ್ಡ್",
    menuChat: "ಎಐ ಚಾಟ್",
    menuSearch: "ಸ್ಮಾರ್ಟ್ ಹುಡುಕಾಟ",
    lblCategories: "ಎಐ ಪರಿಹಾರಗಳು",
    catStudent: "ವಿದ್ಯಾರ್ಥಿಗಳು",
    catOffice: "ಕಚೇರಿ / ಕೆಲಸ",
    catProgramming: "ಪ್ರೋಗ್ರಾಮಿಂಗ್",
    catBusiness: "ವ್ಯಾಪಾರ",
    catGeneral: "ಸಾಮಾನ್ಯ",
    lblHistory: "ಇತ್ತೀಚಿನ ಚಾಟ್‌ಗಳು",
    emptyHistory: "ಯಾವುದೇ ಚಾಟ್‌ಗಳಿಲ್ಲ",
    dashTitle: "🧠 ಎಐ ಕಮಾಂಡ್ ಸೆಂಟರ್",
    dashSub:
      "ವಿದ್ಯಾರ್ಥಿ, ಕಚೇರಿ, ಪ್ರೋಗ್ರಾಮಿಂಗ್ ಮತ್ತು ವ್ಯವಹಾರ ಪ್ರಶ್ನೆಗಳನ್ನು ಜೆಮಿನಿ ಎಐನೊಂದಿಗೆ ಪರಿಹರಿಸಿ",
    lblStatSessions: "ಚಾಟ್ ಸೆಷನ್‌ಗಳು",
    lblStatMessages: "ಎಐ ಸಂದೇಶಗಳು",
    lblStatSearches: "ಹುಡುಕಾಟಗಳು",
    lblStatKnowledge: "ಜ್ಞಾನ ಕೋಶ ಪರಿಹಾರಗಳು",
    lblChooseCategory: "ಹುಡುಕಾಟ ವಿಭಾಗವನ್ನು ಆಯ್ಕೆಮಾಡಿ",
    cardStudentTitle: "ವಿದ್ಯಾರ್ಥಿ ಸಮಸ್ಯೆಗಳು",
    cardStudentDesc:
      "ಪರೀಕ್ಷೆಗಳು, ವೃತ್ತಿ ಮಾರ್ಗದರ್ಶನ, ಅಧ್ಯಯನ ತಂತ್ರಗಳು ಮತ್ತು ಅಸೈನ್‌ಮೆಂಟ್ಸ್ ಸಹಾಯ.",
    cardOfficeTitle: "ಕಚೇರಿ / ಕೆಲಸದ ತೊಂದರೆಗಳು",
    cardOfficeDesc:
      "ರೆಸ್ಯೂಮೆ ತಯಾರಿಕೆ, ವೃತ್ತಿಪರ ಮೇಲ್ ಬರಹಗಳು ಮತ್ತು ಕೆಲಸದ ಸಮಸ್ಯೆಗಳು.",
    cardProgrammingTitle: "ಪ್ರೋಗ್ರಾಮಿಂಗ್ ಸಮಸ್ಯೆಗಳು",
    cardProgrammingDesc:
      "ಕೋಡ್ ವಿವರಣೆಗಳು, ದೋಷಗಳ ತಿದ್ದುಪಡಿ ಮತ್ತು ಡೇಟಾಬೇಸ್ ಪ್ರಶ್ನೆಗಳ ಪರಿಹಾರ.",
    cardBusinessTitle: "ವ್ಯಾಪ್ತಿ / ವ್ಯವಹಾರ ತಂತ್ರಗಳು",
    cardBusinessDesc:
      "ಸ್ಟಾರ್ಟ್‌ಅಪ್ ಆಲೋಚನೆಗಳು, ಮಾರ್ಕೆಟಿಂಗ್ ತಂತ್ರಗಳು ಮತ್ತು ಆದಾಯ ಬೆಳವಣಿಗೆಯ ಸಲಹೆಗಳು.",
    cardGeneralTitle: "ಸಾಮಾನ್ಯ ಪ್ರಶ್ನೆಗಳು",
    cardGeneralDesc:
      "ದೈನಂದಿನ ಜೀವನೋಪಾಯ ವಿಧಾನಗಳು, ಸಾಮಾನ್ಯ ಜ್ಞಾನ, ಭಾಷಾ ಅನುವಾದ ಮತ್ತು ಸಾರಾಂಶಗಳು.",
    cardTranslationTitle: "ಭಾಷಾಂತರ ಮತ್ತು ವ್ಯಾಕರಣ",
    cardTranslationDesc:
      "ಕನ್ನಡ ಮತ್ತು ಇಂಗ್ಲಿಷ್ ನಡುವೆ ನಿಖರವಾಗಿ ಭಾಷಾಂತರಿಸಿ ಹಾಗೂ ವ್ಯಾಕರಣ ಸುಧಾರಿಸಿ.",
    quickStartTitle: "ವೈಶಿಷ್ಟ್ಯಗಳ ಅವಲೋಕನ",
    quickStartDesc:
      "<strong>📸 ಮಲ್ಟಿಮೋಡಲ್ ಇನ್‌ಪುಟ್:</strong> ಚಿತ್ರಗಳನ್ನು (ರೇಖಾಚಿತ್ರಗಳು, ಲೆಕ್ಕದ ಸೂತ್ರಗಳು, ದೋಷಗಳ ಸ್ಕ್ರೀನ್‌ಶಾಟ್) ಅಟ್ಯಾಚ್ ಮಾಡಲು ಪೇಪರ್ ಕ್ಲಿಪ್ ಐಕಾನ್ ಒತ್ತಿ ಮತ್ತು ಜೆಮಿನಿ ಉತ್ತರಿಸಲಿ.<br><strong>🗣️ ಧ್ವನಿ ಆದೇಶ:</strong> ಮೈಕ್ರೊಫೋನ್ ಬಟನ್ ಒತ್ತಿ ಕನ್ನಡ ಅಥವಾ ಇಂಗ್ಲಿಷ್‌ನಲ್ಲಿ ಮಾತನಾಡಿ.<br><strong>💬 ಚಾಟ್ ಇತಿಹಾಸ:</strong> ಇತಿಹಾಸ ವಿಭಾಗದಲ್ಲಿ ಪ್ಲಸ್ ಬಟನ್ ಒತ್ತಿ ಹೊಸ ಚಾಟ್ ಆರಂಭಿಸಿ ಮತ್ತು ಹಿಂದಿನ ಚಾಟ್‌ಗಳನ್ನು ಕಾಪಾಡಿಕೊಳ್ಳಿ.<br><strong>🌐 ದ್ವಿಭಾಷೆ:</strong> ಭಾಷೆಯ ಸೆಲೆಕ್ಟರ್ ಬಳಸಿ ಇಂಗ್ಲಿಷ್ ಮತ್ತು ಕನ್ನಡದಲ್ಲಿ ತಕ್ಷಣವೇ ಬದಲಾಯಿಸಿ.",
    chatPlaceholder:
      "ನನಗೆ ಏನನ್ನಾದರೂ ಕೇಳಿ — ಶಿಕ್ಷಣ, ಕಚೇರಿ ಕೆಲಸ, ಕೋಡಿಂಗ್, ವ್ಯಾಪಾರ ಸಲಹೆಗಳು...",
    searchHeroTitle: "ಸ್ಮಾರ್ಟ್ ಎಐ ಹುಡುಕಾಟ",
    searchHeroDesc: "ಜ್ಞಾನಕೋಶ ಮತ್ತು ಮಳಿಗೆಯ ಉತ್ಪನ್ನಗಳಾದ್ಯಂತ ಹುಡುಕಾಡಿ",
    searchInputPlaceholder: "ಪರಿಹಾರಗಳು ಮತ್ತು ಉತ್ಪನ್ನಗಳಿಗಾಗಿ ಹುಡುಕಿ...",
    anTitle: "📊 ಎಐ ವಿಶ್ಲೇಷಣೆ",
    anDesc: "ವೇದಿಕೆಯ ಬಳಕೆಯ ಅಂಕಿಅಂಶಗಳು ಮತ್ತು ಒಳನೋಟಗಳು",
    lblStatSessions2: "ಒಟ್ಟು ಚಾಟ್ ಸೆಷನ್‌ಗಳು",
    lblStatMessages2: "ಒಟ್ಟು ಸಂದೇಶಗಳು",
    lblStatSearches2: "ಒಟ್ಟು ಹುಡುಕಾಟಗಳು",
    lblStatKnowledge2: "ಪರಿಹಾರಗಳು",
    anFutureTitle: "📈 ಬಿಸಿನೆಸ್ ಇಂಟೆಲಿಜೆನ್ಸ್",
    anFutureDesc:
      "ಸಿಸ್ಟಮ್ ಪ್ರವೇಶಗಳಿಂದ ನೇರ ಪ್ರಕ್ರಿಯೆ ಸಮಯ, ಡೇಟಾಬೇಸ್ ಪರಿಶೀಲನೆ, ಮತ್ತು ಭಾಷಾ ಬಳಕೆಯ ಅಂಕಿಅಂಶಗಳು ಇಲ್ಲಿ ಲಭ್ಯವಾಗುತ್ತವೆ.",
    poweredBy: "ಕೆಎಸ್ಆರ್ ಗುರು ಜಿ ತಂತ್ರಜ್ಞಾನ ಆಧಾರಿತವಾಗಿದೆ",
    welcomeTitle:
      "KSR GURU JI AI ಗೆ ಸುಸ್ವಾಗತ 🧠✨\n\nನಾನು ನಿಮಗೆ ಈ ವಿಷಯದಲ್ಲಿ ಸಹಾಯ ಮಾಡಬಲ್ಲೆ:\n\n🎓 **ವಿದ್ಯಾರ್ಥಿ ಸಮಸ್ಯೆಗಳು** — ಪರೀಕ್ಷೆ ಮತ್ತು ಅಧ್ಯಯನ ಸಲಹೆಗಳು\n💼 **ಕಚೇರಿ ಕೆಲಸಗಳು** — ಕೆಲಸದ ವಿವರಣೆಗಳು, ಮೇಲ್ ಸಂವಹನ\n💻 **ಪ್ರೋಗ್ರಾಮಿಂಗ್ ಮತ್ತು ಕೋಡಿಂಗ್** — ಕೋಡ್ ದೋಷಗಳ ತಿದ್ದುಪಡಿ\n📈 **ವ್ಯವಹಾರ ಸಲಹೆಗಳು** — ಹೊಸ ವ್ಯಾಪಾರ ಮತ್ತು ಮಾರ್ಕೆಟಿಂಗ್ ತಂತ್ರಗಳು\n\nಚಿತ್ರಗಳನ್ನು ಅಟ್ಯಾಚ್ ಮಾಡಲು ಕೂಡಾ ಅವಕಾಶವಿದೆ. ನಿಮ್ಮ ಪ್ರಶ್ನೆಗಳನ್ನು ಕೆಳಗೆ ಟೈಪ್ ಮಾಡಿ!",
    welcomeSug0: "ಪರೀಕ್ಷೆಗಳಿಗೆ ಹೇಗೆ ಸಿದ್ಧರಾಗಬೇಕು?",
    welcomeSug1: "ಟ್ಯಾಕ್ಸ್ ಫೈಲಿಂಗ್ ಮಾಹಿತಿ",
    welcomeSug2: "ಎಸ್ ಕ್ಯು ಎಲ್ ಮತ್ತು ನೋ ಎಸ್ ಕ್ಯು ಎಲ್ ವ್ಯತ್ಯಾಸವೇನು?",
  },
};

// ── Panel Navigation ────────────────────────────────────────
function showPanel(panelId) {
  document
    .querySelectorAll(".panel")
    .forEach((p) => p.classList.remove("active"));
  document
    .querySelectorAll(".sidebar-item")
    .forEach((s) => s.classList.remove("active"));

  const panel = document.getElementById(panelId);
  if (panel) panel.classList.add("active");

  const sideItem = document.querySelector(`[data-panel="${panelId}"]`);
  if (sideItem) sideItem.classList.add("active");

  // Close menu on mobile after selection
  document.querySelector(".sidebar").classList.remove("mobile-open");

  if (panelId === "panel-analytics") {
    loadAnalytics();
  }
}

// Category selection helper from Dashboard grids or sidebar
function selectCategoryBtn(category) {
  setCategory(category);
  // Set tab display active
  showPanel("panel-chat");
}

// ── API Core ────────────────────────────────────────────────
async function apiPost(action, data = {}) {
  try {
    const res = await fetch(`${API_BASE}?action=${action}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Session-Token": clientToken,
      },
      body: JSON.stringify(data),
    });
    return await res.json();
  } catch (e) {
    console.error("API error:", e);
    return { success: false, error: e.message };
  }
}

async function apiGet(action, params = {}) {
  // Sync the client token in params
  params.session_token = clientToken;
  const qs = new URLSearchParams(params).toString();
  try {
    const res = await fetch(`${API_BASE}?action=${action}&${qs}`, {
      headers: { "X-Session-Token": clientToken },
    });
    return await res.json();
  } catch (e) {
    console.error("API error:", e);
    return { success: false, error: e.message };
  }
}

// ── Bilingual Language Translator ───────────────────────────
function setLanguage(lang) {
  selectedLanguage = lang;
  localStorage.setItem("ai_language", lang);
  document.getElementById("langSelect").value = lang;

  const dict = translations[lang];
  if (!dict) return;

  // Header and generic tags
  document.getElementById("logoSubText").textContent = dict.logoSub;
  document.getElementById("statusText").textContent = dict.statusOnline;
  document.getElementById("labelMain").textContent = dict.lblMain;
  document.getElementById("menuDashboard").textContent = dict.menuDashboard;
  document.getElementById("menuChat").textContent = dict.menuChat;
  document.getElementById("menuSearch").textContent = dict.menuSearch;
  document.getElementById("labelCategories").textContent = dict.lblCategories;
  document.getElementById("catStudent").textContent = dict.catStudent;
  document.getElementById("catOffice").textContent = dict.catOffice;
  document.getElementById("catProgramming").textContent = dict.catProgramming;
  document.getElementById("catBusiness").textContent = dict.catBusiness;
  document.getElementById("catGeneral").textContent = dict.catGeneral;
  document.getElementById("labelHistory").textContent = dict.lblHistory;

  // Dashboard Panel
  document.getElementById("dashTitle").textContent = dict.dashTitle;
  document.getElementById("dashSub").textContent = dict.dashSub;
  document.getElementById("lblStatSessions").textContent = dict.lblStatSessions;
  document.getElementById("lblStatMessages").textContent = dict.lblStatMessages;
  document.getElementById("lblStatSearches").textContent = dict.lblStatSearches;
  document.getElementById("lblStatKnowledge").textContent =
    dict.lblStatKnowledge;
  document.getElementById("lblChooseCategory").textContent =
    dict.lblChooseCategory;

  document.getElementById("cardStudentTitle").textContent =
    dict.cardStudentTitle;
  document.getElementById("cardStudentDesc").textContent = dict.cardStudentDesc;
  document.getElementById("cardOfficeTitle").textContent = dict.cardOfficeTitle;
  document.getElementById("cardOfficeDesc").textContent = dict.cardOfficeDesc;
  document.getElementById("cardProgrammingTitle").textContent =
    dict.cardProgrammingTitle;
  document.getElementById("cardProgrammingDesc").textContent =
    dict.cardProgrammingDesc;
  document.getElementById("cardBusinessTitle").textContent =
    dict.cardBusinessTitle;
  document.getElementById("cardBusinessDesc").textContent =
    dict.cardBusinessDesc;
  document.getElementById("cardGeneralTitle").textContent =
    dict.cardGeneralTitle;
  document.getElementById("cardGeneralDesc").textContent = dict.cardGeneralDesc;
  document.getElementById("cardTranslationTitle").textContent =
    dict.cardTranslationTitle;
  document.getElementById("cardTranslationDesc").textContent =
    dict.cardTranslationDesc;

  document.getElementById("quickStartTitle").innerHTML =
    `<i class="fas fa-sparkles" style="color:var(--accent-orange)"></i> ` +
    dict.quickStartTitle;
  document.getElementById("quickStartDesc").innerHTML = dict.quickStartDesc;

  // Chat Input
  document.getElementById("chatInput").placeholder = dict.chatPlaceholder;

  // Search Panel
  document.getElementById("searchHeroTitle").textContent = dict.searchHeroTitle;
  document.getElementById("searchHeroDesc").textContent = dict.searchHeroDesc;
  document.getElementById("searchInput").placeholder =
    dict.searchInputPlaceholder;

  // Analytics Panel
  document.getElementById("anTitle").textContent = dict.anTitle;
  document.getElementById("anDesc").textContent = dict.anDesc;
  document.getElementById("lblStatSessions2").textContent =
    dict.lblStatSessions2;
  document.getElementById("lblStatMessages2").textContent =
    dict.lblStatMessages2;
  document.getElementById("lblStatSearches2").textContent =
    dict.lblStatSearches2;
  document.getElementById("lblStatKnowledge2").textContent =
    dict.lblStatKnowledge2;
  document.getElementById("anFutureTitle").textContent = dict.anFutureTitle;
  document.getElementById("anFutureDesc").textContent = dict.anFutureDesc;
  document.getElementById("poweredByText").textContent = dict.poweredBy;

  // Render empty history fallback in selected language if list is empty
  const histList = document.getElementById("historyList");
  if (
    histList &&
    (histList.children.length === 0 || histList.querySelector(".empty-history"))
  ) {
    histList.innerHTML = `<div class="empty-history" id="emptyHistoryText">${dict.emptyHistory}</div>`;
  }
}

// ── Image Attachment Handling (Multimodality input stream) ──
function handleImageFileSelect(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    alert("Please upload image files only (PNG, JPG, WEBP, etc.)");
    return;
  }

  const reader = new FileReader();
  reader.onload = function (e) {
    const base64Data = e.target.result;
    attachedImage = {
      data: base64Data,
      mime: file.type,
    };

    // Render preview elements
    const previewWrapper = document.getElementById("imagePreviewWrapper");
    const previewThumb = document.getElementById("imgPreviewThumb");
    previewThumb.src = base64Data;
    previewWrapper.style.display = "block";
  };
  reader.readAsDataURL(file);
}

function clearAttachedImage() {
  attachedImage = null;
  document.getElementById("imageFileInput").value = "";
  document.getElementById("imagePreviewWrapper").style.display = "none";
  document.getElementById("imgPreviewThumb").src = "";
}

// ── Recent Chat Sessions Manager (Maintain History) ─────────
async function loadSessions() {
  const res = await apiGet("list_sessions");
  const histList = document.getElementById("historyList");
  const dict = translations[selectedLanguage];

  if (res.success && res.sessions && res.sessions.length > 0) {
    histList.innerHTML = res.sessions
      .map((s) => {
        const activeClass = s.uuid === activeSessionUuid ? "active" : "";
        const icon = getCategoryIconString(s.category);
        return `
        <div class="history-item ${activeClass}" onclick="openChatSession('${s.uuid}')" ondblclick="renameSession('${s.uuid}', event)" title="Double-click to rename">
          <div class="history-item-details">
            <i class="fas ${icon}"></i>
            <span class="history-item-title">${escHtml(s.title || "Conversation")}</span>
          </div>
          <button class="btn-delete-session" onclick="deleteSession('${s.uuid}', event)" title="Delete session">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>
      `;
      })
      .join("");
  }   else {
      histList.innerHTML = `<div class="empty-history" id="emptyHistoryText">${dict.emptyHistory}</div>`;
    }
  }

  // Rename a chat session (triggered by double-clicking a history item)
  async function renameSession(uuid, event) {
    if (event) event.stopPropagation();

    const promptMsg =
      selectedLanguage === "kn"
        ? "ಚಾಟ್‌ಗೆ ಹೊಸ ಹೆಸರು ನೀಡಿ:"
        : "Enter a new name for this chat:";
    const currentItem = event
      ? event.currentTarget.querySelector(".history-item-title")
      : null;
    const current = currentItem ? currentItem.textContent : "";
    const title = prompt(promptMsg, current);
    if (title === null) return;
    const trimmed = title.trim();
    if (!trimmed || trimmed === current) return;

    const res = await apiPost("rename_session", {
      session_token: clientToken,
      session_uuid: uuid,
      title: trimmed,
    });

    if (res.success) {
      loadSessions();
    }
  }

async function openChatSession(uuid) {
  activeSessionUuid = uuid;
  localStorage.setItem("active_session_uuid", uuid);

  // Highlight active session item
  document.querySelectorAll(".history-item").forEach((item) => {
    item.classList.remove("active");
  });

  loadSessions(); // Reload active indicators

  // Clear chat box and load history messages
  const container = document.getElementById("chatMessages");
  container.innerHTML =
    '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--accent-purple)"></i></div>';

  const res = await apiGet("chat_history", { session_uuid: uuid });
  container.innerHTML = "";

  if (res.success && res.messages && res.messages.length > 0) {
    res.messages.forEach((m) => {
      // Decode image metadata if present
      let metadata = null;
      if (m.metadata) {
        try {
          metadata = JSON.parse(m.metadata);
        } catch (e) {
          metadata = null;
        }
      }
      addMessage(m.role, m.content, [], metadata);
    });
  } else {
    // Show standard initial greeting in active language
    const dict = translations[selectedLanguage];
    addMessage("assistant", dict.welcomeTitle, [
      dict.welcomeSug0,
      dict.welcomeSug1,
      dict.welcomeSug2,
    ]);
  }
}

async function startNewChatSession() {
  const res = await apiPost("new_session", {
    session_token: clientToken,
    category: currentCategory,
  });
  if (res.success && res.session_uuid) {
    activeSessionUuid = res.session_uuid;
    localStorage.setItem("active_session_uuid", res.session_uuid);
    openChatSession(res.session_uuid);
  } else {
    alert("Failed to start a new conversation. Please try again.");
  }
}

async function deleteSession(uuid, event) {
  if (event) event.stopPropagation(); // Avoid triggering openChatSession callback

  const confirmMsg =
    selectedLanguage === "kn"
      ? "ಈ ಚಾಟ್ ಐತಿಹಾಸಿಕ ವಿವರಗಳನ್ನು ಅಳಿಸಲು ಖಚಿತಪಡಿಸಿ?"
      : "Are you sure you want to delete this chat session?";

  if (!confirm(confirmMsg)) return;

  const res = await apiPost("delete_session", {
    session_token: clientToken,
    session_uuid: uuid,
  });
  if (res.success) {
    if (activeSessionUuid === uuid) {
      activeSessionUuid = "";
      localStorage.removeItem("active_session_uuid");
      // Load standard greeting
      const container = document.getElementById("chatMessages");
      container.innerHTML = "";
      const dict = translations[selectedLanguage];
      addMessage("assistant", dict.welcomeTitle, [
        dict.welcomeSug0,
        dict.welcomeSug1,
        dict.welcomeSug2,
      ]);
    }
    loadSessions();
  } else {
    alert("Failed to delete search history session.");
  }
}

function getCategoryIconString(category) {
  return (
    {
      student: "fa-graduation-cap",
      office: "fa-briefcase",
      programming: "fa-code",
      business: "fa-chart-line",
      general: "fa-globe",
    }[category] || "fa-comments"
  );
}

// ── Chat Flow Logic ─────────────────────────────────────────
function addMessage(role, content, suggestions = [], metadata = null) {
  const container = document.getElementById("chatMessages");
  const div = document.createElement("div");
  div.className = `msg ${role}`;

  const avatar =
    role === "assistant"
      ? '<i class="fas fa-robot"></i>'
      : '<i class="fas fa-user"></i>';
  let formatted = formatMarkdown(content);

  // If there is an image in the metadata, render it inside the message bubble!
  if (metadata && metadata.image_url) {
    const localUrl = metadata.image_url.startsWith("storage")
      ? "../" + metadata.image_url
      : metadata.image_url;
    formatted += `<img class="chat-message-image" src="${localUrl}" onclick="window.open('${localUrl}')" alt="Uploaded Image Dependency">`;
  }

  let sugHtml = "";
  if (suggestions.length > 0 && role === "assistant") {
    sugHtml =
      '<div class="msg-suggestions">' +
      suggestions
        .map(
          (s) =>
            `<button onclick="sendSuggestion('${s.replace(/'/g, "\\'")}')">${escHtml(s)}</button>`,
        )
        .join("") +
      "</div>";
  }

  // Action bar (copy / regenerate) for assistant replies
  let actionsHtml = "";
  if (role === "assistant") {
    actionsHtml = `<div class="msg-actions">
      <button onclick="copyMessageText(this)" title="Copy answer"><i class="fas fa-copy"></i></button>
      <button onclick="regenerateResponse()" title="Regenerate answer"><i class="fas fa-rotate-right"></i></button>
    </div>`;
  }

  div.innerHTML = `
    <div class="msg-avatar">${avatar}</div>
    <div class="msg-bubble">${formatted}${sugHtml}${actionsHtml}</div>
  `;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  if (typeof refreshQuickChips === "function") refreshQuickChips();
}

// Copy the plain text of a whole assistant message
function copyMessageText(btn) {
  const bubble = btn.closest(".msg-bubble");
  if (!bubble) return;
  const clone = bubble.cloneNode(true);
  clone
    .querySelectorAll(".msg-actions, .msg-suggestions, .md-copy-btn")
    .forEach((n) => n.remove());
  const text = clone.innerText.trim();
  const done = () => {
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.add("copied");
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-copy"></i>';
      btn.classList.remove("copied");
    }, 1500);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard
      .writeText(text)
      .then(done)
      .catch(() => fallbackCopy(text, done));
  } else {
    fallbackCopy(text, done);
  }
}

// Re-send the last user message to get a fresh answer
async function regenerateResponse() {
  if (!lastUserMessage) return;
  // Remove the trailing assistant reply so the new one replaces it
  const replies = document.querySelectorAll("#chatMessages .msg.assistant");
  if (replies.length > 1) {
    replies[replies.length - 1].remove();
  }
  await dispatchMessage(lastUserMessage, null);
}

function showTyping() {
  const container = document.getElementById("chatMessages");
  const div = document.createElement("div");
  div.className = "msg assistant";
  div.id = "typingMsg";
  div.innerHTML = `
    <div class="msg-avatar"><i class="fas fa-robot"></i></div>
    <div class="msg-bubble"><div class="typing-indicator"><span></span><span></span><span></span></div></div>
  `;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

function removeTyping() {
  const el = document.getElementById("typingMsg");
  if (el) el.remove();
}

async function sendMessage() {
  const input = document.getElementById("chatInput");
  const message = input.value.trim();

  if (!message && !attachedImage) return;

  // Clear inputs and keep copy
  input.value = "";
  input.style.height = "auto";

  // Render user text and image preview
  addMessage(
    "user",
    message,
    [],
    attachedImage ? { image_url: attachedImage.data } : null,
  );

  const imageToSend = attachedImage;
  clearAttachedImage(); // Clear active upload attachment thumbnail preview

  await dispatchMessage(message, imageToSend);
}

// Core send routine — reused by sendMessage() and regenerateResponse()
async function dispatchMessage(message, imageToSend) {
  lastUserMessage = message;
  showTyping();

  const btn = document.getElementById("sendBtn");
  if (btn) btn.disabled = true;

  const activeModel = document.getElementById("modelSelect").value;

  const payload = {
    message: message,
    category: currentCategory,
    session_token: clientToken,
    session_uuid: activeSessionUuid || null,
    language: selectedLanguage,
    model: activeModel,
    image: imageToSend || null,
  };

  const res = await apiPost("chat", payload);

  removeTyping();
  if (btn) btn.disabled = false;

  if (res.success) {
    if (res.session_uuid) {
      activeSessionUuid = res.session_uuid;
      localStorage.setItem("active_session_uuid", res.session_uuid);
    }

    addMessage("assistant", res.message, res.suggestions || []);

    // Sync active category state
    if (res.category && res.category !== currentCategory) {
      setCategory(res.category);
    }

    // Refresh history sidebar list to update titles/chats ordering
    loadSessions();
  } else {
    const errorMsg =
      selectedLanguage === "kn"
        ? "❌ ಸಾರಿ, ದೋಷ ಸಂಭವಿಸಿದೆ. ಮತ್ತೊಮ್ಮೆ ಪ್ರಯತ್ನಿಸಿ."
        : "❌ Sorry, something went wrong. Please try again.";
    addMessage("assistant", errorMsg);
  }
}

function sendSuggestion(text) {
  document.getElementById("chatInput").value = text;
  sendMessage();
}

function setCategory(cat) {
  currentCategory = cat;
  document.querySelectorAll(".cat-btn").forEach((b) => {
    b.classList.toggle("active", b.dataset.category === cat);
  });
}

// ── Search Logic ────────────────────────────────────────────
async function performSearch() {
  const query = document.getElementById("searchInput").value.trim();
  const container = document.getElementById("searchResults");
  if (!query || query.length < 2) {
    container.innerHTML = "";
    return;
  }

  const searchingText =
    selectedLanguage === "kn" ? "ಹುಡುಕಲಾಗುತ್ತಿದೆ..." : "Searching...";
  container.innerHTML = `<div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i><p style="margin-top:8px">${searchingText}</p></div>`;

  const res = await apiGet("search", { q: query });

  const hasResults = res.success && res.results && res.results.length > 0;
  const hasAI = res.success && res.ai_answer;

  if (hasResults || hasAI) {
    let html = "";

    // AI-synthesised answer card first
    if (hasAI) {
      const aiLabel =
        selectedLanguage === "kn" ? "ಎಐ ಉತ್ತರ" : "AI Answer";
      html += `<div class="ai-answer-card">
        <div class="ai-answer-head">
          <i class="fas fa-wand-magic-sparkles"></i>
          <span>${aiLabel}</span>
          <button class="ai-ask-btn" onclick="askAIFromSearch('${query.replace(/'/g, "\\'")}") ">
            <i class="fas fa-comments"></i> ${selectedLanguage === "kn" ? "ಚಾಟ್‌ನಲ್ಲಿ ಕೇಳಿ" : "Ask in chat"}
          </button>
        </div>
        <div class="ai-answer-body">${formatMarkdown(res.ai_answer)}</div>
      </div>`;
    }

    if (hasResults) {
      html += res.results
        .map((r) => {
          const isProduct = r.type === "product";
          const click = isProduct
            ? `window.open('${(r.link || "#").replace(/'/g, "\\'")}','_blank')`
            : `askAIFromSearch('${(r.title || "").replace(/'/g, "\\'")}')`;
          return `
        <div class="search-result-item" onclick="${click}">
          <div class="search-result-icon"><i class="fas ${r.icon || "fa-search"}"></i></div>
          <div style="flex:1;min-width:0">
            <div class="search-result-title">${escHtml(r.title)}</div>
            <div class="search-result-snippet">${escHtml(r.snippet)}</div>
            <div class="search-result-meta">${escHtml(r.category || "")} · ${isProduct ? (selectedLanguage === "kn" ? "ಉತ್ಪನ್ನ" : "Product") : (selectedLanguage === "kn" ? "ಸಂಬಂಧ" : "Relevance") + ": " + ((r.relevance || 0) * 100).toFixed(0) + "%"}</div>
          </div>
        </div>`;
        })
        .join("");
    }

    if (res.did_you_mean) {
      const suggestLabel =
        selectedLanguage === "kn" ? "ಇದನ್ನು ಅರ್ಥೈಸಿದ್ದೀರಾ: " : "Did you mean: ";
      html =
        `<div style="padding:8px 0;color:var(--accent-purple);font-size:.85rem;margin-bottom:8px">
        ${suggestLabel}<a href="#" onclick="document.getElementById('searchInput').value='${res.did_you_mean}';performSearch();return false;" style="color:var(--accent-cyan);text-decoration:underline">${escHtml(res.did_you_mean)}</a>?
      </div>` + html;
    }

    container.innerHTML = html;
  } else {
    const noResultsText =
      selectedLanguage === "kn"
        ? "ಯಾವುದೇ ಫಲಿತಾಂಶಗಳು ಕಂಡುಬಂದಿಲ್ಲ. ಬೇರೆ ಪದಗಳನ್ನು ಬಳಸಿ ಪ್ರಯತ್ನಿಸಿ."
        : "No results found. Try different keywords.";
    container.innerHTML = `<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-search" style="font-size:2rem;margin-bottom:10px;display:block"></i>${noResultsText}</div>`;
  }
}

// Send a search query straight into the AI chat panel
function askAIFromSearch(query) {
  showPanel("panel-chat");
  document.getElementById("chatInput").value = query;
  sendMessage();
}

function openSearchResult(title, snippet) {
  askAIFromSearch(title);
}

// ── Voice Input (Speech-to-Text Bilingual support) ──────────
function toggleVoice() {
  if (!("webkitSpeechRecognition" in window || "SpeechRecognition" in window)) {
    alert(
      selectedLanguage === "kn"
        ? "ಈ ಬ್ರೌಸರ್ ಧ್ವನಿ ಸೌಲಭ್ಯವನ್ನು ಬೆಂಬಲಿಸುವುದಿಲ್ಲ. ಕ್ರೋಮ್ ಬಳಸಿ."
        : "Voice recognition not supported in this browser. Try Chrome.",
    );
    return;
  }

  const btn = document.getElementById("voiceBtn");

  if (isRecording) {
    recognition.stop();
    isRecording = false;
    btn.classList.remove("recording");
    return;
  }

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  recognition = new SR();

  // Set speech language to Kannada or English accordingly
  recognition.lang = selectedLanguage === "kn" ? "kn-IN" : "en-IN";
  recognition.continuous = false;
  recognition.interimResults = false;

  recognition.onstart = () => {
    isRecording = true;
    btn.classList.add("recording");
  };
  recognition.onresult = (e) => {
    const text = e.results[0][0].transcript;
    document.getElementById("chatInput").value = text;
    sendMessage();
  };
  recognition.onend = () => {
    isRecording = false;
    btn.classList.remove("recording");
  };
  recognition.onerror = () => {
    isRecording = false;
    btn.classList.remove("recording");
  };
  recognition.start();
}

function toggleSearchVoice() {
  if (!("webkitSpeechRecognition" in window || "SpeechRecognition" in window)) {
    alert("Voice not supported. Try Chrome.");
    return;
  }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  const r = new SR();
  r.lang = selectedLanguage === "kn" ? "kn-IN" : "en-IN";
  r.onresult = (e) => {
    document.getElementById("searchInput").value = e.results[0][0].transcript;
    performSearch();
  };
  r.start();
}

// ── Health check & dynamic model list ───────────────────────
async function checkHealth() {
  const statusEl = document.getElementById("statusText");
  const badge = statusEl ? statusEl.closest(".badge-status") : null;

  const res = await apiGet("health");
  if (!res || !res.success) {
    if (statusEl) statusEl.textContent = "AI Offline";
    if (badge) badge.classList.add("offline");
    return;
  }

  const liveAI = res.ai && res.ai.mode === "live-ai";
  const dict = translations[selectedLanguage];
  if (statusEl) {
    statusEl.textContent = liveAI ? dict.statusOnline : dict.statusOnline;
  }
  if (badge) {
    badge.classList.toggle("offline", !liveAI && res.database !== "connected");
  }
}

// Populate the model selector from the backend config
async function loadModels() {
  const sel = document.getElementById("modelSelect");
  if (!sel) return;
  try {
    const res = await apiGet("models");
    if (res && res.success && Array.isArray(res.models) && res.models.length) {
      sel.innerHTML = res.models
        .map(
          (m) =>
            `<option value="${escHtml(m.id)}"${m.id === res.default ? " selected" : ""}>${escHtml(m.label)}</option>`,
        )
        .join("");
    }
  } catch (e) {
    /* keep static options */
  }
}

// ── Trending searches (search panel chips) ──────────────────
async function loadTrending() {
  const bar = document.getElementById("trendingBar");
  if (!bar) return;
  try {
    const res = await apiGet("trending", { limit: 6 });
    if (res && res.success && Array.isArray(res.trending) && res.trending.length) {
      bar.innerHTML = res.trending
        .map(
          (t) =>
            `<button class="trending-chip" onclick="runTrendingSearch('${(t.keyword || "").replace(/'/g, "\\'")}')"><i class="fas fa-fire"></i> ${escHtml(t.keyword)}</button>`,
        )
        .join("");
      bar.style.display = "flex";
    }
  } catch (e) {
    /* ignore */
  }
}

function runTrendingSearch(keyword) {
  document.getElementById("searchInput").value = keyword;
  performSearch();
}

// Hide the quick prompt chips as soon as a real conversation begins
function refreshQuickChips() {
  const chips = document.getElementById("quickChips");
  const box = document.getElementById("chatMessages");
  if (!chips || !box) return;
  chips.style.display = box.children.length > 1 ? "none" : "flex";
}

// ── Analytics ───────────────────────────────────────────────
async function loadAnalytics() {  const res = await apiGet("analytics");
  if (!res.success) return;
  const a = res.analytics;

  document.getElementById("statSessions").textContent = a.chat_sessions || 0;
  document.getElementById("statMessages").textContent = a.total_messages || 0;
  document.getElementById("statSearches").textContent = a.total_searches || 0;

  document.getElementById("statSessions2").textContent = a.chat_sessions || 0;
  document.getElementById("statMessages2").textContent = a.total_messages || 0;
  document.getElementById("statSearches2").textContent = a.total_searches || 0;
}

// ── Database Initialization ─────────────────────────────────
async function initDatabase() {
  const btn = document.getElementById("initDbBtn");
  if (!btn) return;

  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

  const res = await apiPost("init_db");
  if (res.success) {
    alert("✅ DB initialization: " + res.message);
    loadAnalytics();
  } else {
    alert("❌ DB initialization error: " + (res.error || "Unknown error"));
  }
  btn.disabled = false;
  btn.innerHTML = originalHtml;
}

// ── Escaping and Format Helpers ─────────────────────────────
function escHtml(str) {
  if (!str) return "";
  const d = document.createElement("div");
  d.textContent = str;
  return d.innerHTML;
}

function formatMarkdown(text) {
  if (!text) return "";

  // 1. Extract fenced code blocks first so their content is never mangled by
  //    inline markdown rules (**, *, `, lists).
  const codeBlocks = [];
  let src = String(text).replace(
    /```([a-zA-Z0-9+#-]*)\n?([\s\S]*?)```/g,
    (m, lang, code) => {
      const idx = codeBlocks.length;
      codeBlocks.push({ lang: lang || "", code });
      return `\u0000CODEBLOCK${idx}\u0000`;
    },
  );

  // 2. Escape HTML in the remaining prose
  src = src
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

  // 3. Block-level markdown
  src = src
    .replace(
      /^### (.+)$/gm,
      '<h4 class="md-h4">$1</h4>',
    )
    .replace(/^## (.+)$/gm, '<h3 class="md-h3">$1</h3>')
    .replace(/^# (.+)$/gm, '<h3 class="md-h3">$1</h3>');

  // 4. Inline markdown
  src = src
    .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
    .replace(/(^|[^*])\*(?!\s)(.+?)\*/g, "$1<em>$2</em>")
    .replace(/`([^`\n]+)`/g, "<code>$1</code>");

  // 5. Lists — group consecutive items into <ul>/<ol>
  const lines = src.split("\n");
  const out = [];
  let listType = null;
  const closeList = () => {
    if (listType) {
      out.push(listType === "ul" ? "</ul>" : "</ol>");
      listType = null;
    }
  };

  for (const line of lines) {
    const ul = line.match(/^\s*[-*•]\s+(.+)$/);
    const ol = line.match(/^\s*\d+[.)]\s+(.+)$/);

    if (ul) {
      if (listType !== "ul") {
        closeList();
        out.push('<ul class="md-ul">');
        listType = "ul";
      }
      out.push(`<li>${ul[1]}</li>`);
    } else if (ol) {
      if (listType !== "ol") {
        closeList();
        out.push('<ol class="md-ol">');
        listType = "ol";
      }
      out.push(`<li>${ol[1]}</li>`);
    } else {
      closeList();
      out.push(line);
    }
  }
  closeList();
  src = out.join("\n");

  // 6. Horizontal rule + line breaks
  src = src
    .replace(/^---+$/gm, '<hr class="md-hr">')
    .replace(/\n{2,}/g, "<br><br>")
    .replace(/\n/g, "<br>");

  // 7. Restore code blocks as styled, copyable <pre> blocks
  src = src.replace(/\u0000CODEBLOCK(\d+)\u0000/g, (m, i) => {
    const { lang, code } = codeBlocks[Number(i)];
    const escaped = code
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
    const label = lang ? escHtml(lang) : "code";
    return `<div class="md-code">
      <div class="md-code-head"><span class="md-code-lang">${label}</span>
      <button class="md-copy-btn" onclick="copyCodeBlock(this)" title="Copy code"><i class="fas fa-copy"></i> Copy</button></div>
      <pre><code>${escaped}</code></pre>
    </div>`;
  });

  return src;
}

// Copy a fenced code block to the clipboard
function copyCodeBlock(btn) {
  const pre = btn.closest(".md-code")?.querySelector("pre code");
  if (!pre) return;
  const text = pre.textContent;
  const done = () => {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied';
    btn.classList.add("copied");
    setTimeout(() => {
      btn.innerHTML = original;
      btn.classList.remove("copied");
    }, 1500);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
  } else {
    fallbackCopy(text, done);
  }
}

function fallbackCopy(text, done) {
  const ta = document.createElement("textarea");
  ta.value = text;
  ta.style.position = "fixed";
  ta.style.opacity = "0";
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand("copy");
    done && done();
  } catch (e) {
    /* ignore */
  }
  document.body.removeChild(ta);
}

function toggleMobileMenu() {
  document.querySelector(".sidebar").classList.toggle("mobile-open");
}

// ── Auto-resize input & event registers ──────────────────────
document.addEventListener("DOMContentLoaded", () => {
  const ta = document.getElementById("chatInput");
  if (ta) {
    ta.addEventListener("input", () => {
      ta.style.height = "auto";
      ta.style.height = Math.min(ta.scrollHeight, 120) + "px";
    });
    ta.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  const si = document.getElementById("searchInput");
  if (si) {
    si.addEventListener("input", () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(performSearch, 500);
    });
    si.addEventListener("keydown", (e) => {
      if (e.key === "Enter") performSearch();
    });
  }

  // Load language settings on launch
  setLanguage(selectedLanguage);

  // Verify platform + AI provider health
  checkHealth();
  loadModels();

  // Synchronize dynamic lists of sessions
  loadSessions();

  // Load initial active chat session history (if user had one saved)
  if (activeSessionUuid) {
    openChatSession(activeSessionUuid);
  } else {
    // Show static greeting
    const dict = translations[selectedLanguage];
    addMessage("assistant", dict.welcomeTitle, [
      dict.welcomeSug0,
      dict.welcomeSug1,
      dict.welcomeSug2,
    ]);
  }

  // Load analytics counts
  loadAnalytics();

  // Trending chips in the search panel
  loadTrending();
});
