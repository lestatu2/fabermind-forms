<?php
/**
 * Alla cassa di WooCommerce: casella "Iscriviti alla newsletter" e, se spuntata, le stesse
 * preferenze della registrazione ("Su quale squadra desideri ricevere news e offerte?").
 * Vale anche per chi compra senza account. L'iscrizione va a Mailster (liste con il nome delle
 * voci scelte); se c'e' l'account, le preferenze restano anche nel profilo.
 *
 * Si accende in Impostazioni > Moduli Fabermind o con il filtro fabermind_forms_defaults
 * (checkout_newsletter).
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Checkout {

	private const BOX   = 'fabermind_newsletter';
	private const FIELD = 'fabermind_newsletter_terms';

	public static function init(): void {
		add_action( 'woocommerce_after_checkout_billing_form', array( self::class, 'render' ) );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'save' ), 20, 3 );
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
