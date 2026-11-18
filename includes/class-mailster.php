<?php
/**
 * Collegamento con Mailster (la newsletter resta sua). Se Mailster non c'e', qui non succede nulla.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Mailster {

	public static function init(): void {
		add_action( 'created_term', array( self::class, 'ensure_list' ), 10, 3 );
		add_action( 'edited_term', array( self::class, 'ensure_list' ), 10, 3 );
	}

	public static function available(): bool {
		return function_exists( 'mailster' );
	}

	/**
	 * Una lista Mailster per ogni voce della classificazione scelta (per esempio una per squadra).
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public static function ensure_list( $term_id, $tt_id, $taxonomy ): void {
		$settings = Fabermind_Forms_Settings::get();
		if ( ! self::available() || ! $settings['sync_lists'] || ! in_array( $taxonomy, Fabermind_Forms_Settings::brand_taxonomies(), true ) ) {
			return;
		}
		$term = get_term( (int) $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term || isset( self::lists_by_name()[ $term->name ] ) ) {
			return;
		}
		mailster( 'lists' )->add( array( 'name' => $term->name ) );
	}

	/**
	 * @return array<string,int> Nome della lista => identificativo.
	 */
	private static function lists_by_name(): array {
		$out = array();
		foreach ( (array) mailster( 'lists' )->get() as $list ) {
			$out[ (string) $list->name ] = (int) $list->ID;
		}

		return $out;
	}

	/**
	 * Iscrive una persona alle liste con questi nomi. Le liste che non esistono vengono saltate.
	 *
	 * Con $always la persona diventa iscritta anche se non ha scelto liste (casella "newsletter").
	 *
	 * @param string[] $list_names
	 * @return bool Se l'iscrizione e' andata a buon fine.
	 */
	public static function subscribe( string $email, string $first_name, string $last_name, array $list_names, bool $always = false ): bool {
		if ( ! self::available() || ( ! $list_names && ! $always ) ) {
			return false;
		}
		$lists = array_values( array_intersect_key( self::lists_by_name(), array_flip( $list_names ) ) );
		if ( ! $lists && ! $always ) {
			return false;
		}

		// Come faceva il sito: iscrizione diretta (senza email di conferma), aggiornando chi c'e' gia'.
		$subscriber_id = mailster( 'subscribers' )->add(
			array(
				'email'     => $email,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'status'    => 1,
			),
			true
		);
		if ( is_wp_error( $subscriber_id ) || ! $subscriber_id ) {
			return false;
		}
		if ( $lists ) {
			mailster( 'subscribers' )->assign_lists( $subscriber_id, $lists );
		}

		return true;
	}
}
