document.getElementById("year").textContent = new Date().getFullYear();

/* =========================================================
   Theme toggle: follows the device until you pick one,
   then remembers your choice (localStorage "rn_theme").
   ========================================================= */
(function themeToggle() {
  const btn = document.querySelector(".theme-toggle");
  const html = document.documentElement;
  const mq = matchMedia("(prefers-color-scheme: dark)");
  const effective = () => html.getAttribute("data-theme") || (mq.matches ? "dark" : "light");
  function paint() {
    const dark = effective() === "dark";
    document.querySelectorAll('meta[name="theme-color"]').forEach((m) => m.setAttribute("content", dark ? "#0a0a0a" : "#ffffff"));
    if (!btn) return;
    btn.dataset.mode = dark ? "dark" : "light";
    btn.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
  }
  btn?.addEventListener("click", () => {
    const next = effective() === "dark" ? "light" : "dark";
    html.setAttribute("data-theme", next);
    try { localStorage.setItem("rn_theme", next); } catch {}
    paint();
  });
  mq.addEventListener?.("change", () => { if (!html.getAttribute("data-theme")) paint(); });
  paint();
})();

/* =========================================================
   Contact chat
   ---------------------------------------------------------
   Messages are delivered with Web3Forms (free, no backend).
   1. Go to https://web3forms.com, enter rnamocotdev@gmail.com,
      and copy the access key they email you.
   2. Paste it below. Until then the chat falls back to opening
      the visitor's email app with the message pre-filled.
   ========================================================= */
const CHAT = {
  web3formsKey: "", // ← paste your Web3Forms access key here
  email: "rnamocotdev@gmail.com",
};

