# UX MVP — Auditoria Humana Líquida (para validação)

## Persona principal

**Operador de confiança** do despachante: volume alto, pouco tempo, zero paciência para redigitar.

## Jornada feliz (Filament)

1. **Fila de revisão** — lista processos em `review`, com badges de alertas (fails/warns).
2. **Abrir processo** — layout em duas colunas:
   - Esquerda: PDF viewer (documento selecionado)
   - Direita: campos pré-preenchidos pela IA, agrupados por documento
3. **Alertas de validação** no topo — fails em destaque; warns expansíveis.
4. **Corrigir só o necessário** — campos editáveis; highlight nos que a IA marcou baixa confiança.
5. **Aprovar processo** — um CTA primário “Aprovar”; bloqueado se houver fail não resolvido.
6. **Confirmação** — toast + processo sai da fila (`approved`).

## Telas mínimas Filament

| Tela | Objetivo |
|------|----------|
| Login | Usuário do tenant |
| Clientes | CRUD simples |
| Processos | Lista + criar + status |
| Upload docs | No processo: drag-and-drop por tipo |
| Fila de revisão | Trabalho diário do operador |
| Detalhe revisão | PDF + campos + aprovar |
| (Admin) Config Drive/IA | Chaves e pasta raiz — básico |

## Fora do MVP visual

- Portal rico do cliente final (além de upload autenticado se necessário)
- Dashboard analítico pesado
- Editor complexo de regras aduaneiras

## Princípios de UI

- Uma ação primária por tela de revisão: **Aprovar**
- Não parecer formulário Siscomex; parecer checklist de conferência
- Densidade alta de informação, mas hierarquia clara (alerta → campo → PDF)
