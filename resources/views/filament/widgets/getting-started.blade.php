<div class="dp-start fi-wi-widget rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
    <div class="dp-start__hero px-6 py-5 md:px-8 md:py-6">
        <p class="text-sm font-medium tracking-wide uppercase text-teal-100/90 mb-1">Copilot de Comércio Exterior</p>
        <h2 class="text-2xl md:text-3xl font-semibold text-white tracking-tight">Por onde começar</h2>
        <p class="mt-2 max-w-2xl text-teal-50/90 text-sm md:text-base leading-relaxed">
            Cadastre os clientes do despachante, crie usuários deles para o portal, abra o processo, envie os PDFs e revise o que a IA pré-preencheu — sem redigitar.
        </p>
        <p class="mt-3 text-xs text-teal-100/80">
            Portal do cliente: <span class="font-semibold text-white">/portal</span>
            · equipe do despachante: <span class="font-semibold text-white">/admin</span>
        </p>
    </div>

    <div class="grid gap-4 p-6 md:grid-cols-4 md:gap-5">
        <a href="{{ $clientsUrl }}" class="dp-start__card group">
            <span class="dp-start__step">1</span>
            <h3 class="font-semibold text-gray-900 dark:text-white">Cadastrar cliente</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Importador ou exportador atendido pela sua empresa.</p>
            <span class="dp-start__link">Novo cliente →</span>
        </a>

        <a href="{{ $processesUrl }}" class="dp-start__card group">
            <span class="dp-start__step">2</span>
            <h3 class="font-semibold text-gray-900 dark:text-white">Abrir processo</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Referência de importação ou exportação vinculada ao cliente.</p>
            <span class="dp-start__link">Novo processo →</span>
        </a>

        <a href="{{ $processesIndexUrl }}" class="dp-start__card group">
            <span class="dp-start__step">3</span>
            <h3 class="font-semibold text-gray-900 dark:text-white">Enviar documentos</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Upload dos PDFs no processo. A IA extrai em seguida.</p>
            <span class="dp-start__link">Ver processos →</span>
        </a>

        <a href="{{ $reviewUrl }}" class="dp-start__card group">
            <span class="dp-start__step">4</span>
            <h3 class="font-semibold text-gray-900 dark:text-white">Revisar e aprovar</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Conferência humana líquida: ajustar só o necessário e aprovar.</p>
            <span class="dp-start__link">Fila de revisão →</span>
        </a>
    </div>

    <div class="border-t border-gray-100 px-6 py-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300 md:px-8">
        Dica: em <strong>Operação → Caixa de entrada</strong> use <em>Simular e-mail demo</em> para abrir um processo automaticamente com Invoice, Packing List e BL.
    </div>
</div>
