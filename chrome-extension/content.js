/**
 * Content script core - parser-agnostic booking saver.
 *
 * The confirmation modal and its API/reference helpers live in the shared
 * booking-modal.js (loaded before this file), so they can be reused by the
 * popup. This file keeps the page-specific concerns: finding booking cards,
 * injecting the "Send to HelloOtel" button, and tracking per-booking state.
 */

const ENHANCED_ATTR = "data-ttb-enhanced";

function getEffectiveLocation() {
  const meta = document.querySelector('meta[name="ttb-preview-url"]');
  if (meta?.content) {
    try { return new URL(meta.content); } catch {}
  }
  return window.location;
}

const BUTTON_LABEL          = "Send to HelloOtel";
const CONFIRMED_LABEL       = "Confirm & send to HelloOtel";
const HOTEL_NOT_FOUND_LABEL = "Hotel not found in HelloOtel";
const FAILED_LABEL          = "Failed to send booking";
const SENT_LABEL            = "Sent to HelloOtel ✓";
const SAVING_LABEL          = "Saving...";

let scanQueued = false;

// ── Booking state caches ──────────────────────────────────────────────
// Key format: "domain:booking_code"
let sentCodes           = new Set(); // hellootel_reservation_id set → green
let failedCodes         = new Set(); // confirmed_at set, no reservation_id → orange
let savedMatchedCodes   = new Set(); // not confirmed, hotel_id set → yellow "Confirm & send"
let savedUnmatchedCodes = new Set(); // not confirmed, no hotel_id → yellow "Hotel not found"
let sentBookingProcessed = new Map(); // booking_code → processed_booking for sent bookings

async function refreshConfirmedCodes() {
  try {
    const bookings = await loadBookingsFromServer();
    sentCodes = new Set(
      bookings
        .filter(b => b.processed_booking?.hellootel_reservation_id)
        .map(b => b.booking_code)
    );
    bookings
      .filter(b => b.processed_booking?.hellootel_reservation_id)
      .forEach(b => sentBookingProcessed.set(b.booking_code, b.processed_booking));
    failedCodes = new Set(
      bookings
        .filter(b => b.processed_booking?.confirmed_at && !b.processed_booking?.hellootel_reservation_id)
        .map(b => b.booking_code)
    );
    savedMatchedCodes = new Set(
      bookings
        .filter(b => !b.processed_booking?.confirmed_at && b.processed_booking?.hotel_id)
        .map(b => b.booking_code)
    );
    savedUnmatchedCodes = new Set(
      bookings
        .filter(b => !b.processed_booking?.confirmed_at && !b.processed_booking?.hotel_id)
        .map(b => b.booking_code)
    );
  } catch { /* non-critical */ }
}

// ── Button injection ─────────────────────────────────────────────────

function buildCommonBookingData(booking) {
  // Use effective location (real source URL on preview pages) — otherwise
  // saves from booking.localhost and booking-configurator.hellootel.com
  // would end up with different source_domain and create duplicate rows.
  const loc = getEffectiveLocation();
  return {
    ...booking,
    source_url:  loc.href,
    page_title:  document.title,
    language:    document.documentElement.lang || "en",
    captured_at: new Date().toISOString(),
  };
}

// ── Inject save button ────────────────────────────────────────────────

// Swap a button's click handler to "show sent data" mode.
// Clones the node when it's already in the DOM to drop prior listeners.
// Returns the (possibly new) node — callers must reassign their reference.
function attachSentClickHandler(btn, parsedCode) {
  const inDom  = !!btn.parentNode;
  const target = inDom ? btn.cloneNode(true) : btn;
  if (inDom) btn.replaceWith(target);
  target.disabled = false;
  target.addEventListener("click", async () => {
    target.disabled = true;
    try {
      let processed = parsedCode ? sentBookingProcessed.get(parsedCode) : null;
      if (!processed && parsedCode) {
        const bookings = await loadBookingsFromServer();
        const match = bookings.find(b => b.booking_code === parsedCode && b.processed_booking?.hellootel_reservation_id);
        processed = match?.processed_booking ?? null;
        if (processed) sentBookingProcessed.set(parsedCode, processed);
      }
      if (processed) showSentDataModal(processed);
    } catch (e) {
      console.error("[TTB] failed to load sent booking", e);
    } finally {
      target.disabled = false;
    }
  });
  return target;
}

