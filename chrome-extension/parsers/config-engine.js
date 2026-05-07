/**
 * ConfigParserEngine
 *
 * Builds a parser object from a JSON config stored in the database.
 * No eval / new Function required — the engine interprets the config directly.
 *
 * ─── Parser types ─────────────────────────────────────────────────────────────
 *
 *   type  {string?}  "card" (default) | "form" | "table"
 *
 * ─── Top-level config keys (type: "card") ─────────────────────────────────────
 *
 *   path    {string?}  Regex tested against location.pathname.
 *                      Required when multiple parsers share the same domain.
 *                      e.g. "\/demo\/bookings-demo$"
 *
 *   card    {string}   CSS selector for booking card elements.
 *
 *   button  {string?}  CSS selector (relative to card) for button injection.
 *                      Defaults to the card element itself.
 *
 * ─── fields ───────────────────────────────────────────────────────────────────
 *
 *   Each key maps to a booking field (booking_code, hotel_name, stay_dates …).
 *   Supported spec properties:
 *
 *   SIMPLE EXTRACTION
 *     sel          {string}   CSS selector → textContent
 *     attr         {string?}  Return this attribute value instead of textContent
 *     multi        {bool?}    Return array of all matching texts
 *     strip_icons  {bool?}    Text-nodes only (skips <i> / <svg> children)
 *     strip_prefix  {string?}  Strip this literal prefix from extracted text
 *                             (case-insensitive, use with sel)
 *     strip_pattern {string?}  RegExp pattern (no backslash-letter escapes: use [0-9] not \d,
 *                             [ ]* not \s, [(] not \() applied to text AFTER strip_prefix.
 *                             Works in both single and multi modes.
 *     append_location {string?}  Append " (text)" from another selector
 *
 *   DATASET
 *     data         {string}   Read card.dataset[data]  (e.g. "ref" → data-ref)
 *     fallback     {string?}  Fallback selector when data attr is empty
 *
 *   H-ELEMENT SPLIT  — for patterns like <h3>ORD-001: Hotel Name</h3>
 *     h_code       {string}   Selector → text before first ": "  (the code)
 *     h_hotel      {string}   Selector → text after  first ": "  (the hotel name)
 *       location_p {string?}  Also grab city from this <p> and append as " (City)"
 *       seps       {string[]?} Separator list for city extraction (default [" · ",". "])
 *
 *   FIRST-P SUBTITLE  — for patterns like <p>City · Subtitle</p>
 *     p_subtitle   {string}   Selector → text after the first matching separator
 *       seps       {string[]?} Separators (default [" · ", ". "])
 *
 *   BR-LINE MAP  — for <p>Key: Value<br>Key: Value<br>…</p>
 *     br_map       {string}   Selector of the paragraph containing <br> lines
 *     key_match    {string[]} Keywords to match in the key part (before ": ")
 *
 * ─── The following keys work in ALL parser types (card, form, table) ──────────
 *
 * ─── label_maps ───────────────────────────────────────────────────────────────
 *
 *   Array of label/value grid configs (e.g. .meta-item containing .label + .value):
 *   [{ item, label, value, fields: { fieldName: ["keyword1", ...] } }]
 *
 * ─── dl_maps ──────────────────────────────────────────────────────────────────
 *
 *   Array of definition-list configs (dt/dd pairs):
 *   [{ container?, item, key, value, fields: { fieldName: ["keyword1", ...] } }]
 *
 * ─── meta_maps ────────────────────────────────────────────────────────────────
 *
 *   Same format as label_maps but results go into result.meta instead of
 *   top-level booking fields. Use for site-specific data (from, to, vehicle …):
 *   [{ item, label, value, fields: { metaKey: ["keyword1", ...] } }]
 *
 * ─── meta_fields ──────────────────────────────────────────────────────────────
 *
 *   Object whose keys go into result.meta. Each value is a normal field spec
 *   (same as fields entries: sel, strip_prefix, strip_pattern, etc.).
 *   Use when a single selector needs to be stored under meta rather than a
 *   top-level booking field (e.g. reservation_at from "Забронирован:"):
 *   { metaKey: { sel: "...", strip_prefix: "..." } }
 *
 * ─── tourist_blocks ───────────────────────────────────────────────────────────
 *
 *   Extracts an array of tourist/passenger objects into result.tourists (DB column).
 *   Each matching item element is one tourist; fields matched via label text:
 *   { item, label?, value?, fields: { last_name: ["фамилия"], first_name: ["имя"], dob: [...] } }
 *
 * ─── type: "form" ─────────────────────────────────────────────────────────────
 *
 *   container  {string?}  CSS selector for the form wrapper (default: body).
 *   button     {string?}  CSS selector for save-button injection area.
 *   fields     {object}   Each key → { label_match: ["keyword", ...] }
 *                         Matches <label> text to find the associated control.
 *
 * ─── type: "table" ────────────────────────────────────────────────────────────
 *
 *   table       {string}   CSS selector for the <table> element.
 *   button_cell {string?}  CSS selector (relative to <tr>) for button injection.
 *                          Defaults to the last <td>.
 *   fields      {object}   Each key → ["header keyword", ...]
 *                          Matched against <thead th> text to map column indices.
 */

