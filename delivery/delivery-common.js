/* ============================================================
   KSR GURU JI — delivery shared front-end helpers
   Loaded by every page in /delivery
   ============================================================ */

const dpApi = {
  async get(action, query = {}) {
    const u = new URLSearchParams(query);
    const url = "api.php?action=" + action + (u.toString() ? "&" + u : "");
    const r = await fetch(url, { cache: "no-store" });
    if (r.status === 401) {
      location.href = "login.php?redirect=" + encodeURIComponent(currentPage());
      return null;
    }
    return r.json();
  },
  async post(action, data = {}) {
    const r = await fetch("api.php?action=" + action, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(data),
    });
    if (r.status === 401) {
      location.href = "login.php?redirect=" + encodeURIComponent(currentPage());
      return null;
    }
    return r.json();
  },
};

function currentPage() {
  const f = location.pathname.split("/").pop();
  return f ? f + location.search : "dashboard.php";
}

function dpMoney(n) {
  return (
    "₹" + Number(n || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 })
  );
}

function dpStatusBadge(s) {
  const m = {
    pending: ["Pending", "warn"],
    confirmed: ["Confirmed", "info"],
    accepted: ["Accepted", "info"],
    picked_up: ["Picked Up", "info"],
    in_transit: ["Out for Delivery", "accent"],
    delivered: ["Delivered", "ok"],
    cancelled: ["Cancelled", "bad"],
  }[s] || [s || "—", "muted"];
  return '<span class="ds-badge ds-' + m[1] + '">' + m[0] + "</span>";
}

function dpTimeAgo(dt) {
  if (!dt) return "—";
  const t = new Date(String(dt).replace(" ", "T")).getTime();
  if (isNaN(t)) return "—";
  const d = (Date.now() - t) / 1000;
  if (d < 60) return "just now";
  if (d < 3600) return Math.floor(d / 60) + "m ago";
  if (d < 86400) return Math.floor(d / 3600) + "h ago";
  if (d < 604800) return Math.floor(d / 86400) + "d ago";
  return new Date(t).toLocaleDateString("en-IN", {
    day: "2-digit",
    month: "short",
  });
}

function dpEscape(str) {
  const d = document.createElement("div");
  d.textContent = str == null ? "" : String(str);
  return d.innerHTML;
}

let _dpToastTimer = null;
function dpToast(msg, tone = "ok") {
  const t = document.getElementById("dpToast");
  if (!t) {
    return;
  }
  t.textContent = msg;
  t.className = "dp-toast on " + tone;
  clearTimeout(_dpToastTimer);
  _dpToastTimer = setTimeout(() => {
    t.className = "dp-toast";
  }, 3400);
}

function dpRenderTrend(trend) {
  const box = document.getElementById("trendBars");
  if (!box) return;
  const max = Math.max(1, ...trend.map((t) => t.count));
  box.innerHTML = trend
    .map(
      (t) => `
    <div class="dp-bar" title="${t.count} order(s) · ${dpMoney(t.amount)}">
      <div class="dp-bar-w"><div class="dp-bar-f" style="height:${(t.count / max) * 100}%"></div></div>
      <span class="dp-bar-l">${t.label}</span>
      <span class="dp-bar-v">${t.count}</span>
    </div>`,
    )
    .join("");
  const totalEl = document.getElementById("trendTotal");
  if (totalEl) {
    const total = trend.reduce((a, b) => a + b.count, 0);
    totalEl.textContent = total + " order" + (total === 1 ? "" : "s");
  }
}

function dpRenderRecent(rows) {
  const box = document.getElementById("recentList");
  if (!box) return;
  if (!rows || !rows.length) {
    box.innerHTML =
      '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No orders assigned yet</div>';
    return;
  }
  box.innerHTML = rows
    .map(
      (r) => `
    <a class="dp-row" href="order.php?id=${r.id}">
      <div class="dp-row-l">
        <b>${dpEscape(r.order_number)}</b>
        <small>${dpEscape(r.name)} · ${dpEscape(r.city || "—")}</small>
      </div>
      <div class="dp-row-r">
        <b>${dpMoney(r.total)}</b>
        <small>${dpTimeAgo(r.placed_at || r.created_at)}</small>
      </div>
      ${dpStatusBadge(r.status)}
    </a>`,
    )
    .join("");
}

/** The three delivery agents — mirrors DELIVERY_ROSTER in bootstrap.php */
const DP_ROSTER = [
  { code: "JK", name: "JK", phone: "8088584421" },
  { code: "JAMPALEPP", name: "jampaleppa", phone: "8762474406" },
  { code: "SHANKAR", name: "shankar", phone: "9590902742" },
];

function dpWaLink(phone, message) {
  const digits = String(phone || "").replace(/\D/g, "");
  const num = digits.length === 10 ? "91" + digits : digits;
  return (
    "https://wa.me/" +
    num +
    (message ? "?text=" + encodeURIComponent(message) : "")
  );
}

/** WhatsApp message button markup for a specific agent. */
function dpAgentWaButton(agentCode, message, label) {
  const boy = DP_ROSTER.find((b) => b.code === agentCode);
  if (!boy) return "";
  return `<a class="dp-btn dp-btn-wa dp-btn-sm" target="_blank" rel="noopener"
             href="${dpWaLink(boy.phone, message)}">
            <i class="fa-brands fa-whatsapp"></i> ${label || "WhatsApp " + boy.name}
          </a>`;
}

/** Live poll helper: keeps a page fresh without a full reload. */
function dpPoll(fn, ms = 15000) {
  fn();
  setInterval(fn, ms);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) fn();
  });
}
