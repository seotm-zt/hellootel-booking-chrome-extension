<?php

use Illuminate\Support\Facades\Route;

// Redirect root to admin panel
Route::get('/', fn () => redirect('/admin'));

// Page Report HTML preview (served inside Filament admin iframe)
Route::get('/admin/extension/page-reports/{id}/html', function (int $id) {
    $report = \App\Models\ExtensionPageReport::findOrFail($id);

    $inject = '<base href="' . e($report->url) . '"><meta name="ttb-preview-url" content="' . e($report->url) . '">';
    $html   = preg_replace('/(<head\b[^>]*>)/i', '$1' . $inject, $report->html, 1);
    // Strip all scripts and meta refresh — preview only needs static HTML/CSS
    $html   = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    $html   = preg_replace('/<script\b[^>]*\/>/i', '', $html);
    $html   = preg_replace('/<meta[^>]+http-equiv=["\']?refresh["\']?[^>]*>/i', '', $html);

    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
})->middleware(['web', \Filament\Http\Middleware\Authenticate::class])->name('admin.extension.report.html');