(function chat() {
  const root = document.getElementById("chat");
  if (!root) return;
  const fab = root.querySelector(".chat__fab");
  const panel = document.getElementById("chat-panel");
  const closeBtn = root.querySelector(".chat__x");
  const log = document.getElementById("chat-log");
  const chips = document.getElementById("chat-chips");
  const form = document.getElementById("chat-form");
  const input = document.getElementById("chat-input");
  const send = form.querySelector(".chat__send");
  const honeypot = form.querySelector(".hp");

  const state = { step: "topic", topic: "", message: "", email: "", name: "", started: false };
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const wait = (ms) => new Promise((r) => setTimeout(r, reduce ? 0 : ms));

  const TOPICS = ["Question about an app", "Hire me for a project", "Report a bug", "Just saying hi"];

  function scroll() { log.scrollTop = log.scrollHeight; }

  function bubble(text, who = "bot") {
    const el = document.createElement("div");
    el.className = `msg msg--${who}`;
    el.textContent = text;
    log.append(el);
    scroll();
    return el;
  }

  async function bot(text, delay = 600) {
    const t = document.createElement("div");
    t.className = "msg msg--bot typing";
    t.setAttribute("aria-label", "Renie is typing");
    t.innerHTML = "<i></i><i></i><i></i>";
    log.append(t);
    scroll();
    await wait(delay);
    t.remove();
    return bubble(text, "bot");
  }

  function setChips(list, onPick) {
    chips.innerHTML = "";
    list.forEach((label) => {
      const b = document.createElement("button");
      b.type = "button";
      b.textContent = label;
      b.addEventListener("click", () => onPick(label));
      chips.append(b);
    });
  }

  function setInput({ placeholder, type = "text" }) {
    input.value = "";
    input.placeholder = placeholder;
    input.inputMode = type === "email" ? "email" : "text";
    input.setAttribute("autocomplete", type === "email" ? "email" : type === "name" ? "name" : "off");
    autosize();
    updateSend();
  }

  function autosize() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 120) + "px";
  }
  function updateSend() { send.disabled = !input.value.trim() || state.step === "done" || state.step === "sending"; }

  async function start() {
    if (state.started) return;
    state.started = true;
    await bot("Hi! I'm Renie.", 350);
    await bot("What can I help you with? Pick one or just type your message.", 700);
    setChips(TOPICS, (t) => { state.topic = t; bubble(t, "me"); chips.innerHTML = ""; askMessage(); });
    setInput({ placeholder: "Write a message…" });
  }

  async function askMessage() {
    state.step = "message";
    const prompts = {
      "Question about an app": "Sure. Which app, and what would you like to know?",
      "Hire me for a project": "Nice! Tell me a bit about the project: what you need and a rough timeline.",
      "Report a bug": "Thanks for catching it. Which app, and what happened?",
      "Just saying hi": "Hello! Anything you'd like to tell me?",
    };
    await bot(prompts[state.topic] || "Tell me more.");
    setInput({ placeholder: "Write a message…" });
    input.focus();
  }

  async function askName() {
    state.step = "name";
    await bot("Got it. What's your name?");
    setInput({ placeholder: "Your name", type: "name" });
    input.focus();
  }

  async function askEmail() {
    state.step = "email";
    await bot(`Thanks, ${state.name.split(" ")[0]}. What email should I reply to?`);
    setInput({ placeholder: "you@example.com", type: "email" });
    input.focus();
  }

  async function deliver() {
    state.step = "sending";
    updateSend();
    const subject = `[apps.renienamocot.com] ${state.topic || "New message"} from ${state.name}`;
    const body = `${state.message}\n\n— ${state.name} <${state.email}>`;

    if (CHAT.web3formsKey) {
      const t = await bot("Sending…", 200);
      try {
        const res = await fetch("https://api.web3forms.com/submit", {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({
            access_key: CHAT.web3formsKey,
            subject,
            from_name: "apps.renienamocot.com",
            name: state.name,
            email: state.email,
            topic: state.topic || "Other",
            message: state.message,
            botcheck: honeypot.checked,
          }),
        });
        const data = await res.json();
        t.remove();
        if (!data.success) throw new Error(data.message || "Failed");
        await bot(`Sent! I'll reply to ${state.email}, usually within a day.`);
        return finish();
      } catch (err) {
        t.remove();
        await bot("Hmm, that didn't go through. Let me open your email app instead.");
      }
    }

    const href = `mailto:${CHAT.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = href;
    const m = await bot("Your email app should open with the message ready. Just hit send.\n\nIf nothing opened, email me at ");
    const a = document.createElement("a");
    a.href = href;
    a.textContent = CHAT.email;
    m.append(a);
    finish();
  }

  function finish() {
    state.step = "done";
    input.placeholder = "Message sent";
    input.disabled = true;
    updateSend();
    setChips(["Send another message"], reset);
  }

  function reset() {
    log.innerHTML = "";
    chips.innerHTML = "";
    input.disabled = false;
    Object.assign(state, { step: "topic", topic: "", message: "", email: "", name: "", started: false });
    start();
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const val = input.value.trim();
    if (!val || send.disabled) return;

    if (state.step === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val)) {
      bubble(val, "me");
      input.value = "";
      updateSend();
      await bot("That doesn't look like an email address. Mind checking it?");
      return;
    }

    bubble(val, "me");
    input.value = "";
    autosize();
    chips.innerHTML = "";

    if (state.step === "topic" || state.step === "message") { state.message = val; return askName(); }
    if (state.step === "name") { state.name = val.slice(0, 80); return askEmail(); }
    if (state.step === "email") { state.email = val; return deliver(); }
  });

  input.addEventListener("input", () => { autosize(); updateSend(); });
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
  });

  function open() {
    panel.hidden = false;
    root.classList.add("is-open");
    fab.setAttribute("aria-expanded", "true");
    start();
    setTimeout(() => input.focus(), reduce ? 0 : 50);
  }
  function close() {
    panel.hidden = true;
    root.classList.remove("is-open");
    fab.setAttribute("aria-expanded", "false");
    fab.focus();
  }

  fab.addEventListener("click", () => (panel.hidden ? open() : close()));
  closeBtn.addEventListener("click", close);
  root.addEventListener("click", (e) => { if (e.target === root) close(); }); // tap on the dimmed backdrop (mobile)
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !panel.hidden) close(); });
  document.querySelectorAll("[data-open-chat]").forEach((el) =>
    el.addEventListener("click", (e) => { e.preventDefault(); open(); })
  );
  updateSend();
})();

/* =========================================================
   Visitors: total (Abacus, unique per browser) + live ("here right now",
   api/presence.php on this server). Never counts local previews.
   ========================================================= */
const IS_LOCAL = location.protocol === "file:" || /^(localhost|127\.|0\.0\.0\.0|\[::1\])/.test(location.hostname);

function showVisits() {
  const live = !document.getElementById("live-wrap").hidden;
  const total = !document.getElementById("visits-wrap").hidden;
  document.getElementById("visits-sep").hidden = !(live && total);
  document.getElementById("visits").hidden = !(live || total);
}

(function visitorTotal() {
  const out = document.getElementById("visits-count");
  if (!out) return;
  const API = "https://abacus.jasoncameron.dev";
  const KEY = "apps-renienamocot-com/visitors-live";
  let seen = false;
  try { seen = localStorage.getItem("rn_visited") === "1"; } catch {}
  const ctrl = new AbortController();
  setTimeout(() => ctrl.abort(), 5000);
  fetch(`${API}/${seen || IS_LOCAL ? "get" : "hit"}/${KEY}`, { signal: ctrl.signal })
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
    .then(({ value }) => {
      if (typeof value !== "number") return;
      if (!IS_LOCAL) try { localStorage.setItem("rn_visited", "1"); } catch {}
      out.textContent = value.toLocaleString("en-US");
      document.getElementById("visits-label").textContent = value === 1 ? "visitor" : "visitors";
      document.getElementById("visits-wrap").hidden = false;
      showVisits();
    })
    .catch(() => {});
})();

(function livePresence() {
  const out = document.getElementById("live-count");
  if (!out || location.protocol === "file:") return;
  const URL = "api/presence.php";
  let id;
  try { id = localStorage.getItem("rn_pid"); } catch {}
  if (!/^[a-f0-9]{32}$/.test(id || "")) {
    id = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, "0")).join("");
    try { localStorage.setItem("rn_pid", id); } catch {}
  }
  let timer = null;

  function ping() {
    fetch(URL, { method: "POST", body: new URLSearchParams({ id }), cache: "no-store" })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then(({ now }) => {
        if (typeof now !== "number" || now < 1) return;
        out.textContent = now.toLocaleString("en-US");
        document.getElementById("live-wrap").hidden = false;
        showVisits();
      })
      .catch(() => {});
  }
  const start = () => { ping(); clearInterval(timer); timer = setInterval(ping, 20000); };
  const stop = () => { clearInterval(timer); timer = null; };

  document.addEventListener("visibilitychange", () => (document.hidden ? stop() : start()));
  addEventListener("pagehide", () => navigator.sendBeacon?.(URL, new URLSearchParams({ id, leave: "1" })));
  if (!document.hidden) start();
})();

/* =========================================================
   Revenue / MRR (managed in /admin, stored in data/revenue.json)
   - Each visible app gets "₱X/mo" or "₱X revenue" on its card
   - The summary card totals only apps that are NOT hidden,
     grouped by currency (no conversion)
   ========================================================= */
(function revenue() {
  const SYM = { PHP: "₱", USD: "$" };
  const fmt = (n, cur) => (SYM[cur] || "") + Number(n).toLocaleString("en-US", { maximumFractionDigits: n % 1 ? 2 : 0 });

  // Order cards: MRR apps (highest first), then total-revenue apps, then the rest
  // in their original order. USD is converted only to compare; amounts shown stay exact.
  function sortCards(apps) {
    const USD_TO_PHP = 58; // approximate; only used for ordering
    const list = document.querySelector("#apps .grid2");
    if (!list) return;
    const items = [...list.children];
    const rank = (li, i) => {
      const d = apps[li.querySelector(".app")?.dataset.app];
      const amt = d && d.mode !== "hidden" ? (Number(d.amount) || 0) * (d.currency === "USD" ? USD_TO_PHP : 1) : 0;
      const group = !amt ? 2 : d.mode === "mrr" ? 0 : 1;
      return { li, i, group, amt };
    };
    items.map(rank)
      .sort((x, y) => x.group - y.group || y.amt - x.amt || x.i - y.i)
      .forEach(({ li }) => list.append(li));
  }
  const join = (byCur) => Object.entries(byCur).filter(([, v]) => v > 0).map(([c, v]) => fmt(v, c)).join(" + ");

  fetch("data/revenue.json?v=" + Date.now(), { cache: "no-store" })
    .then((r) => (r.ok ? r.json() : Promise.reject()))
    .then(({ apps = {}, updated }) => {
      const mrr = {}, total = {};
      let earning = 0;

      document.querySelectorAll(".app[data-app]").forEach((card) => {
        const d = apps[card.dataset.app];
        card.querySelector(".app__mrr")?.remove();
        if (!d || d.mode === "hidden") return;
        const amount = Number(d.amount) || 0;
        if (amount > 0) earning++;

        const p = document.createElement("p");
        p.className = "app__mrr";
        const b = document.createElement("b");
        b.textContent = fmt(amount, d.currency);
        p.append(b, document.createTextNode(d.mode === "mrr" ? "/mo" : " revenue"));
        if (d.mode === "mrr") {
          const sr = document.createElement("span");
          sr.className = "sr-only";
          sr.textContent = " monthly recurring revenue";
          p.append(sr);
          mrr[d.currency] = (mrr[d.currency] || 0) + amount;
        } else {
          total[d.currency] = (total[d.currency] || 0) + amount;
        }
        card.querySelector(".app__desc").after(p);
      });

      sortCards(apps);

      const box = document.getElementById("rev-stats");
      const mrrText = join(mrr);
      const totalText = join(total);
      if (!box || (!mrrText && !totalText)) return;

      const set = (id, text, suffix) => {
        const el = document.getElementById(id);
        el.textContent = text || "–";
        if (text && suffix) {
          const s = document.createElement("span");
          s.textContent = suffix;
          el.append(s);
        }
        el.parentElement.hidden = !text;
      };
      set("rev-mrr", mrrText, "/mo");
      set("rev-total", totalText);
      set("rev-count", String(earning), " of " + document.querySelectorAll(".app[data-app]").length);
      if (updated) {
        const dt = new Date(updated);
        document.getElementById("rev-updated").textContent =
          "Updated " + dt.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) + ". Self-reported, before fees.";
      }
      box.hidden = false;
    })
    .catch(() => {});
})();
