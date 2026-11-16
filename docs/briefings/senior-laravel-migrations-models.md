# Briefing — Senior Laravel Developer

**De:** Cérebro DockPilot  
**Para:** Agente Senior Laravel Developer  
**Status:** CONCLUÍDO — migrations + models no código; `php artisan migrate` limpo.  
**Não fazer nesta entrega:** Filament resources, Services de IA, Jobs de Drive, seeders complexos (exceto se útil para smoke test mínimo).

## Fontes da verdade

1. `docs/architecture/03-banco-mvp.md`
2. `docs/architecture/02-modelagem-regras.md`
3. `docs/architecture/05-contrato-ia-json.md` (enums/status alinhados)
4. `docs/architecture/06-apis-mvp.md`

## Ambiente

- PHP 8.2+ / Laravel / MySQL
- Queue: `database` (criar tabelas `jobs`, `failed_jobs`, `job_batches` se ainda não existirem)
- Sem Redis, sem dependências que exijam root

## Tarefa

Criar migrations e Eloquent models para o MVP, com isolamento multi-tenant.

### Models

`Company`, `User`, `Client`, `ComexProcess` (tabela `processes` — evitar conflito com reserved word se necessário; nome da tabela: `processes`), `Document`, `Extraction`, `ValidationResult`, `Approval`

> Se `Process` colidir com algo do ecossistema, usar model `ComexProcess` + `protected $table = 'processes'`.

### Colunas e tipos (obrigatório)

#### companies
| coluna | tipo |
|--------|------|
| id | bigint PK |
| name | string |
| slug | string unique |
| settings | json nullable |
| drive_folder_root | string nullable |
| timestamps | |

#### users
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK companies, cascade/restrict |
| name | string |
| email | string unique |
| password | string |
| role | string enum logic: `admin`, `operator` (string + index) |
| timestamps | |
| remember_token | |

#### clients
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| name | string |
| document_number | string nullable index |
| email | string nullable |
| timestamps | |
| deleted_at | softDeletes |

#### processes
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| client_id | FK clients |
| reference | string |
| type | string: `import`, `export` |
| status | string: `draft`, `documents_pending`, `extracting`, `review`, `approved`, `ready_for_siscomex` |
| notes | text nullable |
| timestamps | |
| UNIQUE(company_id, reference) | |

#### documents
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| process_id | FK processes |
| type | string: `commercial_invoice`, `packing_list`, `bill_of_lading`, `air_waybill` |
| original_filename | string |
| storage_path | string |
| mime | string |
| checksum | string nullable |
| status | string: `uploaded`, `stored`, `queued_ai`, `extracted`, `validation_done`, `approved`, `needs_fix` |
| drive_file_id | string nullable |
| drive_status | string: `drive_pending`, `drive_synced`, `drive_failed` nullable/default `drive_pending` |
| uploaded_by | FK users nullable |
| timestamps | |
| INDEX(company_id, process_id, type) | |

#### extractions
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| document_id | FK documents |
| version | unsigned integer |
| model | string |
| prompt_version | string |
| payload_json | json |
| confidence_json | json nullable |
| status | string: `pending`, `succeeded`, `failed` |
| raw_response_meta | json nullable |
| timestamps | |
| UNIQUE(document_id, version) | |

#### validation_results
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| process_id | FK processes |
| code | string |
| severity | string: `fail`, `warn`, `info` |
| message | text |
| sources_json | json nullable |
| resolved_at | timestamp nullable |
| timestamps | |
| INDEX(company_id, process_id, severity) | |

#### approvals
| coluna | tipo |
|--------|------|
| id | bigint PK |
| company_id | FK |
| process_id | FK processes |
| approved_by | FK users |
| approved_payload_json | json |
| approved_at | timestamp |
| timestamps opcional ou só approved_at | |
| UNIQUE(process_id) no MVP (uma aprovação ativa) | |

## Isolamento tenant (obrigatório nesta entrega)

1. Trait `BelongsToCompany` (ou equivalente) com:
   - `boot` Global Scope filtrando `company_id`
   - `creating` preenchendo `company_id` do contexto atual quando ausente
2. Helper/contexto `CurrentCompany` (ou usar `auth()->user()->company_id`)
3. Relacionamentos Eloquent definidos nos dois sentidos relevantes
4. `User` pertence a `Company`; não vazar queries cross-tenant

**Não** aplicar Global Scope em `Company` de forma que quebre auth/admin futuro — apenas models filhos.

## Enums

Preferir PHP 8.2 backed enums em `App\Enums\...` espelhando os strings acima (`ProcessStatus`, `DocumentType`, `DocumentStatus`, `DriveStatus`, `UserRole`, `ValidationSeverity`, `ProcessType`, `ExtractionStatus`).

Casts nos models para enums + `array`/`AsArrayObject` para JSONs.

## Fora de escopo agora

- Filament
- Controllers de API
- Chamadas OpenAI
- Google Drive
- Policies avançadas (pode deixar TODO; no mínimo company_id nas FKs)

## Critério de pronto

- [ ] `php artisan migrate` limpo em MySQL
- [ ] Models + relations + casts + tenant scope
- [ ] Enums alinhados à spec
- [ ] Nenhuma tabela de negócio sem `company_id` (exceto `companies`)

## Próximo handoff (não fazer ainda)

Services + Jobs conforme `06-apis-mvp.md` e contrato `05-contrato-ia-json.md`.
