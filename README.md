# Fabermind Forms

I due moduli che servono a un negozio. Nessun costruttore di moduli: i campi sono scritti
nel codice (`includes/class-forms.php`); un modulo nuovo si aggiunge lì, o da un tema con il
filtro `fabermind_forms`.

## Registrazione — `[fabermind_form id="register"]`

Nome, cognome, email, password con conferma, "Come ci hai conosciuti" (se configurata),
preferenze per la newsletter (se configurate), consenso alla privacy.

Quando arriva a posto:

1. crea il cliente (WooCommerce, se c'è: parte la sua email di benvenuto);
2. gli fa subito l'accesso e lo porta nell'area personale;
3. salva nel profilo "come ci hai conosciuti" (`fabermind_heard_from`) e le preferenze
   (`fabermind_newsletter_terms`, identificativi delle voci);
4. se ha scelto almeno una voce e Mailster è attivo, lo iscrive alle liste Mailster con lo
   stesso nome delle voci scelte.

Le preferenze sono le voci di una classificazione scelta nelle impostazioni (per esempio i
marchi), mostrate con il loro logo. Con l'opzione accesa, ogni voce nuova crea la sua lista
in Mailster.
Un tema puo' aggiungere altre classificazioni alle stesse scelte con il filtro
`fabermind_forms_brand_taxonomies` (elenco di nomi tecnici).

## Contatti — `[fabermind_form id="contact"]`

Nome, email, messaggio, consenso alla privacy. Manda una email a chi gestisce il sito, con
"rispondi" verso chi ha scritto. I messaggi non vengono salvati nel database.

## Impostazioni

Impostazioni > Moduli Fabermind: indirizzo che riceve i contatti, risposte di "Come ci hai
conosciuti", classificazione delle preferenze e testo della domanda. Valori di partenza da
un tema: filtro `fabermind_forms_defaults`.

## Protezione dagli invii automatici

Senza servizi esterni: campo trappola invisibile, tempo minimo fra apertura e invio, al
massimo cinque invii ogni dieci minuti per indirizzo.

## Per i temi e per le app

    fabermind_form( 'register' );

    POST /wp-json/fabermind-forms/v1/submit/<modulo>      corpo JSON con i campi
    200 { "ok": true, "message": "...", "redirect": "..." }
    422 { "ok": false, "errors": { "campo": "messaggio" }, "message": "..." }

Un'app deve mandare anche `fm_stamp` (il valore si legge dal modulo stampato nella pagina):
quando faremo le app serve un indirizzo che lo consegni.

Le classi sono Tailwind e DaisyUI e le compila il tema Fabermind Woo: dopo aver installato o
aggiornato il plugin va ricompilato il tema.

## Archivio dei messaggi

Ogni invio valido viene registrato prima di tutto il resto (email, account), così non si
perde nemmeno se l'email non parte. Si vede in bacheca alla voce **Messaggi**: chi ha
scritto, email, modulo, data, tutto quello che ha compilato, e l'esito se qualcosa non è
andato. I messaggi nuovi sono in grassetto, con il numero nel menu; da ogni messaggio si
risponde, si segna da leggere o si elimina. Le password non vengono mai registrate. La
cancellazione dei dati personali di WordPress (Strumenti > Cancella dati personali) toglie
anche i messaggi di quella email.

## Newsletter per le app

Con "newsletter alla cassa" accesa e Mailster presente:

- `GET wp-json/fabermind-forms/v1/newsletter`: cosa chiedere (`label` della casella,
  `question` e `options` delle voci) e `subscribed` (il cliente ha già detto sì);
- alla cassa della Store API (`POST wc/store/v1/checkout`) la scelta viaggia in
  `extensions["fabermind-forms"] = { "newsletter": true, "terms": [id, ...] }`: vale come la
  casella della cassa del sito.

Chi dice sì resta segnato nel profilo (`fabermind_newsletter`).
