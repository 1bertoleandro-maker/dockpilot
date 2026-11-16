# Index — Documentação de arquitetura DockPilot

| Doc | Status |
|-----|--------|
| [01 Visão MVP](01-visao-mvp.md) | Aprovado |
| [02 Modelagem e regras](02-modelagem-regras.md) | Aprovado |
| [03 Banco MVP](03-banco-mvp.md) | Aprovado |
| [04 UX MVP](04-ux-mvp.md) | Aprovado |
| [05 Contrato IA JSON](05-contrato-ia-json.md) | Aprovado |
| [07 Fase 2a Operação](07-fase-2a-operacao.md) | Feito |
| [08 Fase 2b Portal + automação](08-fase-2b-portal-e-automacao.md) | Em andamento |

## Briefings ativos

| Agente | Briefing | Escopo |
|--------|----------|--------|
| Senior Laravel | [migrations + models](../briefings/senior-laravel-migrations-models.md) | **DONE** |
| AI Engineer | [extração + validação](../briefings/ai-engineer-extraction-validation.md) | **DONE** |
| UI/UX + Filament | [painel operador](../briefings/uiux-filament-painel.md) | **DONE — teste liberado** |

## Teste no browser

- URL: `http://localhost:8080/dockpilot`
- Alias Apache: configurado em `httpd-vhosts.conf` (reiniciar Apache)
- **Teste de produto liberado só após Filament** (login + fila de revisão)
