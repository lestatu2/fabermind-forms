<?php
/**
 * I pezzi di interfaccia del backend: il foglio di stile Tailwind e DaisyUI (src/admin.css,
 * compilato in build/admin.css e chiuso dentro ".fm-admin"), i colori del sito e le icone Lucide.
 *
 * Le pagine di WordPress restano di WordPress: dentro, i nostri bottoni stanno in un elemento
 * ".fm-admin" (vedi open()), cosi' prendono lo stile di DaisyUI senza toccare il resto.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Ui {

	/** Il foglio di stile, da caricare sulle schermate che mostrano i nostri bottoni. */
	public static function enqueue(): void {
		$file = dirname( __DIR__ ) . '/build/admin.css';
		wp_enqueue_style( 'fabermind-forms-ui', plugin_dir_url( __DIR__ ) . 'build/admin.css', array(), (string) filemtime( $file ) );
	}

	/** Carica il foglio di stile sulle pagine del backend con questo "page=" nell'indirizzo. */
	public static function for_pages( string ...$pages ): void {
		add_action(
			'admin_enqueue_scripts',
			static function () use ( $pages ): void {
				$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				if ( in_array( $page, $pages, true ) ) {
					self::enqueue();
				}
			}
		);
	}

	/** Il bottone che salva un modulo (al posto di submit_button() di WordPress). */
	public static function submit( string $label = '', string $name = 'submit' ): string {
		$label = '' !== $label ? $label : __( 'Save Changes' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- la traduzione e' quella di WordPress.

		return self::open( 'p', 'mt-6' ) . '<button type="submit" name="' . esc_attr( $name ) . '" class="btn btn-primary">' . esc_html( $label ) . '</button></p>';
	}

	/** I colori del sito (primary, accent, neutral... dalle impostazioni del tema) come variabili CSS. */
	public static function colors(): string {
		$vars = function_exists( 'fabermind_woo_css_variables' ) ? fabermind_woo_css_variables() : array();
		$out  = array();
		foreach ( (array) apply_filters( 'fabermind_forms_admin_colors', $vars ) as $name => $value ) {
			// Solo variabili CSS, senza caratteri che chiudono la regola.
			if ( is_string( $name ) && is_string( $value ) && str_starts_with( $name, '--' ) && ! preg_match( '/[;{}<>]/', $name . $value ) ) {
				$out[] = $name . ':' . $value;
			}
		}

		return implode( ';', $out );
	}

	/** Apre l'elemento ".fm-admin" che porta lo stile e i colori (da chiudere con lo stesso tag). */
	public static function open( string $tag = 'div', string $class = '', array $attrs = array() ): string {
		$more = '';
		foreach ( $attrs as $name => $value ) {
			$more .= ' ' . sanitize_key( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<' . tag_escape( $tag ) . ' class="' . esc_attr( trim( 'fm-admin ' . $class ) ) . '" style="' . esc_attr( self::colors() ) . '"' . $more . '>';
	}

	/** Un bottone da solo, dentro il suo ".fm-admin" (per i bottoni sparsi nelle pagine di WordPress). */
	public static function button( string $html ): string {
		return self::open( 'span', 'inline-flex align-middle' ) . $html . '</span>';
	}

	/** Un'icona Lucide (decorativa: il testo o l'aria-label stanno sul bottone). */
	public static function icon( string $name, string $class = 'size-4' ): string {
		static $icons = null;
		$icons      ??= require __DIR__ . '/ui-icons.php';

		return '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ( $icons[ $name ] ?? '' ) . '</svg>';
	}
}
