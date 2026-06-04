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

// Age buckets for auto-counting guests from their date of birth (age at check-in).
// Must match the server rule in BookingProcessorService::parseGuestCounts():
//   0-5  → infant, 6-12 → child, 13+ → adult. Unknown DOB → adult.
// (constants are exclusive upper bounds: age < 6 → infant, age < 13 → child)
const INFANT_MAX_AGE = 6;
const CHILD_MAX_AGE  = 13;

function ageAt(dobIso, refDate) {
  const dob = new Date(dobIso);
  if (isNaN(dob.getTime())) return null;
  let age = refDate.getFullYear() - dob.getFullYear();
  const m = refDate.getMonth() - dob.getMonth();
  if (m < 0 || (m === 0 && refDate.getDate() < dob.getDate())) age--;
  return age;
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

    <div class="ttb-modal__section-title">Booking details</div>

    <label class="ttb-modal__label">Operator</label>
    <select class="ttb-modal__select" id="ttb-operator-select">
      <option value="">— select operator —</option>
    </select>

    <div class="ttb-modal__row-2">
      <div>
        <label class="ttb-modal__label">Booking number</label>
        <input class="ttb-modal__input" id="ttb-booking-code" type="text" placeholder="ORD-123456" />
      </div>
      <div>
        <label class="ttb-modal__label">Booking date</label>
        <input class="ttb-modal__input" id="ttb-reserv-date" type="date" />
      </div>
    </div>

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

    <div class="ttb-modal__row-2">
      <div>
        <label class="ttb-modal__label">Price</label>
        <input class="ttb-modal__input" id="ttb-price" type="text" placeholder="1250.00" />
      </div>
      <div>
        <label class="ttb-modal__label">Currency</label>
        <select class="ttb-modal__input ttb-modal__select" id="ttb-currency">
          <option value="">—</option>
        </select>
      </div>
    </div>

    <div class="ttb-modal__section-title">Guests <span class="ttb-required">*</span></div>
    <div id="ttb-tourists-list"></div>
    <button class="ttb-modal__add-tourist" type="button" id="ttb-add-tourist">+ Add guest</button>

    <div class="ttb-modal__row-3">
      <div>
        <label class="ttb-modal__label">Adults</label>
        <input class="ttb-modal__input" id="ttb-adults" type="number" min="0" />
      </div>
      <div>
        <label class="ttb-modal__label">Children</label>
        <input class="ttb-modal__input" id="ttb-children" type="number" min="0" />
      </div>
      <div>
        <label class="ttb-modal__label">Infants</label>
        <input class="ttb-modal__input" id="ttb-infants" type="number" min="0" />
      </div>
    </div>
    <p class="ttb-modal__required-note">Counted automatically from dates of birth — you can edit them.</p>

    <div class="ttb-modal__section-title">Hotel</div>

    <label class="ttb-modal__label">Hotel <span class="ttb-required">*</span></label>
    <div class="ttb-modal__autocomplete">
      <input class="ttb-modal__input" id="ttb-hotel-input" type="text" placeholder="Type hotel name..." autocomplete="off" />
      <ul class="ttb-modal__suggestions" id="ttb-hotel-suggestions" hidden></ul>
    </div>
    <div class="ttb-hotel-location" id="ttb-hotel-location" hidden></div>

    <div class="ttb-rating-row">
      <span class="ttb-rating-label">Your hotel rating <span class="ttb-required">*</span></span>
      <div class="ttb-stars" id="ttb-stars">
        ${[1,2,3,4,5,6,7,8,9,10].map(i => `<span class="ttb-star" data-vote="${i}">☆</span>`).join("")}
      </div>
    </div>

    <label class="ttb-modal__label">Room type <span class="ttb-required">*</span></label>
    <select class="ttb-modal__select" id="ttb-room-select" disabled>
      <option value="">— select hotel first —</option>
    </select>

    <p class="ttb-modal__required-note"><span class="ttb-required">*</span> Required fields</p>
    <p class="ttb-modal__send-note">You are sending booking information directly to the hotel manager via the HelloOtel system.</p>

    <div class="ttb-modal__api-error" id="ttb-api-error" hidden></div>

    <div class="mb-form__footer">
      <button class="ttb-modal__btn ttb-modal__btn--cancel"  type="button" id="mb-cancel">Cancel</button>
      <button class="ttb-modal__btn ttb-modal__btn--confirm" type="button" id="mb-confirm" disabled>Confirm</button>
    </div>
  `;
  document.body.appendChild(form);

  // Populate selects (lists may still be loading).
  ensureCurrencies().then(() => populateCurrencySelectEl(form.querySelector("#ttb-currency"), prefill?.currency_code ?? ""));
  ensureOperators().then(() => populateOperatorSelect(form.querySelector("#ttb-operator-select"), prefill?.operator_id ?? null));

  const hotelInput      = form.querySelector("#ttb-hotel-input");
  const suggestions     = form.querySelector("#ttb-hotel-suggestions");
  const hotelLocationEl = form.querySelector("#ttb-hotel-location");
  const roomSelect      = form.querySelector("#ttb-room-select");
  const touristsList    = form.querySelector("#ttb-tourists-list");
  const arrivalInput    = form.querySelector("#ttb-arrival");
  const departureInput  = form.querySelector("#ttb-departure");
  const adultsInput     = form.querySelector("#ttb-adults");
  const childrenInput   = form.querySelector("#ttb-children");
  const infantsInput    = form.querySelector("#ttb-infants");
  const confirmBtn      = form.querySelector("#mb-confirm");
  const apiErrorEl      = form.querySelector("#ttb-api-error");

  let selectedHotelId   = null;
  let selectedHotelName = "";
  let selectedVote      = null;
  let hotelSearchTid    = null;
  let autoCounts        = true; // recompute from DOB on guest changes (create and edit); saved counts are kept on load since recompute isn't called during prefill

  // ── Auto-count guests by age (from DOB, at check-in) — editable afterwards ──
  function recomputeCounts() {
    if (!autoCounts) return;
    const ref = arrivalInput.value ? new Date(arrivalInput.value) : new Date();
    let adults = 0, children = 0, infants = 0;
    for (const row of touristsList.querySelectorAll(".ttb-tourist-row")) {
      const hasName = row.querySelector(".ttb-tourist__last").value.trim() ||
                      row.querySelector(".ttb-tourist__first").value.trim();
      if (!hasName) continue;
      const dob = row.querySelector(".ttb-tourist__dob").value;
      const age = dob ? ageAt(dob, ref) : null;
      if (age === null)             adults++;        // unknown DOB → adult
      else if (age < INFANT_MAX_AGE) infants++;
      else if (age < CHILD_MAX_AGE)  children++;
      else                           adults++;
    }
    adultsInput.value   = String(adults);
    childrenInput.value = String(children);
    infantsInput.value  = String(infants);
  }
  // Manual edit stops auto-recompute.
  [adultsInput, childrenInput, infantsInput].forEach(el =>
    el.addEventListener("input", () => { autoCounts = false; }));

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
    confirmBtn.disabled = !selectedHotelId || !roomSelect.value || !(selectedVote > 0) || !hasAnyTourist();
  }
  roomSelect.addEventListener("change", updateConfirmState);

  // ── Room types depend on hotel + the dates entered above ──
  function reloadRoomTypesForDates() {
    if (!selectedHotelId) return;
    const keep = roomSelect.value || null;
    loadRoomTypes(selectedHotelId, roomSelect, keep, arrivalInput.value || null, departureInput.value || null)
      .then(updateConfirmState);
  }
  // Changing the guest list or the check-in date must always re-derive the
  // counts from DOB — re-enable autoCounts so it wins over any prior manual edit.
  arrivalInput.addEventListener("change", () => { reloadRoomTypesForDates(); autoCounts = true; recomputeCounts(); });
  departureInput.addEventListener("change", reloadRoomTypesForDates);

  // ── Guests: prefilled rows when editing, otherwise one empty row ──
  if (prefill?.tourists?.length) {
    for (const t of prefill.tourists) touristsList.appendChild(buildTouristRow(t));
  } else {
    touristsList.appendChild(buildTouristRow());
  }
  form.querySelector("#ttb-add-tourist").addEventListener("click", () => {
    touristsList.appendChild(buildTouristRow());
    updateConfirmState();
    autoCounts = true;
    recomputeCounts();
  });
  touristsList.addEventListener("input", () => { autoCounts = true; updateConfirmState(); recomputeCounts(); });
  touristsList.addEventListener("click", (e) => {
    if (e.target.classList?.contains("ttb-tourist__remove")) {
      queueMicrotask(() => { updateConfirmState(); autoCounts = true; recomputeCounts(); });
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
      li.addEventListener("mousedown", async (e) => {
        e.preventDefault();
        selectedHotelId   = h.id;
        selectedHotelName = h.name;
        hotelInput.value  = h.name;
        updateHotelLocation(h.country_id, h.city_id);
        hideSuggestions();
        await loadRoomTypes(h.id, roomSelect, null, arrivalInput.value || null, departureInput.value || null);
        updateConfirmState();
        getHotelVoteFromServer(h.id).then(v => updateStars(v ? Math.round(v / 10) : 0)).catch(() => {});
      });
      suggestions.appendChild(li);
    }
    suggestions.hidden = false;
  }
  hotelInput.addEventListener("input", () => {
    selectedHotelId = null;
    updateHotelLocation(null, null);
    updateConfirmState();
    clearTimeout(hotelSearchTid);
    const q = hotelInput.value.trim();
    if (q.length < 2) { hideSuggestions(); return; }
    hotelSearchTid = setTimeout(async () => {
      try { showSuggestions(await searchHotelsOnServer(q)); } catch { hideSuggestions(); }
    }, 300);
  });
  hotelInput.addEventListener("blur", () => setTimeout(hideSuggestions, 150));

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
    }
    updateConfirmState();
  }

  // ── Cancel / Confirm ──
  const clearApiError = () => { apiErrorEl.hidden = true; apiErrorEl.textContent = ""; };

  form.querySelector("#mb-cancel").addEventListener("click", () => window.close());

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
        confirmBtn.textContent = "Retry";
        const choice = await showHellootelErrorDialog(result.hellootel.error);
        if (choice === "ignore") {
          showToast("Failed to send HelloOtel ! (Booking confirmed)");
          window.close();
        }
        // "fix" → stay on the form, user can edit and retry
        return;
      }

      // Success: nothing more to show — just close the window.
      window.close();
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

  const prefill = await readEditBooking();
  renderManualForm(prefill);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
