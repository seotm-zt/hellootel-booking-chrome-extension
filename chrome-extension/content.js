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

const BUTTON_LABEL     = "Сохранить в базе";
const UPDATE_LABEL     = "Обновить в базе";
const CONFIRMED_LABEL  = "Подтверждено ✓";
const SAVING_LABEL     = "Сохранение...";

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
const confirmBookingOnServer = (bookingId, payload) => sendMessage({ type: "CONFIRM_BOOKING", bookingId, payload });
const deleteBookingFromServer = (bookingId)          => sendMessage({ type: "DELETE_BOOKING",  bookingId });

// ── Booking state caches ──────────────────────────────────────────────
// Key format: "domain:booking_code"
let confirmedCodes = new Set(); // confirmed bookings
let savedCodes     = new Set(); // saved but not yet confirmed

async function refreshConfirmedCodes() {
  try {
    const bookings = await loadBookingsFromServer();
    confirmedCodes = new Set(
      bookings
        .filter(b => b.processed_booking?.confirmed_at)
        .map(b => `${b.source_domain}:${b.booking_code}`)
    );
    savedCodes = new Set(
      bookings
        .filter(b => !b.processed_booking?.confirmed_at)
        .map(b => `${b.source_domain}:${b.booking_code}`)
    );
  } catch { /* non-critical, fallback to empty sets */ }
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

function buildTouristRow(t = {}) {
  const div = document.createElement("div");
  div.className = "ttb-tourist-row";
  div.innerHTML = `
    <input class="ttb-modal__input ttb-tourist__last"  type="text" placeholder="Фамилия"       value="${esc(t.last_name)}">
    <input class="ttb-modal__input ttb-tourist__first" type="text" placeholder="Имя"           value="${esc(t.first_name)}">
    <input class="ttb-modal__input ttb-tourist__dob"   type="text" placeholder="Дата рождения" value="${esc(t.dob)}">
    <button class="ttb-tourist__remove" type="button" title="Удалить">✕</button>
  `;
  div.querySelector(".ttb-tourist__remove").addEventListener("click", () => div.remove());
  return div;
}

async function loadRoomTypes(hotelId, selectEl, preselectedId) {
  selectEl.disabled = true;
  selectEl.innerHTML = '<option value="">Загрузка...</option>';
  try {
    const types = await getRoomTypesFromServer(hotelId);
    selectEl.innerHTML = '<option value="">— выберите тип номера —</option>';
    for (const t of types) {
      const opt = document.createElement("option");
      opt.value       = t.id;
      opt.textContent = t.name;
      if (preselectedId && t.id === preselectedId) opt.selected = true;
      selectEl.appendChild(opt);
    }
    selectEl.disabled = false;
  } catch {
    selectEl.innerHTML = '<option value="">— ошибка загрузки —</option>';
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
    currency:    processed?.currency_code  ?? "",
    adults:      processed?.person_count_adults   ?? raw.adults   ?? "",
    children:    processed?.person_count_children ?? raw.children ?? "",
    infants:     processed?.person_count_teens    ?? raw.infants  ?? "",
    tourists:    processed?.tourists ?? raw.tourists ?? [],
  };

  const overlay = document.createElement("div");
  overlay.className = "ttb-modal-overlay";
  overlay.innerHTML = `
    <div class="ttb-modal" role="dialog" aria-modal="true">

      <div class="ttb-modal__header">
        <span class="ttb-modal__title">Подтверждение брони</span>
        <button class="ttb-modal__close" type="button" aria-label="Закрыть">✕</button>
      </div>

      <div class="ttb-modal__body">

        <label class="ttb-modal__label">Отель</label>
        <div class="ttb-modal__autocomplete">
          <input class="ttb-modal__input" id="ttb-hotel-input" type="text"
            placeholder="Введите название отеля..."
            value="${esc(pre.hotelName)}" autocomplete="off" />
          <ul class="ttb-modal__suggestions" id="ttb-hotel-suggestions" hidden></ul>
        </div>
        ${hotelMatch ? `<div class="ttb-modal__match-badge">Авто-сопоставление · ${hotelMatch.score}%</div>` : ""}

        <label class="ttb-modal__label">Тип номера</label>
        <select class="ttb-modal__select" id="ttb-room-select" ${pre.hotelId ? "" : "disabled"}>
          <option value="">${pre.hotelId ? "— выберите тип номера —" : "— сначала выберите отель —"}</option>
        </select>

        <div class="ttb-modal__section-title">Данные брони</div>

        <label class="ttb-modal__label">Номер брони</label>
        <input class="ttb-modal__input" id="ttb-booking-code" type="text" value="${esc(pre.bookingCode)}" placeholder="ORD-123456" />

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Дата брони</label>
            <input class="ttb-modal__input" id="ttb-reserv-date" type="date" value="${esc(pre.reservDate)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Время</label>
            <input class="ttb-modal__input" id="ttb-reserv-time" type="text" placeholder="17:03" maxlength="5" value="${esc(pre.reservTime)}" />
          </div>
        </div>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Дата заезда</label>
            <input class="ttb-modal__input" id="ttb-arrival" type="date" value="${esc(pre.arrivalAt)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Дата выезда</label>
            <input class="ttb-modal__input" id="ttb-departure" type="date" value="${esc(pre.departureAt)}" />
          </div>
        </div>

        <div class="ttb-modal__row-2">
          <div>
            <label class="ttb-modal__label">Стоимость</label>
            <input class="ttb-modal__input" id="ttb-price" type="text" placeholder="1250.00" value="${esc(pre.price)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Валюта</label>
            <input class="ttb-modal__input" id="ttb-currency" type="text" maxlength="3" placeholder="EUR" value="${esc(pre.currency)}" />
          </div>
        </div>

        <div class="ttb-modal__row-3">
          <div>
            <label class="ttb-modal__label">Взрослых</label>
            <input class="ttb-modal__input" id="ttb-adults" type="number" min="0" value="${esc(pre.adults)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Детей</label>
            <input class="ttb-modal__input" id="ttb-children" type="number" min="0" value="${esc(pre.children)}" />
          </div>
          <div>
            <label class="ttb-modal__label">Младенцев</label>
            <input class="ttb-modal__input" id="ttb-infants" type="number" min="0" value="${esc(pre.infants)}" />
          </div>
        </div>

        <div class="ttb-modal__section-title">Жильцы</div>
        <div id="ttb-tourists-list"></div>
        <button class="ttb-modal__add-tourist" type="button" id="ttb-add-tourist">+ Добавить жильца</button>

      </div>

      <div class="ttb-modal__footer">
        <button class="ttb-modal__btn ttb-modal__btn--delete"  type="button">Удалить из базы</button>
        <div style="flex:1"></div>
        <button class="ttb-modal__btn ttb-modal__btn--cancel"  type="button">Отменить</button>
        <button class="ttb-modal__btn ttb-modal__btn--confirm" type="button" disabled>Подтвердить</button>
      </div>

    </div>
  `;

  document.body.appendChild(overlay);
  modalElement = overlay;

  const hotelInput   = overlay.querySelector("#ttb-hotel-input");
  const suggestions  = overlay.querySelector("#ttb-hotel-suggestions");
  const roomSelect   = overlay.querySelector("#ttb-room-select");
  const touristsList = overlay.querySelector("#ttb-tourists-list");
  const confirmBtn   = overlay.querySelector(".ttb-modal__btn--confirm");

  let selectedHotelId   = pre.hotelId;
  let selectedHotelName = pre.hotelName;

  // ── Confirm button state ──────────────────────────────────────────
  function updateConfirmState() {
    confirmBtn.disabled = !selectedHotelId || !roomSelect.value;
  }

  roomSelect.addEventListener("change", updateConfirmState);

  // ── Pre-load room types if hotel already matched ──────────────────
  if (pre.hotelId) {
    await loadRoomTypes(pre.hotelId, roomSelect, pre.roomTypeId);
    updateConfirmState();
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
      showToast("Бронь сохранена. Ещё не подтверждена.");
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
      if (!confirm("Удалить бронь из базы данных?")) return;
      deleteBtn.disabled    = true;
      deleteBtn.textContent = "Удаление...";
      try {
        await deleteBookingFromServer(raw.id);
        destroyModal();
        showToast("Бронь удалена из базы.");
        resolve("deleted");
      } catch (err) {
        deleteBtn.disabled    = false;
        deleteBtn.textContent = "Удалить из базы";
        showToast(`Ошибка: ${err.message}`);
      }
    });

    confirmBtn.addEventListener("click", async () => {
      confirmBtn.disabled    = true;
      confirmBtn.textContent = "Отправка...";
      try {
        const selectedRoomId   = roomSelect.value ? parseInt(roomSelect.value, 10) : null;
        const selectedRoomName = roomSelect.selectedOptions[0]?.textContent?.trim() ?? null;

        const tourists = [...touristsList.querySelectorAll(".ttb-tourist-row")].map(row => ({
          last_name:  row.querySelector(".ttb-tourist__last").value.trim(),
          first_name: row.querySelector(".ttb-tourist__first").value.trim(),
          dob:        row.querySelector(".ttb-tourist__dob").value.trim(),
        })).filter(t => t.last_name || t.first_name);

        await confirmBookingOnServer(raw.id, {
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
          tourists: tourists.length ? tourists : null,
        });

        destroyModal();
        showToast("Бронь подтверждена ✓");
        resolve(true);
      } catch (err) {
        confirmBtn.disabled    = false;
        confirmBtn.textContent = "Подтвердить";
        showToast(`Ошибка: ${err.message}`);
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

  // Check if this booking was already confirmed in a previous session
  const domain      = getEffectiveLocation().hostname;
  const parsedCode  = parser.parseCard(card)?.booking_code;
  const wasConfirmed = parsedCode && confirmedCodes.has(`${domain}:${parsedCode}`);

  if (wasConfirmed) {
    btn.textContent = CONFIRMED_LABEL;
    btn.classList.add("ttb-save-booking-button--confirmed");
  } else if (parsedCode && savedCodes.has(`${domain}:${parsedCode}`)) {
    btn.textContent = UPDATE_LABEL;
    btn.classList.add("ttb-save-booking-button--saved");
  } else {
    btn.textContent = BUTTON_LABEL;
  }

  const wrap = document.createElement("div");
  wrap.className = "ttb-save-booking-actions";
  wrap.append(btn);

  parser.getButtonContainer(card).appendChild(wrap);

  btn.addEventListener("click", async () => {
    const prev = btn.textContent;
    btn.disabled    = true;
    btn.textContent = SAVING_LABEL;

    try {
      const authorized = await isAuthorized();
      if (!authorized) {
        showToast("Sign in to the Booking Saver extension.");
        btn.textContent = prev;
        return;
      }

      const raw     = parser.parseCard(card);
      const booking = buildCommonBookingData(raw);
      const result  = await saveBookingToServer(booking);

      // If booking was already confirmed in a previous session — reflect that immediately
      const alreadyConfirmed = !!result.processed?.confirmed_at;
      if (alreadyConfirmed) {
        btn.textContent = CONFIRMED_LABEL;
        btn.classList.add("ttb-save-booking-button--confirmed");
      } else {
        btn.textContent = UPDATE_LABEL;
      }

      const modalResult = await showConfirmModal(result);

      if (modalResult === "deleted") {
        btn.textContent = BUTTON_LABEL;
        btn.classList.remove("ttb-save-booking-button--confirmed");
        btn.classList.remove("ttb-save-booking-button--saved");
      } else if (modalResult === true) {
        btn.textContent = CONFIRMED_LABEL;
        btn.classList.remove("ttb-save-booking-button--saved");
        btn.classList.add("ttb-save-booking-button--confirmed");
      } else if (!alreadyConfirmed) {
        btn.textContent = UPDATE_LABEL;
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
installObserver();
