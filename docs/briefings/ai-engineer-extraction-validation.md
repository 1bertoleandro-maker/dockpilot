# Briefing — AI Engineer

**De:** Cérebro DockPilot  
**Para:** Agente AI Engineer (com apoio do Senior Laravel se precisar de wiring)  
**Status:** CONCLUÍDO — Services, Jobs, fake driver e commands no código.  
**Fora desta entrega:** Filament UI, Google Drive real, PUCOMEX, intake por e-mail, `ProcessApprovalService` (próximo handoff com UI).

## Fontes da verdade

1. `docs/architecture/05-contrato-ia-json.md` — contrato `ai.extract.v1` (obrigatório)
2. `docs/architecture/06-apis-mvp.md` — Services/Jobs
3. `docs/architecture/02-modelagem-regras.md` — status e regras
4. Models existentes: `Document`, `Extraction`, `ValidationResult`, `ComexProcess`, `Company`
5. Enums: `DocumentType`, `DocumentStatus`, `ExtractionStatus`, `ValidationSeverity`, `ProcessStatus`

## Objetivo

Fechar o miolo da esteira: **PDF → LLM Structured Outputs → Extraction versionada → validação cruzada → processo em `review`**.

---

## Escopo obrigatório

### 1. Config

- `OPENAI_API_KEY`, `OPENAI_MODEL` (default `gpt-4o`) em `.env` + `config/services.php` (ou `config/openai.php` enxuto)
- Sem pacotes que exijam Redis/root. Preferir HTTP client Laravel (`Http::`) ou SDK oficial leve se já couber no Composer KingHost-friendly
- `prompt_version` constante: `extract.v1`

### 2. JSON Schemas tipados

Criar schemas PHP/array equivalentes à seção 3 de `05-contrato-ia-json.md` por `DocumentType`:

- `commercial_invoice`
- `packing_list`
- `bill_of_lading`
- `air_waybill`

Usar Structured Outputs / `response_format` com JSON Schema (strict quando possível).  
Normalizar a resposta no **envelope** da seção 2 do contrato antes de persistir.

### 3. `App\Services\Ai\DocumentExtractionService`

Método principal sugerido: `extract(Document $document): Extraction`

Fluxo:
1. Validar `company_id` do documento (Jobs passam `company_id` e setam `CurrentCompany`)
2. Garantir arquivo legível em `storage_path` (Storage disk configurado)
3. Marcar documento `queued_ai` se ainda não estiver
4. Criar `Extraction` com `version = max+1`, status `pending`
5. Enviar PDF (ou conteúdo) ao modelo com schema do `document.type`
6. Validar envelope (`contract_version`, `document_type`, `data` com chaves obrigatórias)
7. Persistir:
   - `payload_json` = envelope completo
   - `confidence_json` = `field_confidence`
   - `raw_response_meta` = ids/tokens/modelo úteis para debug (sem vazar chave)
   - `model`, `prompt_version`
   - status `succeeded` ou `failed`
8. Se sucesso: documento → `extracted`
9. Se falha: documento → `needs_fix`; Extraction `failed`; exceção logada; Job pode retry

**Regras:**
- Nunca apagar extractions anteriores
- Nunca alterar `approvals` existentes
- Controllers continuam magras — zero lógica de prompt em Controller

### 4. `App\Services\Comex\CrossValidationService`

Método: `validate(ComexProcess $process): Collection` (de `ValidationResult`)

Fluxo:
1. Carregar última Extraction `succeeded` por documento do processo (por tipo)
2. Apagar `validation_results` do processo com `resolved_at IS NULL` (recria rodada)
3. Aplicar regras MVP (contrato §4):

| code | severity | lógica |
|------|----------|--------|
| `MISSING_DOCUMENT` | fail | Falta Invoice **ou** Packing List **ou** (BL **e** AWB ambos ausentes) |
| `GROSS_WEIGHT_MISMATCH` | fail | Comparar `gross_weight_kg` de PL vs BL ou AWB; Δ% > tolerância |
| `PACKAGE_COUNT_MISMATCH` | fail | `total_packages` PL vs BL/AWB |
| `PARTY_NAME_MISMATCH` | warn | Normalizar nomes (uppercase, trim, remove pontuação básica) Invoice exporter/importer vs shipper/consignee |
| `INVOICE_TOTAL_INCONSISTENT` | warn | Soma `items[].total_price` vs `total_amount`; Δ% > 0.5% |

