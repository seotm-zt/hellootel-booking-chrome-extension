/**
 * Content script core - parser-agnostic booking saver.
 *
 * Finds the correct parser for the current page via ParserRegistry,
 * then injects "Save booking" buttons into each detected booking card.
 *
 * To support a new site: create parsers/<site>.js and register it via
 * ParserRegistry.register(). No changes needed here.
 */

const ENHANCED_ATTR = "data-ttb-enhanced";

// On Page Report preview pages the real URL is injected as a meta tag.
// Use it for parser matching so the correct parser is found.
function getEffectiveLocation() {
  const meta = document.querySelector('meta[name="ttb-preview-url"]');
  if (meta?.content) {
    try { return new URL(meta.content); } catch {}
  }
  return window.location;
}
const BUTTON_LABEL = "Save to database";
const UPDATE_LABEL = "Update in database";
const SAVING_LABEL = "Saving...";

let scanQueued = false;
let toastElement = null;

// Toast

function getToast() {
  if (toastElement) return toastElement;
  toastElement = document.createElement("div");
  toastElement.className = "ttb-toast";
  document.body.appendChild(toastElement);
  return toastElement;
}

function showToast(message) {
  const toast = getToast();
  toast.textContent = message;
  toast.dataset.visible = "true";
  window.clearTimeout(showToast._tid);
  showToast._tid = window.setTimeout(() => {
    toast.dataset.visible = "false";
  }, 2500);
}

// API

async function saveBookingToServer(booking) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage({ type: "SAVE_BOOKING", payload: booking }, (response) => {
      if (chrome.runtime.lastError) {
        reject(new Error(chrome.runtime.lastError.message));
        return;
      }
      if (response?.ok) {
        resolve(response.data);
      } else {
        reject(new Error(response?.error || "Unknown error"));
      }
    });
  });
}

// Button injection

function buildCommonBookingData(booking) {
  return {
    ...booking,
    source_url: window.location.href,
    page_title: document.title,
    language: document.documentElement.lang || "en",
    captured_at: new Date().toISOString(),
  };
}

async function injectButton(card, parser) {
  if (card.hasAttribute(ENHANCED_ATTR)) return;
  card.setAttribute(ENHANCED_ATTR, "true");

  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "ttb-save-booking-button";
  btn.textContent = BUTTON_LABEL;

  const wrap = document.createElement("div");
  wrap.className = "ttb-save-booking-actions";
  wrap.append(btn);

  parser.getButtonContainer(card).appendChild(wrap);

  btn.addEventListener("click", async () => {
    const prev = btn.textContent;
    btn.disabled = true;
    btn.textContent = SAVING_LABEL;

    try {
      const authorized = await isAuthorized();
      if (!authorized) {
        showToast("Sign in to the Booking Saver extension.");
        btn.textContent = prev;
        return;
      }

      const raw = parser.parseCard(card);
      const booking = buildCommonBookingData(raw);
      const result = await saveBookingToServer(booking);

      btn.textContent = UPDATE_LABEL;
      showToast(
        result.created
          ? `Booking ${booking.booking_code || booking.hotel_name || ""} saved.`
          : `Booking ${booking.booking_code || booking.hotel_name || ""} updated.`
      );
    } catch (err) {
      console.error("[TTB] save failed", err);
      btn.textContent = prev;
      showToast(`Failed to save: ${err.message || "error"}`);
    } finally {
      btn.disabled = false;
    }
  });
}

function clearInjectedButtons() {
  document.querySelectorAll(".ttb-save-booking-actions").forEach((el) => el.remove());
  document.querySelectorAll(`[${ENHANCED_ATTR}]`).forEach((el) => el.removeAttribute(ENHANCED_ATTR));
}

// Scan

function queueScan() {
  if (scanQueued) return;
  scanQueued = true;

  window.requestAnimationFrame(async () => {
    scanQueued = false;

    const parser = ParserRegistry.find(getEffectiveLocation());
    if (!parser) return;

    const authorized = await isAuthorized();
    if (!authorized) {
      clearInjectedButtons();
      return;
    }

    for (const card of parser.getCards()) {
      await injectButton(card, parser);
    }
  });
}

function installObserver() {
  new MutationObserver(() => queueScan()).observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
}

// Boot

async function boot() {
  await Promise.all([
    ParserRegistry.loadParsers(),
    ParserRegistry.loadRules(),
  ]);
  queueScan();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot, { once: true });
} else {
  boot();
}

window.addEventListener("focus", queueScan);
document.addEventListener("visibilitychange", () => {
  if (!document.hidden) queueScan();
});

installObserver();
