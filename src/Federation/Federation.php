<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater\Federation;

/**
 * Class Federation
 *
 * Add federated repos and/or remove defederated repos in Git Updater Additions.
 */
class Federation {
	use \Fragen\Git_Updater\Traits\GU_Trait;

	// phpcs:disable Generic.Commenting.DocComment.MissingShort
	/** @var string */
	protected static $rest_endpoint = 'wp-json/git-updater/v1/get-additions-data/';

	/** @var array */
	protected static $additions;

	/** @var array */
	protected static $options;

	/** @var array */
	protected $response;
	// phpcs:enable

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$additions = get_site_option( 'git_updater_additions', [] );
		self::$options   = get_site_option( 'git_updater_federation', [] );
	}

	/**
	 * Start it up.
	 *
	 * @return void
	 */
	public function run() {
		$additions = [];
		foreach ( self::$options as $option ) {
			if ( 'Federated' === $option['type'] ) {
				$additions = $this->get_additions_data( $option['uri'] );
				continue;
			}
			if ( 'Defederated' === $option['type'] ) {
				$additions = $this->get_additions_data( $option['uri'] );
				continue;
			}
		}
		self::$additions = array_merge( self::$additions, $additions );
		self::$additions = array_map( 'unserialize', array_unique( array_map( 'serialize', self::$additions ) ) );
	}

	public function load_additions( $listing, $repos, $type ) {
		$this->run();
		$my_additions = self::$additions;
		$config       = get_site_option( 'git_updater_additions', [] );
		foreach ( self::$additions as $key => $addition ) {
			if ( ! str_contains( $addition['type'], $type ) ) {
				unset( $my_additions[ $key ] );
			}
		}
		foreach ( $config as $key => $addition ) {
			if ( ! str_contains( $addition['type'], $type ) ) {
				unset( $config[ $key ] );
			}
		}

		$config = array_merge( $config, $my_additions );
		$config = array_map( 'unserialize', array_unique( array_map( 'serialize', $config ) ) );
		$this->set_repo_cache( "git_updater_repository_add_{$type}", $config, "git_updater_repository_add_{$type}" );
		$additions = new \Fragen\Git_Updater\Additions\Additions();
		$additions->register( $config, $repos, $type );

		return $additions->add_to_git_updater;
	}

	/**
	 * Get REST API additions data.
	 *
	 * @param string $uri URI of federated/defederated server.
	 *
	 * @return array
	 */
	private function get_additions_data( string $uri ) {
		$response = $this->get_repo_cache( $uri );
		$response = $response ? $response[ $uri ] : $response;
		if ( ! $response ) {
			$endpoint = trailingslashit( $uri ) . self::$rest_endpoint;
			$response = wp_remote_post( $endpoint );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
				return [];
			}
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			$this->set_repo_cache( $uri, (array) $response, $uri, '+3 days' );
		}

		return (array) $response;
	}

	public function blast_cache_on_delete( $uri_hash ) {
		$options = get_site_option( 'git_updater_federation' );
		foreach ( $options as $option ) {
			if ( $uri_hash === $option['ID'] ) {
					$this->set_repo_cache( $option['uri'], false, $option['uri'] );
					$this->set_repo_cache( 'git_updater_repository_add_plugin', false, 'git_updater_repository_add_plugin' );
					$this->set_repo_cache( 'git_updater_repository_add_theme', false, 'git_updater_repository_add_theme' );	}
		}
	}
}
