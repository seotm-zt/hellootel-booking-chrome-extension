/**
 * Shared booking modal + API/reference helpers.
 *
 * Loaded by both the content script (booking-site pages) and the popup, so the
 * "Send to HelloOtel" confirmation modal — including the direct-mode flow used
 * for manual bookings — is available in both contexts.
 *
 * Depends on detectCurrency() from parsers/currency-utils.js (loaded separately).
 */

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
        <div class="ttb-he-title">HelloOtel send error</div>
        <div class="ttb-he-message"></div>
        <div class="ttb-he-actions">
          <button class="ttb-he-btn ttb-he-btn--fix" type="button">✎ Edit Booking</button>
          <button class="ttb-he-btn ttb-he-btn--ignore" type="button">Cancel send</button>
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
const getRoomTypesFromServer = (hotelId, arrivalAt, departureAt) =>
  sendMessage({ type: "GET_ROOM_TYPES", hotelId, arrivalAt, departureAt }).then(d => d?.data ?? []);
const confirmBookingOnServer  = (bookingId, payload) => sendMessage({ type: "CONFIRM_BOOKING",  bookingId, payload });
const storeProcessedDirect    = (payload)            => sendMessage({ type: "STORE_PROCESSED_DIRECT", payload });
const updateProcessedDirect   = (id, payload)        => sendMessage({ type: "UPDATE_PROCESSED_DIRECT", id, payload });
const loadProcessedFromServer = ()                   => sendMessage({ type: "LOAD_PROCESSED" }).then(d => d?.data ?? []);
const deleteProcessedFromServer = (id)               => sendMessage({ type: "DELETE_PROCESSED", id });
const deleteBookingFromServer = (bookingId)          => sendMessage({ type: "DELETE_BOOKING",   bookingId });
const getHotelVoteFromServer  = (hotelId)            => sendMessage({ type: "GET_HOTEL_VOTE",   hotelId }).then(d => d?.vote ?? null);

// ── Reference list caches ─────────────────────────────────────────────
let _currencies = null; // null = not yet fetched
let _operators  = null; // null = not yet fetched
let _countries  = null; // {id: name} after fetch
let _cities     = null; // {id: name} after fetch

async function ensureOperators() {
  if (_operators !== null) return _operators;
  try {
    const json = await sendMessage({ type: "GET_OPERATORS" });
    _operators = json?.data ?? [];
  } catch {
    // Leave the cache unset (null) on failure so a later call retries instead of
    // being stuck empty after a transient error / expired session at page load.
  }
  return _operators ?? [];
}

async function ensureCountries() {
  if (_countries !== null) return _countries;
  try {
    const json = await sendMessage({ type: "GET_COUNTRIES" });
    const arr  = json?.data ?? [];
    _countries = Object.fromEntries(arr.map(c => [c.id, c.name]));
  } catch {
    // Leave unset (null) on failure so a later call retries.
  }
  return _countries ?? {};
}

async function ensureCities() {
  if (_cities !== null) return _cities;
  try {
    const json = await sendMessage({ type: "GET_CITIES" });
    const arr  = json?.data ?? [];
    _cities = Object.fromEntries(arr.map(c => [c.id, c.name]));
  } catch {
    // Leave unset (null) on failure so a later call retries.
  }
  return _cities ?? {};
}

function formatHotelLocation(countryId, cityId) {
  const country = countryId && _countries ? _countries[countryId] : null;
  const city    = cityId    && _cities    ? _cities[cityId]       : null;
  return [country, city].filter(Boolean).join(", ");
}

function populateOperatorSelect(selectEl, preselectedId) {
  selectEl.innerHTML = '<option value="">— select operator —</option>';
  for (const op of (_operators || [])) {
    const opt = document.createElement("option");
    opt.value       = op.id;
    opt.textContent = op.name;
    if (op.id === preselectedId) opt.selected = true;
    selectEl.appendChild(opt);
  }
}

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
    // Leave unset (null) on failure so a later call retries.
  }
  return _currencies ?? [];
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

