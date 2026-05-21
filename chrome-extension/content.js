/**
 * Content script core - parser-agnostic booking saver.
 */

const ENHANCED_ATTR = "data-ttb-enhanced";

function getEffectiveLocation() {
  const meta = document.querySelector('meta[name="ttb-preview-url"]');
  if (meta?.content) {
    try { return new URL(meta.content); } catch {}
  }
  return window.location;
}

const BUTTON_LABEL     = "Save to database";
const UPDATE_LABEL     = "Update in database";
const CONFIRMED_LABEL  = "Confirmed ✓";
const SENT_LABEL       = "Sent to HellOotel ✓";
const SAVING_LABEL     = "Saving...";

let scanQueued = false;
let toastElement = null;

// ── Toast ────────────────────────────────────────────────────────────

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
  showToast._tid = window.setTimeout(() => { toast.dataset.visible = "false"; }, 2500);
}

function showHellootelErrorDialog(message) {
  return new Promise((resolve) => {
    const dialog = document.createElement("div");
    dialog.className = "ttb-he-overlay";
    dialog.innerHTML = `
      <div class="ttb-he-box">
        <div class="ttb-he-icon">⚠️</div>
        <div class="ttb-he-title">HellOotel send error</div>
        <div class="ttb-he-message"></div>
        <div class="ttb-he-actions">
          <button class="ttb-he-btn ttb-he-btn--fix" type="button">✎ Fix</button>
          <button class="ttb-he-btn ttb-he-btn--ignore" type="button">Ignore</button>
        </div>
      </div>
    `;
    dialog.querySelector(".ttb-he-message").textContent = message;
    document.body.appendChild(dialog);

    dialog.querySelector(".ttb-he-btn--fix").addEventListener("click", () => {
      dialog.remove();
      resolve("fix");
    });
    dialog.querySelector(".ttb-he-btn--ignore").addEventListener("click", () => {
      dialog.remove();
      resolve("ignore");
    });
  });
}

// ── API helpers ──────────────────────────────────────────────────────

function sendMessage(msg) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(msg, (response) => {
      if (chrome.runtime.lastError) { reject(new Error(chrome.runtime.lastError.message)); return; }
      response?.ok ? resolve(response.data) : reject(new Error(response?.error || "Unknown error"));
    });
  });
}

const loadBookingsFromServer = ()                   => sendMessage({ type: "LOAD_BOOKINGS" }).then(d => d?.data ?? []);
const saveBookingToServer    = (booking)            => sendMessage({ type: "SAVE_BOOKING",    payload: booking });
const searchHotelsOnServer   = (query)              => sendMessage({ type: "SEARCH_HOTELS",   query }).then(d => d?.data ?? []);
const getRoomTypesFromServer = (hotelId)            => sendMessage({ type: "GET_ROOM_TYPES",  hotelId }).then(d => d?.data ?? []);
const confirmBookingOnServer  = (bookingId, payload) => sendMessage({ type: "CONFIRM_BOOKING",  bookingId, payload });
const deleteBookingFromServer = (bookingId)          => sendMessage({ type: "DELETE_BOOKING",   bookingId });
const getHotelVoteFromServer  = (hotelId)            => sendMessage({ type: "GET_HOTEL_VOTE",   hotelId }).then(d => d?.vote ?? null);

// ── Currency list cache ───────────────────────────────────────────────
let _currencies = null; // null = not yet fetched

async function ensureCurrencies() {
  if (_currencies !== null) return _currencies;
  try {
    const json = await sendMessage({ type: "LOAD_CURRENCIES" });
    const raw = json?.data ?? [];
    if (Array.isArray(raw)) {
      _currencies = raw.map((c) =>
        typeof c === "object" && c.code
          ? { code: c.code, name: c.name ?? c.code }
          : { code: String(c), name: String(c) }
      );
    } else {
      _currencies = Object.entries(raw).map(([code, name]) => ({ code, name }));
    }
  } catch {
    _currencies = [];
  }
  return _currencies;
}

function populateCurrencySelectEl(select, selectedCode) {
  select.innerHTML = '<option value="">—</option>';
  let found = false;
  for (const c of (_currencies || [])) {
    const opt = document.createElement("option");
    opt.value = c.code;
    opt.textContent = c.code + (c.name && c.name !== c.code ? " — " + c.name : "");
    if (c.code === selectedCode) { opt.selected = true; found = true; }
    select.appendChild(opt);
  }
  // If selectedCode is not in the API list, add it so the value is visible
  if (selectedCode && !found) {
    const opt = document.createElement("option");
    opt.value = selectedCode;
    opt.textContent = selectedCode;
    opt.selected = true;
    select.appendChild(opt);
  }
}

