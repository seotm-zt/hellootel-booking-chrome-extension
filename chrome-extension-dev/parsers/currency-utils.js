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
