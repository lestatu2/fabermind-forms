<?php
/**
 * Plugin Name:       Fabermind Forms
 * Description:       I due moduli che servono a un negozio: registrazione dei clienti (con le preferenze per la newsletter Mailster) e contatti. Nessun costruttore di moduli.
 * Version:           0.2.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Fabermind
 * Text Domain:       fabermind-forms
 */

defined( 'ABSPATH' ) || exit;

// Testi in inglese nel codice; le traduzioni (italiano...) stanno in languages/.
add_action( 'init', static fn() => load_plugin_textdomain( 'fabermind-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ), 0 );

define( 'FABERMIND_FORMS_VERSION', '0.2.3' );
define( 'FABERMIND_FORMS_DIR', __DIR__ );
define( 'FABERMIND_FORMS_URL', plugin_dir_url( __FILE__ ) );

// Non tocca gli ordini: va bene anche con l'archivio ordini nuovo di WooCommerce (HPOS).
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		require_once FABERMIND_FORMS_DIR . '/includes/class-ui.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-settings.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-mailster.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-forms.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-renderer.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-submission.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-handlers.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-entries.php';
		require_once FABERMIND_FORMS_DIR . '/includes/class-checkout.php';

		Fabermind_Forms_Settings::init();
		Fabermind_Forms_Mailster::init();
		Fabermind_Forms_Checkout::init();
		Fabermind_Forms_Renderer::init();
		Fabermind_Forms_Submission::init();
		Fabermind_Forms_Entries::init();
	}
);

/**
 * Stampa un modulo: 'register' (registrazione) oppure 'contact' (contatti).
 * In un contenuto si usa lo shortcode [fabermind_form id="contact"].
 */
function fabermind_form( string $id ): void {
	echo Fabermind_Forms_Renderer::render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
