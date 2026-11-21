/*
 * Prende le icone Lucide che servono e le scrive in includes/ui-icons.php.
 * Per aggiungerne una: metterne il nome in scripts/icons.json e lanciare "npm run icons".
 */
import { readFileSync, writeFileSync } from 'node:fs';

const names = JSON.parse( readFileSync( new URL( './icons.json', import.meta.url ), 'utf8' ) );
const lines = names.map( ( name ) => {
	const svg = readFileSync( new URL( `../node_modules/lucide-static/icons/${ name }.svg`, import.meta.url ), 'utf8' );
	const inner = svg.replace( /<!--[\s\S]*?-->/g, '' ).replace( /^[\s\S]*?<svg[^>]*>/, '' ).replace( /<\/svg>\s*$/, '' ).replace( /\s*\n\s*/g, '' ).trim();
	return `\t'${ name }' => '${ inner.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }',`;
} );
writeFileSync(
	new URL( '../includes/ui-icons.php', import.meta.url ),
	`<?php\n// Scritto da scripts/icons-php.mjs (icone Lucide): non modificare a mano.\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn array(\n${ lines.join( '\n' ) }\n);\n`
);
console.log( 'icone:', names.join( ', ' ) );
