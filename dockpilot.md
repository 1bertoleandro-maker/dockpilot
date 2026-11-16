# DockPilot — Princípios Mestres

Regras obrigatórias do produto. Toda especificação e implementação deve respeitá-las. Desvio exige atualização deste documento e aprovação explícita.

## Arquitetura e código

| # | Princípio | Implicação |
|---|-----------|------------|
| 1 | Nunca criar código duplicado | Extrair Shared/Services, traits, components; DRY obrigatório |
| 2 | Sempre utilizar Laravel Way | Conventions Laravel: Form Request, Resource, Policy, Job, Event, Migration |
| 3 | Nunca utilizar jQuery | JS moderno (Alpine/Livewire/Vite); sem `$()` / plugins jQuery |
| 4 | Sempre utilizar Services | Lógica de domínio em `app/Services`; Controller só orquestra HTTP |
| 5 | Nunca colocar regras de negócio no Controller | Controller: validate → authorize → service → response |
| 6 | Sempre utilizar tipagem forte | PHP strict types, return/param types, PHPStan/Larastan alinhado |
| 7 | Sempre utilizar Enum | Estados, tipos, status e constantes de domínio via PHP Enum |
| 8 | Sempre utilizar DTO | Entrada/saída entre camadas via DTO tipado; sem arrays soltos de domínio |

## API, qualidade e UX

| # | Princípio | Implicação |
|---|-----------|------------|
| 9 | Toda funcionalidade deve possuir API | Endpoint REST (ou contrato equivalente) para cada capacidade de produto |
| 10 | Todo módulo deve possuir testes | Feature e/ou Unit cobrindo regras críticas do módulo |
| 11 | Todo formulário deve utilizar validação | Form Request (ou equivalente); nunca confiar só no front |
| 12 | Toda tela deve ser responsiva | Mobile-first; breakpoints Tailwind; sem layout quebrado em viewport pequena |
| 13 | Sempre utilizar Tailwind | Estilo via utilitários Tailwind; sem CSS ad-hoc paralelo ao design system |

## Segurança, documentos e IA

| # | Princípio | Implicação |
|---|-----------|------------|
| 14 | Toda funcionalidade deve possuir permissão | Policy/Gate/Permission por ação; sem rota “aberta” de produto |
| 15 | Todo upload deve possuir backup | Arquivo original preservado (cópia/backup) antes de qualquer transformação |
| 16 | Todo documento deve possuir versionamento | Nova versão imutável; histórico auditável; nunca sobrescrever silenciosamente |
| 17 | Toda IA deve gerar Score de Confiança | Toda saída de IA inclui `confidence_score` interpretável |
| 18 | Nunca atualizar informações automaticamente | Sugestões de IA/OCR/integração ficam pendentes; não gravam no registro oficial sozinhas |
| 19 | Sempre solicitar aprovação do operador | Mudança de dados de domínio exige confirmação explícita do operador |

## Checklist de liberação (por feature)

Antes de considerar uma funcionalidade pronta:

- [ ] Sem duplicação injustificada
- [ ] Laravel Way (Request, Policy, Service, Resource/DTO)
- [ ] Sem jQuery
- [ ] Regra de negócio só no Service
- [ ] API documentada/contratada
- [ ] Testes do módulo
- [ ] Validação de formulário
- [ ] UI responsiva + Tailwind
- [ ] Tipagem forte + Enum + DTO
- [ ] Permissão por ação
- [ ] Upload com backup (se houver upload)
- [ ] Documento versionado (se houver documento)
- [ ] Score de confiança (se houver IA)
- [ ] Sem auto-update; fluxo de aprovação do operador