const ConfigParserEngine = (() => {

  function build(entry) {
    const cfg       = entry.config;
    const domain    = entry.domain     || null;
    const pathMatch = entry.path_match || null;

    const type = cfg.type || "card";

    if (type === "form")  return _buildForm(entry, domain, pathMatch);
    if (type === "table") return _buildTable(entry, domain, pathMatch);

    // ── default: card-based parser ──────────────────────────────────────────
    return {
      name: entry.name,
      _domain: domain,
      _pathMatch: pathMatch,

      matches: _makeMatches(domain, pathMatch),

      getCards() {
        return Array.from(document.querySelectorAll(cfg.card));
      },

      getButtonContainer(card) {
        if (!cfg.button) return card;
        return card.querySelector(cfg.button) || card;
      },

      parseCard(card) {
        const result = {};

        if (cfg.fields) {
          for (const [field, spec] of Object.entries(cfg.fields)) {
            result[field] = _extractField(card, spec);
          }
        }

        _applyCommonMaps(card, cfg, result);

        return result;
      },
    };
  }

  // ── type: "form" ────────────────────────────────────────────────────────────

  function _buildForm(entry, domain, pathMatch) {
    const cfg = entry.config;

    return {
      name: entry.name,
      _domain: domain,
      _pathMatch: pathMatch,

      matches: _makeMatches(domain, pathMatch),

      getCards() {
        const el = cfg.container
          ? document.querySelector(cfg.container)
          : document.body;
        return el ? [el] : [];
      },

      getButtonContainer(card) {
        if (!cfg.button) return card;
        return card.querySelector(cfg.button) || card;
      },

      parseCard(card) {
        const result = _extractFormFields(card, cfg.fields || {});
        _applyCommonMaps(card, cfg, result);
        return result;
      },
    };
  }

  function _extractFormFields(container, fieldDefs) {
    const result = {};
    const labels = Array.from(container.querySelectorAll("label"));

    for (const label of labels) {
      const labelText = _norm(label.textContent).toLowerCase();

      for (const [field, spec] of Object.entries(fieldDefs)) {
        if (result[field] != null) continue;
        const keywords = spec.label_match || [];
        if (!keywords.some((kw) => labelText.includes(kw))) continue;

        // 1. label[for] → #id
        let control = null;
        const forAttr = label.getAttribute("for");
        if (forAttr) {
          try { control = container.querySelector(`#${CSS.escape(forAttr)}`); } catch {}
        }
        // 2. input/select/textarea inside the label
        if (!control) control = label.querySelector("input, select, textarea");
        // 3. first matching sibling after the label
        if (!control) {
          let el = label.nextElementSibling;
          while (el && !["INPUT", "SELECT", "TEXTAREA"].includes(el.tagName)) {
            const inner = el.querySelector("input, select, textarea");
            if (inner) { control = inner; break; }
            el = el.nextElementSibling;
          }
          if (!control && el && ["INPUT", "SELECT", "TEXTAREA"].includes(el.tagName)) {
            control = el;
          }
        }
        // 4. any control inside the label's parent
        if (!control) {
          control = label.parentElement?.querySelector("input, select, textarea") || null;
        }

        if (control) {
          const val = _getControlValue(control);
          result[field] = spec.as_array ? (val ? [val] : []) : val;
        }
      }
    }
    return result;
  }

  function _getControlValue(el) {
    if (!el) return "";
    if (el.tagName === "SELECT") {
      return el.options[el.selectedIndex]?.text?.trim() || el.value || "";
    }
    return el.value || _norm(el.textContent) || "";
  }

  // ── type: "table" ────────────────────────────────────────────────────────────

  function _buildTable(entry, domain, pathMatch) {
    const cfg = entry.config;
    let _colMap = null;

    function _getTable() {
      return cfg.table ? document.querySelector(cfg.table) : null;
    }

    function _buildColMap(table) {
      const headers = Array.from(table.querySelectorAll("thead th, thead td"));
      const map = {};
      const fieldDefs = cfg.fields || {};
      headers.forEach((th, idx) => {
        const hText = _norm(th.textContent).toLowerCase();
        for (const [field, def] of Object.entries(fieldDefs)) {
          const keywords = Array.isArray(def) ? def : (def.keywords || []);
          if (keywords.some((kw) => hText.includes(kw))) {
            map[idx] = field;
            break;
          }
        }
      });
      return map;
    }

    return {
      name: entry.name,
      _domain: domain,
      _pathMatch: pathMatch,

      matches: _makeMatches(domain, pathMatch),

      getCards() {
        const table = _getTable();
        if (!table) return [];
        _colMap = _buildColMap(table);
        return Array.from(table.querySelectorAll("tbody tr"));
      },

      getButtonContainer(card) {
        if (cfg.button_cell) return card.querySelector(cfg.button_cell) || card;
        const cells = card.querySelectorAll("td");
        return cells.length ? cells[cells.length - 1] : card;
      },

      parseCard(card) {
        if (!_colMap) {
          const table = _getTable();
          if (table) _colMap = _buildColMap(table);
        }
        const cells = Array.from(card.querySelectorAll("td"));
        const result = {};
        const fieldDefs = cfg.fields || {};
        for (const [idxStr, field] of Object.entries(_colMap || {})) {
          const cell = cells[parseInt(idxStr)];
          if (!cell) continue;
          const def = fieldDefs[field];
          const asArray = !Array.isArray(def) && def?.as_array;
          const val = _norm(cell.textContent);
          result[field] = asArray ? (val ? [val] : []) : val;
        }

        _applyCommonMaps(card, cfg, result);

        return result;
      },
    };
  }

  // ── shared: apply label_maps / dl_maps / meta_maps / tourist_blocks ──────────
  //   Called at the end of parseCard() in all three parser types.

  function _applyCommonMaps(card, cfg, result) {
    if (cfg.label_maps) {
      for (const map of cfg.label_maps) {
        Object.assign(result, _extractLabelMap(card, map));
      }
    }

    if (cfg.dl_maps) {
      for (const map of cfg.dl_maps) {
        Object.assign(result, _extractDlMap(card, map));
      }
    }

    if (cfg.meta_maps) {
      result.meta = result.meta || {};
      for (const map of cfg.meta_maps) {
        Object.assign(result.meta, _extractLabelMap(card, map));
      }
    }

    if (cfg.meta_fields) {
      result.meta = result.meta || {};
      for (const [key, spec] of Object.entries(cfg.meta_fields)) {
        const val = _extractField(card, spec);
        if (val !== null && val !== undefined && val !== "") {
          result.meta[key] = val;
        }
      }
    }

    if (cfg.tourist_blocks) {
      const tourists = _extractTouristBlocks(card, cfg.tourist_blocks);
      if (tourists.length) result.tourists = tourists;
    }
  }

  // ── shared matches factory ───────────────────────────────────────────────────

  function _makeMatches(domain, pathMatch) {
    return function matches(location) {
      if (!domain) return false;
      const hostMatch =
        location.host     === domain ||
        location.hostname === domain;
      if (!hostMatch) return false;
      if (pathMatch) {
        const after = location.pathname[pathMatch.length];
        return location.pathname.startsWith(pathMatch) &&
          (!after || after === "/" || after === "?" || after === "#");
      }
      return true;
    };
  }

  // ── helpers ────────────────────────────────────────────────────────────────

  function _norm(v) {
    return (v || "").replace(/\s+/g, " ").trim();
  }

  function _textOf(el, stripIcons) {
    if (!el) return "";
    if (stripIcons) {
      return Array.from(el.childNodes)
        .filter((n) => n.nodeType === Node.TEXT_NODE)
        .map((n) => n.textContent.trim())
        .filter(Boolean)
        .join(" ")
        .trim();
    }
    return _norm(el.textContent);
  }

  function _splitOn(text, seps) {
    for (const sep of seps) {
      const idx = text.indexOf(sep);
      if (idx !== -1) return [text.slice(0, idx).trim(), text.slice(idx + sep.length).trim()];
    }
    return [text, ""];
  }

  function _extractField(card, spec) {

    // ── dataset attribute ───────────────────────────────────────────────────
    if (spec.data !== undefined) {
      const val = card.dataset[spec.data] || "";
      if (val) return val;
      if (spec.fallback) return _textOf(card.querySelector(spec.fallback), spec.strip_icons);
      return null;
    }

    // ── h-element split: code (before ": ") ────────────────────────────────
    if (spec.h_code !== undefined) {
      const el = card.querySelector(spec.h_code);
      const text = _norm(el?.textContent || "");
      const idx = text.indexOf(": ");
      return idx !== -1 ? text.slice(0, idx) : null;
    }

    // ── h-element split: hotel name (after ": ") + optional location ───────
    if (spec.h_hotel !== undefined) {
      const el = card.querySelector(spec.h_hotel);
      const hText = _norm(el?.textContent || "");
      const hIdx = hText.indexOf(": ");
      const hotel = hIdx !== -1 ? hText.slice(hIdx + 2) : hText;

      if (spec.location_p) {
        const seps = spec.seps || [" · ", ". "];
        const pEl = card.querySelector(spec.location_p);
        const pText = _norm(pEl?.textContent || "");
        const [city] = _splitOn(pText, seps);
        return hotel && city ? `${hotel} (${city})` : hotel;
      }
      return hotel;
    }

    // ── first-p subtitle (after separator) ─────────────────────────────────
    if (spec.p_subtitle !== undefined) {
      const seps = spec.seps || [" · ", ". "];
      const el = card.querySelector(spec.p_subtitle);
      const text = _norm(el?.textContent || "");
      const [, subtitle] = _splitOn(text, seps);
      return subtitle;
    }

    // ── br-line key:value map ───────────────────────────────────────────────
    if (spec.br_map !== undefined) {
      const el = card.querySelector(spec.br_map);
      if (!el) return "";
      const keywords = spec.key_match || [];
      const lines = el.innerHTML
        .split(/<br\s*\/?>/i)
        .map((l) => l.replace(/<[^>]+>/g, "").trim())
        .filter(Boolean);
      for (const line of lines) {
        const colon = line.indexOf(": ");
        if (colon === -1) continue;
        const key = line.slice(0, colon).toLowerCase();
        if (keywords.some((kw) => key.includes(kw))) return line.slice(colon + 2).trim();
      }
      return "";
    }

    // ── normal selector ─────────────────────────────────────────────────────
    if (!spec.sel) return spec.multi ? [] : null;

    if (spec.multi) {
      return Array.from(card.querySelectorAll(spec.sel))
        .map((el) => {
          let t = _textOf(el, spec.strip_icons);
          if (spec.strip_prefix) {
            const rx = new RegExp("^" + spec.strip_prefix.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\s*", "i");
            t = t.replace(rx, "").trim();
          }
          if (spec.strip_pattern) {
            t = t.replace(new RegExp(spec.strip_pattern), "").trim();
          }
          return t;
        })
        .filter(Boolean);
    }

    const el = card.querySelector(spec.sel);
    if (!el) return spec.attr ? null : "";

    if (spec.attr) return el.getAttribute(spec.attr) || null;

    let text = _textOf(el, spec.strip_icons);

    if (spec.strip_prefix) {
      const rx = new RegExp(
        "^" + spec.strip_prefix.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\s*",
        "i"
      );
      text = text.replace(rx, "").trim();
    }

    if (spec.strip_pattern) {
      text = text.replace(new RegExp(spec.strip_pattern), "").trim();
    }

    if (spec.append_location) {
      const locEl = card.querySelector(spec.append_location);
      const loc = locEl ? _textOf(locEl, true) : "";
      return text && loc ? `${text} (${loc})` : text;
    }

    return text;
  }

  function _extractLabelMap(card, map) {
    const result = {};
    for (const item of card.querySelectorAll(map.item)) {
      const labelEl = item.querySelector(map.label);
      const valueEl = item.querySelector(map.value);
      if (!labelEl || !valueEl) continue;
      const label = _norm(labelEl.textContent).toLowerCase();
      const value = _norm(valueEl.textContent);
      for (const [field, keywords] of Object.entries(map.fields)) {
        if (keywords.some((kw) => label.includes(kw))) result[field] = value;
      }
    }
    return result;
  }

  function _extractTouristBlocks(card, cfg) {
    const tourists = [];
    for (const itemEl of card.querySelectorAll(cfg.item)) {
      const tourist = {};
      for (const colEl of itemEl.children) {
        const labelEl = colEl.querySelector(cfg.label || "label");
        const valueEl = colEl.querySelector(cfg.value || "span");
        if (!labelEl || !valueEl) continue;
        const labelText = _norm(labelEl.textContent).toLowerCase();
        const valueText = _norm(valueEl.textContent);
        for (const [field, keywords] of Object.entries(cfg.fields || {})) {
          if (keywords.some((kw) => labelText.includes(kw))) tourist[field] = valueText;
        }
      }
      if (Object.keys(tourist).length) tourists.push(tourist);
    }
    return tourists;
  }

  function _extractDlMap(card, map) {
    const result = {};
    const root = map.container ? card.querySelector(map.container) : card;
    if (!root) return result;
    for (const item of root.querySelectorAll(map.item)) {
      const keyEl = item.querySelector(map.key);
      const valueEl = item.querySelector(map.value);
      if (!keyEl || !valueEl) continue;
      const key = _norm(keyEl.textContent).toLowerCase();
      const value = _norm(valueEl.textContent);
      for (const [field, keywords] of Object.entries(map.fields)) {
        if (keywords.some((kw) => key.includes(kw))) result[field] = value;
      }
    }
    return result;
  }

  return { build };
})();
