<x-filament-panels::page>
    <div class="space-y-5">
        {{-- Validation strip --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Validação cruzada</h3>
                <span class="text-xs text-gray-500">{{ $this->record->status->label() }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @forelse ($this->openValidations() as $result)
                    <span @class([
                        'inline-flex max-w-full items-start rounded-lg px-2.5 py-1.5 text-xs font-medium ring-1 ring-inset',
                        'bg-red-50 text-red-800 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300' => $result->severity->value === 'fail',
                        'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-200' => $result->severity->value === 'warn',
                        'bg-slate-50 text-slate-700 ring-slate-500/20 dark:bg-white/5 dark:text-slate-300' => $result->severity->value === 'info',
                    ])>
                        <span class="mr-1.5 shrink-0 rounded px-1 py-0.5 text-[10px] font-bold uppercase tracking-wide
                            {{ $result->severity->value === 'fail' ? 'bg-red-600 text-white' : ($result->severity->value === 'warn' ? 'bg-amber-500 text-white' : 'bg-slate-500 text-white') }}">
                            {{ $result->severity->value === 'fail' ? 'Bloqueio' : ($result->severity->value === 'warn' ? 'Alerta' : 'Info') }}
                        </span>
                        <span>
                            <strong class="font-semibold">{{ $result->code }}</strong>
                            <span class="opacity-90"> — {{ $result->message }}</span>
                        </span>
                    </span>
                @empty
                    <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300">
                        Sem bloqueios ou alertas abertos
                    </span>
                @endforelse
            </div>
        </div>

        {{-- Document tabs --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($this->documents() as $document)
                <button
                    type="button"
                    wire:click="selectDocument({{ $document->id }})"
                    @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-teal-700 text-white shadow-sm' => $this->documentId === $document->id,
                        'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10' => $this->documentId !== $document->id,
                    ])
                >
                    {{ $document->type->label() }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 dark:border-white/10 dark:text-gray-100">
                    Documento original
                    @if ($this->selectedDocument())
                        <span class="ml-1 font-normal text-gray-400">· {{ $this->selectedDocument()->original_filename }}</span>
                    @endif
                </div>
                <div class="h-[70vh] bg-slate-100 dark:bg-gray-950">
                    @if ($this->documentPreviewUrl())
                        <iframe
                            src="{{ $this->documentPreviewUrl() }}"
                            class="h-full w-full"
                            title="Pré-visualização do PDF"
                        ></iframe>
                    @else
                        <div class="flex h-full items-center justify-center px-6 text-center text-sm text-gray-500">
                            Selecione um documento com arquivo armazenado.
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-800 dark:border-white/10 dark:text-gray-100">
                    Dados pré-preenchidos pela IA
                    <span class="ml-1 font-normal text-gray-400">· edite só o que estiver errado</span>
                </div>

                <div class="max-h-[70vh] space-y-3 overflow-y-auto p-4">
                    @forelse ($this->fieldValues as $key => $value)
                        <div>
                            <label class="mb-1 flex items-center justify-between gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                                <span class="truncate" title="{{ $key }}">{{ str_replace('.', ' › ', $key) }}</span>
                                @if ($this->isLowConfidence($key))
                                    <span class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-400/20 dark:text-amber-200">
                                        baixa confiança
                                        @if ($this->fieldConfidence[$key] !== null)
                                            ({{ number_format($this->fieldConfidence[$key] * 100, 0) }}%)
                                        @endif
                                    </span>
                                @elseif ($this->fieldConfidence[$key] !== null)
                                    <span class="shrink-0 text-[10px] text-gray-400">
                                        {{ number_format($this->fieldConfidence[$key] * 100, 0) }}%
                                    </span>
                                @endif
                            </label>
                            <input
                                type="text"
                                wire:model="fieldValues.{{ $key }}"
                                @class([
                                    'w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-600',
                                    'border-amber-400 bg-amber-50 dark:border-amber-500/50 dark:bg-amber-500/10' => $this->isLowConfidence($key),
                                    'border-gray-300 bg-white dark:border-white/10 dark:bg-white/5' => ! $this->isLowConfidence($key),
                                ])
                            />
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-white/10">
                            <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma extração concluída para este documento.</p>
                            <p class="mt-1 text-xs text-gray-400">Volte ao processo e use “Reprocessar IA”.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-10 flex flex-col gap-3 rounded-xl border border-teal-800/20 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-teal-400/20 dark:bg-gray-900/95 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
                @if ($this->hasOpenFails())
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                        Aprovação bloqueada — resolva os bloqueios (docs faltando ou divergências).
                    </p>
                @elseif ($this->hasOpenWarns())
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model.live="acknowledgeWarns" class="rounded border-gray-300 text-teal-700 focus:ring-teal-600" />
                        Estou ciente dos alertas e quero aprovar mesmo assim
                    </label>
                @else
                    <p class="text-sm text-teal-800 dark:text-teal-300">Checklist ok — pode aprovar com um clique.</p>
                @endif
            </div>

            <button
                type="button"
                wire:click="approve"
                wire:loading.attr="disabled"
                @disabled(! $this->canApprove())
                @class([
                    'inline-flex min-w-[140px] items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition',
                    'bg-teal-700 hover:bg-teal-600' => $this->canApprove(),
                    'cursor-not-allowed bg-gray-300 dark:bg-gray-700' => ! $this->canApprove(),
                ])
            >
                <span wire:loading.remove wire:target="approve">Aprovar processo</span>
                <span wire:loading wire:target="approve">Aprovando…</span>
            </button>
        </div>
    </div>
</x-filament-panels::page>