// ── Confirmation modal ────────────────────────────────────────────────

let modalElement = null;
let hotelSearchTimeout = null;

function destroyModal() {
  modalElement?.remove();
  modalElement = null;
  clearTimeout(hotelSearchTimeout);
}

function esc(v) {
  return String(v ?? "")
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
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

// Coerce a parsed date-of-birth into the yyyy-mm-dd value a <input type="date">
// expects. Accepts ISO, dd.mm.yyyy, dd/mm/yyyy, dd-mm-yyyy; otherwise blank.
function toDateInputValue(v) {
  if (!v) return "";
  const s = String(v).trim();
  let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  m = s.match(/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/);
  if (m) return `${m[3]}-${m[2].padStart(2, "0")}-${m[1].padStart(2, "0")}`;
  return "";
}

function buildTouristRow(t = {}, { withDob = true } = {}) {
  const n   = normaliseTourist(t);
  const div = document.createElement("div");
  div.className = withDob ? "ttb-tourist-row" : "ttb-tourist-row ttb-tourist-row--no-dob";
  div.innerHTML = `
    <input class="ttb-modal__input ttb-tourist__last"  type="text" placeholder="Last name"    value="${esc(n.last_name)}">
    <input class="ttb-modal__input ttb-tourist__first" type="text" placeholder="First name"   value="${esc(n.first_name)}">
    ${withDob ? `<input class="ttb-modal__input ttb-tourist__dob" type="date" title="Date of birth" value="${esc(toDateInputValue(n.dob))}">` : ""}
    <button class="ttb-tourist__remove" type="button" title="Remove">✕</button>
  `;
  div.querySelector(".ttb-tourist__remove").addEventListener("click", () => div.remove());
  return div;
}

async function loadRoomTypes(hotelId, selectEl, preselectedId, arrivalAt, departureAt) {
  selectEl.disabled = true;
  selectEl.innerHTML = '<option value="">Loading...</option>';
  try {
    const types = await getRoomTypesFromServer(hotelId, arrivalAt, departureAt);
    selectEl.innerHTML = '<option value="">— select room type —</option>';
    const wanted = preselectedId != null ? String(preselectedId) : "";
    for (const t of types) {
      const opt = document.createElement("option");
      opt.value       = t.id;
      opt.textContent = t.name;
      if (wanted && String(t.id) === wanted) opt.selected = true;
      selectEl.appendChild(opt);
    }
    selectEl.disabled = false;
  } catch {
    selectEl.innerHTML = '<option value="">— loading error —</option>';
    selectEl.disabled  = false;
  }
}

function fmtDate(val) {
  if (!val) return null;
  const s = String(val).slice(0, 10); // YYYY-MM-DD
  const [y, m, d] = s.split("-");
  return y && m && d ? `${d}.${m}.${y}` : s;
}

function staticField(label, value) {
  if (value == null || value === "") return null;
  const wrap = document.createElement("div");
  const lbl = document.createElement("label");
  lbl.className = "ttb-modal__label";
  lbl.textContent = label;
  const val = document.createElement("div");
  val.className = "ttb-modal__static-value";
  val.textContent = value;
  wrap.append(lbl, val);
  return wrap;
}

function staticRow2(pairs) {
  const row = document.createElement("div");
  row.className = "ttb-modal__row-2";
  for (const [label, value] of pairs) {
    const f = staticField(label, value);
    if (f) row.appendChild(f);
    else row.appendChild(document.createElement("div"));
  }
  return row;
}

function staticRow3(pairs) {
  const row = document.createElement("div");
  row.className = "ttb-modal__row-3";
  for (const [label, value] of pairs) {
    const f = staticField(label, value ?? "—");
    row.appendChild(f);
  }
  return row;
}

function showSentDataModal(processed) {
  destroyModal();

  const operatorName = processed.operator_name
    || (_operators || []).find(o => o.id == processed.operator_id)?.name
    || (processed.operator_id ? `#${processed.operator_id}` : null);

  const tourists = (processed.tourists || [])
    .map(t => {
      const name = [t.last_name, t.first_name].filter(Boolean).join(" ");
      const dob  = fmtDate(t.dob || t.birth_date);
      return { name, dob };
    })
    .filter(t => t.name);

  const overlay = document.createElement("div");
  overlay.className = "ttb-modal-overlay";

  const modal = document.createElement("div");
  modal.className = "ttb-modal";
  modal.setAttribute("role", "dialog");
  modal.setAttribute("aria-modal", "true");

  // Header
  const header = document.createElement("div");
  header.className = "ttb-modal__header";
  const titleEl = document.createElement("span");
  titleEl.className = "ttb-modal__title";
  titleEl.textContent = "Sent to HelloOtel ✓";
  const closeBtn = document.createElement("button");
  closeBtn.className = "ttb-modal__close";
  closeBtn.type = "button";
  closeBtn.setAttribute("aria-label", "Close");
  closeBtn.textContent = "✕";
  closeBtn.addEventListener("click", () => overlay.remove());
  header.append(titleEl, closeBtn);

  // Body
  const body = document.createElement("div");
  body.className = "ttb-modal__body";

  // Reservation ID badge
  if (processed.hellootel_reservation_id) {
    const badge = document.createElement("div");
    badge.className = "ttb-modal__reservation-badge";
    badge.textContent = `Reservation ID: #${processed.hellootel_reservation_id}`;
    body.appendChild(badge);
  }

  // Hotel
  const hotelField = staticField("Hotel", processed.hotel_name);
  if (hotelField) body.appendChild(hotelField);

  // Check-in / Check-out
  body.appendChild(staticRow2([
    ["Check-in",  fmtDate(processed.arrival_at)],
    ["Check-out", fmtDate(processed.departure_at)],
  ]));

  // Room type
  const roomField = staticField("Room type", processed.room_type_name);
  if (roomField) body.appendChild(roomField);

  // Booking date / Booking number
  body.appendChild(staticRow2([
    ["Booking date",   fmtDate(processed.reservation_date)],
    ["Booking number", processed.booking_code],
  ]));

  // Operator
  const opField = staticField("Operator", operatorName);
  if (opField) body.appendChild(opField);

  // Guest counts
  body.appendChild(staticRow3([
    ["Adults",   processed.person_count_adults != null ? String(processed.person_count_adults) : "0"],
    ["Children", processed.person_count_children != null ? String(processed.person_count_children) : "0"],
    ["Infants",  processed.person_count_teens != null ? String(processed.person_count_teens) : "0"],
  ]));

  // Guests
  if (tourists.length) {
    for (const t of tourists) {
      body.appendChild(staticRow2([
        ["Name", t.name],
        ["Date of birth", t.dob || null],
      ]));
    }
  }

  // Price / Currency
  body.appendChild(staticRow2([
    ["Tour price", processed.price ? String(processed.price) : null],
    ["Currency", processed.currency_code],
  ]));

  // Your hotel rating — same horizontal .ttb-rating-row markup as the editable forms.
  if (processed.hotel_vote != null) {
    const ratingRow = document.createElement("div");
    ratingRow.className = "ttb-rating-row";
    const voteLbl = document.createElement("span");
    voteLbl.className = "ttb-rating-label";
    voteLbl.textContent = "Your hotel rating";
    const stars = document.createElement("div");
    stars.className = "ttb-stars ttb-stars--readonly";
    const score = Math.round(processed.hotel_vote / 10);
    for (let i = 1; i <= 10; i++) {
      const s = document.createElement("span");
      s.className = "ttb-star" + (i <= score ? " ttb-star--filled" : "");
      s.textContent = i <= score ? "★" : "☆";
      stars.appendChild(s);
    }
    ratingRow.append(voteLbl, stars);
    body.appendChild(ratingRow);
  }

  modal.append(header, body);
  overlay.appendChild(modal);
  overlay.addEventListener("click", e => { if (e.target === overlay) overlay.remove(); });
  document.body.appendChild(overlay);
}

// Returns Promise<boolean> — true if user confirmed, false if cancelled
async function showConfirmModal(saveResult) {
  destroyModal();

  const raw        = saveResult.data;
  const processed  = saveResult.processed;
  const hotelMatch = saveResult.hotel_match;
  // Direct mode: parser found no guests, so the raw booking was NOT persisted.
  // The user must enter guests manually here and we POST to /processed-bookings/direct
  // on confirm (no source_booking_id linkage).
  const direct     = !!saveResult._direct;

  const pre = {
    hotelId:     processed?.hotel_id       ?? null,
    // If auto-matched, show the canonical HellOotel name, not the raw parsed value
    hotelName:   hotelMatch ? hotelMatch.name : (processed?.hotel_name ?? raw.hotel_name ?? ""),
    countryId:   hotelMatch?.country_id    ?? null,
    cityId:      hotelMatch?.city_id       ?? null,
    roomTypeId:  processed?.room_type_id   ?? null,
    bookingCode: processed?.booking_code   ?? raw.booking_code ?? "",
    reservDate:  processed?.reservation_date ?? "",
    reservTime:  null,
    arrivalAt:   processed?.arrival_at     ?? "",
    departureAt: processed?.departure_at   ?? "",
    price:       processed?.price          ?? extractPriceNumber(raw.total_price) ?? "",
    currency:    processed?.currency_code  ?? detectCurrency(raw.total_price) ?? "",
    adults:      processed?.person_count_adults   ?? raw.adults   ?? "",
    children:    processed?.person_count_children ?? raw.children ?? "",
    infants:     processed?.person_count_teens    ?? raw.infants  ?? "",
    tourists:    processed?.tourists ?? raw.tourists ?? [],
    hotelVote:   processed?.hotel_vote ?? null,
    operatorId:  processed?.operator_id ?? saveResult._operatorId ?? null,
  };

  const overlay = document.createElement("div");
  overlay.className = "ttb-modal-overlay";
  overlay.innerHTML = `
    <div class="ttb-modal" role="dialog" aria-modal="true">

      <div class="ttb-modal__header">
        <span class="ttb-modal__title">Confirm sending to HelloOtel</span>
        <button class="ttb-modal__close" type="button" aria-label="Close">✕</button>
      </div>

      <div class="ttb-modal__body">

        <label class="ttb-modal__label">
          Hotel
          ${hotelMatch
            ? `<span class="ttb-modal__match-badge">Auto-matched · <strong>${Number(hotelMatch.score) || 0}%</strong></span>`
            : `<span class="ttb-modal__match-badge ttb-modal__match-badge--notfound">Hotel not found</span>`}
        </label>
        <div class="ttb-modal__autocomplete">
          <input class="ttb-modal__input ttb-modal__input--with-btn" id="ttb-hotel-input" type="text"
            placeholder="Type hotel name..."
            value="${esc(pre.hotelName)}" autocomplete="off" />
          <button class="ttb-modal__browse-btn" type="button" id="ttb-hotel-browse" title="Browse all hotels">▾</button>
          <ul class="ttb-modal__suggestions" id="ttb-hotel-suggestions" hidden></ul>
        </div>
        <div class="ttb-hotel-location" id="ttb-hotel-location" hidden></div>

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

        <label class="ttb-modal__label">Room type</label>
        <select class="ttb-modal__select" id="ttb-room-select" ${pre.hotelId ? "" : "disabled"}>
          <option value="">${pre.hotelId ? "— select room type —" : "— select hotel first —"}</option>
        </select>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Booking date</label>
            <input class="ttb-modal__input" id="ttb-reserv-date" type="date" value="${esc(pre.reservDate)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Booking number</label>
            <input class="ttb-modal__input" id="ttb-booking-code" type="text" value="${esc(pre.bookingCode)}" placeholder="ORD-123456" />
          </div>
        </div>

        <label class="ttb-modal__label">Operator</label>
        <select class="ttb-modal__select" id="ttb-operator-select">
          <option value="">— select operator —</option>
        </select>

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

        <div id="ttb-tourists-list"></div>
        <button class="ttb-modal__add-tourist" type="button" id="ttb-add-tourist">+ Add guest</button>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Tour price</label>
            <input class="ttb-modal__input" id="ttb-price" type="text" value="${esc(pre.price)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Currency</label>
            <select class="ttb-modal__input ttb-modal__select" id="ttb-currency">
              <option value="">—</option>
            </select>
          </div>
        </div>

        <div class="ttb-rating-row">
          <span class="ttb-rating-label">Your hotel rating</span>
          <div class="ttb-stars" id="ttb-stars">
            ${[1,2,3,4,5,6,7,8,9,10].map(i => `<span class="ttb-star" data-vote="${i}">☆</span>`).join("")}
          </div>
        </div>

        <p class="ttb-modal__required-note">All fields are required except Tour price and Currency</p>

      </div>

      <p class="ttb-modal__send-note">You are sending booking information directly to the hotel manager via the HelloOtel system.</p>
      ${pre.tourists.length === 0 ? `<p class="ttb-modal__warn-note">Attention! To copy all data correctly, you must first expand the detailed booking view before sending the reservation.</p>` : ``}
      <div class="ttb-modal__footer">
        <button class="ttb-modal__btn ttb-modal__btn--delete"  type="button" ${direct ? `hidden` : ``}>Cancel Send</button>
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
    updateConfirmState();
  });

  // Populate operator select
  ensureOperators().then(() => {
    const opSelect = overlay.querySelector("#ttb-operator-select");
    if (opSelect) populateOperatorSelect(opSelect, pre.operatorId);
    updateConfirmState();
  });

  const hotelInput      = overlay.querySelector("#ttb-hotel-input");
  const hotelBrowseBtn  = overlay.querySelector("#ttb-hotel-browse");
  const suggestions     = overlay.querySelector("#ttb-hotel-suggestions");
  const hotelLocationEl = overlay.querySelector("#ttb-hotel-location");
  const matchBadge      = overlay.querySelector(".ttb-modal__match-badge");
  const roomSelect      = overlay.querySelector("#ttb-room-select");
  const touristsList    = overlay.querySelector("#ttb-tourists-list");
  const confirmBtn      = overlay.querySelector(".ttb-modal__btn--confirm");
  const arrivalInput    = overlay.querySelector("#ttb-arrival");
  const departureInput  = overlay.querySelector("#ttb-departure");
  const reservDateInput = overlay.querySelector("#ttb-reserv-date");
  const bookingCodeInput= overlay.querySelector("#ttb-booking-code");
  const operatorSelect  = overlay.querySelector("#ttb-operator-select");
  const adultsInput     = overlay.querySelector("#ttb-adults");
  const childrenInput   = overlay.querySelector("#ttb-children");
  const infantsInput    = overlay.querySelector("#ttb-infants");
  const priceInput      = overlay.querySelector("#ttb-price");
  const currencySelect  = overlay.querySelector("#ttb-currency");

  let selectedHotelId   = pre.hotelId;
  let selectedHotelName = pre.hotelName;
  // DB/API stores 10-100 (stars×10); divide by 10 to get star count for display
  let selectedVote      = pre.hotelVote ? Math.round(pre.hotelVote / 10) : null;

  function updateHotelLocation(countryId, cityId) {
    const text = formatHotelLocation(countryId, cityId);
    if (text) {
      hotelLocationEl.textContent = text;
      hotelLocationEl.hidden = false;
    } else {
      hotelLocationEl.textContent = "";
      hotelLocationEl.hidden = true;
    }
  }

  // Show auto-matched location on initial open
  updateHotelLocation(pre.countryId, pre.cityId);

  hotelInput.classList.toggle("ttb-modal__input--notfound", !hotelMatch);

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
  function hasAnyTourist() {
    return [...touristsList.querySelectorAll(".ttb-tourist-row")]
      .some(row =>
        row.querySelector(".ttb-tourist__last").value.trim() ||
        row.querySelector(".ttb-tourist__first").value.trim()
      );
  }
  function updateConfirmState() {
    const ok =
      !!selectedHotelId &&
      !!arrivalInput.value &&
      !!departureInput.value &&
      !!roomSelect.value &&
      !!reservDateInput.value &&
      !!bookingCodeInput.value.trim() &&
      !!operatorSelect.value &&
      adultsInput.value   !== "" &&
      childrenInput.value !== "" &&
      infantsInput.value  !== "" &&
      hasAnyTourist() &&
      (selectedVote > 0);
    confirmBtn.disabled = !ok;
  }

  roomSelect.addEventListener("change", updateConfirmState);
  [reservDateInput, bookingCodeInput, priceInput, adultsInput, childrenInput, infantsInput].forEach(el =>
    el.addEventListener("input", updateConfirmState));
  [operatorSelect, currencySelect].forEach(el =>
    el.addEventListener("change", updateConfirmState));

  function reloadRoomTypesForDates() {
    if (!selectedHotelId) return;
    const keepSelected = roomSelect.value || null;
    return loadRoomTypes(
      selectedHotelId,
      roomSelect,
      keepSelected,
      arrivalInput.value || null,
      departureInput.value || null,
    ).then(updateConfirmState);
  }

  arrivalInput.addEventListener("change", () => { reloadRoomTypesForDates(); updateConfirmState(); });
  departureInput.addEventListener("change", () => { reloadRoomTypesForDates(); updateConfirmState(); });

  // ── Pre-load room types and vote if hotel already matched ────────
  if (pre.hotelId) {
    await loadRoomTypes(pre.hotelId, roomSelect, pre.roomTypeId, pre.arrivalAt || null, pre.departureAt || null);
    updateConfirmState();
    if (selectedVote === null) {
      getHotelVoteFromServer(pre.hotelId).then(v => {
        if (selectedVote === null) updateStars(v ? Math.round(v / 10) : 0);
      }).catch(() => {});
    }
  }

  // ── Pre-fill tourists ─────────────────────────────────────────────
  for (const t of pre.tourists) {
    touristsList.appendChild(buildTouristRow(t));
  }
  updateConfirmState();

  overlay.querySelector("#ttb-add-tourist").addEventListener("click", () => {
    touristsList.appendChild(buildTouristRow());
    updateConfirmState();
  });

  // Re-evaluate Confirm enable state when guest inputs change or rows are removed
  // (matters only in direct mode where ≥1 guest is required).
  touristsList.addEventListener("input",  updateConfirmState);
  touristsList.addEventListener("click",  (e) => {
    if (e.target.classList?.contains("ttb-tourist__remove")) {
      // remove handler runs on the row itself; schedule state check after removal
      queueMicrotask(updateConfirmState);
    }
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
        updateHotelLocation(h.country_id, h.city_id);
        if (matchBadge) matchBadge.hidden = true;
        hotelInput.classList.remove("ttb-modal__input--notfound");
        hideSuggestions();
        await loadRoomTypes(h.id, roomSelect, null, arrivalInput.value || null, departureInput.value || null);
        updateConfirmState();
        getHotelVoteFromServer(h.id).then(v => { updateStars(v ? Math.round(v / 10) : 0); }).catch(() => {});
      });
      suggestions.appendChild(li);
    }
    suggestions.hidden = false;
  }

  hotelInput.addEventListener("input", () => {
    selectedHotelId = null;
    updateHotelLocation(null, null); // hide location while user is typing
    if (matchBadge && !hotelMatch) matchBadge.hidden = false;
    if (!hotelMatch) hotelInput.classList.add("ttb-modal__input--notfound");
    updateConfirmState();
    clearTimeout(hotelSearchTimeout);
    const q = hotelInput.value.trim();
    if (q.length < 2) { hideSuggestions(); return; }
    hotelSearchTimeout = setTimeout(async () => {
      try { showSuggestions(await searchHotelsOnServer(q)); } catch { hideSuggestions(); }
    }, 300);
  });

  hotelInput.addEventListener("blur", () => setTimeout(hideSuggestions, 150));

  hotelBrowseBtn.addEventListener("mousedown", (e) => e.preventDefault());
  hotelBrowseBtn.addEventListener("click", async () => {
    if (!suggestions.hidden) { hideSuggestions(); return; }
    clearTimeout(hotelSearchTimeout);
    try { showSuggestions(await searchHotelsOnServer("")); } catch { hideSuggestions(); }
  });

  // ── Return Promise resolving to { status, processed? } ──────────
  // status: "sent" | "confirmed_only" | "deleted" | "cancelled"
  // processed: fresh ProcessedBooking from /confirm response, when available
  return new Promise((resolve) => {

    function closeCancel() {
      destroyModal();
      resolve({ status: "cancelled" });
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
        resolve({ status: "deleted" });
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

        const operatorSelectEl = overlay.querySelector("#ttb-operator-select");
        const selectedOperatorId = operatorSelectEl?.value ? parseInt(operatorSelectEl.value, 10) : null;

        const payload = {
          hotel_id:         selectedHotelId || null,
          hotel_name:       selectedHotelName || null,
          room_type_id:     selectedRoomId || null,
          room_type_name:   selectedRoomId ? selectedRoomName : null,
          booking_code:     overlay.querySelector("#ttb-booking-code").value.trim() || null,
          reservation_date: overlay.querySelector("#ttb-reserv-date").value  || null,
          arrival_at:       overlay.querySelector("#ttb-arrival").value   || null,
          departure_at:     overlay.querySelector("#ttb-departure").value  || null,
          price:            overlay.querySelector("#ttb-price").value.trim() || null,
          currency_code:    overlay.querySelector("#ttb-currency").value.trim().toUpperCase() || null,
          adults:   overlay.querySelector("#ttb-adults").value   !== "" ? parseInt(overlay.querySelector("#ttb-adults").value,   10) : null,
          children: overlay.querySelector("#ttb-children").value !== "" ? parseInt(overlay.querySelector("#ttb-children").value, 10) : null,
          infants:  overlay.querySelector("#ttb-infants").value  !== "" ? parseInt(overlay.querySelector("#ttb-infants").value,  10) : null,
          tourists:    tourists,
          hotel_vote:  selectedVote !== null ? selectedVote * 10 : undefined,
          operator_id: selectedOperatorId,
        };

        const result = direct
          ? await storeProcessedDirect(payload)
          : await confirmBookingOnServer(raw.id, payload);

        if (result?.hellootel?.error) {
          confirmBtn.disabled    = false;
          confirmBtn.textContent = "Confirm";
          const choice = await showHellootelErrorDialog(result.hellootel.error);
          if (choice === "ignore") {
            destroyModal();
            showToast("Failed to send HelloOtel ! (Booking confirmed)");
            resolve({ status: "confirmed_only", processed: result?.data ?? null });
          }
          // "fix" → modal stays open, user can edit and retry
          return;
        }

        destroyModal();
        const wasSent = !!result?.hellootel?.id;
        showToast(wasSent ? "Booking sent to HelloOtel ✓" : "Booking confirmed ✓");
        resolve({
          status:    wasSent ? "sent" : "confirmed_only",
          processed: result?.data ?? null,
        });
      } catch (err) {
        confirmBtn.disabled    = false;
        confirmBtn.textContent = "Retry";
        const choice = await showHellootelErrorDialog(err.message || "Connection error");
        if (choice === "ignore") {
          destroyModal();
          showToast("Failed to send HelloOtel ! (Booking confirmed)");
          resolve({ status: "confirmed_only", processed: null });
        }
        // "fix" → modal stays open
      }
    });
  });
}