// ── Booking state caches ──────────────────────────────────────────────
// Key format: "domain:booking_code"
let sentCodes      = new Set(); // confirmed + accepted by HellOotel
let confirmedCodes = new Set(); // confirmed but not yet sent to HellOotel
let savedCodes     = new Set(); // saved but not yet confirmed

async function refreshConfirmedCodes() {
  try {
    const bookings = await loadBookingsFromServer();
    sentCodes = new Set(
      bookings
        .filter(b => b.processed_booking?.hellootel_reservation_id)
        .map(b => b.booking_code)
    );
    confirmedCodes = new Set(
      bookings
        .filter(b => b.processed_booking?.confirmed_at && !b.processed_booking?.hellootel_reservation_id)
        .map(b => b.booking_code)
    );
    savedCodes = new Set(
      bookings
        .filter(b => !b.processed_booking?.confirmed_at)
        .map(b => b.booking_code)
    );
  } catch { /* non-critical */ }
}

// ── Button injection ─────────────────────────────────────────────────

function buildCommonBookingData(booking) {
  return {
    ...booking,
    source_url:  window.location.href,
    page_title:  document.title,
    language:    document.documentElement.lang || "en",
    captured_at: new Date().toISOString(),
  };
}

// ── Confirmation modal ────────────────────────────────────────────────

let modalElement = null;
let hotelSearchTimeout = null;

function destroyModal() {
  modalElement?.remove();
  modalElement = null;
  clearTimeout(hotelSearchTimeout);
}

