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
