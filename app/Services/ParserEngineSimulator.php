<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * PHP port of chrome-extension/parsers/config-engine.js.
 *
 * Used by the `parser:test` artisan command to dry-run JSON parser configs
 * against saved page reports without spinning up a browser. Supports the
 * card-type parser and the common shared maps (fields, meta_fields,
 * label_maps, dl_maps, meta_maps, tourist_blocks).
 *
 * NOT a 1:1 port: form/table types, p_subtitle/h_code/h_hotel/br_map
 * specials, append_location, and a few other rarely-used niceties are
 * omitted. Add them as needed when a parser starts relying on them.
 */
class ParserEngineSimulator
{
    private CssSelectorConverter $cssToXpath;

    public function __construct()
    {
        $this->cssToXpath = new CssSelectorConverter();
    }

    /** Run a parser config against raw HTML, return array of booking records. */
    public function run(array $config, string $html): array
    {
        // Captured HTML is always UTF-8 (the extension serialises live outerHTML),
        // but the source page's own <meta charset> tag (e.g. windows-1251) is
        // captured along with it. libxml's HTML parser honours that meta tag and
        // misreads the UTF-8 bytes as the declared legacy charset, garbling
        // multi-byte sequences and aborting after the first invalid byte —
        // silently truncating the tree to just a handful of elements. Strip any
        // declared charset so the explicit UTF-8 prolog below is what wins.
        $html = preg_replace('/<meta[^>]+charset[^>]*>/i', '', $html);

        // Belt-and-braces: drop any genuinely invalid UTF-8 byte sequences too.
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $html);
        if ($clean !== false && $clean !== '') $html = $clean;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $type = $config['type'] ?? 'card';
        if ($type !== 'card') {
            // form/table not yet supported by this simulator
            return [];
        }

        if (empty($config['card'])) return [];

