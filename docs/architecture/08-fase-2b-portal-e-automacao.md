# Fase 2b — Portal do cliente, ingestão e automação

## Modelo de uso (confirmado)

| Papel | Quem | O que faz |
|-------|------|-----------|
| Empresa (tenant) | Despachante | Cadastra clientes, opera processos, sobe/revisa docs |
| Usuário da empresa | `admin` / `operator` | Painel `/admin` |
| Cliente do despachante | Importador/exportador | Cadastro da empresa |
| Usuário do cliente | `client` ligado a `client_id` | Painel `/portal` — acompanha **só os processos dele**, observa e pede |

## Entregas desta fase (ordem de construção)

1. **Usuários do cliente** + painel Portal (acompanhar processos)
2. **Mensagens no processo** (observação / pedido) visíveis conforme papel
3. **Visual** com mais presença (CSS painel + portal + login)
4. **Stubs + pipelines** e-mail → Drive → abrir processo → extrair → checklist  
   (IMAP/Outlook e Drive API reais entram em 2b-impl; contratos e Jobs já nascem)

## Fora desta fatia

- PUCOMEX transmissão
- WhatsApp / notificações push
