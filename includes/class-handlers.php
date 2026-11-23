<?php
/**
 * Cosa succede quando un modulo arriva a posto.
 * Ogni funzione riceve i valori gia' controllati e restituisce il messaggio per l'utente,
 * oppure un errore (con il nome del campo a cui si riferisce, se c'e').
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Handlers {

	/**
	 * Preferenze per la newsletter (voci scelte): salvate nel profilo (se c'e' un account) e passate
	 * a Mailster come liste con lo stesso nome. Le usano la registrazione e la cassa; con $always
	 * (casella "newsletter" della cassa) la persona viene iscritta anche senza voci scelte.
	 *
	 * @param array<int|string> $term_ids
	 */
	public static function preferences( int $user_id, string $email, string $first_name, string $last_name, array $term_ids, bool $always = false, string $source = 'form', string $consent = '' ): void {
		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
		if ( ! $term_ids && ! $always ) {
			return;
		}
		if ( $user_id && $term_ids ) {
			update_user_meta( $user_id, 'fabermind_newsletter_terms', $term_ids );
		}
		if ( $user_id ) {
			// Il cliente ha detto si' alla newsletter: non glielo si richiede a ogni acquisto.
			update_user_meta( $user_id, 'fabermind_newsletter', 1 );
		}
		$names_of_lists = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id );
			if ( $term instanceof WP_Term && in_array( $term->taxonomy, Fabermind_Forms_Settings::brand_taxonomies(), true ) ) {
				$names_of_lists[] = $term->name;
			}
		}
		Fabermind_Forms_Mailster::subscribe( $email, $first_name, $last_name, $names_of_lists, $always, $source, $consent );
	}

	/**
	 * Registrazione: crea il cliente, gli fa l'accesso, salva le sue preferenze e lo iscrive alle liste Mailster scelte.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,string>|WP_Error
	 */
	public static function register( array $values ) {
		if ( is_user_logged_in() ) {
			return new WP_Error( 'fabermind_logged_in', __( 'You are already logged in with your account.', 'fabermind-forms' ), '' );
		}
		$email = (string) $values['email'];
		if ( email_exists( $email ) ) {
			return new WP_Error( 'fabermind_email_exists', __( 'An account with this email already exists: you can log in.', 'fabermind-forms' ), 'email' );
		}

		$names = array(
			'first_name' => (string) $values['first_name'],
			'last_name'  => (string) $values['last_name'],
		);
		if ( function_exists( 'wc_create_new_customer' ) ) {
			// Cliente di WooCommerce: parte anche la sua email di benvenuto.
			$user_id = wc_create_new_customer( $email, '', (string) $values['password'], $names );
		} else {
			$user_id = wp_insert_user(
				array_merge(
					$names,
					array(
						'user_login' => $email,
						'user_email' => $email,
						'user_pass'  => (string) $values['password'],
					)
				)
			);
		}
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'fabermind_register', wp_strip_all_tags( $user_id->get_error_message() ), '' );
		}
		$user_id = (int) $user_id;

		if ( ! empty( $values['heard_from'] ) ) {
			update_user_meta( $user_id, 'fabermind_heard_from', (string) $values['heard_from'] );
		}

		// Preferenze per la newsletter: salvate nel profilo e passate a Mailster.
		self::preferences( $user_id, $email, $names['first_name'], $names['last_name'], (array) ( $values['brands'] ?? array() ), false, 'register', Fabermind_Forms_Settings::get()['brand_question'] );

		// Accesso immediato.
		if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
			wc_set_customer_auth_cookie( $user_id );
		} else {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}
		do_action( 'fabermind_forms_registered', $user_id, $values );

		return array(
			'message'  => __( 'Registration complete. Welcome!', 'fabermind-forms' ),
			'redirect' => (string) apply_filters( 'fabermind_forms_register_redirect', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ) ),
		);
	}

	/**
	 * Contatti: una email a chi gestisce il sito, con "rispondi" verso chi ha scritto.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,string>|WP_Error
	 */
	public static function contact( array $values ) {
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$name = (string) $values['name'];
		$body = sprintf(
			"%s: %s\n%s: %s\n\n%s",
			__( 'Name', 'fabermind-forms' ),
			$name,
			__( 'Email', 'fabermind-forms' ),
			(string) $values['email'],
			(string) $values['message']
		);

		$sent = wp_mail(
			Fabermind_Forms_Settings::contact_email(),
			/* translators: 1: nome del sito, 2: nome di chi scrive */
			sprintf( __( '[%1$s] Message from the site: %2$s', 'fabermind-forms' ), $site, $name ),
			$body,
			// Il nome va fra virgolette e senza caratteri che spezzerebbero l'intestazione.
			array( 'Reply-To: "' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '" <' . $values['email'] . '>' )
		);
		if ( ! $sent ) {
			return new WP_Error( 'fabermind_mail', __( 'We couldn\'t send the message. Try again shortly.', 'fabermind-forms' ), '' );
		}

		return array( 'message' => __( 'Message sent. We will reply as soon as possible.', 'fabermind-forms' ) );
	}
}
