# Modelagem e Regras de Negócio (rascunho para validação)

## 1. Entidades principais

| Entidade | Papel |
|----------|--------|
| `Company` | Tenant (despachante) |
| `User` | Operador/admin do despachante (`company_id`) |
| `Client` | Cliente do despachante (importador/exportador) |
| `Process` | Processo Comex (import/export) ligado a um `Client` |
| `Document` | Arquivo PDF + tipo + status de processamento |
| `Extraction` | JSON estruturado retornado pela IA + metadados |
| `ValidationResult` | Achados de validação cruzada (pass/fail/warn) |
| `Approval` | Snapshot aprovado pelo operador |
| `DriveSyncJob` / status no Document | Estado da cópia no Google Drive |

## 2. Tipos de documento (MVP)

- `commercial_invoice`
- `packing_list`
- `bill_of_lading` (BL)
- `air_waybill` (AWB)

## 3. Estados do processo (proposta)

`draft` → `documents_pending` → `extracting` → `review` → `approved` → `ready_for_siscomex` (futuro: `transmitted`)

## 4. Estados do documento

`uploaded` → `stored` → `queued_ai` → `extracted` → `validation_done` → `approved` | `needs_fix`  
Paralelo: `drive_pending` → `drive_synced` | `drive_failed`

## 5. Regras de negócio críticas

1. Documento e processo **sempre** pertencem a uma `company_id`; cliente também.
2. Extração IA **nunca** sobrescreve um `Approval` já confirmado sem nova revisão.
3. Aprovação de 1 clique só libera se não houver validação cruzada em status `fail` (warn pode exigir confirmação explícita).
4. Arquivo original é imutável após upload; correções do operador ficam no snapshot aprovado / campos revisados.
5. Reprocessar IA gera nova `Extraction` versionada; não apaga histórico.
6. Sync Drive é best-effort assíncrono: falha não bloqueia revisão humana (alerta operacional).

## 6. Validação cruzada MVP (exemplos)

| Regra | Fontes | Severidade |
|-------|--------|------------|
| Peso bruto BL/AWB ≈ Packing List | BL/AWB × PL | fail se Δ > tolerância |
| Quantidade volumes | BL/AWB × PL | fail |
| Consistência exportador/importador | Invoice × BL | warn/fail |
| Moeda e totais Invoice | Invoice interna | warn |

Tolerâncias numéricas e campos exatos: a definir com o negócio na próxima rodada.
