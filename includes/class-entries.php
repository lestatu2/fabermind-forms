<?php
/**
 * Archivio di quello che arriva dai moduli: chi ha scritto, quando, cosa. Ogni invio valido
 * viene registrato prima di tutto il resto, cosi' non si perde nemmeno se l'email non parte.
 * Si vede in bacheca, alla voce "Messaggi".
 */

defined( 'ABSPATH' ) || exit;

final class Fabermind_Forms_Entries {

	private const SCHEMA   = '1';
	private const PAGE     = 'fabermind-forms-entries';
	private const PER_PAGE = 30;

	public static function init(): void {
		self::maybe_install();
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		Fabermind_Forms_Ui::for_pages( self::PAGE );
		add_action( 'admin_post_fabermind_forms_entry', array( self::class, 'handle' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_filter( 'fabermind_dashboard_blocks', array( self::class, 'dashboard_block' ) );
	}

	/**
	 * Riquadro "Messaggi dai moduli" nel riepilogo del negozio (plugin Fabermind Dashboard, se c'e').
	 *
	 * @param array<string,array<string,mixed>> $blocks
	 * @return array<string,array<string,mixed>>
	 */
	public static function dashboard_block( array $blocks ): array {
		$blocks['forms'] = array(
			'title' => __( 'Messages from forms', 'fabermind-forms' ),
			'type'  => 'counter',
			'cap'   => 'manage_options', // Chi puo' aprire la pagina dei messaggi.
			'data'  => static function (): array {
				$unread = self::unread();

				return array(
					'value' => $unread,
					'note'  => __( 'unread', 'fabermind-forms' ),
					'link'  => array( 'label' => __( 'Read messages', 'fabermind-forms' ), 'url' => self::url() ),
				);
			},
		);

		return $blocks;
	}

	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'fabermind_forms_entries';
	}

	private static function maybe_install(): void {
		if ( get_option( 'fabermind_forms_entries_schema' ) === self::SCHEMA ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form varchar(60) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			outcome varchar(255) NOT NULL DEFAULT '',
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form (form),
			KEY email (email),
			KEY is_read (is_read)
			) $charset;"
		);
		update_option( 'fabermind_forms_entries_schema', self::SCHEMA, false );
	}

