<?php

use Illuminate\Support\Facades\Route;

// Redirect root to admin panel
Route::get('/', fn () => redirect('/admin'));

// Chrome Web Store — Privacy Policy
Route::get('/privacy', fn () => view('privacy'))->name('privacy');

// Page Report HTML preview (served inside Filament admin iframe)
Route::get('/admin/extension/page-reports/{id}/html', function (int $id) {
    $report = \App\Models\ExtensionPageReport::findOrFail($id);

    $inject = '<base href="' . e($report->url) . '"><meta name="ttb-preview-url" content="' . e($report->url) . '">';
    // Self-contained preview CSS: SPA snapshots reference build-hashed bundles
    // (Next.js /_next/static/css/<hash>.css) that 404 after redeploys, and root
    // elements often carry inline overflow:hidden waiting for JS hydration.
    // Force visibility and scroll, then inject a minimal readable baseline so
    // the DOM structure is inspectable even when external stylesheets fail.
    $inject .= '<style id="ttb-preview-reset">'
        // Force the page to be visible and scrollable
        . 'html,body{overflow:auto!important;height:auto!important;visibility:visible!important;display:block!important}'
        . 'body{font-family:system-ui,sans-serif;color:#111;background:#fff;margin:0;padding:8px}'
        . '*{box-sizing:border-box}'
        . 'table{border-collapse:collapse}'
        . 'td,th{padding:4px 8px;border:1px solid #ddd;vertical-align:top}'
        . '[hidden],[inert]{display:revert!important}'
        // Reveal content stuck collapsed/transparent by JS-driven animations.
        // The end-anchor variants ($=) avoid matching "opacity: 0.5" etc.
        . '[style*="max-height: 0px"],[style*="max-height:0px"],[style$="max-height: 0"],[style$="max-height:0"]{max-height:none!important}'
        . '[style*="opacity: 0;"],[style*="opacity:0;"],[style$="opacity: 0"],[style$="opacity:0"]{opacity:1!important}'
        // Tailwind-utility fallback (only matches Next.js/SPA snapshots — SAMO HTML has 0 of these)
        . '.flex{display:flex;flex-wrap:wrap;gap:4px}'
        . '.flex-col{flex-direction:column}.flex-row{flex-direction:row}'
        . '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:4px}'
        . '.hidden{display:none}.block{display:block}.inline-block{display:inline-block}'
        . '.relative{position:relative}'
        . '[class*=" w-full"],[class^="w-full"]{width:100%}'
        . '[class*=" p-"],[class^="p-"]{padding:8px}'
        . '[class*=" m-"],[class^="m-"]{margin:4px}'
        . '[class*=" px-"],[class^="px-"]{padding-left:8px;padding-right:8px}'
        . '[class*=" py-"],[class^="py-"]{padding-top:4px;padding-bottom:4px}'
        . '[class*=" mt-"],[class^="mt-"]{margin-top:4px}'
        . '[class*=" mb-"],[class^="mb-"]{margin-bottom:4px}'
        . '[class*=" gap-"],[class^="gap-"]{gap:4px}'
        . '[class*=" rounded"],[class^="rounded"]{border-radius:4px}'
        . '[class*=" border"],[class^="border"]{border:1px solid #e5e5e5}'
        . '[class*=" bg-white"],[class^="bg-white"]{background:#fff}'
        . '[class*=" bg-fog"],[class^="bg-fog"]{background:#f5f5f7}'
        . '[class*=" shadow"],[class^="shadow"]{box-shadow:0 1px 2px rgba(0,0,0,.08)}'
        . '</style>';
    $html   = preg_replace('/(<head\b[^>]*>)/i', '$1' . $inject, $report->html, 1);
    // Strip scripts and meta refresh — preview only needs static HTML/CSS
    $html   = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    $html   = preg_replace('/<script\b[^>]*\/>/i', '', $html);
    $html   = preg_replace('/<meta[^>]+http-equiv=["\']?refresh["\']?[^>]*>/i', '', $html);
    // Strip <link rel="preload|modulepreload|prefetch|manifest"> — load hints
    // for hashed JS bundles that won't run anyway (scripts already stripped),
    // and PWA manifests that trip CORS on cross-origin previews.
    // <link rel="stylesheet"> is kept: public-asset sites (SAMO) load fine
    // cross-origin; SPA snapshots fall back to the reset <style> above.
    $html   = preg_replace('/<link\b[^>]*\brel=["\']?(?:preload|modulepreload|prefetch|manifest)["\']?[^>]*>/i', '', $html);
    // Strip inline style on root <html> (Next.js apps set CSS vars +
    // overflow:hidden there, waiting for hydration that never happens here)
    $html   = preg_replace('/(<html\b[^>]*)\sstyle="[^"]*"/i', '$1', $html);

    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
})->middleware(['web', \Filament\Http\Middleware\Authenticate::class])->name('admin.extension.report.html');
