<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater\Collections;

/**
 * Class Settings
 */
class Settings {
	/**
	 * Holds the values for collections settings.
	 *
	 * @var array $option_remote
	 */
	public static $options_collections;

	/**
	 * Supported types.
	 *
	 * @var array $addition_types
	 */
	public static $collections_types = [];

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		$this->load_options();
		self::$collections_types = [
			'collection' => __( 'Collection', 'git-updater-collections' ),
		];
	}

	/**
	 * Load site options.
	 */
	private function load_options() {
		self::$options_collections = get_site_option( 'git_updater_collections', [] );
		$this->add_settings_tabs();
	}

	/**
	 * Load needed action/filter hooks.
	 */
	public function load_hooks() {
		add_action(
			'gu_update_settings',
			function ( $post_data ) {
				$this->save_settings( $post_data );
			}
		);

		add_filter(
			'gu_add_admin_page',
			function ( $tab, $action ) {
				$this->add_admin_page( $tab, $action );
			},
			10,
			2
		);
	}

	/**
	 * Save Collections settings.
	 *
	 * @uses 'gu_update_settings' action hook
	 * @uses 'gu_save_redirect' filter hook
	 *
	 * @param array $post_data $_POST data.
	 */
	public function save_settings( $post_data ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'git_updater_collections-options' ) ) {
			return;
		}
		$options   = (array) get_site_option( 'git_updater_collections', [] );
		$duplicate = false;
		$bad_input = false;
		if ( isset( $post_data['option_page'] ) &&
			'git_updater_collections' === $post_data['option_page']
		) {
			$new_options = $post_data['git_updater_collections'] ?? [];
			$new_options = $this->sanitize( $new_options );
			$bad_input   = empty( $new_options[0]['uri'] );

			foreach ( $options as $option ) {
				$duplicate = in_array( $new_options[0]['ID'], $option, true );
				if ( $duplicate || $bad_input ) {
					$_POST['action'] = false;
					break;
				}
			}

			if ( ! $duplicate && ! $bad_input ) {
				$options = array_merge( $options, $new_options );
				$options = array_filter( $options );
				update_site_option( 'git_updater_collections', $options );
			}

			add_filter(
				'gu_save_redirect',
				function ( $option_page ) {
					return array_merge( $option_page, [ 'git_updater_collections' ] );
				}
			);
		}
	}

	/**
	 * Adds Collections tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_collections' => esc_html__( 'Collections', 'git-updater-collections' ) ];
		add_filter(
			'gu_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			},
			20,
			1
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'gu_add_admin_page' action hook
	 *
	 * @param string $tab    Tab name.
	 * @param string $action Form action.
	 */
	public function add_admin_page( $tab, $action ) {
		$this->collections_page_init();

		if ( 'git_updater_collections' === $tab ) {
			$action = add_query_arg(
				[
					'page' => 'git-updater',
					'tab'  => $tab,
				],
				$action
			);
			( new Repo_List_Table( self::$options_collections ) )->render_list_table();
			?>
			<form class="settings" method="post" action="<?php echo esc_attr( $action ); ?>">
				<?php
				settings_fields( 'git_updater_collections' );
				do_settings_sections( 'git_updater_collections' );
				submit_button();
				?>
			</form>
			<?php
		}
	}

	/**
	 * Settings for Additions.
	 */
	public function collections_page_init() {
		register_setting(
			'git_updater_collections',
			'git_updater_collections',
			[]
		);

		add_settings_section(
			'git_updater_collections',
			esc_html__( 'Update API Server', 'git-updater-collections' ),
			[ $this, 'print_section_collections' ],
			'git_updater_collections'
		);

		add_settings_field(
			'uri',
			esc_html__( 'URI', 'git-updater-collections' ),
			[ $this, 'callback_field' ],
			'git_updater_collections',
			'git_updater_collections',
			[
				'id'      => 'git_updater_collections_uri',
				'setting' => 'uri',
				'title'   => __( 'Ensure proper URI for Update API Server.', 'git-updater-collections' ),
			]
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys.
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = [];

		foreach ( (array) $input as $key => $value ) {
			$new_input[0][ $key ] = 'uri' === $key ? untrailingslashit( esc_url_raw( trim( $value ) ) ) : sanitize_text_field( $value );
		}
		$new_input[0]['ID'] = md5( $new_input[0]['uri'] );

		return $new_input;
	}

	/**
	 * Print the Collections text.
	 */
	public function print_section_collections() {
		echo '<p>';
		esc_html_e( 'Add URI for Git Updater Update API Servers here.', 'git-updater-collections' );
		echo '</p>';
	}

	/**
	 * Field callback.
	 *
	 * @param array $args Data passed from add_settings_field().
	 *
	 * @return void
	 */
	public function callback_field( $args ) {
		$placeholder = $args['placeholder'] ?? null;
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<input type="text" style="width:50%;" id="<?php esc_attr( $args['id'] ); ?>" name="git_updater_collections[<?php echo esc_attr( $args['setting'] ); ?>]" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<br>
			<span class="description">
				<?php echo esc_attr( $args['title'] ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Dropdown callback.
	 *
	 * @param array $args Data passed from add_settings_field().
	 *
	 * @return void
	 */
	public function callback_dropdown( $args ) {
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
		<select id="<?php echo esc_attr( $args['id'] ); ?>" name="git_updater_collections[<?php echo esc_attr( $args['setting'] ); ?>]">
		<?php
		foreach ( self::$collections_types as $item ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $item ),
				selected( 'type', $item, false ),
				esc_html( $item )
			);

		}
		?>
		</select>
		</label>
		<?php
	}
}
