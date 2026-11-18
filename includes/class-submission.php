<?php
/**
 * Riceve i moduli (anche dalle app), li controlla e li passa a chi li gestisce.
 *
 *   POST /wp-json/fabermind-forms/v1/submit/<modulo>     corpo JSON con i campi
 *
 * Risposte:
 *   200 { "ok": true, "message": "...", "redirect": "..." (facoltativo) }
 *   422 { "ok": false, "errors": { "campo": "messaggio" }, "message": "..." }
 *   429 troppi invii in poco tempo
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Submission {

	private const MIN_SECONDS = 3;   // Meno di cosi' fra pagina aperta e invio: non e' una persona.
	private const MAX_PER_IP  = 5;   // Invii per modulo...
	private const WINDOW      = 600; // ...ogni dieci minuti, per indirizzo.

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'fabermind-forms/v1',
			'/submit/(?P<form>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true', // Moduli pubblici: i controlli sono qui sotto.
				'callback'            => array( self::class, 'handle' ),
			)
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$id   = (string) $request['form'];
		$form = Fabermind_Forms::get( $id );
		if ( ! $form ) {
			return self::fail( array(), __( 'Modulo non trovato.', 'fabermind-forms' ), 404 );
		}
		$data = (array) $request->get_json_params();

		// Invii automatici: campo trappola pieno, oppure modulo spedito troppo in fretta.
		// Si risponde come se fosse andato tutto bene, cosi' chi ci prova non capisce perche' non funziona.
		if ( '' !== trim( (string) ( $data['fm_website'] ?? '' ) ) || ! self::stamp_ok( (string) ( $data['fm_stamp'] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Grazie, abbiamo ricevuto il modulo.', 'fabermind-forms' ) ) );
		}
		if ( ! self::within_limit( $id ) ) {
			return self::fail( array(), __( 'Troppi invii in poco tempo. Riprova fra qualche minuto.', 'fabermind-forms' ), 429 );
		}

		$values = array();
		$errors = array();
		foreach ( $form['fields'] as $name => $field ) {
			$value           = self::clean( $field, $data[ $name ] ?? null );
			$values[ $name ] = $value;
			$error           = self::check( $field, $value, $values );
			if ( '' !== $error ) {
				$errors[ $name ] = $error;
			}
		}
		if ( $errors ) {
			return self::fail( $errors, __( 'Controlla i campi segnati.', 'fabermind-forms' ) );
		}

		// Prima si registra chi ha scritto e cosa; poi si fa il resto (email, account...).
		$entry  = Fabermind_Forms_Entries::store( $id, $form, $values );
		$result = call_user_func( $form['handler'], $values );
		Fabermind_Forms_Entries::outcome( $entry, is_wp_error( $result ) ? $result->get_error_message() : 'ok' );
		if ( is_wp_error( $result ) ) {
			$field = (string) $result->get_error_data();

			return self::fail( '' !== $field ? array( $field => $result->get_error_message() ) : array(), $result->get_error_message() );
		}

		return new WP_REST_Response( array( 'ok' => true ) + (array) $result );
	}

	/**
	 * @param array<string,mixed> $field
	 * @param mixed               $raw
	 * @return mixed
	 */
	private static function clean( array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'consent':
				return ! empty( $raw );
			case 'choices':
				return array_values( array_intersect( array_map( 'strval', (array) $raw ), array_map( 'strval', array_keys( (array) $field['options'] ) ) ) );
			case 'email':
				return sanitize_email( (string) $raw );
			case 'password':
				return is_string( $raw ) ? $raw : ''; // Le password non si ritoccano.
			case 'textarea':
				return trim( sanitize_textarea_field( (string) $raw ) );
			default:
				return trim( sanitize_text_field( (string) $raw ) );
		}
	}

	/**
	 * @param array<string,mixed> $field
	 * @param mixed               $value
	 * @param array<string,mixed> $values Campi gia' letti (per "conferma la password").
	 * @return string Messaggio di errore, vuoto se va bene.
	 */
	private static function check( array $field, $value, array $values ): string {
		$empty = false === $value || '' === $value || array() === $value;
		if ( $empty ) {
			if ( empty( $field['required'] ) ) {
				return '';
			}

			return 'consent' === $field['type'] ? __( 'Per proseguire serve il tuo consenso.', 'fabermind-forms' ) : __( 'Questo campo è obbligatorio.', 'fabermind-forms' );
		}
		if ( 'email' === $field['type'] && ! is_email( $value ) ) {
			return __( 'L\'indirizzo email non è valido.', 'fabermind-forms' );
		}
		if ( 'select' === $field['type'] && ! isset( $field['options'][ $value ] ) ) {
			return __( 'Scegli una delle voci.', 'fabermind-forms' );
		}
		if ( isset( $field['min'] ) && mb_strlen( (string) $value ) < (int) $field['min'] ) {
			/* translators: %d: numero minimo di caratteri */
			return sprintf( __( 'Servono almeno %d caratteri.', 'fabermind-forms' ), (int) $field['min'] );
		}
		if ( isset( $field['max'] ) && mb_strlen( (string) $value ) > (int) $field['max'] ) {
			/* translators: %d: numero massimo di caratteri */
			return sprintf( __( 'Al massimo %d caratteri.', 'fabermind-forms' ), (int) $field['max'] );
		}
		if ( isset( $field['same_as'] ) && $value !== ( $values[ $field['same_as'] ] ?? null ) ) {
			return __( 'Le due password non coincidono.', 'fabermind-forms' );
		}

		return '';
	}

	private static function stamp_ok( string $stamp ): bool {
		$parts = explode( '.', $stamp, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( 'fabermind-forms|' . $parts[0] ), $parts[1] ) ) {
			return false;
		}

		return time() - (int) $parts[0] >= self::MIN_SECONDS;
	}

	private static function within_limit( string $form ): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'fm_forms_' . md5( $form . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::MAX_PER_IP ) {
			return false;
		}
		set_transient( $key, $count + 1, self::WINDOW );

		return true;
	}

	/**
	 * @param array<string,string> $errors
	 */
	private static function fail( array $errors, string $message, int $status = 422 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'errors'  => (object) $errors,
				'message' => $message,
			),
			$status
		);
	}
}
