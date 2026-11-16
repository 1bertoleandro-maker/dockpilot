# DockPilot — Visão de Produto e Arquitetura MVP

## 1. Problema

Despachantes redigitam e conferem manualmente PDFs de Comex (Invoice, Packing List, BL/AWB). Erro humano na triagem chega ao Siscomex.

## 2. Proposta de valor

Copilot que: captura documentos → armazena com redundância → extrai dados via IA → valida cruzado → operador só revisa/aprova no Filament → prepara carga para PUCOMEX (futuro).

## 3. Modelo de negócio

| Conceito | Descrição |
|----------|-----------|
| Tenant | Empresa de despacho (`companies`) |
| Cliente do tenant | Importador/exportador atendido pelo despachante |
| Processo | Processo de importação/exportação com documentos e status |

SaaS multi-tenant em **banco único**, isolamento lógico por `company_id`.

## 4. Stack alvo (KingHost-friendly)

- PHP 8.2+ / Laravel
- MySQL clássico
- FilamentPHP (painel do operador)
- Queues: `database` driver (sem Redis obrigatório)
- Storage: local + S3-compatible se disponível; sync Drive via Jobs
- IA: GPT-4o Structured Outputs (service dedicado)

## 5. Escopo MVP vs depois

### MVP (entregar valor na esteira)

- Multi-tenant + usuários do despachante
- Clientes do despachante
- Processos + upload de PDFs (portal)
- Persistência arquivo + Job Drive (pode ser stub configurável)
- Extração IA → JSON estruturado
- Validações cruzadas básicas (ex.: peso bruto BL × Packing List)
- Tela Filament: revisar campos pré-preenchidos + aprovar 1 clique
- Auditoria de aprovação (quem/quando)

### Fase 2

- Captura automática por e-mail (Outlook API / IMAP)
- Integração REST PUCOMEX
- Regras aduaneiras avançadas / scoring de confiança por campo
- Portal self-service do cliente final (além do upload mínimo)

## 6. Arquitetura lógica (camadas)

```
[Portal Upload / E-mail Ingestor]
            ↓
[Document Intake Service] → Storage + Queue (Drive Sync Job)
            ↓
[AI Extraction Service] → Structured JSON + CrossValidation Service
            ↓
[Filament Review UI] → Approval Service
            ↓
[Pucomex Payload Builder] (futuro / stub)
```

Controllers magras → Domain/Application Services → Eloquent (sempre scoped por company).

## 7. Princípios não negociáveis

1. Nenhuma query sem filtro de tenant
2. Regras de Comex e prompts/parsers de IA fora de Controllers
3. Jobs idempotentes e reprocessáveis (falha de Drive/IA não perde o arquivo)
4. Operador não digita no fluxo feliz — só corrige exceções e aprova
