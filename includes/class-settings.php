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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Fabermind forms', 'fabermind-forms' ); ?></h1>
			<p><?php esc_html_e( 'Shortcode: [fabermind_form id="register"] for registration, [fabermind_form id="contact"] for contacts.', 'fabermind-forms' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'fabermind_forms' ); ?>
				<h2><?php esc_html_e( 'Contacts', 'fabermind-forms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fm-contact-email"><?php esc_html_e( 'Messages go to', 'fabermind-forms' ); ?></label></th>
						<td><input type="email" class="regular-text" id="fm-contact-email" name="<?php echo $name( 'contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"></td>
					</tr>
				</table>
				<h2><?php esc_html_e( 'Registration', 'fabermind-forms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fm-heard"><?php esc_html_e( '"How did you hear about us": answers', 'fabermind-forms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="7" id="fm-heard" name="<?php echo $name( 'heard_from' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_textarea( implode( "\n", $settings['heard_from'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Empty: the question doesn\'t appear.', 'fabermind-forms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fm-tax"><?php esc_html_e( 'Newsletter preferences', 'fabermind-forms' ); ?></label></th>
						<td>
							<select id="fm-tax" name="<?php echo $name( 'brand_taxonomy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
								<option value=""><?php esc_html_e( '— Don\'t ask —', 'fabermind-forms' ); ?></option>
								<?php foreach ( $taxonomies as $taxonomy ) : ?>
									<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $settings['brand_taxonomy'], $taxonomy->name ); ?>><?php echo esc_html( $taxonomy->labels->name . ' (' . $taxonomy->name . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'New customers choose among the items of this taxonomy; they are subscribed to the newsletter lists with the same name.', 'fabermind-forms' ); ?></p>
							<p><input type="text" class="large-text" name="<?php echo $name( 'brand_question' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $settings['brand_question'] ); ?>" aria-label="<?php esc_attr_e( 'Question text', 'fabermind-forms' ); ?>"></p>
							<label><input type="checkbox" name="<?php echo $name( 'sync_lists' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1" <?php checked( $settings['sync_lists'] ); ?>> <?php esc_html_e( 'Create a newsletter list for each new item', 'fabermind-forms' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo $name( 'checkout_newsletter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1" <?php checked( $settings['checkout_newsletter'] ); ?>> <?php esc_html_e( 'At checkout: "Subscribe to the newsletter" checkbox, with these preferences', 'fabermind-forms' ); ?></label>
							<?php if ( ! Fabermind_Forms_Mailster::available() ) : ?>
								<p class="description" style="color:#b32d2e"><?php esc_html_e( 'The newsletter is off (Mailster or Email): preferences are saved in the customer profile but nobody is subscribed to the lists.', 'fabermind-forms' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php echo Fabermind_Forms_Ui::submit(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</form>
		</div>
		<?php
	}
}