async function injectButton(card, parser) {
  if (card.hasAttribute(ENHANCED_ATTR)) return;
  card.setAttribute(ENHANCED_ATTR, "true");

  let btn = document.createElement("button");
  btn.type      = "button";
  btn.className = "ttb-save-booking-button";

  // Check booking state from previous sessions
  const parsedCode = parser.parseCard(card)?.booking_code;
  if (parsedCode) btn.dataset.bookingCode = parsedCode;

  const wrap = document.createElement("div");
  wrap.className = "ttb-save-booking-actions";


  if (parsedCode && sentCodes.has(parsedCode)) {
    btn.textContent = SENT_LABEL;
    btn.classList.add("ttb-save-booking-button--sent");
    btn = attachSentClickHandler(btn, parsedCode);
    wrap.append(btn);
    const container = parser.getButtonContainer(card);
    const placement = parser.buttonPlacement ?? "inside";
    if (placement === "before") container.parentNode?.insertBefore(wrap, container);
    else if (placement === "after") container.parentNode?.insertBefore(wrap, container.nextSibling);
    else container.appendChild(wrap);
    return;
  } else if (parsedCode && failedCodes.has(parsedCode)) {
    btn.textContent = FAILED_LABEL;
    btn.classList.add("ttb-save-booking-button--confirmed");
  } else if (parsedCode && savedMatchedCodes.has(parsedCode)) {
    btn.textContent = CONFIRMED_LABEL;
    btn.classList.add("ttb-save-booking-button--saved");
  } else if (parsedCode && savedUnmatchedCodes.has(parsedCode)) {
    btn.textContent = HOTEL_NOT_FOUND_LABEL;
    btn.classList.add("ttb-save-booking-button--notfound");
  } else {
    btn.textContent = BUTTON_LABEL;
  }
  wrap.append(btn);

  const container = parser.getButtonContainer(card);
  const placement = parser.buttonPlacement ?? "inside";
  if (placement === "before") {
    container.parentNode?.insertBefore(wrap, container);
  } else if (placement === "after") {
    container.parentNode?.insertBefore(wrap, container.nextSibling);
  } else {
    container.appendChild(wrap);
  }

  btn.addEventListener("click", async () => {
    const prev = btn.textContent;
    btn.disabled    = true;
    btn.textContent = SAVING_LABEL;

    try {
      const authorized = await isAuthorized();
      if (!authorized) {
        showToast("Sign in to the extension first.");
        btn.textContent = prev;
        return;
      }

      const raw     = parser.parseCard(card);
      const booking = buildCommonBookingData(raw);

      // No guests parsed → skip persisting the raw booking. Open the confirm
      // modal in direct mode so the user can enter guests by hand; on confirm
      // we POST to /processed-bookings/direct (no source_booking_id).
      if (!raw.tourists || raw.tourists.length === 0) {
        btn.textContent = prev;
        btn.disabled    = false;
        const modalResult = await showConfirmModal({
          data: booking, processed: null, hotel_match: null, _direct: true,
          _operatorId: parser.operator_id ?? null,
        });
        const modalStatus = modalResult?.status;
        if (modalStatus === "sent") {
          btn.textContent = SENT_LABEL;
          btn.classList.add("ttb-save-booking-button--sent");
          if (parsedCode && modalResult.processed) {
            sentBookingProcessed.set(parsedCode, modalResult.processed);
          }
          btn = attachSentClickHandler(btn, parsedCode);
        } else if (modalStatus === "confirmed_only") {
          btn.textContent = FAILED_LABEL;
          btn.classList.add("ttb-save-booking-button--confirmed");
        }
        return;
      }

      const result  = await saveBookingToServer(booking);

      // Reflect state from previous sessions immediately
      const alreadySent      = !!result.processed?.hellootel_reservation_id;
      const alreadyFailed    = !!result.processed?.confirmed_at && !alreadySent;
      const hasHotelId       = !!result.processed?.hotel_id;

      if (alreadySent) {
        btn.textContent = SENT_LABEL;
        btn.classList.add("ttb-save-booking-button--sent");
        if (parsedCode) sentBookingProcessed.set(parsedCode, result.processed);
        showSentDataModal(result.processed);
        btn = attachSentClickHandler(btn, parsedCode);
        return;
      }

      if (alreadyFailed) {
        btn.textContent = FAILED_LABEL;
        btn.classList.remove("ttb-save-booking-button--saved");
        btn.classList.add("ttb-save-booking-button--confirmed");
      } else if (hasHotelId) {
        btn.textContent = CONFIRMED_LABEL;
        btn.classList.remove("ttb-save-booking-button--confirmed");
        btn.classList.add("ttb-save-booking-button--saved");
      } else {
        btn.textContent = HOTEL_NOT_FOUND_LABEL;
        btn.classList.remove("ttb-save-booking-button--confirmed", "ttb-save-booking-button--saved");
        btn.classList.add("ttb-save-booking-button--notfound");
      }

      const modalResult = await showConfirmModal(result);
      const modalStatus = modalResult?.status;
      // Server returns the up-to-date ProcessedBooking from /confirm; fall back
      // to the pre-confirm snapshot only if the modal couldn't capture it.
      const freshProcessed = modalResult?.processed ?? result.processed ?? null;

      if (modalStatus === "deleted") {
        btn.textContent = BUTTON_LABEL;
        btn.disabled = false;
        btn.classList.remove("ttb-save-booking-button--sent", "ttb-save-booking-button--confirmed", "ttb-save-booking-button--saved", "ttb-save-booking-button--notfound");
      } else if (modalStatus === "sent") {
        btn.textContent = SENT_LABEL;
        btn.classList.remove("ttb-save-booking-button--confirmed", "ttb-save-booking-button--saved");
        btn.classList.add("ttb-save-booking-button--sent");
        if (parsedCode && freshProcessed) sentBookingProcessed.set(parsedCode, freshProcessed);
        btn = attachSentClickHandler(btn, parsedCode);
      } else if (modalStatus === "confirmed_only") {
        // confirmed but not sent to HellOotel (no hotel or send failed)
        btn.textContent = FAILED_LABEL;
        btn.disabled = false;
        btn.classList.remove("ttb-save-booking-button--saved", "ttb-save-booking-button--sent");
        btn.classList.add("ttb-save-booking-button--confirmed");
      }
      // else (modal cancelled) → button label already set above, no change needed
    } catch (err) {
      console.error("[TTB] save failed", err);
      btn.textContent = prev;
      showToast(`Failed to save: ${err.message || "error"}`);
    } finally {
      if (!btn.classList.contains("ttb-save-booking-button--sent")) {
        btn.disabled = false;
      }
    }
  });
}

