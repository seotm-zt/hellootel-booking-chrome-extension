<?php

use Illuminate\Support\Facades\Route;

// Redirect root to admin panel
Route::get('/', fn () => redirect('/admin'));

// Page Report HTML preview (served inside Filament admin iframe)
Route::get('/admin/extension/page-reports/{id}/html', function (int $id) {
    $report = \App\Models\ExtensionPageReport::findOrFail($id);
    $meta   = '<meta name="ttb-preview-url" content="' . e($report->url) . '">';
    $html   = preg_replace('/(<head\b[^>]*>)/i', '$1' . $meta, $report->html, 1);
    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
})->middleware(['web', 'auth'])->name('admin.extension.report.html');
