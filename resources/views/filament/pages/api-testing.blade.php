<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Hotel ID input --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        ID отеля
                    </label>
                    <input
                        type="text"
                        wire:model.live="hotelId"
                        class="fi-input block w-24 rounded-lg border border-gray-300 shadow-sm text-sm dark:border-white/10 dark:bg-white/5 dark:text-white px-3 py-1.5"
                        placeholder="32"
                    />
                </div>
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        API токен (Basic Auth)
                    </label>
                    <input
                        type="text"
                        wire:model.live="apiToken"
                        class="fi-input block w-full min-w-0 rounded-lg border border-gray-300 shadow-sm text-sm dark:border-white/10 dark:bg-white/5 dark:text-white px-3 py-1.5 font-mono"
                        placeholder="токен"
                    />
                </div>
            </div>
        </div>

        {{-- Endpoint cards --}}
        @foreach($this->getEndpoints() as $key => $endpoint)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                {{-- Header --}}
                <div class="flex items-center justify-between gap-4 p-4 border-b border-gray-100 dark:border-white/10">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $endpoint['label'] }}
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                            {{ $endpoint['url'] }}
                            @if(!empty($endpoint['params']))
                                <span class="text-amber-600 dark:text-amber-400">
                                    &nbsp;+&nbsp;hotel_id={{ $hotelId }}
                                </span>
                            @endif
                        </p>
                    </div>
                    <button
                        wire:click="callApi('{{ $key }}')"
                        wire:loading.attr="disabled"
                        wire:target="callApi('{{ $key }}')"
                        class="fi-btn fi-btn-size-sm fi-btn-color-primary fi-color-primary fi-color-custom inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold shadow-sm bg-amber-600 hover:bg-amber-500 text-white disabled:opacity-50 shrink-0 transition"
                    >
                        <span wire:loading.remove wire:target="callApi('{{ $key }}')">Выполнить</span>
                        <span wire:loading wire:target="callApi('{{ $key }}')">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                {{-- Response --}}
                @if(isset($apiErrors[$key]) && $apiErrors[$key])
                    <div class="p-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/20">
                        Ошибка: {{ $apiErrors[$key] }}
                    </div>
                @elseif(isset($responses[$key]))
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium
                                {{ $responses[$key]['ok'] ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                HTTP {{ $responses[$key]['status'] }}
                            </span>
                            @if(is_array($responses[$key]['body']))
                                @php
                                    $count = isset($responses[$key]['body']['data']) && is_array($responses[$key]['body']['data'])
                                        ? count($responses[$key]['body']['data'])
                                        : (is_array($responses[$key]['body']) ? count($responses[$key]['body']) : null);
                                @endphp
                                @if($count !== null)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $count }} записей</span>
                                @endif
                            @endif
                        </div>
                        <pre class="text-xs bg-gray-50 dark:bg-gray-950 rounded-lg p-3 overflow-auto max-h-96 text-gray-800 dark:text-gray-200 ring-1 ring-gray-200 dark:ring-white/10">{{ is_array($responses[$key]['body']) ? json_encode($responses[$key]['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $responses[$key]['body'] }}</pre>
                    </div>
                @else
                    <div class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 italic">
                        Нажмите «Выполнить» чтобы получить ответ
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
