/**
 * Standalone window host for the manual ("direct") booking flow.
 *
 * Opened from the popup via chrome.windows.create so the form gets a full-size
 * window. Unlike the parser modal (showConfirmModal in booking-modal.js), this
 * window has its own layout/logic:
 *   - no "expand the detailed booking view" warning;
 *   - Booking details and Guests first, Hotel/Rating/Room type at the bottom;
 *   - one empty guest row shown immediately;
 *   - light, borderless layout (form top-aligned, centered).
 *
 * Low-level behaviour is reused from booking-modal.js (hotel search, room-type
 * loading by dates, selects, tourist rows, send). On confirm it POSTs to
 * /processed-bookings/direct — creating a ProcessedBooking only (no raw booking).
 */

function showSuccessDialog(result) {
  const reservationId = result?.hellootel?.id ?? null;
  const bookingsUrl   = chrome.runtime.getURL("bookings.html");

  const overlay = document.createElement("div");
  overlay.className = "ttb-he-overlay";
  overlay.innerHTML = `
    <div class="ttb-he-box">
      <div class="ttb-he-icon">✓</div>
      <div class="ttb-he-title">HelloOtel - Hotel reservation information successfully added to HelloOtel</div>
      ${reservationId
        ? `<div class="ttb-he-message ttb-he-message--success">Reservation ID: #${reservationId}</div>`
        : ""}
      <div class="ttb-he-actions">
        <button class="ttb-he-btn ttb-he-btn--ignore" type="button" id="mb-success-ok">OK</button>
        <button class="ttb-he-btn ttb-he-btn--fix"    type="button" id="mb-success-view">View all bookings</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  overlay.querySelector("#mb-success-ok").addEventListener("click", () => window.close());
  overlay.querySelector("#mb-success-view").addEventListener("click", () => {
    chrome.tabs.create({ url: bookingsUrl });
    window.close();
  });
}

function showFallback(message) {
  const fb = document.getElementById("mb-fallback");
  fb.hidden = false;
  fb.textContent = message;
}

