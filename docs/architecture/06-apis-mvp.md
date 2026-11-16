# APIs internas MVP — APROVADO

APIs HTTP públicas mínimas neste momento. O painel Filament consome Services. Contratos abaixo são a interface entre agentes.

## Services (Application)

| Service | Responsabilidade |
|---------|------------------|
| `DocumentIntakeService` | Recebe upload, grava `documents`, dispara Jobs |
| `DriveSyncService` | Envia arquivo ao Google Drive do tenant (Job) |
| `DocumentExtractionService` | Chama LLM, valida envelope `ai.extract.v1`, persiste `extractions` |
| `CrossValidationService` | Gera `validation_results` do processo |
| `ProcessApprovalService` | Aplica overrides, bloqueia se fail, grava `approvals`, avança status |

## Jobs (database queue)

| Job | Payload mínimo | Idempotência |
|-----|----------------|--------------|
| `SyncDocumentToDriveJob` | `company_id`, `document_id` | Se `drive_status=drive_synced` e `drive_file_id` preenchido → no-op |
| `ExtractDocumentJob` | `company_id`, `document_id` | Nova `Extraction` com `version+1`; não apaga anteriores |
| `RunProcessValidationJob` | `company_id`, `process_id` | Substitui results abertos do processo (ou marca resolved e recria — preferir delete dos não-resolved + insert) |

## Endpoints HTTP (MVP enxuto)

Filament cobre CRUD. Se houver upload externo do cliente:

### `POST /api/v1/processes/{process}/documents`
Auth: Sanctum/token do tenant  
Body: multipart `file` + `type`  
Resposta 201: documento + status `uploaded`

### `GET /api/v1/processes/{process}`
Retorna processo + documentos + status (scoped por company do token)

Demais mutações de negócio: Filament Actions → Services.

## Códigos de erro de domínio

| code | HTTP | Quando |
|------|------|--------|
| `TENANT_MISMATCH` | 403 | Recurso de outra company |
| `APPROVAL_BLOCKED_BY_FAIL` | 422 | Aprovar com validation fail aberto |
| `PROCESS_ALREADY_APPROVED` | 409 | Reaprovação sem fluxo de reabertura |
| `INVALID_DOCUMENT_TYPE` | 422 | type fora do enum MVP |