        $cardNodes = $this->css($xpath, $dom, $config['card']);
        $bookings = [];
        foreach ($cardNodes as $card) {
            $bookings[] = $this->parseCard($card, $config, $xpath);
        }
        return $bookings;
    }

    private function parseCard(DOMElement $card, array $cfg, DOMXPath $xpath): array
    {
        $result = [];

        // Resolve alternative extraction roots (mirrors config-engine.js):
        //   data_root  → "fields/maps/tourist_blocks come from here, not from card"
        //   card_root  → ancestor of card that scopes card_fields/card_label_maps
        $dataRoot = $this->resolveDataRoot($card, $cfg, $xpath);
        $cardRoot = $this->resolveCardRoot($card, $cfg, $xpath);

        foreach ($cfg['fields'] ?? [] as $field => $spec) {
            $result[$field] = $this->extractField($dataRoot, $spec, $xpath);
        }

        foreach ($cfg['card_fields'] ?? [] as $field => $spec) {
            $val = $this->extractField($cardRoot, $spec, $xpath);
            if ($val !== null && $val !== '' && $val !== []) {
                $result[$field] = $val;
            }
        }

        $this->applyCommonMaps($dataRoot, $cfg, $result, $xpath);

        foreach ($cfg['card_label_maps'] ?? [] as $map) {
            $result = array_merge($result, $this->extractLabelMap($cardRoot, $map, $xpath));
        }

        return $result;
    }

    private function resolveDataRoot(DOMElement $card, array $cfg, DOMXPath $xpath): DOMElement
    {
        if (empty($cfg['data_root']['selector_template'])) return $card;
        $tpl = $cfg['data_root']['selector_template'];
        $codeSpec = $cfg['data_root']['code_source'] ?? null;
        if (!$codeSpec) return $card;
        $code = $this->extractField($card, $codeSpec, $xpath);
        if (!is_string($code) || $code === '') return $card;
        $sel = str_replace('{code}', $code, $tpl);
        $found = $this->css($xpath, $card->ownerDocument, $sel)[0] ?? null;
        return $found ?: $card;
    }

    private function resolveCardRoot(DOMElement $card, array $cfg, DOMXPath $xpath): DOMElement
    {
        if (empty($cfg['card_root'])) return $card;
        return $this->closest($card, $cfg['card_root'], $xpath) ?? $card;
    }

    /** Walk up ancestors looking for a match — DOMElement has no closest(). */
    private function closest(DOMElement $el, string $selector, DOMXPath $xpath): ?DOMElement
    {
        try {
            $selfXp = $this->cssToXpath->toXPath($selector, 'self::');
        } catch (\Throwable) {
            return null;
        }
        $cur = $el;
        while ($cur instanceof DOMElement) {
            $nodes = $xpath->query($selfXp, $cur);
            if ($nodes && $nodes->length > 0) return $cur;
            $cur = $cur->parentNode;
        }
        return null;
    }

    private function applyCommonMaps(DOMElement $card, array $cfg, array &$result, DOMXPath $xpath): void
    {
        // array_merge (not +=) so a match here overwrites an already-set-but-empty
        // field, mirroring config-engine.js's Object.assign(result, ...) semantics.
        foreach ($cfg['label_maps'] ?? [] as $map) {
            $result = array_merge($result, $this->extractLabelMap($card, $map, $xpath));
        }
        foreach ($cfg['dl_maps'] ?? [] as $map) {
            $result = array_merge($result, $this->extractDlMap($card, $map, $xpath));
        }
        if (!empty($cfg['meta_maps'])) {
            $result['meta'] = $result['meta'] ?? [];
            foreach ($cfg['meta_maps'] as $map) {
                $result['meta'] = array_merge($result['meta'], $this->extractLabelMap($card, $map, $xpath));
            }
        }
        if (!empty($cfg['meta_fields'])) {
            $result['meta'] = $result['meta'] ?? [];
            foreach ($cfg['meta_fields'] as $key => $spec) {
                $val = $this->extractField($card, $spec, $xpath);
                if ($val !== null && $val !== '' && $val !== []) {
                    $result['meta'][$key] = $val;
                }
            }
        }
        if (!empty($cfg['tourist_blocks'])) {
            $tourists = $this->extractTouristBlocks($card, $cfg['tourist_blocks'], $xpath);
            if ($tourists) $result['tourists'] = $tourists;
        }
    }

    private function extractField(DOMElement $root, array $spec, DOMXPath $xpath)
    {
        // dataset attribute
        if (array_key_exists('data', $spec)) {
            $attr = 'data-' . $spec['data'];
            $val  = $root->getAttribute($attr) ?: '';
            if ($val !== '') return $val;
            if (!empty($spec['fallback'])) {
                $el = $this->css($xpath, $root, $spec['fallback'])[0] ?? null;
                return $this->textOf($el, $spec['strip_icons'] ?? false);
            }
            return null;
        }

        // self: extract from the root element itself (no sub-querying)
        if (!empty($spec['self'])) {
            $text = $this->textOf($root, $spec['strip_icons'] ?? false);
            return $this->applyStrip($text, $spec);
        }

        if (empty($spec['sel'])) {
            return !empty($spec['multi']) ? [] : null;
        }

        if (!empty($spec['multi'])) {
            $out = [];
            foreach ($this->css($xpath, $root, $spec['sel']) as $el) {
                $t = $this->textOf($el, $spec['strip_icons'] ?? false);
                $t = $this->applyStrip($t, $spec);
                if ($t !== '') $out[] = $t;
            }
            // join: when set, return joined string instead of array.
            return isset($spec['join']) ? implode($spec['join'], $out) : $out;
        }

        $el = $this->css($xpath, $root, $spec['sel'])[0] ?? null;
        if (!$el) return !empty($spec['attr']) ? null : '';

        if (!empty($spec['attr'])) return $el->getAttribute($spec['attr']) ?: null;

        $text = $this->textOf($el, $spec['strip_icons'] ?? false);
        return $this->applyStrip($text, $spec);
    }

    private function applyStrip(string $text, array $spec): string
    {
        if (!empty($spec['strip_prefix'])) {
            $prefix = preg_quote($spec['strip_prefix'], '/');
            $text = preg_replace('/^' . $prefix . '\s*/iu', '', $text);
        }
        if (!empty($spec['strip_pattern'])) {
            // JS engine defaults to single-match (no /g). Mirror that here by using
            // preg_replace with limit=1, unless the config opts into global with
            // strip_flags: "g". strip_replace defaults to "" (remove match).
            $pattern     = '/' . str_replace('/', '\/', $spec['strip_pattern']) . '/u';
            $limit       = (strpos($spec['strip_flags'] ?? '', 'g') !== false) ? -1 : 1;
            $replacement = $spec['strip_replace'] ?? '';
            $text        = preg_replace($pattern, $replacement, $text, $limit);
        }
        return trim($text ?? '');
    }

    private function extractLabelMap(DOMElement $card, array $map, DOMXPath $xpath): array
    {
        $out = [];
        foreach ($this->css($xpath, $card, $map['item']) as $item) {
            $labelEl = $this->css($xpath, $item, $map['label'])[0] ?? null;
            $valueEl = $this->css($xpath, $item, $map['value'])[0] ?? null;
            if (!$labelEl || !$valueEl) continue;
            $label = mb_strtolower($this->norm($labelEl->textContent));
            $value = $this->norm($valueEl->textContent);
            foreach ($map['fields'] ?? [] as $field => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($label, mb_strtolower($kw))) { $out[$field] = $value; break; }
                }
            }
        }
        return $out;
    }

    private function extractDlMap(DOMElement $card, array $map, DOMXPath $xpath): array
    {
        $out = [];
        $root = !empty($map['container'])
            ? ($this->css($xpath, $card, $map['container'])[0] ?? null)
            : $card;
        if (!$root) return $out;
        foreach ($this->css($xpath, $root, $map['item']) as $item) {
            $keyEl   = $this->css($xpath, $item, $map['key'])[0]   ?? null;
            $valueEl = $this->css($xpath, $item, $map['value'])[0] ?? null;
            if (!$keyEl || !$valueEl) continue;
            $key   = mb_strtolower($this->norm($keyEl->textContent));
            $value = $this->norm($valueEl->textContent);
            foreach ($map['fields'] ?? [] as $field => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($key, mb_strtolower($kw))) { $out[$field] = $value; break; }
                }
            }
        }
        return $out;
    }

    private function extractTouristBlocks(DOMElement $card, array $cfg, DOMXPath $xpath): array
    {
        // Split into CSS-mode (object spec) and label-mode (legacy: keyword array)
        $cssFields   = [];
        $labelFields = [];
        foreach ($cfg['fields'] ?? [] as $field => $spec) {
            if (is_array($spec) && !array_is_list($spec)) {
                $cssFields[$field] = $spec;
            } elseif (is_array($spec)) {
                $labelFields[$field] = $spec;
            }
        }

        $tourists = [];
        foreach ($this->css($xpath, $card, $cfg['item']) as $itemEl) {
            $tourist = [];

            foreach ($cssFields as $field => $spec) {
                $val = $this->extractField($itemEl, $spec, $xpath);
                if ($val !== null && $val !== '' && $val !== []) {
                    $tourist[$field] = $val;
                }
            }

            if ($labelFields) {
                foreach (iterator_to_array($itemEl->childNodes) as $colEl) {
                    if (!$colEl instanceof DOMElement) continue;
                    $labelEl = $this->css($xpath, $colEl, $cfg['label'] ?? 'label')[0] ?? null;
                    if (!$labelEl) continue;
                    $labelText = mb_strtolower($this->norm($labelEl->textContent));
                    if (!empty($cfg['td_text'])) {
                        $valueText = '';
                        foreach ($colEl->childNodes as $n) {
                            if ($n->nodeType === XML_TEXT_NODE) {
                                $valueText .= ' ' . trim($n->textContent);
                            }
                        }
                        $valueText = $this->norm($valueText);
                        if ($valueText === '') continue;
                    } else {
                        $valueEl = $this->css($xpath, $colEl, $cfg['value'] ?? 'span')[0] ?? null;
                        if (!$valueEl) continue;
                        $valueText = $this->norm($valueEl->textContent);
                    }
                    foreach ($labelFields as $field => $keywords) {
                        foreach ($keywords as $kw) {
                            if (str_contains($labelText, mb_strtolower($kw))) {
                                $tourist[$field] = $valueText;
                                break;
                            }
                        }
                    }
                }
            }

            if ($tourist) $tourists[] = $tourist;
        }
        return $tourists;
    }

    // ── DOM helpers ──────────────────────────────────────────────────────────

    /** @return DOMElement[] */
    private function css(DOMXPath $xpath, DOMNode $context, string $selector): array
    {
        try {
            $xp = $this->cssToXpath->toXPath($selector, 'descendant-or-self::');
        } catch (\Throwable $e) {
            return [];
        }
        $nodes = $xpath->query($xp, $context);
        if (!$nodes) return [];
        $out = [];
        foreach ($nodes as $n) {
            if ($n instanceof DOMElement) $out[] = $n;
        }
        return $out;
    }

    private function textOf(?DOMElement $el, bool $stripIcons): string
    {
        if (!$el) return '';
        if ($stripIcons) {
            $parts = [];
            foreach ($el->childNodes as $n) {
                if ($n->nodeType === XML_TEXT_NODE) {
                    $t = trim($n->textContent);
                    if ($t !== '') $parts[] = $t;
                }
            }
            return trim(implode(' ', $parts));
        }
        return $this->norm($el->textContent);
    }

    private function norm(?string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s ?? ''));
    }
}
