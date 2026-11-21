<?php
/**
 * Alla cassa di WooCommerce: casella "Iscriviti alla newsletter" e, se spuntata, le stesse
 * preferenze della registrazione ("Su quale squadra desideri ricevere news e offerte?").
 * Vale anche per chi compra senza account. L'iscrizione va a Mailster (liste con il nome delle
 * voci scelte); se c'e' l'account, le preferenze restano anche nel profilo.
 *
 * Si accende in Impostazioni > Moduli Fabermind o con il filtro fabermind_forms_defaults
 * (checkout_newsletter).
 *
 * Per le app (cassa della Store API): GET fabermind-forms/v1/newsletter dice cosa chiedere
 * (la casella, le voci, se il cliente e' gia' iscritto); la scelta arriva con l'ordine in
 * extensions["fabermind-forms"] = { newsletter: true, terms: [id, ...] }.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Checkout {

	private const BOX   = 'fabermind_newsletter';
	private const FIELD = 'fabermind_newsletter_terms';
	private const SPACE = 'fabermind-forms';

	public static function init(): void {
		add_action( 'woocommerce_after_checkout_billing_form', array( self::class, 'render' ) );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'save' ), 20, 3 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::extend();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( self::class, 'extend' ) );
		}
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( self::class, 'save_from_app' ), 20, 2 );
	}

	/** La newsletter si puo' proporre (interruttore acceso e Mailster presente). */
	public static function available(): bool {
		return self::on();
	}

	public static function routes(): void {
		register_rest_route(
			self::SPACE . '/v1',
			'/newsletter',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'question' ),
			)
		);
	}

	/**
	 * Cosa chiedere, in una forma buona anche per le app.
	 *
	 * @return array<string,mixed>
	 */
	public static function question_data(): array {
		$choices = self::on() ? self::choices() : null;
		$options = array();
		foreach ( (array) ( $choices['options'] ?? array() ) as $value => $option ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => is_array( $option ) ? (string) ( $option['label'] ?? '' ) : (string) $option,
				'image' => is_array( $option ) && ! empty( $option['image'] ) ? (string) $option['image'] : null,
			);
		}
		$user_id = get_current_user_id();

		return array(
			'enabled'    => self::on(),
			'label'      => __( 'Iscriviti alla newsletter', 'fabermind-forms' ),
			'question'   => (string) ( $choices['label'] ?? '' ),
			'options'    => $options,
			// Chi ha gia' detto si' non se lo sente richiedere.
			'subscribed' => $user_id && (bool) get_user_meta( $user_id, 'fabermind_newsletter', true ),
		);
	}

	public static function question() {
		$response = rest_ensure_response( self::question_data() );
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/** Dice alla Store API che con l'ordine puo' arrivare la scelta sulla newsletter. */
	public static function extend(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::SPACE,
				'schema_type'     => ARRAY_A,
				'schema_callback' => static fn(): array => array(
					'newsletter' => array( 'type' => array( 'boolean', 'null' ) ),
					'terms'      => array(
						'type'  => array( 'array', 'null' ),
						'items' => array( 'type' => 'integer' ),
					),
				),
			)
		);
	}

	/**
	 * Ordine in arrivo da un'app (Store API): come la casella della cassa del sito.
	 *
	 * @param WC_Order        $order
	 * @param WP_REST_Request $request
	 */
	public static function save_from_app( $order, $request ): void {
		if ( ! self::on() || ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) {
			return;
		}
		$data = (array) ( ( (array) $request->get_param( 'extensions' ) )[ self::SPACE ] ?? array() );
		if ( empty( $data['newsletter'] ) ) {
			return;
		}
		$choices  = self::choices();
		$allowed  = $choices ? array_map( 'intval', array_keys( (array) $choices['options'] ) ) : array();
		$term_ids = array_values( array_intersect( array_map( 'intval', (array) ( $data['terms'] ?? array() ) ), $allowed ) );
		Fabermind_Forms_Handlers::preferences( (int) $order->get_customer_id(), (string) $order->get_billing_email(), (string) $order->get_billing_first_name(), (string) $order->get_billing_last_name(), $term_ids, true );
		$order->update_meta_data( '_fabermind_newsletter', 1 );
	}

	private static function on(): bool {
		return Fabermind_Forms_Settings::get()['checkout_newsletter'] && Fabermind_Forms_Mailster::available();
	}

	/** @return array<string,mixed>|null Il campo delle preferenze, se c'e' qualcosa da scegliere. */
	private static function choices(): ?array {
		$form = Fabermind_Forms::get( 'register' );

		return isset( $form['fields']['brands'] ) ? $form['fields']['brands'] : null;
	}

	public static function render(): void {
		if ( ! self::on() ) {
			return;
		}
		$choices = self::choices();
		echo '<div class="fabermind-forms group mt-4">';
		echo '<label class="label cursor-pointer gap-3 whitespace-normal text-base-content"><input type="checkbox" class="checkbox checkbox-primary checkbox-sm" name="' . esc_attr( self::BOX ) . '" value="1" data-fm-newsletter> <span>' . esc_html__( 'Iscriviti alla newsletter', 'fabermind-forms' ) . '</span></label>';
		if ( $choices ) {
			// Le preferenze compaiono solo con la casella spuntata.
			echo '<div class="mt-3 hidden group-has-[[data-fm-newsletter]:checked]:block">' . Fabermind_Forms_Renderer::field_html( 'fm-checkout-terms', self::FIELD, $choices ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}

	/**
	 * @param int      $order_id
	 * @param array    $posted
	 * @param WC_Order $order
	 */
	public static function save( $order_id, $posted, $order ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- la cassa di WooCommerce ha gia' controllato il suo nonce.
		if ( ! self::on() || ! $order instanceof WC_Order || empty( $_POST[ self::BOX ] ) ) {
			return;
		}
		$choices  = self::choices();
		$allowed  = $choices ? array_map( 'intval', array_keys( (array) $choices['options'] ) ) : array();
		$term_ids = array_values( array_intersect( array_map( 'intval', (array) wp_unslash( $_POST[ self::FIELD ] ?? array() ) ), $allowed ) );
		// phpcs:enable
		Fabermind_Forms_Handlers::preferences( (int) $order->get_customer_id(), (string) $order->get_billing_email(), (string) $order->get_billing_first_name(), (string) $order->get_billing_last_name(), $term_ids, true );
		$order->update_meta_data( '_fabermind_newsletter', 1 );
		$order->save();
	}
}