	/**
	 * Registra un invio. Le password non si salvano mai.
	 *
	 * @param array<string,mixed> $form   Definizione del modulo (titolo, campi).
	 * @param array<string,mixed> $values Valori gia' controllati.
	 * @return int Identificativo della riga.
	 */
	public static function store( string $form_id, array $form, array $values ): int {
		global $wpdb;
		$rows = array();
		foreach ( $form['fields'] as $name => $field ) {
			if ( 'password' === ( $field['type'] ?? '' ) || ! array_key_exists( $name, $values ) ) {
				continue;
			}
			$value = $values[ $name ];
			if ( is_array( $value ) ) {
				// Scelte multiple: si salva quello che l'utente ha letto, non il numero dietro.
				$value = implode( ', ', array_map( static fn( $v ): string => (string) ( $field['options'][ $v ] ?? $v ), $value ) );
			} elseif ( isset( $field['options'][ $value ] ) ) {
				$value = (string) $field['options'][ $value ];
			} elseif ( 'consent' === ( $field['type'] ?? '' ) ) {
				$value = $value ? __( 'Yes', 'fabermind-forms' ) : __( 'No', 'fabermind-forms' );
			}
			$rows[] = array( 'label' => wp_strip_all_tags( (string) ( $field['label'] ?? $name ) ), 'value' => (string) $value );
		}
		$name = trim( (string) ( $values['name'] ?? ( ( $values['first_name'] ?? '' ) . ' ' . ( $values['last_name'] ?? '' ) ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			self::table(),
			array(
				'form'       => $form_id,
				'name'       => mb_substr( $name, 0, 190 ),
				'email'      => mb_substr( (string) ( $values['email'] ?? '' ), 0, 190 ),
				'user_id'    => get_current_user_id(),
				'data'       => wp_json_encode( $rows ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/** Com'e' andata dopo: "ok", oppure il motivo per cui il modulo non e' andato a buon fine. */
	public static function outcome( int $id, string $outcome ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), array( 'outcome' => mb_substr( $outcome, 0, 255 ) ), array( 'id' => $id ) );
	}

	private static function unread(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_read = 0' );
	}

	// ---------- Bacheca ----------

	public static function menu(): void {
		$unread = self::unread();
		$label  = __( 'Messages', 'fabermind-forms' ) . ( $unread ? ' <span class="awaiting-mod">' . $unread . '</span>' : '' );
		add_menu_page( __( 'Messages from forms', 'fabermind-forms' ), $label, 'manage_options', self::PAGE, array( self::class, 'page' ), 'dashicons-email-alt', 26 );
	}

	/** @param array<string,mixed> $args */
	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You don\'t have permission to do this.', 'fabermind-forms' ) );
		}
		check_admin_referer( 'fabermind-forms-entry' );
		global $wpdb;
		$id = absint( $_REQUEST['entry'] ?? 0 );
		if ( $id && 'delete' === ( $_REQUEST['do'] ?? '' ) ) {
			$wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} elseif ( $id && 'unread' === ( $_REQUEST['do'] ?? '' ) ) {
			$wpdb->update( self::table(), array( 'is_read' => 0 ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function page(): void {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id    = absint( $_GET['entry'] ?? 0 );
		$form  = isset( $_GET['form'] ) ? sanitize_key( $_GET['form'] ) : '';
		$paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$forms = array();
		foreach ( Fabermind_Forms::all() as $key => $def ) {
			$forms[ $key ] = (string) ( $def['title'] ?? $key );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Messages from forms', 'fabermind-forms' ) . '</h1>';

		if ( $id ) {
			$entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
			echo '<p><a href="' . esc_url( self::url() ) . '">&larr; ' . esc_html__( 'All messages', 'fabermind-forms' ) . '</a></p>';
			if ( ! $entry ) {
				echo '<p>' . esc_html__( 'Message not found.', 'fabermind-forms' ) . '</p></div>';

				return;
			}
			$wpdb->update( self::table(), array( 'is_read' => 1 ), array( 'id' => $id ) );
			echo '<div class="postbox" style="padding:4px 16px 16px;max-width:860px"><table class="form-table" role="presentation"><tbody>';
			echo '<tr><th>' . esc_html__( 'Form', 'fabermind-forms' ) . '</th><td>' . esc_html( $forms[ $entry->form ] ?? $entry->form ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Received on', 'fabermind-forms' ) . '</th><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at . ' UTC' ) ) ) . '</td></tr>';
			if ( (int) $entry->user_id ) {
				echo '<tr><th>' . esc_html__( 'Account', 'fabermind-forms' ) . '</th><td><a href="' . esc_url( (string) get_edit_user_link( (int) $entry->user_id ) ) . '">' . esc_html( (string) get_userdata( (int) $entry->user_id )->display_name ) . '</a></td></tr>';
			}
			foreach ( (array) json_decode( (string) $entry->data, true ) as $row ) {
				$is_mail = is_email( $row['value'] );
				echo '<tr><th>' . esc_html( $row['label'] ) . '</th><td>' . ( $is_mail ? '<a href="mailto:' . esc_attr( $row['value'] ) . '">' . esc_html( $row['value'] ) . '</a>' : nl2br( esc_html( $row['value'] ) ) ) . '</td></tr>';
			}
			if ( '' !== $entry->outcome && 'ok' !== $entry->outcome ) {
				echo '<tr><th>' . esc_html__( 'Outcome', 'fabermind-forms' ) . '</th><td style="color:#b32d2e">' . esc_html( $entry->outcome ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo Fabermind_Forms_Ui::open( 'div', 'flex flex-wrap gap-2 mt-4' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $entry->email ) {
				echo '<a class="btn btn-primary btn-sm" href="mailto:' . esc_attr( $entry->email ) . '">' . Fabermind_Forms_Ui::icon( 'reply' ) . esc_html__( 'Reply', 'fabermind-forms' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<a class="btn btn-sm" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fabermind_forms_entry', 'do' => 'unread', 'entry' => $id ), admin_url( 'admin-post.php' ) ), 'fabermind-forms-entry' ) ) . '">' . Fabermind_Forms_Ui::icon( 'mail' ) . esc_html__( 'Mark as unread', 'fabermind-forms' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<a class="btn btn-sm btn-ghost text-error" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fabermind_forms_entry', 'do' => 'delete', 'entry' => $id ), admin_url( 'admin-post.php' ) ), 'fabermind-forms-entry' ) ) . '">' . Fabermind_Forms_Ui::icon( 'trash-2' ) . esc_html__( 'Delete', 'fabermind-forms' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			echo '</div></div>';

			return;
		}

		$where = $form ? $wpdb->prepare( ' WHERE form = %s', $form ) : '';
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . $where );
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . self::table() . $where . $wpdb->prepare( ' ORDER BY id DESC LIMIT %d OFFSET %d', self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) );
		// phpcs:enable

		echo '<ul class="subsubsub"><li><a href="' . esc_url( self::url() ) . '" class="' . ( '' === $form ? 'current' : '' ) . '">' . esc_html__( 'All', 'fabermind-forms' ) . '</a></li>';
		foreach ( $forms as $key => $title ) {
			echo '<li> | <a href="' . esc_url( self::url( array( 'form' => $key ) ) ) . '" class="' . ( $form === $key ? 'current' : '' ) . '">' . esc_html( $title ) . '</a></li>';
		}
		echo '</ul><table class="wp-list-table widefat striped"><thead><tr>';
		foreach ( array( __( 'From', 'fabermind-forms' ), __( 'Email', 'fabermind-forms' ), __( 'Form', 'fabermind-forms' ), __( 'Received on', 'fabermind-forms' ) ) as $head ) {
			echo '<th>' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No messages.', 'fabermind-forms' ) . '</td></tr>';
		}
		foreach ( (array) $rows as $entry ) {
			$bold = (int) $entry->is_read ? '' : 'font-weight:600';
			echo '<tr><td style="' . esc_attr( $bold ) . '"><a href="' . esc_url( self::url( array( 'entry' => $entry->id ) ) ) . '">' . esc_html( $entry->name ?: __( '(no name)', 'fabermind-forms' ) ) . '</a></td>';
			echo '<td>' . esc_html( $entry->email ) . '</td><td>' . esc_html( $forms[ $entry->form ] ?? $entry->form ) . '</td>';
			echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at . ' UTC' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > self::PER_PAGE ) {
			echo Fabermind_Forms_Ui::open( 'p', 'flex gap-2 mt-4' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $paged > 1 ) {
				echo '<a class="btn btn-sm btn-square" aria-label="' . esc_attr__( 'Previous page', 'fabermind-forms' ) . '" href="' . esc_url( self::url( array( 'form' => $form, 'paged' => $paged - 1 ) ) ) . '">' . Fabermind_Forms_Ui::icon( 'chevron-left' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( $paged * self::PER_PAGE < $total ) {
				echo '<a class="btn btn-sm btn-square" aria-label="' . esc_attr__( 'Next page', 'fabermind-forms' ) . '" href="' . esc_url( self::url( array( 'form' => $form, 'paged' => $paged + 1 ) ) ) . '">' . Fabermind_Forms_Ui::icon( 'chevron-right' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</p>';
		}
		echo '</div>';
	}

	// ---------- Privacy: cancellazione dei dati di una persona ----------

	/** @param array<string,array<string,mixed>> $erasers */
	public static function register_eraser( array $erasers ): array {
		$erasers['fabermind-forms'] = array( 'eraser_friendly_name' => __( 'Messages from forms', 'fabermind-forms' ), 'callback' => array( self::class, 'erase' ) );

		return $erasers;
	}

	/** @return array<string,mixed> */
	public static function erase( string $email ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$removed = (int) $wpdb->delete( self::table(), array( 'email' => $email ) );

		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
