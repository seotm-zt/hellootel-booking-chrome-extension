@php
    $reportId = $getState();
@endphp

<div class="relative w-full overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900">
    <div class="flex items-center gap-2 border-b border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" />
        </svg>
        <span>Captured page — rendered in sandboxed frame</span>
        <a
            href="{{ route('admin.extension.report.html', $reportId) }}"
            target="_blank"
            class="ml-auto inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-900/50 transition-colors"
        >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            Open HTML
        </a>
    </div>
    <iframe
        src="{{ route('admin.extension.report.html', $reportId) }}"
        class="w-full"
        style="height: 72vh; border: none; display: block;"
        sandbox="allow-same-origin allow-scripts allow-forms"
        loading="lazy"
    ></iframe>
</div>
