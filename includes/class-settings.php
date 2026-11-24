<?php
/**
 * Impostazioni (Impostazioni > Moduli Fabermind).
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Settings {

	private const OPTION = 'fabermind_forms';

	public static function init(): void {
		Fabermind_Forms_Ui::for_pages( 'fabermind-forms' );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
	}

	/**
	 * Classificazioni delle scelte "ricevi news su...": quella delle impostazioni, piu' altre
	 * aggiunte da un tema con il filtro (per esempio squadre e marchi insieme).
	 *
	 * @return string[]
	 */
	public static function brand_taxonomies(): array {
		$main = self::get()['brand_taxonomy'];
		$list = (array) apply_filters( 'fabermind_forms_brand_taxonomies', '' !== $main ? array( $main ) : array() );

		return array_values( array_unique( array_filter( array_map( 'strval', $list ), 'taxonomy_exists' ) ) );
	}

	/**
	 * @return array{contact_email:string,heard_from:string[],brand_taxonomy:string,brand_question:string,sync_lists:bool,checkout_newsletter:bool}
	 */
	public static function get(): array {
		$saved    = get_option( self::OPTION );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = (array) apply_filters(
			'fabermind_forms_defaults',
			array(
				// A chi arrivano i messaggi del modulo contatti (vuoto: l'email dell'amministratore).
				'contact_email'  => '',
				// Risposte alla domanda "Come ci hai conosciuti" (vuoto: la domanda non compare).
				'heard_from'     => array(),
				// Classificazione le cui voci si possono scegliere alla registrazione per ricevere la newsletter
				// (per esempio i marchi). Vuoto: la domanda non compare.
				'brand_taxonomy' => '',
				'brand_question' => __( 'What would you like to receive news and offers about?', 'fabermind-forms' ),
				// Tiene in Mailster una lista per ogni voce di quella classificazione.
				'sync_lists'     => true,
				// Alla cassa: casella "Iscriviti alla newsletter" con le preferenze.
				'checkout_newsletter' => false,
			)
		);

		return array(
			'contact_email'  => (string) ( $saved['contact_email'] ?? $defaults['contact_email'] ),
			'heard_from'     => array_values( array_filter( array_map( 'strval', (array) ( $saved['heard_from'] ?? $defaults['heard_from'] ) ) ) ),
			'brand_taxonomy' => (string) ( $saved['brand_taxonomy'] ?? $defaults['brand_taxonomy'] ),
			'brand_question' => (string) ( $saved['brand_question'] ?? $defaults['brand_question'] ),
			'sync_lists'     => (bool) ( $saved['sync_lists'] ?? $defaults['sync_lists'] ),
			'checkout_newsletter' => (bool) ( $saved['checkout_newsletter'] ?? $defaults['checkout_newsletter'] ),
		);
	}

	public static function contact_email(): string {
		$email = self::get()['contact_email'];

		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/** Le impostazioni dei moduli sono riservate a Fabermind (fabermind-admin); i messaggi no. */
	public static function capability(): string {
		return (string) apply_filters( 'fabermind_setup_capability', 'manage_options' );
	}

	public static function menu(): void {
		add_options_page(
			__( 'Fabermind forms', 'fabermind-forms' ),
			__( 'Fabermind forms', 'fabermind-forms' ),
			self::capability(),
			'fabermind-forms',
			array( self::class, 'page' )
		);
	}

	public static function register(): void {
		add_filter( 'option_page_capability_fabermind_forms', array( self::class, 'capability' ) );
		register_setting(
			'fabermind_forms',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static function ( $input ): array {
					$input = is_array( $input ) ? $input : array();
					$lines = preg_split( '/\r\n|\r|\n/', (string) ( $input['heard_from'] ?? '' ) );

					return array(
						'contact_email'  => sanitize_email( (string) ( $input['contact_email'] ?? '' ) ),
						'heard_from'     => array_values( array_filter( array_map( 'sanitize_text_field', (array) $lines ) ) ),
						'brand_taxonomy' => sanitize_key( (string) ( $input['brand_taxonomy'] ?? '' ) ),
						'brand_question' => sanitize_text_field( (string) ( $input['brand_question'] ?? '' ) ),
						'sync_lists'     => ! empty( $input['sync_lists'] ),
						'checkout_newsletter' => ! empty( $input['checkout_newsletter'] ),
					);
				},
			)
		);
	}

	public static function page(): void {
		$settings   = self::get();
		$name       = static fn( string $key ): string => esc_attr( self::OPTION . "[$key]" );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		echo '<div class="wrap">' . Fabermind_Forms_Ui::open( 'div', 'flex max-w-4xl flex-col gap-6 py-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flex flex-col gap-1"><h1 class="text-2xl font-semibold">' . esc_html__( 'Fabermind forms', 'fabermind-forms' ) . '</h1>';
		echo '<p class="text-sm text-base-content/70">' . esc_html__( 'Shortcode: [fabermind_form id="register"] for registration, [fabermind_form id="contact"] for contacts.', 'fabermind-forms' ) . '</p></div>';
		echo '<form method="post" action="options.php" class="flex flex-col gap-6">';
		settings_fields( 'fabermind_forms' );

		echo '<div class="card card-border bg-base-100"><div class="card-body gap-4"><h2 class="card-title">' . esc_html__( 'Contacts', 'fabermind-forms' ) . '</h2>';
		echo '<fieldset class="fieldset"><legend class="fieldset-legend">' . esc_html__( 'Messages go to', 'fabermind-forms' ) . '</legend><input type="email" class="input w-full max-w-md" name="' . $name( 'contact_email' ) . '" value="' . esc_attr( $settings['contact_email'] ) . '" placeholder="' . esc_attr( (string) get_option( 'admin_email' ) ) . '"></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div>';

		echo '<div class="card card-border bg-base-100"><div class="card-body gap-4"><h2 class="card-title">' . esc_html__( 'Registration', 'fabermind-forms' ) . '</h2>';
		echo '<fieldset class="fieldset"><legend class="fieldset-legend">' . esc_html__( '"How did you hear about us": answers', 'fabermind-forms' ) . '</legend><textarea class="textarea w-full" rows="7" name="' . $name( 'heard_from' ) . '">' . esc_textarea( implode( "\n", $settings['heard_from'] ) ) . '</textarea><p class="label whitespace-normal">' . esc_html__( 'One per line. Empty: the question doesn\'t appear.', 'fabermind-forms' ) . '</p></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<fieldset class="fieldset gap-2"><legend class="fieldset-legend">' . esc_html__( 'Newsletter preferences', 'fabermind-forms' ) . '</legend><select class="select w-full max-w-md" name="' . $name( 'brand_taxonomy' ) . '"><option value="">' . esc_html__( '— Don\'t ask —', 'fabermind-forms' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $taxonomies as $taxonomy ) {
			echo '<option value="' . esc_attr( $taxonomy->name ) . '"' . selected( $settings['brand_taxonomy'], $taxonomy->name, false ) . '>' . esc_html( $taxonomy->labels->name . ' (' . $taxonomy->name . ')' ) . '</option>';
		}
		echo '</select><p class="label whitespace-normal">' . esc_html__( 'New customers choose among the items of this taxonomy; they are subscribed to the newsletter lists with the same name.', 'fabermind-forms' ) . '</p>';
		echo '<input type="text" class="input w-full" name="' . $name( 'brand_question' ) . '" value="' . esc_attr( $settings['brand_question'] ) . '" aria-label="' . esc_attr__( 'Question text', 'fabermind-forms' ) . '"></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( array( 'sync_lists' => __( 'Create a newsletter list for each new item', 'fabermind-forms' ), 'checkout_newsletter' => __( 'At checkout: "Subscribe to the newsletter" checkbox, with these preferences', 'fabermind-forms' ) ) as $key => $label ) {
			echo '<label class="flex items-center gap-3"><input type="checkbox" class="toggle toggle-primary" name="' . $name( $key ) . '" value="1"' . checked( $settings[ $key ], true, false ) . '><span>' . esc_html( $label ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! Fabermind_Forms_Mailster::available() ) {
			echo '<div role="alert" class="alert alert-warning alert-soft text-sm"><span>' . esc_html__( 'The newsletter is off (Mailster or Email): preferences are saved in the customer profile but nobody is subscribed to the lists.', 'fabermind-forms' ) . '</span></div>';
		}
		echo '</div></div>';
		echo '<div><button type="submit" class="btn btn-primary">' . esc_html__( 'Save Changes' ) . '</button></div>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- testo di WordPress.
		echo '</form></div></div>';
	}
}
