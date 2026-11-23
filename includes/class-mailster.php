<?php
/**
 * Collegamento con la newsletter: quella di fabermind-mail quando è accesa, altrimenti Mailster.
 * Se non c'è né l'una né l'altro, qui non succede nulla.
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Mailster {

	/** Chi ha scelto quali squadre, tenuto da parte per qualche minuto. */
	private const CACHE = 'fabermind_forms_followers';

	public static function init(): void {
		add_action( 'created_term', array( self::class, 'ensure_list' ), 10, 3 );
		add_action( 'edited_term', array( self::class, 'ensure_list' ), 10, 3 );
		// Per le notifiche dell'app: chi sceglie una squadra per la newsletter la segue.
		add_filter( 'fabermind_app_newsletter_follows', array( self::class, 'followers' ) );
	}

	/**
	 * Chi ha scelto ogni squadra o brand della newsletter (in registrazione, alla cassa, o messo
	 * nella lista da Mailster). Serve a fabermind-app: chi ha scelto una squadra ne riceve le notifiche.
	 * Contano solo gli iscritti attivi che sono anche clienti del sito (le notifiche vanno ai clienti).
	 *
	 * @param array<string,int[]> $map
	 * @return array<string,int[]> "classificazione:id" => identificativi dei clienti.
	 */
	public static function followers( $map ): array {
		$map = (array) $map;
		if ( ! self::available() ) {
			return $map;
		}
		$found = get_transient( self::CACHE );
		if ( ! is_array( $found ) ) {
			$found = array();
			// Le liste hanno il nome della voce (vedi ensure_list).
			$keys = array();
			foreach ( (array) get_terms( array( 'taxonomy' => Fabermind_Forms_Settings::brand_taxonomies(), 'hide_empty' => false ) ) as $term ) {
				if ( $term instanceof WP_Term ) {
					$keys[ $term->name ][] = $term->taxonomy . ':' . $term->term_id;
				}
			}
			foreach ( self::lists_by_name() as $name => $list_id ) {
				if ( empty( $keys[ $name ] ) ) {
					continue;
				}
				$users = array();
				foreach ( self::members( $list_id ) as $subscriber ) {
					$subscriber = (object) $subscriber;
					$user       = (int) ( $subscriber->wp_id ?? 0 );
					if ( ! $user && ! empty( $subscriber->email ) ) {
						$account = get_user_by( 'email', (string) $subscriber->email );
						$user    = $account ? (int) $account->ID : 0;
					}
					if ( $user ) {
						$users[] = $user;
					}
				}
				foreach ( $keys[ $name ] as $key ) {
					$found[ $key ] = array_values( array_unique( $users ) );
				}
			}
			set_transient( self::CACHE, $found, 10 * MINUTE_IN_SECONDS );
		}
		foreach ( $found as $key => $users ) {
			$map[ $key ] = array_values( array_unique( array_merge( $map[ $key ] ?? array(), $users ) ) );
		}

		return $map;
	}

	/** Gli iscritti attivi di una lista (oggetti con wp_id ed email). */
	private static function members( int $list_id ): array {
		if ( ! self::own() ) {
			return (array) mailster( 'subscribers' )->query( array( 'lists' => (string) $list_id, 'fields' => 'all' ) );
		}
		$out = array();
		for ( $page = 1;; $page++ ) {
			$data = Fabermind_Mail_Subscribers::query( '', 'subscribed', $list_id, $page, 500 );
			foreach ( $data['rows'] as $row ) {
				$out[] = (object) array( 'wp_id' => (int) $row->user_id, 'email' => (string) $row->email );
			}
			if ( count( $data['rows'] ) < 500 ) {
				return $out;
			}
		}
	}

	public static function available(): bool {
		return self::own() || function_exists( 'mailster' );
	}

	/** La newsletter di fabermind-mail è accesa: si usa quella al posto di Mailster. */
	private static function own(): bool {
		return class_exists( 'Fabermind_Mail_Subscribers' ) && Fabermind_Mail_Subscribers::on();
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
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		if ( self::own() ) {
			Fabermind_Mail_Subscribers::list_id( $term->name, $term->taxonomy . ':' . $term->term_id );

			return;
		}
		if ( isset( self::lists_by_name()[ $term->name ] ) ) {
			return;
		}
		mailster( 'lists' )->add( array( 'name' => $term->name ) );
	}

	/**
	 * @return array<string,int> Nome della lista => identificativo.
	 */
	private static function lists_by_name(): array {
		$out = array();
		if ( self::own() ) {
			foreach ( Fabermind_Mail_Subscribers::lists() as $list ) {
				$out[ (string) $list->name ] = (int) $list->id;
			}

			return $out;
		}
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
	 * @param string   $source  Da dove arriva (checkout, register, app): resta come prova del consenso.
	 * @param string   $consent Il testo a cui ha detto sì.
	 * @return bool Se l'iscrizione e' andata a buon fine.
	 */
	public static function subscribe( string $email, string $first_name, string $last_name, array $list_names, bool $always = false, string $source = 'form', string $consent = '' ): bool {
		if ( ! self::available() || ( ! $list_names && ! $always ) ) {
			return false;
		}
		$lists = array_values( array_intersect_key( self::lists_by_name(), array_flip( $list_names ) ) );
		if ( ! $lists && ! $always ) {
			return false;
		}

		if ( self::own() ) {
			$id = Fabermind_Mail_Subscribers::subscribe(
				$email,
				array(
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'source'       => $source,
					'consent_text' => $consent,
					'double_optin' => Fabermind_Mail_Subscribers::settings()['double_optin'],
				),
				array_keys( array_intersect_key( self::lists_by_name(), array_flip( $list_names ) ) )
			);
			delete_transient( self::CACHE );

			return ! is_wp_error( $id );
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
		delete_transient( self::CACHE );

		return true;
	}
}