function clearInjectedButtons() {
  document.querySelectorAll(".ttb-save-booking-actions").forEach((el) => el.remove());
  document.querySelectorAll(`[${ENHANCED_ATTR}]`).forEach((el) => el.removeAttribute(ENHANCED_ATTR));
}

// ── Scan ──────────────────────────────────────────────────────────────

// Self-heal the registry: boot() loads parsers only once, so a transient failure
// (asleep service worker, network blip, stale token → 401) would otherwise leave
// the page button-less forever. Retry the load — guarded against spamming the API
// on a persistent failure (e.g. a truly invalid token) with an in-flight lock and
// a cooldown. A 401 is handled separately (background marks the session expired →
// isAuthorized() turns false → queueScan stops calling this).
const PARSER_RELOAD_COOLDOWN_MS = 10000;
let parserReloadInFlight = null;
let lastParserReloadAt   = 0;

function ensureParsersLoaded() {
  if (ParserRegistry.isLoaded()) return Promise.resolve(true);
  if (parserReloadInFlight) return parserReloadInFlight;
  if (Date.now() - lastParserReloadAt < PARSER_RELOAD_COOLDOWN_MS) return Promise.resolve(false);
  lastParserReloadAt = Date.now();
  parserReloadInFlight = (async () => {
    try {
      await Promise.all([ParserRegistry.loadParsers(), ParserRegistry.loadRules()]);
      // Booking-state sets were likely empty too if the boot load failed.
      if (ParserRegistry.isLoaded()) await refreshConfirmedCodes();
    } finally {
      parserReloadInFlight = null;
    }
    return ParserRegistry.isLoaded();
  })();
  return parserReloadInFlight;
}

