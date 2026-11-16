# Banco de Dados — MVP — APROVADO

> Status: aprovado pelo produto (Cérebro). Fonte da verdade para migrations.  
> MySQL clássico · sem Redis · soft deletes onde fizer sentido · **toda tabela de negócio com `company_id`** (exceto `companies` e pivots de sistema).

## Tabelas

### companies
- id, name, slug, settings (JSON), drive_folder_root, created_at, updated_at

### users
- id, company_id, name, email, password, role (`admin`|`operator`), timestamps

### clients
- id, company_id, name, document_number (CNPJ/CPF), email, timestamps, deleted_at

### processes
- id, company_id, client_id, reference (único por company), type (`import`|`export`), status, notes, timestamps

### documents
- id, company_id, process_id, type, original_filename, storage_path, mime, checksum, status, drive_file_id nullable, drive_status, uploaded_by, timestamps

### extractions
- id, company_id, document_id, version, model, prompt_version, payload_json, confidence_json nullable, status, raw_response_meta, timestamps

### validation_results
- id, company_id, process_id, code, severity (`fail`|`warn`|`info`), message, sources_json, resolved_at nullable, timestamps

### approvals
- id, company_id, process_id, approved_by, approved_payload_json, approved_at

### jobs / failed_jobs
- padrão Laravel (database queue)

## Índices essenciais

- `(company_id, …)` em todas as FKs de negócio
- `processes (company_id, reference)` UNIQUE
- `documents (company_id, process_id, type)`
- `extractions (document_id, version)` UNIQUE

## Isolamento

Global Scope Eloquent em models tenant-aware + middleware que define a company do usuário autenticado. Jobs recebem `company_id` explicitamente no payload.
