/**
 * Detects an ISO-4217 currency code from a raw price string.
 * Mirrors the PHP logic in BookingProcessorService::parseTotalPrice().
 *
 * Examples:
 *   detectCurrency("1 904 EUR")  → "EUR"
 *   detectCurrency("1 904€")     → "EUR"
 *   detectCurrency("$1,250.00")  → "USD"
 *   detectCurrency("abc")        → null
 */
function detectCurrency(text) {
  if (!text) return null;

  const upper = String(text).toUpperCase();

  // 3-letter ISO code, e.g. "EUR", "USD"
  const iso = upper.match(/\b([A-Z]{3})\b/);
  if (iso) return iso[1];

  // Currency symbol
  const sym = String(text).match(/[€$£¥₽]/);
  if (sym) {
    return { '€': 'EUR', '$': 'USD', '£': 'GBP', '¥': 'JPY', '₽': 'RUB' }[sym[0]] ?? null;
  }

  return null;
}

/**
 * Extracts a numeric price from a raw price string.
 * Mirrors BookingProcessorService::extractNumeric() (PHP) so the confirm
 * modal can show a price even when no server round-trip has happened yet
 * (e.g. the "direct" no-guests flow, which never persists the raw booking).
 *
 * Examples:
 *   extractPriceNumber("1 817,12 €") → 1817.12
 *   extractPriceNumber("$1,250.00")  → 1250
 *   extractPriceNumber("abc")        → null
 */
function extractPriceNumber(text) {
  if (!text) return null;
  let digits = String(text).replace(/[^\d.,]/g, "");
  if (!digits) return null;

  if (digits.includes(",") && digits.includes(".")) {
    digits = digits.lastIndexOf(",") > digits.lastIndexOf(".")
      ? digits.replace(/\./g, "").replace(",", ".")
      : digits.replace(/,/g, "");
  } else if (digits.includes(",")) {
    digits = digits.replace(/\./g, "").replace(",", ".");
  }

  const num = parseFloat(digits);
  return Number.isFinite(num) ? num : null;
}