function queueScan() {
  if (scanQueued) return;
  scanQueued = true;
  window.requestAnimationFrame(async () => {
    scanQueued = false;
    const authorized = await isAuthorized();
    if (!authorized) { clearInjectedButtons(); return; }
    // Recover from a failed one-shot boot load before giving up on this page.
    if (!ParserRegistry.isLoaded()) await ensureParsersLoaded();
    const parser = ParserRegistry.find(getEffectiveLocation());
    if (!parser) return;
    for (const card of parser.getCards()) {
      await injectButton(card, parser);
    }
  });
}

function installObserver() {
  new MutationObserver(() => queueScan()).observe(document.documentElement, {
    childList: true, subtree: true,
  });
}

// ── Boot ──────────────────────────────────────────────────────────────

async function boot() {
  await Promise.all([
    ParserRegistry.loadParsers(),
    ParserRegistry.loadRules(),
    refreshConfirmedCodes(),
    ensureCurrencies(),
    ensureOperators(),
    ensureCountries(),
    ensureCities(),
  ]);
  queueScan();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot, { once: true });
} else {
  boot();
}

window.addEventListener("focus", queueScan);
document.addEventListener("visibilitychange", () => { if (!document.hidden) queueScan(); });

// Clean up the modal if the user navigates away (Livewire SPA or real unload).
window.addEventListener("pagehide", destroyModal);
document.addEventListener("livewire:navigate", destroyModal);

// React to login/logout from the popup without a page refresh.
// Without this, buttons injected right after login show the default blue
// "Send to HelloOtel" because the sent/failed/saved sets were populated
// (or rather, not populated) while the user was still anonymous.
chrome.storage.onChanged.addListener(async (changes, area) => {
  if (area !== "local" || !changes[AUTH_STATE_KEY]) return;
  const newValue  = changes[AUTH_STATE_KEY].newValue;
  const wasAuthed = !!changes[AUTH_STATE_KEY].oldValue?.authorized;
  const isAuthed  = !!newValue?.authorized;
  if (isAuthed && !wasAuthed) {
    // Parsers/rules now require auth. If boot() ran while logged out the registry
    // is empty, so (re)load them on login before scanning — otherwise no parser
    // matches the page and no buttons appear until a manual reload.
    await Promise.all([ParserRegistry.loadParsers(), ParserRegistry.loadRules()]);
    await refreshConfirmedCodes();
    clearInjectedButtons();
    queueScan();
  } else if (!isAuthed && wasAuthed) {
    clearInjectedButtons();
    // Tell the user when the session was dropped by an expired/revoked token
    // (background.js sets reason:"expired"), but stay silent on a manual logout.
    if (newValue?.reason === "expired") {
      showToast("Session expired — please sign in to the extension again.");
    }
  }
});

installObserver();

// ── Popup → content messaging ─────────────────────────────────────────
chrome.runtime.onMessage.addListener((message) => {
  if (message.type === "BOOKING_DELETED" && message.bookingCode) {
    const btn = document.querySelector(`.ttb-save-booking-button[data-booking-code="${message.bookingCode}"]`);
    if (btn) {
      btn.textContent = BUTTON_LABEL;
      btn.disabled    = false;
      btn.className   = "ttb-save-booking-button";
    }
    // Refresh state sets so MutationObserver rescans don't restore old state
    refreshConfirmedCodes();
  }
});
