# Briefing — Filament / UI Operador (MVP)

**De:** Cérebro DockPilot  
**Para:** Senior Laravel + UI (FilamentPHP)  
**Status:** CONCLUÍDO — Admin + Portal do cliente (`/portal`).  
**Teste liberado:**  
- Despachante: `http://localhost:8080/dockpilot/admin`  
- Cliente: `http://localhost:8080/dockpilot/portal`  
**URL local:** `http://localhost:8080/dockpilot`  
**Painel alvo:** `http://localhost:8080/dockpilot/admin`

## Pré-requisitos (já OK)

- Migrations/models + tenant scope
- AI Engineer: `DocumentExtractionService`, `CrossValidationService`, `ExtractDocumentJob`, `RunProcessValidationJob`, `AI_DRIVER=fake`
- Alias Apache `/dockpilot` → `public` (reiniciar Apache se alterar vhost)
- `APP_URL=http://localhost:8080/dockpilot` + `RewriteBase /dockpilot/`

## Fontes da verdade

1. `docs/architecture/04-ux-mvp.md` — UX
2. `docs/architecture/05-contrato-ia-json.md` — envelope + `approval.v1`
3. `docs/architecture/06-apis-mvp.md` — Services
4. Models/Enums existentes em `app/`

---

## Escopo desta sprint

### A) Infra Filament (subdir)

1. Instalar Filament compatível com **Laravel 12 / PHP 8.2** (Filament **v4**; não forçar v3)
2. Panel path: `admin` (URL final: `/dockpilot/admin`)
3. Auth: model `User` (já tem `company_id` + `role`)
4. Garantir assets/URLs corretas no subdirectory (não quebrar `/gotreino` nem Opaporeto)
5. Middleware do panel deve setar `CurrentCompany` a partir do user logado (já existe `SetCurrentCompany` no grupo `web` — confirmar no panel)

### B) Services que faltam (obrigatório para a UI)

#### `App\Services\Documents\DocumentIntakeService`
- Input: `ComexProcess`, uploaded file, `DocumentType`, `User` uploader
- Salva em disk (`local` ou `public`) path: `companies/{company_id}/processes/{process_id}/...`
- Cria `Document` (`uploaded` → `stored`), checksum
- Dispara `ExtractDocumentJob` (e stub opcional `SyncDocumentToDriveJob` **só se já existir**; senão TODO/no-op)
- Atualiza processo para `extracting` / `documents_pending` conforme fizer sentido

#### `App\Services\Comex\ProcessApprovalService`
- Input: processo, user, `operator_overrides` (por document_id), warns acknowledged
- Bloqueia se houver `validation_results` `fail` com `resolved_at` null → exceção/`APPROVAL_BLOCKED_BY_FAIL`
- Bloqueia se já aprovado → `PROCESS_ALREADY_APPROVED`
- Monta snapshot `approval.v1` (contrato §5)
- Grava `approvals`; processo → `approved`; docs relevantes → `approved`
- Controllers magras: Action Filament chama o Service

### C) Seeder de demo (obrigatório para teste humano)

`DatabaseSeeder` / `DemoSeeder`:
- 1 `Company` (ex.: slug `demo-despachante`)
- 1 `User` admin: email `admin@dockpilot.test` / senha `password`
- 1 `Client`
- Opcional: 1 processo vazio pronto para upload

### D) Resources / páginas Filament

| Item | Detalhe |
|------|---------|
| `ClientResource` | CRUD: name, document_number, email |
| `ComexProcessResource` | Lista + create: client, reference, type, notes; coluna status |
| Upload no processo | RelationManager ou Action: file + `DocumentType`; chama Intake |
| **Fila de revisão** | Página/custom Resource filtrando `status = review` (e talvez `extracting` como “em processamento”) |
| **Detalhe revisão** | Página custom (prioridade UX): ver abaixo |

### E) Tela de revisão (UX crítica)

Layout (pode ser Livewire/Filament custom page):

1. **Topo:** badges de `validation_results` — `fail` vermelho, `warn` amarelo
2. **Esquerda:** viewer PDF do documento selecionado (tabs/select por documento do processo)
3. **Direita:** campos da última Extraction `succeeded` (`payload_json.data`), editáveis (overrides)
4. Highlight baixa confiança (`confidence_json` < 0.8 ou config)
5. CTA único primário: **Aprovar**
   - Desabilitado se existir fail aberto
   - Warns: checkbox “ciente” ou auto-ack no approve com confirmação
6. Toast sucesso → volta à fila

Princípio: operador **não digita do zero**; só corrige e aprova.

### F) Queue no ambiente local

Documentar no README curto do briefing:
- Após upload, rodar `php artisan queue:work` (database) **ou** `QUEUE_CONNECTION=sync` no `.env` local para teste imediato sem worker
- Preferência MVP local: `sync` no `.env` de desenvolvimento se facilitar o teste do usuário (comentar no PR/resumo)

---

## Fora de escopo

- Google Drive real / Outlook IMAP
- PUCOMEX
- Portal self-service do cliente final
- Dashboard analítico
- Multi-panel / white-label visual pesado

---

## Critério de pronto (usuário consegue testar)

- [ ] `http://localhost:8080/dockpilot/admin` abre login Filament
- [ ] Login com `admin@dockpilot.test` / `password`
- [ ] Criar cliente + processo
- [ ] Upload PDF (qualquer) com tipo; com `AI_DRIVER=fake` gera extraction
- [ ] Processo aparece na fila de revisão (após validate job)
- [ ] Abrir revisão, ver campos fake, aprovar (ou ver fail bloqueando)
- [ ] GoTreino (`/gotreino`) e Opaporeto (`/`) continuam OK

## Prompt curto para colar no agente

```
Você é o Senior Laravel + Filament do DockPilot.

Execute estritamente: docs/briefings/uiux-filament-painel.md
UX: docs/architecture/04-ux-mvp.md
Contratos: docs/architecture/05-contrato-ia-json.md e 06-apis-mvp.md

Entrega:
1) Instalar Filament panel em /admin (URL final http://localhost:8080/dockpilot/admin)
2) DocumentIntakeService + ProcessApprovalService
3) Seeder demo (admin@dockpilot.test / password)
4) Resources: Clients, Processes + upload docs
5) Fila de revisão + tela PDF|campos + Aprovar 1 clique
6) Respeitar company_id / CurrentCompany; Controllers magras
7) Não quebrar Alias subdirectory; não implementar Drive/PUCOMEX/e-mail

KingHost-friendly: PHP 8.2, MySQL, queue database (sync ok no .env local para demo).
Ao terminar, diga a URL exata de login e o passo a passo de teste.
```
