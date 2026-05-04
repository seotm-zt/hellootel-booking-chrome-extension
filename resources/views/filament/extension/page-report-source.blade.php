@php
    $html = $getState();
    $size = strlen($html ?? '');
    $sizeLabel = $size > 1048576
        ? round($size / 1048576, 1) . ' MB'
        : round($size / 1024, 1) . ' KB';
    $lineCount = substr_count($html ?? '', "\n") + 1;
    $entryId = 'src-' . $getId();
@endphp

<div
    x-data="{ copied: false }"
    class="relative w-full overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
>
    <div class="flex items-center gap-3 border-b border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium text-gray-600 dark:text-gray-300">HTML source</span>
        <span class="text-gray-400">{{ number_format($lineCount) }} lines &middot; {{ $sizeLabel }}</span>
        <button
            type="button"
            x-on:click="navigator.clipboard.writeText(document.getElementById('{{ $entryId }}').value); copied = true; setTimeout(() => copied = false, 2000);"
            class="ml-auto inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
            x-bind:class="copied ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'"
        >
            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
        </button>
    </div>
    <textarea
        id="{{ $entryId }}"
        readonly
        spellcheck="false"
        class="w-full resize-none bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-200 outline-none"
        style="height: 55vh; tab-size: 2;"
    >{{ $html }}</textarea>
</div>