function renderManualForm(prefill = null) {
  const editId = prefill?.id ?? null;

  const form = document.createElement("div");
  form.className = "mb-form";
  form.innerHTML = `
    <h1 class="mb-form__title">${editId ? "Edit booking" : "Add booking to HelloOtel"}</h1>

    <label class="ttb-modal__label">Hotel</label>
    <div class="ttb-modal__autocomplete">
      <input class="ttb-modal__input ttb-modal__input--with-btn" id="ttb-hotel-input" type="text" placeholder="Type hotel name..." autocomplete="off" />
      <button class="ttb-modal__browse-btn" type="button" id="ttb-hotel-browse" title="Browse all hotels">▾</button>
      <ul class="ttb-modal__suggestions" id="ttb-hotel-suggestions" hidden></ul>
    </div>
    <div class="ttb-hotel-location" id="ttb-hotel-location" hidden></div>

    <div class="ttb-modal__row-2">
      <div>
        <label class="ttb-modal__label">Check-in</label>
        <input class="ttb-modal__input" id="ttb-arrival" type="date" />
      </div>
      <div>
        <label class="ttb-modal__label">Check-out</label>
        <input class="ttb-modal__input" id="ttb-departure" type="date" />
      </div>
    </div>

    <label class="ttb-modal__label">Room type</label>
    <select class="ttb-modal__select" id="ttb-room-select" disabled>
      <option value="">— select hotel first —</option>
    </select>

    <div class="ttb-modal__row-2">
      <div>
        <label class="ttb-modal__label">Booking date</label>
        <input class="ttb-modal__input" id="ttb-reserv-date" type="date" />
      </div>
      <div>
        <label class="ttb-modal__label">Booking number</label>
        <input class="ttb-modal__input" id="ttb-booking-code" type="text" placeholder="ORD-123456" />
      </div>
    </div>

    <label class="ttb-modal__label">Operator</label>
    <select class="ttb-modal__select" id="ttb-operator-select">
      <option value="">— select operator —</option>
    </select>

    <div class="ttb-modal__row-3">
      <div>
        <label class="ttb-modal__label">Adults</label>
        <input class="ttb-modal__input" id="ttb-adults" type="number" min="0" value="1" />
      </div>
      <div>
        <label class="ttb-modal__label">Children</label>
        <input class="ttb-modal__input" id="ttb-children" type="number" min="0" value="0" />
      </div>
      <div>
        <label class="ttb-modal__label">Infants</label>
        <input class="ttb-modal__input" id="ttb-infants" type="number" min="0" value="0" />
      </div>
    </div>

    <div id="ttb-tourists-list"></div>
    <button class="ttb-modal__add-tourist" type="button" id="ttb-add-tourist">+ Add guest</button>

    <div class="ttb-modal__row-2">
      <div>
        <label class="ttb-modal__label">Tour price</label>
        <input class="ttb-modal__input" id="ttb-price" type="text" placeholder="1250.00" />
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
    <p class="ttb-modal__send-note">You are sending booking information directly to the hotel manager via the HelloOtel system.</p>

    <div class="ttb-modal__footer">
      <button class="ttb-modal__btn ttb-modal__btn--cancel"  type="button" id="mb-cancel">Cancel</button>
      <button class="ttb-modal__btn ttb-modal__btn--draft"   type="button" id="mb-draft">Save as Draft</button>
      <button class="ttb-modal__btn ttb-modal__btn--confirm" type="button" id="mb-confirm" disabled>Confirm</button>
    </div>

    <div class="ttb-modal__api-error" id="ttb-api-error" hidden></div>
  `;
  document.body.appendChild(form);

  // Populate selects (lists may still be loading).
  ensureCurrencies().then(() => { populateCurrencySelectEl(form.querySelector("#ttb-currency"), prefill?.currency_code ?? ""); updateConfirmState(); });
  ensureOperators().then(() => { populateOperatorSelect(form.querySelector("#ttb-operator-select"), prefill?.operator_id ?? null); updateConfirmState(); });

  const hotelInput      = form.querySelector("#ttb-hotel-input");
  const hotelBrowseBtn  = form.querySelector("#ttb-hotel-browse");
  const suggestions     = form.querySelector("#ttb-hotel-suggestions");
  const hotelLocationEl = form.querySelector("#ttb-hotel-location");
  const roomSelect      = form.querySelector("#ttb-room-select");
  const touristsList    = form.querySelector("#ttb-tourists-list");
  const arrivalInput    = form.querySelector("#ttb-arrival");
  const departureInput  = form.querySelector("#ttb-departure");
  const adultsInput     = form.querySelector("#ttb-adults");
  const childrenInput   = form.querySelector("#ttb-children");
  const infantsInput    = form.querySelector("#ttb-infants");
  const reservDateInput = form.querySelector("#ttb-reserv-date");
  const bookingCodeInput= form.querySelector("#ttb-booking-code");
  const operatorSelect  = form.querySelector("#ttb-operator-select");
  const priceInput      = form.querySelector("#ttb-price");
  const currencySelect  = form.querySelector("#ttb-currency");
  const confirmBtn      = form.querySelector("#mb-confirm");
  const apiErrorEl      = form.querySelector("#ttb-api-error");

  let selectedHotelId   = null;
  let selectedHotelName = "";
  let selectedVote      = null;

  // Guest counts are entered manually.
  [adultsInput, childrenInput, infantsInput].forEach(el =>
    el.addEventListener("input", () => updateConfirmState()));

  // All remaining scalar fields are required — re-evaluate the confirm button on change.
  [reservDateInput, bookingCodeInput, priceInput].forEach(el =>
    el.addEventListener("input", () => updateConfirmState()));
  [operatorSelect, currencySelect].forEach(el =>
    el.addEventListener("change", () => updateConfirmState()));

  function updateHotelLocation(countryId, cityId) {
    const text = formatHotelLocation(countryId, cityId);
    hotelLocationEl.textContent = text;
    hotelLocationEl.hidden = !text;
  }

  // ── Star rating ──
  const starBtns = form.querySelectorAll(".ttb-star");
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
  starBtns.forEach(btn => btn.addEventListener("click", () => updateStars(parseInt(btn.dataset.vote, 10))));

  // ── Confirm enable state ──
  function hasAnyTourist() {
    return [...touristsList.querySelectorAll(".ttb-tourist-row")].some(row =>
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

  // ── Room types depend on hotel + the dates entered above ──
  function reloadRoomTypesForDates() {
    if (!selectedHotelId) return;
    const keep = roomSelect.value || null;
    loadRoomTypes(selectedHotelId, roomSelect, keep, arrivalInput.value || null, departureInput.value || null)
      .then(updateConfirmState);
  }
  arrivalInput.addEventListener("change", () => { reloadRoomTypesForDates(); updateConfirmState(); });
  departureInput.addEventListener("change", () => { reloadRoomTypesForDates(); updateConfirmState(); });

  // ── Guests: prefilled rows when editing, otherwise one empty row (no DOB field) ──
  if (prefill?.tourists?.length) {
    for (const t of prefill.tourists) touristsList.appendChild(buildTouristRow(t, { withDob: false }));
  } else {
    touristsList.appendChild(buildTouristRow({}, { withDob: false }));
  }
  form.querySelector("#ttb-add-tourist").addEventListener("click", () => {
    touristsList.appendChild(buildTouristRow({}, { withDob: false }));
    updateConfirmState();
  });
  touristsList.addEventListener("input", () => updateConfirmState());
  touristsList.addEventListener("click", (e) => {
    if (e.target.classList?.contains("ttb-tourist__remove")) {
      queueMicrotask(() => updateConfirmState());
    }
  });

  // ── Hotel autocomplete ──
  function hideSuggestions() { suggestions.hidden = true; suggestions.innerHTML = ""; }
  function showSuggestions(hotels) {
    suggestions.innerHTML = "";
    if (!hotels.length) { hideSuggestions(); return; }
    for (const h of hotels) {
      const li = document.createElement("li");
      li.className = "ttb-modal__suggestion";
      li.textContent = h.name;
      // Inline + !important so the host page's own CSS can't shrink/re-font
      // the list text out of sync with the input above it.
      li.style.setProperty("font-family", getComputedStyle(hotelInput).fontFamily, "important");
      li.style.setProperty("font-size",   getComputedStyle(hotelInput).fontSize,   "important");
      li.addEventListener("mousedown", async (e) => {
        e.preventDefault();
        selectedHotelId   = h.id;
        selectedHotelName = h.name;
        hotelInput.value  = h.name;
        updateHotelLocation(h.country_id, h.city_id);
        hotelInput.classList.remove("ttb-modal__input--notfound");
        hideSuggestions();
        await loadRoomTypes(h.id, roomSelect, null, arrivalInput.value || null, departureInput.value || null);
        updateConfirmState();
        getHotelVoteFromServer(h.id).then(v => updateStars(v ? Math.round(v / 10) : 0)).catch(() => {});
      });
      suggestions.appendChild(li);
    }
    suggestions.hidden = false;
  }
  hotelInput.addEventListener("input", async () => {
    selectedHotelId = null;
    updateHotelLocation(null, null);
    updateConfirmState();
    const q = hotelInput.value.trim();
    hotelInput.classList.toggle("ttb-modal__input--notfound", q.length > 0);
    if (q.length < 2) { hideSuggestions(); return; }
    showSuggestions(filterHotels(await ensureHotels(), q));
  });
  hotelInput.addEventListener("blur", () => setTimeout(hideSuggestions, 150));

  // Clicking/focusing the field always opens the full hotel list, same as the
  // arrow button — search only kicks in once the user starts typing, via the
  // "input" handler above.
  async function browseAllHotels() {
    showSuggestions(filterHotels(await ensureHotels(), ""));
  }
  hotelInput.addEventListener("focus", browseAllHotels);
  hotelInput.addEventListener("click", browseAllHotels);

  hotelBrowseBtn.addEventListener("mousedown", (e) => e.preventDefault());
  hotelBrowseBtn.addEventListener("click", async () => {
    if (!suggestions.hidden) { hideSuggestions(); return; }
    showSuggestions(filterHotels(await ensureHotels(), ""));
  });

  // ── Prefill scalar fields + hotel/room/rating when editing ──
  if (prefill) {
    form.querySelector("#ttb-booking-code").value = prefill.booking_code ?? "";
    form.querySelector("#ttb-reserv-date").value  = toDateInputValue(prefill.reservation_date);
    arrivalInput.value   = toDateInputValue(prefill.arrival_at);
    departureInput.value = toDateInputValue(prefill.departure_at);
    form.querySelector("#ttb-price").value = prefill.price != null ? String(prefill.price) : "";
    adultsInput.value    = prefill.person_count_adults   != null ? String(prefill.person_count_adults)   : "";
    childrenInput.value  = prefill.person_count_children != null ? String(prefill.person_count_children) : "";
    infantsInput.value   = prefill.person_count_teens    != null ? String(prefill.person_count_teens)    : "";

    if (prefill.hotel_id) {
      selectedHotelId   = prefill.hotel_id;
      selectedHotelName = prefill.hotel_name ?? "";
      hotelInput.value  = prefill.hotel_name ?? "";
      loadRoomTypes(prefill.hotel_id, roomSelect, prefill.room_type_id ?? null,
        arrivalInput.value || null, departureInput.value || null).then(updateConfirmState);
      if (prefill.hotel_vote) {
        updateStars(Math.round(prefill.hotel_vote / 10));
      } else {
        // No saved rating → pull the hotel's vote from HelloOtel (like create mode).
        getHotelVoteFromServer(prefill.hotel_id)
          .then(v => { if (selectedVote === null) updateStars(v ? Math.round(v / 10) : 0); })
          .catch(() => {});
      }
    } else {
      if (prefill.hotel_name) hotelInput.value = prefill.hotel_name;
      updateStars(prefill.hotel_vote ? Math.round(prefill.hotel_vote / 10) : 0);
      hotelInput.classList.toggle("ttb-modal__input--notfound", !!prefill.hotel_name);
    }
    updateConfirmState();
  }

  // ── Cancel / Draft / Confirm ──
  const clearApiError = () => { apiErrorEl.hidden = true; apiErrorEl.textContent = ""; };

  form.querySelector("#mb-cancel").addEventListener("click", () => window.close());

  form.querySelector("#mb-draft").addEventListener("click", async () => {
    const draftBtn = form.querySelector("#mb-draft");
    draftBtn.disabled    = true;
    draftBtn.textContent = "Saving...";
    clearApiError();
    try {
      const selectedRoomId   = roomSelect.value ? parseInt(roomSelect.value, 10) : null;
      const selectedRoomName = roomSelect.selectedOptions[0]?.textContent?.trim() ?? null;
      const tourists = [...touristsList.querySelectorAll(".ttb-tourist-row")].map(row => ({
        last_name:  row.querySelector(".ttb-tourist__last").value.trim(),
        first_name: row.querySelector(".ttb-tourist__first").value.trim(),
      })).filter(t => t.last_name || t.first_name);
      const operatorSelectEl   = form.querySelector("#ttb-operator-select");
      const selectedOperatorId = operatorSelectEl?.value ? parseInt(operatorSelectEl.value, 10) : null;

      const payload = {
        draft:            true,
        hotel_id:         selectedHotelId || null,
        hotel_name:       selectedHotelName || null,
        room_type_id:     selectedRoomId || null,
        room_type_name:   selectedRoomId ? selectedRoomName : null,
        booking_code:     form.querySelector("#ttb-booking-code").value.trim() || null,
        reservation_date: form.querySelector("#ttb-reserv-date").value || null,
        arrival_at:       form.querySelector("#ttb-arrival").value || null,
        departure_at:     form.querySelector("#ttb-departure").value || null,
        price:            form.querySelector("#ttb-price").value.trim() || null,
        currency_code:    form.querySelector("#ttb-currency").value.trim().toUpperCase() || null,
        adults:   form.querySelector("#ttb-adults").value   !== "" ? parseInt(form.querySelector("#ttb-adults").value,   10) : null,
        children: form.querySelector("#ttb-children").value !== "" ? parseInt(form.querySelector("#ttb-children").value, 10) : null,
        infants:  form.querySelector("#ttb-infants").value  !== "" ? parseInt(form.querySelector("#ttb-infants").value,  10) : null,
        tourists:    tourists,
        hotel_vote:  selectedVote !== null ? selectedVote * 10 : undefined,
        operator_id: selectedOperatorId,
      };

      editId
        ? await updateProcessedDirect(editId, payload)
        : await storeProcessedDirect(payload);

      showToast("Draft saved");
      window.close();
    } catch (err) {
      draftBtn.disabled    = false;
      draftBtn.textContent = "Save as Draft";
      apiErrorEl.textContent = err.message || "Save failed";
      apiErrorEl.hidden = false;
    }
  });

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
      })).filter(t => t.last_name || t.first_name);

      const operatorSelectEl   = form.querySelector("#ttb-operator-select");
      const selectedOperatorId = operatorSelectEl?.value ? parseInt(operatorSelectEl.value, 10) : null;

      const payload = {
        hotel_id:         selectedHotelId || null,
        hotel_name:       selectedHotelName || null,
        room_type_id:     selectedRoomId || null,
        room_type_name:   selectedRoomId ? selectedRoomName : null,
        booking_code:     form.querySelector("#ttb-booking-code").value.trim() || null,
        reservation_date: form.querySelector("#ttb-reserv-date").value || null,
        arrival_at:       form.querySelector("#ttb-arrival").value || null,
        departure_at:     form.querySelector("#ttb-departure").value || null,
        price:            form.querySelector("#ttb-price").value.trim() || null,
        currency_code:    form.querySelector("#ttb-currency").value.trim().toUpperCase() || null,
        adults:   form.querySelector("#ttb-adults").value   !== "" ? parseInt(form.querySelector("#ttb-adults").value,   10) : null,
        children: form.querySelector("#ttb-children").value !== "" ? parseInt(form.querySelector("#ttb-children").value, 10) : null,
        infants:  form.querySelector("#ttb-infants").value  !== "" ? parseInt(form.querySelector("#ttb-infants").value,  10) : null,
        tourists:    tourists,
        hotel_vote:  selectedVote !== null ? selectedVote * 10 : undefined,
        operator_id: selectedOperatorId,
      };

      const result = editId
        ? await updateProcessedDirect(editId, payload)
        : await storeProcessedDirect(payload);

      if (result?.hellootel?.error) {
        confirmBtn.disabled    = false;
        confirmBtn.textContent = "Confirm";
        const choice = await showHellootelErrorDialog(result.hellootel.error);
        if (choice === "ignore") {
          showToast("Failed to send HelloOtel ! (Booking confirmed)");
          window.close();
        }
        // "fix" → stay on the form, user can edit and retry
        return;
      }

      showSuccessDialog(result);
    } catch (err) {
      confirmBtn.disabled    = false;
      confirmBtn.textContent = "Retry";
      const choice = await showHellootelErrorDialog(err.message || "Connection error");
      if (choice === "ignore") window.close();
      // "fix" → stay on the form
    }
  });
}

// Read (and consume) the booking the popup asked us to edit, if any.
function readEditBooking() {
  return new Promise((resolve) => {
    try {
      chrome.storage.local.get("ttbEditBooking", (res) => {
        const rec = res?.ttbEditBooking ?? null;
        if (rec) chrome.storage.local.remove("ttbEditBooking");
        resolve(rec);
      });
    } catch {
      resolve(null);
    }
  });
}

async function init() {
  const authorized = await isAuthorized();
  if (!authorized) {
    showFallback("Please sign in to the extension first, then reopen this window.");
    return;
  }

  // Warm reference caches for the selects and the hotel-location line.
  ensureOperators();
  ensureCurrencies();
  ensureCountries();
  ensureCities();
  ensureHotels();

  const prefill = await readEditBooking();
  renderManualForm(prefill);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
