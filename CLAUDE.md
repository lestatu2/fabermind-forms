# Regole per Claude su questo progetto

Queste regole le ha dettate Fabermind. Vengono prima di qualsiasi abitudine o gusto di Claude.

## 1. Il design lo decide Fabermind, non Claude

- **Divasport è una migrazione.** Il modello è il sito attuale, https://www.divasport.it.
  Si riproducono struttura, disposizione e comportamenti di quello che c'è oggi.
- **Prima di toccare una pagina o un componente si guarda com'è sul sito attuale**: nel
  browser (computer e telefono) e nel codice del tema vecchio (`bootscore-child-main`, nel
  repository `divasport`). Vale per ogni pagina: home, elenco, scheda prodotto, anteprima
  rapida, carrello, pannello laterale del carrello, cassa, area personale, pagine di testo.
- **Niente soluzioni "a gusto"**: nessuna disposizione, colore, riquadro o comportamento
  inventato perché "sta bene". Se sul sito attuale una cosa non c'è, o non si capisce come
  funziona, si chiede a Fabermind prima di farla.
- **Prima di consegnare si confronta il risultato con il sito attuale**, schermata contro
  schermata, e si dichiara ogni differenza rimasta.
- Le indicazioni di design che Fabermind dà in conversazione valgono come regole e si
  aggiungono qui sotto.

## 2. Come si costruisce l'interfaccia

- **Tailwind e DaisyUI prima di tutto.** CSS scritto a mano solo dove non arrivano, con il
  motivo scritto accanto. Componenti DaisyUI veri (modal, rating, join, carousel, alert...),
  non imitazioni.
- **Icone Lucide.** Niente Font Awesome, niente Bootstrap Icons.
- **React solo nei punti interattivi**; il resto è PHP. I dati passano dalle API di
  WooCommerce, le stesse che useranno le app iOS e Android.
- **Nessun costruttore di pagine.** Il tema si configura per colori, logo, font e sezioni
  della home; la disposizione la decide il tema.

## 3. Come si scrivono le funzioni

- Plugin `fabermind-*`, uno per funzione, riusabili per altri clienti: generici nella
  struttura, mirati nelle funzioni. Niente plugin con mille opzioni che non servono.
- Restano di terzi solo WooCommerce, Yoast, Mailster, PayPal e Satispay.
- Nessuna copia dei modelli di WooCommerce: si usano gli agganci.

## 4. Indicazioni di design ricevute

- Miniature della scheda prodotto: in riga sotto la foto, non in colonna.
- Anteprima rapida del prodotto: è essenziale, fa restare nell'elenco senza andare avanti
  e indietro. Stessa galleria della scheda prodotto.
- Stelle delle recensioni: componente rating di DaisyUI.
- Carrello nella testata: mostra il totale accanto all'icona; quando si aggiunge un
  prodotto si apre il pannello laterale del carrello.
- Carrello e cassa: come sono oggi sul sito, non reinventati.
- Moduli: campi, menu a tendina, caselle e scelte sono sempre i componenti DaisyUI (input,
  select, checkbox, radio, textarea), anche quando il codice lo genera WooCommerce o un
  plugin. Niente menu a tendina di terzi (select2) con un aspetto loro.
- Caroselli: si scorrono anche trascinando con il mouse, e rispondono all'intensità del gesto:
  un colpo secco fa scorrere più elementi, uno lento meno. Comportamento da slider moderno,
  non il minimo che "funziona".
- Schede prodotto negli elenchi: più immagini con il componente hover-gallery di DaisyUI
  (muovendo il mouse sulla foto scorrono le immagini); barra "Aggiungi al carrello" in fondo
  alla foto al passaggio del mouse.
- Lista dei desideri: cuore nella testata con il numero di prodotti in lista, che porta alla
  pagina della lista; voce "Lista dei desideri" anche nel menu dell'area personale.
- Account nella testata: l'icona apre un pannello laterale da sinistra (accesso, oppure saluto
  e menu dell'area personale); chi non ha fatto l'accesso ha anche l'icona "Registrati", che
  apre il modulo di registrazione in una finestra larga. Come sul sito attuale.