Tolerâncias em `company.settings['validation']`:
```json
{
  "gross_weight_tolerance_pct": 1.0,
  "invoice_total_tolerance_pct": 0.5
}
```
Defaults acima se ausente.

4. Gravar `sources_json` com ids/tipos/valores comparados
5. Atualizar documentos envolvidos → `validation_done` (quando tinham `extracted`)
6. Se processo não estiver `approved`: status → `review` (MVP: após validação, operador revisa)

### 5. Jobs (queue `database`)

#### `App\Jobs\ExtractDocumentJob`
- Payload: `int $companyId`, `int $documentId`
- `CurrentCompany::set($companyId)` no início; `clear` no finally
- Chama `DocumentExtractionService`
- Em sucesso: despacha `RunProcessValidationJob` para o `process_id` do documento
- Idempotência: sempre nova version; ok reprocessar
- `tries`: 3; backoff razoável; falha final deixa `needs_fix`

#### `App\Jobs\RunProcessValidationJob`
- Payload: `int $companyId`, `int $processId`
- Chama `CrossValidationService`
- Não bloqueia por Drive

### 6. Ponto de entrada mínimo (sem Filament ainda)

Uma das opções (escolha a mais simples):
- Artisan command: `php artisan dockpilot:extract {documentId}` + `dockpilot:validate {processId}`
- **ou** método estático/helper usado depois pelo `DocumentIntakeService`

Não é obrigatório endpoint HTTP nesta sprint.

### 7. Testes / smoke (mínimo)

- Unit: normalização de nomes + cálculo de tolerância de peso/total (sem chamar OpenAI)
- Feature opcional: CrossValidation com Extractions fake em memória/DB
- Modo **fake/driver** `AI_DRIVER=fake` que devolve envelope válido a partir de fixture JSON (para KingHost/dev sem gastar token)

---

## Estrutura de pastas sugerida

```
app/Services/Ai/DocumentExtractionService.php
app/Services/Ai/OpenAiClient.php          (ou Http wrapper)
app/Services/Ai/Schemas/…                 (schemas por tipo)
app/Services/Ai/FakeExtractionDriver.php
app/Services/Comex/CrossValidationService.php
app/Jobs/ExtractDocumentJob.php
app/Jobs/RunProcessValidationJob.php
app/Console/Commands/ExtractDocumentCommand.php
app/Console/Commands/ValidateProcessCommand.php
tests/Unit/Comex/CrossValidationTest.php
```

---

## Critério de pronto

- [ ] `AI_DRIVER=fake` extrai e persiste `Extraction` versionada sem OpenAI
- [ ] Com `OPENAI_API_KEY` real, PDF de amostra gera envelope `ai.extract.v1`
- [ ] `CrossValidationService` grava fails/warns corretos
- [ ] Jobs rodam com `queue:database` (`php artisan queue:work`)
- [ ] Tenant: Job sem `CurrentCompany` correto não vaza dados
- [ ] Zero UI Filament nesta entrega

## Fora de escopo (não fazer)

- `DocumentIntakeService` / upload completo (pode stub se precisar)
- `DriveSyncService` / `SyncDocumentToDriveJob`
- `ProcessApprovalService`
- Resources Filament
- Integração Siscomex

## Prompt curto para colar no agente

```
Você é o AI Engineer do DockPilot.
Execute docs/briefings/ai-engineer-extraction-validation.md
Fontes: docs/architecture/05-contrato-ia-json.md e 06-apis-mvp.md
Entregue: DocumentExtractionService + CrossValidationService + ExtractDocumentJob + RunProcessValidationJob + AI_DRIVER=fake + commands artisan.
Não faça Filament, Drive nem Approval.
KingHost: PHP 8.2, MySQL, queue database.
```
