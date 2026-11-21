/*
 * Chiude il foglio di stile compilato dentro ".fm-admin".
 *
 * Perche' serve: nel backend ci sono gia' gli stili di WordPress. Tailwind 4 mette le sue regole in
 * "livelli" (@layer), e una regola fuori dai livelli (quelle di WordPress) vince sempre su una dentro.
 * Quindi qui:
 *   1. si tolgono i livelli, lasciando le regole nello stesso ordine;
 *   2. ogni selettore prende davanti ".fm-admin": le nostre regole valgono solo nella nostra pagina
 *      e pesano piu' di quelle generiche di WordPress (a, h2, p, table...).
 *
 * Uso: node scripts/scope.mjs ingresso.css uscita.css
 */

import { readFileSync, writeFileSync } from 'node:fs';
import postcss from 'postcss';

// La classe che chiude lo stile: ".fm-admin", oppure quella data come terzo argomento (ogni plugin
// ha la sua, cosi' due plugin sulla stessa schermata non si pestano i piedi).
const [ , , input, output, scopeArg ] = process.argv;
const SCOPE = scopeArg || '.fm-admin';

// Selettori che indicano "tutta la pagina": diventano la nostra radice.
const ROOTS = /(^|[\s>+~,(])(:root|:host|html|body)(?![\w-])/g;

function scopeSelector( selector ) {
	const sel = selector.trim();
	if ( sel.startsWith( SCOPE ) ) {
		return sel;
	}
	if ( ROOTS.test( sel ) ) {
		ROOTS.lastIndex = 0;
		return sel.replace( ROOTS, ( match, before ) => before + SCOPE );
	}
	ROOTS.lastIndex = 0;
	// "*" e gli pseudo-elementi da soli: la radice stessa e tutto quello che contiene.
	if ( sel === '*' ) {
		return `${ SCOPE }, ${ SCOPE } *`;
	}

	return `${ SCOPE } ${ sel }`;
}

const scope = {
	postcssPlugin: 'fm-scope',
	Once( root ) {
		// 1. Via i livelli (anche quelli uno dentro l'altro: si ripete finche' ce ne sono).
		let found = true;
		while ( found ) {
			found = false;
			root.walkAtRules( 'layer', ( rule ) => {
				found = true;
				if ( rule.nodes && rule.nodes.length ) {
					rule.replaceWith( rule.nodes );
				} else {
					rule.remove();
				}
			} );
		}
		// 2. Ogni regola dentro ".fm-admin".
		root.walkRules( ( rule ) => {
			const parent = rule.parent;
			// I passi di un'animazione (from, to, 50%) non sono selettori.
			if ( parent && parent.type === 'atrule' && /keyframes$/.test( parent.name ) ) {
				return;
			}
			// Le regole annidate (&:hover dentro un'altra) seguono gia' la regola che le contiene.
			if ( parent && parent.type === 'rule' ) {
				return;
			}
			rule.selectors = rule.selectors.map( scopeSelector );
		} );
	},
};

const css = readFileSync( input, 'utf8' );
const result = await postcss( [ scope ] ).process( css, { from: input, to: output } );
writeFileSync( output, result.css );
console.log( `${ output }: ${ ( result.css.length / 1024 ).toFixed( 1 ) } kB` );
