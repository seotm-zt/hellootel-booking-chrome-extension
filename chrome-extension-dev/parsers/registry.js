/**
 * Parser Registry
 *
 * Each parser must implement:
 *   matches(location)        → bool       — whether this parser handles the current page
 *   getCards()               → Element[]  — all booking card elements on the page
 *   parseCard(card)          → BookingData — extract booking fields from a card element
 *   getButtonContainer(card) → Element    — where to inject the save button inside a card
 *
 * Two sources of parsers:
 *
 *   1. Bundled parsers — registered at load time via ParserRegistry.register().
 *      Their matches() method contains hardcoded URL logic.
 *
 *   2. DB parsers — fetched from /api/v1/extension/parsers at page load.
 *      Built by ConfigParserEngine from a JSON config stored in the admin panel.
 *      DB parsers with the same name replace their bundled counterpart.
 *
 * Domain rules (/api/v1/extension/parser-rules) override matches() for bundled
 * parsers — useful when a bundled parser needs to cover a new domain without
 * an extension update.
 *
 * Priority when find(location) is called:
 *   1. Domain rules (DB parsers' own domain + parser-rules table entries)
 *   2. Hardcoded matches() of each registered parser
 */

const ParserRegistry = (() => {
  /** @type {object[]} registered parser objects */
  const parsers = [];

  /** @type {{domain:string, parser:string}[]} loaded from parser-rules API */
  let remoteRules = [];

  // ── registration ────────────────────────────────────────────────────────────

  function register(parser) {
    parsers.push(parser);
  }

  function findByName(name) {
    return parsers.find((p) => p.name === name) || null;
  }

  // ── lookup ──────────────────────────────────────────────────────────────────

  function find(location) {
    // 1. Domain rules (DB parser domain + parser-rules table)
    //    Rules with path_match take priority over catch-all rules (empty path_match).
    const domainRules = remoteRules.filter((r) => r.domain === location.hostname);
    const rule = domainRules
      .sort((a, b) => (b.path_match || "").length - (a.path_match || "").length)
      .find((r) => {
        const pm = r.path_match || "";
        if (!pm) return true;
        const after = location.pathname[pm.length];
        return location.pathname.startsWith(pm) &&
          (!after || after === "/" || after === "?" || after === "#");
      });

    if (rule) {
      const parser = findByName(rule.parser);
      if (parser) {
        // Bundled parsers: trust the rule fully (no _domain).
        // DB parsers: skip their own domain check (rule already matched the domain);
        //             only verify path_match so path-scoped parsers still work correctly.
        if (!parser._domain) return parser;
        const pm = parser._pathMatch;
        if (!pm) return parser;
        const after = location.pathname[pm.length];
        const pathOk = location.pathname.startsWith(pm) &&
          (!after || after === "/" || after === "?" || after === "#");
        if (pathOk) return parser;
      }
    }

    // 2. Hardcoded matches() — also catches DB parsers on shared domains not in remoteRules
    return parsers.find((p) => p.matches(location)) || null;
  }

  // ── remote loading ──────────────────────────────────────────────────────────

  /**
   * Load DB parsers from the API and register/replace them.
   * DB parsers also auto-register their domain as a rule.
   */
  async function loadParsers() {
    try {
      const resp = await chrome.runtime.sendMessage({ type: "LOAD_PARSERS" });
      if (!resp?.ok || !Array.isArray(resp.data?.data)) return;

      for (const entry of resp.data.data) {
        if (!entry.name || !entry.config) continue;

        const parser = ConfigParserEngine.build(entry);

        // Replace bundled parser with same name, or push new
        const idx = parsers.findIndex((p) => p.name === parser.name);
        if (idx >= 0) {
          parsers[idx] = parser;
        } else {
          parsers.push(parser);
        }

        // Register the domain rule so find() can match by hostname
        if (entry.domain) {
          const pm = entry.path_match || "";
          const existing = remoteRules.find(
            (r) => r.domain === entry.domain && (r.path_match || "") === pm
          );
          if (!existing) {
            remoteRules.push({ domain: entry.domain, path_match: pm, parser: parser.name });
          }
        }
      }
    } catch {
      // background unreachable — rely on bundled parsers only
    }
  }

  async function loadRules() {
    try {
      const resp = await chrome.runtime.sendMessage({ type: "LOAD_RULES" });
      if (!resp?.ok || !Array.isArray(resp.data?.data)) return;

      for (const rule of resp.data.data) {
        const pm = rule.path_match || "";
        if (!remoteRules.find((r) => r.domain === rule.domain && (r.path_match || "") === pm)) {
          remoteRules.push(rule);
        }
      }
    } catch {
      // background unreachable — hardcoded matches() still works
    }
  }

  return { register, find, findByName, loadParsers, loadRules };
})();
