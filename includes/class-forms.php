<?php
/**
 * I moduli e i loro campi. Sono scritti qui, non costruiti da un pannello: quando ne serve uno
 * nuovo lo si aggiunge (anche da un tema, con il filtro fabermind_forms).
 *
 * Tipi di campo: text, email, password, textarea, select, choices (piu' scelte con immagine), consent.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms {

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		static $forms = null;
		if ( null === $forms ) {
			$forms = (array) apply_filters(
				'fabermind_forms',
				array(
					'register' => self::register_form(),
					'contact'  => self::contact_form(),
				)
			);
		}

		return $forms;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	private static function privacy_label(): string {
		$url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		return $url
			/* translators: %s: indirizzo della pagina della privacy */
			? sprintf( __( 'I consent to the processing of my data as described in the <a href="%s" target="_blank" rel="noopener">privacy policy</a>', 'fabermind-forms' ), esc_url( $url ) )
			: __( 'I consent to the processing of my data by this site', 'fabermind-forms' );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function register_form(): array {
		$settings = Fabermind_Forms_Settings::get();
		$fields   = array(
			'first_name' => array( 'type' => 'text', 'label' => __( 'Name', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'given-name', 'width' => 'half' ),
			'last_name'  => array( 'type' => 'text', 'label' => __( 'Last name', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'family-name', 'width' => 'half' ),
			'email'      => array( 'type' => 'email', 'label' => __( 'Email', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'email' ),
			'password'   => array( 'type' => 'password', 'label' => __( 'Password', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'new-password', 'width' => 'half', 'min' => 8, 'help' => __( 'At least 8 characters.', 'fabermind-forms' ) ),
			'password_2' => array( 'type' => 'password', 'label' => __( 'Confirm password', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'new-password', 'width' => 'half', 'same_as' => 'password' ),
		);

		if ( $settings['heard_from'] ) {
			$fields['heard_from'] = array(
				'type'    => 'select',
				'label'   => __( 'How did you hear about us', 'fabermind-forms' ),
				'options' => array_combine( $settings['heard_from'], $settings['heard_from'] ),
			);
		}

		$brand_taxonomies = Fabermind_Forms_Settings::brand_taxonomies();
		if ( $brand_taxonomies ) {
			$terms = get_terms(
				(array) apply_filters(
					'fabermind_forms_brand_terms_args',
					array(
						'taxonomy'   => $brand_taxonomies,
						'hide_empty' => false,
						'orderby'    => 'name',
					)
				)
			);
			$options = array();
			foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
				$options[ (string) $term->term_id ] = array(
					'label' => $term->name,
					'image' => (string) wp_get_attachment_image_url( (int) get_term_meta( $term->term_id, 'thumbnail_id', true ), 'thumbnail' ),
				);
			}
			if ( $options ) {
				$fields['brands'] = array(
					'type'    => 'choices',
					'label'   => $settings['brand_question'],
					'options' => $options,
				);
			}
		}

		$fields['privacy'] = array( 'type' => 'consent', 'label' => self::privacy_label(), 'required' => true );

		return array(
			'title'   => __( 'Register', 'fabermind-forms' ),
			'button'  => __( 'Subscribe', 'fabermind-forms' ),
			'fields'  => $fields,
			'handler' => array( 'Fabermind_Forms_Handlers', 'register' ),
			// Chi ha gia' fatto l'accesso non deve registrarsi di nuovo.
			'guests'  => true,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function contact_form(): array {
		$user = wp_get_current_user();

		return array(
			'title'   => __( 'Contact us', 'fabermind-forms' ),
			'button'  => __( 'Send', 'fabermind-forms' ),
			'fields'  => array(
				'name'    => array( 'type' => 'text', 'label' => __( 'Name', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'name', 'value' => $user->exists() ? trim( $user->first_name . ' ' . $user->last_name ) : '' ),
				'email'   => array( 'type' => 'email', 'label' => __( 'Email', 'fabermind-forms' ), 'required' => true, 'autocomplete' => 'email', 'value' => $user->exists() ? $user->user_email : '' ),
				'message' => array( 'type' => 'textarea', 'label' => __( 'Message', 'fabermind-forms' ), 'required' => true, 'max' => 5000 ),
				'privacy' => array( 'type' => 'consent', 'label' => self::privacy_label(), 'required' => true ),
			),
			'handler' => array( 'Fabermind_Forms_Handlers', 'contact' ),
			'guests'  => false,
		);
	}
}
