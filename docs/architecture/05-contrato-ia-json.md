# Contrato IA — Structured Outputs (MVP) — APROVADO

Versão do contrato: `ai.extract.v1`  
Modelo alvo: GPT-4o (Structured Outputs / JSON Schema)  
Service dono: `App\Services\Ai\DocumentExtractionService` (AI Engineer + Senior Laravel)

## 1. Request (interno)

```json
{
  "contract_version": "ai.extract.v1",
  "company_id": 1,
  "process_id": 10,
  "document_id": 55,
  "document_type": "commercial_invoice",
  "file": {
    "storage_path": "companies/1/processes/10/...",
    "mime": "application/pdf",
    "checksum": "sha256:..."
  }
}
```

## 2. Envelope de resposta (sempre)

Todo documento devolve o mesmo envelope. O conteúdo tipado vai em `data`.

```json
{
  "contract_version": "ai.extract.v1",
  "document_type": "commercial_invoice",
  "data": {},
  "field_confidence": {
    "invoice_number": 0.96
  },
  "warnings": [
    { "code": "LOW_OCR_QUALITY", "message": "Texto parcialmente ilegível na página 2" }
  ],
  "meta": {
    "model": "gpt-4o",
    "prompt_version": "extract.v1",
    "pages_read": 3
  }
}
```

Regras:
- Campos não encontrados → `null` (nunca omitir chaves obrigatórias do schema do tipo).
- Números como number (não string), datas ISO `YYYY-MM-DD` quando completas; senão `null`.
- `field_confidence`: 0–1 por campo foliar relevante; ausente = tratar como baixa confiança na UI.
- Persistência: envelope completo em `extractions.payload_json`; `field_confidence` também em `confidence_json`.

---

## 3. Schemas por `document_type`

### 3.1 `commercial_invoice`

```json
{
  "invoice_number": "string|null",
  "invoice_date": "YYYY-MM-DD|null",
  "currency": "string|null",
  "total_amount": "number|null",
  "incoterm": "string|null",
  "exporter": {
    "name": "string|null",
    "address": "string|null",
    "tax_id": "string|null",
    "country": "string|null"
  },
  "importer": {
    "name": "string|null",
    "address": "string|null",
    "tax_id": "string|null",
    "country": "string|null"
  },
  "items": [
    {
      "line": "number|null",
      "description": "string|null",
      "ncm": "string|null",
      "quantity": "number|null",
      "unit": "string|null",
      "unit_price": "number|null",
      "total_price": "number|null"
    }
  ]
}
```

### 3.2 `packing_list`

```json
{
  "reference": "string|null",
  "packing_date": "YYYY-MM-DD|null",
  "total_packages": "number|null",
  "gross_weight_kg": "number|null",
  "net_weight_kg": "number|null",
  "volume_cbm": "number|null",
  "exporter_name": "string|null",
  "importer_name": "string|null",
  "items": [
    {
      "description": "string|null",
      "quantity": "number|null",
      "unit": "string|null",
      "net_weight_kg": "number|null",
      "gross_weight_kg": "number|null",
      "packages": "number|null"
    }
  ]
}
```

### 3.3 `bill_of_lading`

```json
{
  "bl_number": "string|null",
  "issue_date": "YYYY-MM-DD|null",
  "shipper_name": "string|null",
  "consignee_name": "string|null",
  "notify_party": "string|null",
  "vessel": "string|null",
  "voyage": "string|null",
  "port_of_loading": "string|null",
  "port_of_discharge": "string|null",
  "total_packages": "number|null",
  "gross_weight_kg": "number|null",
  "measurement_cbm": "number|null",
  "freight_terms": "string|null",
  "container_numbers": ["string"]
}
```

### 3.4 `air_waybill`

```json
{
  "awb_number": "string|null",
  "issue_date": "YYYY-MM-DD|null",
  "shipper_name": "string|null",
  "consignee_name": "string|null",
  "airport_of_departure": "string|null",
  "airport_of_destination": "string|null",
  "total_packages": "number|null",
  "gross_weight_kg": "number|null",
  "chargeable_weight_kg": "number|null",
  "flight_number": "string|null"
}
```

---

## 4. Validação cruzada (pós-extração)

Service: `App\Services\Comex\CrossValidationService`  
Entrada: últimas `extractions` bem-sucedidas por tipo no processo.  
Saída: linhas em `validation_results`.

| code | severity | regra MVP |
|------|----------|-----------|
| `GROSS_WEIGHT_MISMATCH` | fail | \|BL/AWB.gross_weight_kg − PL.gross_weight_kg\| > tolerância (default 1%) |
| `PACKAGE_COUNT_MISMATCH` | fail | BL/AWB.total_packages ≠ PL.total_packages |
| `PARTY_NAME_MISMATCH` | warn | exporter/importer Invoice vs shipper/consignee BL/AWB |
| `INVOICE_TOTAL_INCONSISTENT` | warn | soma items ≠ total_amount (Δ > 0.5%) |
| `MISSING_DOCUMENT` | fail | tipo obrigatório ausente (Invoice + PL + BL ou AWB) |

Tolerância configurável em `companies.settings.validation`.

---

## 5. Payload de aprovação (`approvals.approved_payload_json`)

```json
{
  "contract_version": "approval.v1",
  "process_id": 10,
  "documents": [
    {
      "document_id": 55,
      "document_type": "commercial_invoice",
      "extraction_id": 90,
      "data": {}
    }
  ],
  "operator_overrides": {
    "55": { "total_amount": 12500.50 }
  },
  "validation_snapshot": [
    { "code": "PARTY_NAME_MISMATCH", "severity": "warn", "acknowledged": true }
  ]
}
```

Overrides do operador mesclam sobre `data` da extraction antes de gravar o snapshot.
