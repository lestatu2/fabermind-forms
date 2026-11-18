<?php
/**
 * Impostazioni (Impostazioni > Moduli Fabermind).
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Settings {

	private const OPTION = 'fabermind_forms';

	public static function init(): void {
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
	 * @return array{contact_email:string,heard_from:string[],brand_taxonomy:string,brand_question:string,sync_lists:bool}
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
				'brand_question' => __( 'Su cosa desideri ricevere news e offerte?', 'fabermind-forms' ),
				// Tiene in Mailster una lista per ogni voce di quella classificazione.
				'sync_lists'     => true,
			)
		);

		return array(
			'contact_email'  => (string) ( $saved['contact_email'] ?? $defaults['contact_email'] ),
			'heard_from'     => array_values( array_filter( array_map( 'strval', (array) ( $saved['heard_from'] ?? $defaults['heard_from'] ) ) ) ),
			'brand_taxonomy' => (string) ( $saved['brand_taxonomy'] ?? $defaults['brand_taxonomy'] ),
			'brand_question' => (string) ( $saved['brand_question'] ?? $defaults['brand_question'] ),
			'sync_lists'     => (bool) ( $saved['sync_lists'] ?? $defaults['sync_lists'] ),
		);
	}

	public static function contact_email(): string {
		$email = self::get()['contact_email'];

		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	public static function menu(): void {
		add_options_page(
			__( 'Moduli Fabermind', 'fabermind-forms' ),
			__( 'Moduli Fabermind', 'fabermind-forms' ),
			'manage_options',
			'fabermind-forms',
			array( self::class, 'page' )
		);
	}

	public static function register(): void {
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
			<h1><?php esc_html_e( 'Moduli Fabermind', 'fabermind-forms' ); ?></h1>
			<p><?php esc_html_e( 'Shortcode: [fabermind_form id="register"] per la registrazione, [fabermind_form id="contact"] per i contatti.', 'fabermind-forms' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'fabermind_forms' ); ?>
				<h2><?php esc_html_e( 'Contatti', 'fabermind-forms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fm-contact-email"><?php esc_html_e( 'I messaggi arrivano a', 'fabermind-forms' ); ?></label></th>
						<td><input type="email" class="regular-text" id="fm-contact-email" name="<?php echo $name( 'contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"></td>
					</tr>
				</table>
				<h2><?php esc_html_e( 'Registrazione', 'fabermind-forms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fm-heard"><?php esc_html_e( '"Come ci hai conosciuti": risposte', 'fabermind-forms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="7" id="fm-heard" name="<?php echo $name( 'heard_from' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_textarea( implode( "\n", $settings['heard_from'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una per riga. Vuoto: la domanda non compare.', 'fabermind-forms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fm-tax"><?php esc_html_e( 'Preferenze per la newsletter', 'fabermind-forms' ); ?></label></th>
						<td>
							<select id="fm-tax" name="<?php echo $name( 'brand_taxonomy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
								<option value=""><?php esc_html_e( '— Non chiedere —', 'fabermind-forms' ); ?></option>
								<?php foreach ( $taxonomies as $taxonomy ) : ?>
									<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $settings['brand_taxonomy'], $taxonomy->name ); ?>><?php echo esc_html( $taxonomy->labels->name . ' (' . $taxonomy->name . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Chi si registra sceglie fra le voci di questa classificazione; viene iscritto alle liste Mailster con lo stesso nome.', 'fabermind-forms' ); ?></p>
							<p><input type="text" class="large-text" name="<?php echo $name( 'brand_question' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="<?php echo esc_attr( $settings['brand_question'] ); ?>" aria-label="<?php esc_attr_e( 'Testo della domanda', 'fabermind-forms' ); ?>"></p>
							<label><input type="checkbox" name="<?php echo $name( 'sync_lists' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1" <?php checked( $settings['sync_lists'] ); ?>> <?php esc_html_e( 'Crea in Mailster una lista per ogni voce nuova', 'fabermind-forms' ); ?></label>
							<?php if ( ! Fabermind_Forms_Mailster::available() ) : ?>
								<p class="description" style="color:#b32d2e"><?php esc_html_e( 'Mailster non è attivo: le preferenze vengono salvate nel profilo del cliente ma nessuno viene iscritto alle liste.', 'fabermind-forms' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