function esc(v) {
  return String(v ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
}

function normaliseTourist(t = {}) {
  if (t.last_name || t.first_name) return t;
  // full_name: "FIRST LAST" → split on last space → last_name = last word
  const full = (t.full_name || "").trim();
  const idx  = full.lastIndexOf(" ");
  return {
    last_name:  idx !== -1 ? full.slice(idx + 1) : full,
    first_name: idx !== -1 ? full.slice(0, idx)  : "",
    dob: t.dob ?? "",
  };
}

function buildTouristRow(t = {}) {
  const n   = normaliseTourist(t);
  const div = document.createElement("div");
  div.className = "ttb-tourist-row";
  div.innerHTML = `
    <input class="ttb-modal__input ttb-tourist__last"  type="text" placeholder="Last name"    value="${esc(n.last_name)}">
    <input class="ttb-modal__input ttb-tourist__first" type="text" placeholder="First name"   value="${esc(n.first_name)}">
    <input class="ttb-modal__input ttb-tourist__dob"   type="text" placeholder="Date of birth" value="${esc(n.dob)}">
    <button class="ttb-tourist__remove" type="button" title="Remove">✕</button>
  `;
  div.querySelector(".ttb-tourist__remove").addEventListener("click", () => div.remove());
  return div;
}

async function loadRoomTypes(hotelId, selectEl, preselectedId) {
  selectEl.disabled = true;
  selectEl.innerHTML = '<option value="">Loading...</option>';
  try {
    const types = await getRoomTypesFromServer(hotelId);
    selectEl.innerHTML = '<option value="">— select room type —</option>';
    for (const t of types) {
      const opt = document.createElement("option");
      opt.value       = t.id;
      opt.textContent = t.name;
      if (preselectedId && t.id === preselectedId) opt.selected = true;
      selectEl.appendChild(opt);
    }
    selectEl.disabled = false;
  } catch {
    selectEl.innerHTML = '<option value="">— loading error —</option>';
    selectEl.disabled  = false;
  }
}

// Returns Promise<boolean> — true if user confirmed, false if cancelled
async function showConfirmModal(saveResult) {
  destroyModal();

  const raw        = saveResult.data;
  const processed  = saveResult.processed;
  const hotelMatch = saveResult.hotel_match;

  const pre = {
    hotelId:     processed?.hotel_id       ?? null,
    // If auto-matched, show the canonical HellOotel name, not the raw parsed value
    hotelName:   hotelMatch ? hotelMatch.name : (processed?.hotel_name ?? raw.hotel_name ?? ""),
    roomTypeId:  processed?.room_type_id   ?? null,
    bookingCode: processed?.booking_code   ?? raw.booking_code ?? "",
    reservDate:  processed?.reservation_date ?? "",
    reservTime:  processed?.reservation_time ?? "",
    arrivalAt:   processed?.arrival_at     ?? "",
    departureAt: processed?.departure_at   ?? "",
    price:       processed?.price          ?? "",
    currency:    processed?.currency_code  ?? detectCurrency(raw.total_price) ?? "",
    adults:      processed?.person_count_adults   ?? raw.adults   ?? "",
    children:    processed?.person_count_children ?? raw.children ?? "",
    infants:     processed?.person_count_teens    ?? raw.infants  ?? "",
    tourists:    processed?.tourists ?? raw.tourists ?? [],
    hotelVote:   processed?.hotel_vote ?? null,
  };

  const overlay = document.createElement("div");
  overlay.className = "ttb-modal-overlay";
  overlay.innerHTML = `
    <div class="ttb-modal" role="dialog" aria-modal="true">

      <div class="ttb-modal__header">
        <span class="ttb-modal__title">Confirm booking</span>
        <button class="ttb-modal__close" type="button" aria-label="Close">✕</button>
      </div>

      <div class="ttb-modal__body">

        <label class="ttb-modal__label">
          Hotel <span class="ttb-required">*</span>
          ${hotelMatch ? `<span class="ttb-modal__match-badge">Auto-matched · <strong>${hotelMatch.score}%</strong></span>` : ""}
        </label>
        <div class="ttb-modal__autocomplete">
          <input class="ttb-modal__input" id="ttb-hotel-input" type="text"
            placeholder="Type hotel name..."
            value="${esc(pre.hotelName)}" autocomplete="off" />
          <ul class="ttb-modal__suggestions" id="ttb-hotel-suggestions" hidden></ul>
        </div>

        <label class="ttb-modal__label">Room type <span class="ttb-required">*</span></label>
        <select class="ttb-modal__select" id="ttb-room-select" ${pre.hotelId ? "" : "disabled"}>
          <option value="">${pre.hotelId ? "— select room type —" : "— select hotel first —"}</option>
        </select>

        <div class="ttb-rating-row">
          <span class="ttb-rating-label">Hotel rating <span class="ttb-required">*</span></span>
          <div class="ttb-stars" id="ttb-stars">
            ${[1,2,3,4,5,6,7,8,9,10].map(i => `<span class="ttb-star" data-vote="${i}">☆</span>`).join("")}
          </div>
        </div>

        <div class="ttb-modal__section-title">Booking details</div>

        <label class="ttb-modal__label">Booking number</label>
        <input class="ttb-modal__input" id="ttb-booking-code" type="text" value="${esc(pre.bookingCode)}" placeholder="ORD-123456" />

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Booking date</label>
            <input class="ttb-modal__input" id="ttb-reserv-date" type="date" value="${esc(pre.reservDate)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Time</label>
            <input class="ttb-modal__input" id="ttb-reserv-time" type="text" placeholder="17:03" maxlength="5" value="${esc(pre.reservTime)}" />
          </div>
        </div>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Check-in</label>
            <input class="ttb-modal__input" id="ttb-arrival" type="date" value="${esc(pre.arrivalAt)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Check-out</label>
            <input class="ttb-modal__input" id="ttb-departure" type="date" value="${esc(pre.departureAt)}" />
          </div>
        </div>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Price</label>
            <input class="ttb-modal__input" id="ttb-price" type="text" placeholder="1250.00" value="${esc(pre.price)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Currency</label>
            <select class="ttb-modal__input ttb-modal__select" id="ttb-currency">
              <option value="">—</option>
            </select>
          </div>
        </div>

        <div class="ttb-modal__row-3">
          <div>
            <label class="ttb-modal__label">Adults</label>
            <input class="ttb-modal__input" id="ttb-adults" type="number" min="0" value="${esc(pre.adults)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Children</label>
            <input class="ttb-modal__input" id="ttb-children" type="number" min="0" value="${esc(pre.children)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Infants</label>
            <input class="ttb-modal__input" id="ttb-infants" type="number" min="0" value="${esc(pre.infants)}" />
          </div>
        </div>

        <div class="ttb-modal__section-title">Guests</div>
        <div id="ttb-tourists-list"></div>
        <button class="ttb-modal__add-tourist" type="button" id="ttb-add-tourist">+ Add guest</button>

        <p class="ttb-modal__required-note"><span class="ttb-required">*</span> Required fields</p>

      </div>

      <div class="ttb-modal__footer">
        <button class="ttb-modal__btn ttb-modal__btn--delete"  type="button">Delete from database</button>
        <div style="flex:1"></div>
        <button class="ttb-modal__btn ttb-modal__btn--cancel"  type="button">Cancel</button>
        <button class="ttb-modal__btn ttb-modal__btn--confirm" type="button" disabled>Confirm</button>
      </div>
      <div class="ttb-modal__api-error" id="ttb-api-error" hidden></div>

    </div>
  `;

  document.body.appendChild(overlay);
  modalElement = overlay;

  // Populate currency select (async — list may not be loaded yet)
  ensureCurrencies().then(() => {
    const currSelect = overlay.querySelector("#ttb-currency");
    if (currSelect) populateCurrencySelectEl(currSelect, pre.currency);
  });

  const hotelInput   = overlay.querySelector("#ttb-hotel-input");
  const suggestions  = overlay.querySelector("#ttb-hotel-suggestions");
  const roomSelect   = overlay.querySelector("#ttb-room-select");
  const touristsList = overlay.querySelector("#ttb-tourists-list");
  const confirmBtn   = overlay.querySelector(".ttb-modal__btn--confirm");

  let selectedHotelId   = pre.hotelId;
  let selectedHotelName = pre.hotelName;
  // DB/our API stores 1-10 (star count); HellOotel API uses 0-5 (converted server-side)
  let selectedVote      = pre.hotelVote ?? null;

  // ── Star rating ───────────────────────────────────────────────────
  const starBtns = overlay.querySelectorAll(".ttb-star");
  function updateStars(vote) {
    selectedVote = (vote > 0) ? vote : null;
    starBtns.forEach(btn => {
      const v = parseInt(btn.dataset.vote, 10);
      const filled = selectedVote !== null && v <= selectedVote;
      btn.textContent = filled ? "★" : "☆";
      btn.classList.toggle("ttb-star--filled", filled);
    });
    updateConfirmState();
  }
  starBtns.forEach(btn => btn.addEventListener("click", () => {
    updateStars(parseInt(btn.dataset.vote, 10));
  }));
  updateStars(selectedVote ?? 0);

  // ── Confirm button state ──────────────────────────────────────────
  function updateConfirmState() {
    confirmBtn.disabled = !selectedHotelId || !roomSelect.value || !(selectedVote > 0);
  }

  roomSelect.addEventListener("change", updateConfirmState);

  // ── Pre-load room types and vote if hotel already matched ────────
  if (pre.hotelId) {
    await loadRoomTypes(pre.hotelId, roomSelect, pre.roomTypeId);
    updateConfirmState();
    if (selectedVote === null) {
      getHotelVoteFromServer(pre.hotelId).then(v => {
        if (selectedVote === null) updateStars(v ?? 0);
      }).catch(() => {});
    }
  }

  // ── Pre-fill tourists ─────────────────────────────────────────────
  for (const t of pre.tourists) {
    touristsList.appendChild(buildTouristRow(t));
  }

  overlay.querySelector("#ttb-add-tourist").addEventListener("click", () => {
    touristsList.appendChild(buildTouristRow());
  });

  // ── Hotel autocomplete ────────────────────────────────────────────
  function hideSuggestions() {
    suggestions.hidden = true;
    suggestions.innerHTML = "";
  }

  function showSuggestions(hotels) {
    suggestions.innerHTML = "";
    if (!hotels.length) { hideSuggestions(); return; }
    for (const h of hotels) {
      const li = document.createElement("li");
      li.className   = "ttb-modal__suggestion";
      li.textContent = h.name;
      li.addEventListener("mousedown", async (e) => {
        e.preventDefault();
        selectedHotelId   = h.id;
        selectedHotelName = h.name;
        hotelInput.value  = h.name;
        hideSuggestions();
        await loadRoomTypes(h.id, roomSelect, null);
        updateConfirmState();
        getHotelVoteFromServer(h.id).then(v => { updateStars(v ?? 0); }).catch(() => {});
      });
      suggestions.appendChild(li);
    }
    suggestions.hidden = false;
  }

  hotelInput.addEventListener("input", () => {
    selectedHotelId = null;
    updateConfirmState();
    clearTimeout(hotelSearchTimeout);
    const q = hotelInput.value.trim();
    if (q.length < 2) { hideSuggestions(); return; }
    hotelSearchTimeout = setTimeout(async () => {
      try { showSuggestions(await searchHotelsOnServer(q)); } catch { hideSuggestions(); }
    }, 300);
  });

  hotelInput.addEventListener("blur", () => setTimeout(hideSuggestions, 150));

  // ── Return Promise resolving to true / false / "deleted" ─────────
  return new Promise((resolve) => {

    function closeCancel() {
      destroyModal();
      showToast("Booking saved. Not yet confirmed.");
      resolve(false);
    }

    overlay.querySelector(".ttb-modal__close").addEventListener("click", closeCancel);
    overlay.querySelector(".ttb-modal__btn--cancel").addEventListener("click", closeCancel);
    overlay.addEventListener("click", (e) => { if (e.target === overlay) closeCancel(); });
    document.addEventListener("keydown", function onKey(e) {
      if (e.key === "Escape") { closeCancel(); document.removeEventListener("keydown", onKey); }
    });

    const deleteBtn = overlay.querySelector(".ttb-modal__btn--delete");
    deleteBtn.addEventListener("click", async () => {
      if (!confirm("Delete this booking from the database?")) return;
      deleteBtn.disabled    = true;
      deleteBtn.textContent = "Deleting...";
      try {
        await deleteBookingFromServer(raw.id);
        destroyModal();
        showToast("Booking deleted from database.");
        resolve("deleted");
      } catch (err) {
        deleteBtn.disabled    = false;
        deleteBtn.textContent = "Delete from database";
        showToast(`Delete failed: ${err.message}`);
      }
    });

    const apiErrorEl = overlay.querySelector("#ttb-api-error");

    const showApiError = (msg) => {
      apiErrorEl.textContent = msg;
      apiErrorEl.hidden = false;
    };
    const clearApiError = () => { apiErrorEl.hidden = true; apiErrorEl.textContent = ""; };

    confirmBtn.addEventListener("click", async () => {
      confirmBtn.disabled    = true;
      confirmBtn.textContent = "Sending...";
      clearApiError();
      try {
        const selectedRoomId   = roomSelect.value ? parseInt(roomSelect.value, 10) : null;
        const selectedRoomName = roomSelect.selectedOptions[0]?.textContent?.trim() ?? null;

        const tourists = [...touristsList.querySelectorAll(".ttb-tourist-row")].map(row => ({
          last_name:  row.querySelector(".ttb-tourist__last").value.trim(),
          first_name: row.querySelector(".ttb-tourist__first").value.trim(),
          dob:        row.querySelector(".ttb-tourist__dob").value.trim(),
        })).filter(t => t.last_name || t.first_name);

        const result = await confirmBookingOnServer(raw.id, {
          hotel_id:         selectedHotelId || null,
          hotel_name:       selectedHotelName || null,
          room_type_id:     selectedRoomId || null,
          room_type_name:   selectedRoomId ? selectedRoomName : null,
          booking_code:     overlay.querySelector("#ttb-booking-code").value.trim() || null,
          reservation_date: overlay.querySelector("#ttb-reserv-date").value  || null,
          reservation_time: overlay.querySelector("#ttb-reserv-time").value.trim() || null,
          arrival_at:       overlay.querySelector("#ttb-arrival").value   || null,
          departure_at:     overlay.querySelector("#ttb-departure").value  || null,
          price:            overlay.querySelector("#ttb-price").value.trim() || null,
          currency_code:    overlay.querySelector("#ttb-currency").value.trim().toUpperCase() || null,
          adults:   overlay.querySelector("#ttb-adults").value   !== "" ? parseInt(overlay.querySelector("#ttb-adults").value,   10) : null,
          children: overlay.querySelector("#ttb-children").value !== "" ? parseInt(overlay.querySelector("#ttb-children").value, 10) : null,
          infants:  overlay.querySelector("#ttb-infants").value  !== "" ? parseInt(overlay.querySelector("#ttb-infants").value,  10) : null,
          tourists:    tourists.length ? tourists : null,
          hotel_vote:  selectedVote !== null ? selectedVote : undefined,
        });

        if (result?.hellootel?.error) {
          confirmBtn.disabled    = false;
          confirmBtn.textContent = "Retry";
          const choice = await showHellootelErrorDialog(result.hellootel.error);
          if (choice === "ignore") {
            destroyModal();
            showToast("Booking confirmed ✓ (HellOotel skipped)");
            resolve(true);
          }
          // "fix" → modal stays open, user can edit and retry
          return;
        }

        destroyModal();
        const wasSent = !!result?.hellootel?.id;
        showToast(wasSent ? "Booking sent to HellOotel ✓" : "Booking confirmed ✓");
        resolve(wasSent ? "sent" : true);
      } catch (err) {
        confirmBtn.disabled    = false;
        confirmBtn.textContent = "Retry";
        const choice = await showHellootelErrorDialog(err.message || "Connection error");
        if (choice === "ignore") {
          destroyModal();
          showToast("Booking confirmed ✓ (HellOotel skipped)");
          resolve(true);
        }
        // "fix" → modal stays open
      }
    });
  });
}

// ── Inject save button ────────────────────────────────────────────────

async function injectButton(card, parser) {
  if (card.hasAttribute(ENHANCED_ATTR)) return;
  card.setAttribute(ENHANCED_ATTR, "true");

  const btn = document.createElement("button");
  btn.type      = "button";
  btn.className = "ttb-save-booking-button";

  // Check booking state from previous sessions
  const parsedCode = parser.parseCard(card)?.booking_code;

  if (parsedCode && sentCodes.has(parsedCode)) {
    btn.textContent = SENT_LABEL;
    btn.classList.add("ttb-save-booking-button--sent");
    btn.disabled = true;
  } else if (parsedCode && confirmedCodes.has(parsedCode)) {
    btn.textContent = CONFIRMED_LABEL;
    btn.classList.add("ttb-save-booking-button--confirmed");
  } else if (parsedCode && savedCodes.has(parsedCode)) {
    btn.textContent = UPDATE_LABEL;
    btn.classList.add("ttb-save-booking-button--saved");
  } else {
    btn.textContent = BUTTON_LABEL;
  }

  const wrap = document.createElement("div");
  wrap.className = "ttb-save-booking-actions";
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
      const result  = await saveBookingToServer(booking);

      // Reflect state from previous sessions immediately
      const alreadySent      = !!result.processed?.hellootel_reservation_id;
      const alreadyConfirmed = !!result.processed?.confirmed_at;
      if (alreadySent) {
        btn.textContent = SENT_LABEL;
        btn.classList.add("ttb-save-booking-button--sent");
        btn.disabled = true;
      } else if (alreadyConfirmed) {
        btn.textContent = CONFIRMED_LABEL;
        btn.classList.add("ttb-save-booking-button--confirmed");
      } else {
        btn.textContent = UPDATE_LABEL;
      }

      if (alreadySent) return; // already in final state, skip modal

      const modalResult = await showConfirmModal(result);

      if (modalResult === "deleted") {
        btn.textContent = BUTTON_LABEL;
        btn.disabled = false;
        btn.classList.remove("ttb-save-booking-button--sent");
        btn.classList.remove("ttb-save-booking-button--confirmed");
        btn.classList.remove("ttb-save-booking-button--saved");
      } else if (modalResult === "sent") {
        btn.textContent = SENT_LABEL;
        btn.disabled = true;
        btn.classList.remove("ttb-save-booking-button--confirmed");
        btn.classList.remove("ttb-save-booking-button--saved");
        btn.classList.add("ttb-save-booking-button--sent");
      } else if (modalResult === true) {
        btn.textContent = CONFIRMED_LABEL;
        btn.disabled = false;
        btn.classList.remove("ttb-save-booking-button--saved");
        btn.classList.remove("ttb-save-booking-button--sent");
        btn.classList.add("ttb-save-booking-button--confirmed");
      } else if (!alreadyConfirmed) {
        btn.textContent = UPDATE_LABEL;
        btn.disabled = false;
        btn.classList.remove("ttb-save-booking-button--confirmed");
        btn.classList.add("ttb-save-booking-button--saved");
      }
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

// ── Scan ──────────────────────────────────────────────────────────────

function queueScan() {
  if (scanQueued) return;
  scanQueued = true;
  window.requestAnimationFrame(async () => {
    scanQueued = false;
    const parser = ParserRegistry.find(getEffectiveLocation());
    if (!parser) return;
    const authorized = await isAuthorized();
    if (!authorized) { clearInjectedButtons(); return; }
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

installObserver();
