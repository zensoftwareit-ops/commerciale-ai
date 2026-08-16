# Commerciale AI Client Area

Plugin WordPress per registrazione clienti, visualizzazione dei tre pacchetti,
Stripe Checkout annuale, Customer Portal e sincronizzazione idempotente delle
licenze con Commerciale AI.

## Installazione

1. comprimere la cartella `commerciale-ai-client` in ZIP;
2. installarla da **Plugin > Aggiungi plugin > Carica plugin**;
3. aprire **Impostazioni > Commerciale AI**;
4. inserire URL del software, `BILLING_INTEGRATION_KEY`, chiave segreta Stripe e
   signing secret del webhook;
5. creare su Stripe un endpoint webhook verso l'URL mostrato nelle impostazioni;
6. ascoltare almeno `checkout.session.completed`, `customer.subscription.created`,
   `customer.subscription.updated` e `customer.subscription.deleted`;
7. inserire `[commerciale_ai_pricing]` nella pagina pacchetti e
   `[commerciale_ai_account]` nell'area cliente.

Le carte non transitano mai da WordPress: Checkout e Customer Portal sono ospitati
da Stripe. Il provisioning avviene esclusivamente dopo un webhook Stripe firmato.

