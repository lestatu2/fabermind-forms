<?php
/**
 * Disegna i moduli con i componenti DaisyUI. L'invio lo fa lo script (assets/forms.js)
 * senza ricaricare la pagina.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Renderer {

	public static function init(): void {
		add_shortcode(
			'fabermind_form',
			static fn( $atts ): string => self::render( sanitize_key( (string) ( ( (array) $atts )['id'] ?? '' ) ) )
		);
	}

	/** Momento in cui il modulo e' stato stampato, firmato: serve a riconoscere gli invii automatici. */
	public static function stamp(): string {
		$time = (string) time();

		return $time . '.' . wp_hash( 'fabermind-forms|' . $time );
	}

	public static function render( string $id ): string {
		$form = Fabermind_Forms::get( $id );
		if ( ! $form ) {
			return '';
		}
		if ( ! empty( $form['guests'] ) && is_user_logged_in() ) {
			return '<p class="alert">' . esc_html__( 'Hai già fatto l\'accesso con il tuo account.', 'fabermind-forms' ) . '</p>';
		}

		wp_enqueue_script( 'fabermind-forms', FABERMIND_FORMS_URL . 'assets/forms.js', array(), FABERMIND_FORMS_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

		$uid = 'fm-' . $id . '-' . wp_unique_id();
		ob_start();
		?>
		<form class="not-prose grid gap-4 sm:grid-cols-2" method="post" novalidate data-fm-form="<?php echo esc_attr( $id ); ?>" data-endpoint="<?php echo esc_url( rest_url( 'fabermind-forms/v1/submit/' . $id ) ); ?>" data-error="<?php esc_attr_e( 'Non riesco a inviare il modulo. Riprova tra poco.', 'fabermind-forms' ); ?>">
			<?php foreach ( $form['fields'] as $name => $field ) : ?>
				<?php self::field( $uid . '-' . $name, (string) $name, $field ); ?>
			<?php endforeach; ?>

			<?php // Campo trappola: le persone non lo vedono, i programmi automatici lo riempiono. ?>
			<div class="hidden" aria-hidden="true">
				<label><?php esc_html_e( 'Lascia vuoto questo campo', 'fabermind-forms' ); ?> <input type="text" name="fm_website" tabindex="-1" autocomplete="off"></label>
			</div>
			<input type="hidden" name="fm_stamp" value="<?php echo esc_attr( self::stamp() ); ?>">

			<div class="sm:col-span-2" data-fm-message role="status" aria-live="polite"></div>
			<div class="sm:col-span-2">
				<button type="submit" class="btn btn-primary"><?php echo esc_html( (string) $form['button'] ); ?></button>
			</div>
			<noscript class="sm:col-span-2"><?php esc_html_e( 'Per inviare il modulo serve JavaScript attivo nel browser.', 'fabermind-forms' ); ?></noscript>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private static function field( string $id, string $name, array $field ): void {
		$type     = (string) $field['type'];
		$required = ! empty( $field['required'] );
		$wide     = 'half' === ( $field['width'] ?? '' ) ? '' : 'sm:col-span-2';
		$mark     = $required ? ' <span class="text-error" aria-hidden="true">*</span>' : '';
		$attrs    = ( $required ? ' required' : '' ) . ( isset( $field['autocomplete'] ) ? ' autocomplete="' . esc_attr( (string) $field['autocomplete'] ) . '"' : '' );

		echo '<div class="' . esc_attr( $wide ) . '" data-fm-field="' . esc_attr( $name ) . '">';

		if ( 'consent' === $type ) {
			echo '<label class="label items-start gap-3 whitespace-normal text-base-content"><input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-0.5" name="' . esc_attr( $name ) . '" value="1"' . $attrs . '><span>' . wp_kses_post( (string) $field['label'] ) . $mark . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'choices' === $type ) {
			echo '<fieldset><legend class="mb-3 font-medium">' . esc_html( (string) $field['label'] ) . $mark . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">';
			foreach ( (array) $field['options'] as $value => $option ) {
				echo '<label class="flex cursor-pointer flex-col items-center gap-2 rounded-box border border-base-300 p-3 text-center text-sm has-checked:border-primary has-checked:bg-primary/5">';
				if ( '' !== (string) $option['image'] ) {
					echo '<img src="' . esc_url( (string) $option['image'] ) . '" alt="" class="size-16 object-contain" loading="lazy">';
				}
				echo '<span>' . esc_html( (string) $option['label'] ) . '</span>';
				echo '<input type="checkbox" class="checkbox checkbox-primary checkbox-sm" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $value ) . '"></label>';
			}
			echo '</div></fieldset>';
		} else {
			echo '<label class="mb-1 block text-sm font-medium" for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . $mark . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( 'textarea' === $type ) {
				echo '<textarea class="textarea min-h-32 w-full" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '></textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( 'select' === $type ) {
				echo '<select class="select w-full" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '><option value="">' . esc_html__( 'Seleziona', 'fabermind-forms' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( (array) $field['options'] as $value => $label ) {
					echo '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $label ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input type="' . esc_attr( $type ) . '" class="input w-full" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) ( $field['value'] ?? '' ) ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( ! empty( $field['help'] ) ) {
				echo '<p class="mt-1 text-xs text-base-content/60">' . esc_html( (string) $field['help'] ) . '</p>';
			}
		}

		echo '<p class="mt-1 hidden text-sm text-error" data-fm-error></p></div>';
	}
}
