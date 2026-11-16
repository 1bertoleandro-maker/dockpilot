<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-teal-800/15 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Como funciona</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>PDFs entram na pasta local <code class="rounded bg-gray-100 px-1 text-xs dark:bg-white/10">storage/app/inbound/{empresa}</code> (hoje) ou via IMAP/Outlook (em seguida).</li>
                <li>O sistema abre o <strong>processo</strong>, classifica Invoice / Packing List / BL / AWB pelo nome.</li>
                <li>Dispara extração IA, monta checklist do que falta e prepara caminho da pasta no Drive.</li>
                <li>Use <strong>Simular e-mail demo</strong> para gerar 3 PDFs de exemplo e ver o fluxo completo.</li>
            </ol>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Arquivos pendentes</h3>
                <span class="text-xs text-gray-500">{{ $this->getInboxPath() }}</span>
            </div>

            @php($files = $this->pendingFiles())
            @if (count($files) === 0)
                <p class="text-sm text-gray-500">Nenhum PDF pendente na caixa. Rode a simulação ou copie arquivos para a pasta.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                    @foreach ($files as $file)
                        <li class="py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $file }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-filament-panels::page>
