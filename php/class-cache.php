<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;

/**
 * Plugin report class.
 */
class Cache extends Settings_Component {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the Connect component.
	 *
	 * @var Connect
	 */
	protected $connect;
	/**
	 * Holds the Rest API component.
	 *
	 * @var REST_API
	 */
	protected $api;
	/**
	 * Defaults for file cacheing.
	 *
	 * @var array
	 */
	public $file_cache_default = array(
		'version' => null,
		'files'   => array(),
	);

	/**
	 * Holds the meta keys to be used.
	 */
	const META_KEYS = array(
		'queue'           => '_cloudinary_cache_queue',
		'url'             => '_cloudinary_cache_url',
		'cached'          => '_cloudinary_cached',
		'plugin_files'    => '_cloudinary_plugin_files',
		'upload_error'    => '_cloudinary_upload_errors',
		'uploading_cache' => '_cloudinary_uploading_cache',
	);

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin  = $plugin;
		$this->media   = $this->plugin->get_component( 'media' );
		$this->connect = $this->plugin->get_component( 'connect' );
		$this->api     = $this->plugin->get_component( 'api' );
		$this->register_hooks();
		add_filter( 'template_include', array( $this, 'frontend_rewrite' ), PHP_INT_MAX );

		add_action( 'cloudinary_settings_save_setting_cache_theme', array( $this, 'clear_theme_cache' ), 10 );
		add_action( 'cloudinary_settings_save_setting_cache_plugins', array( $this, 'clear_theme_cache' ), 10 );
		add_action( 'cloudinary_settings_save_setting_cache_wordpress', array( $this, 'clear_wp_cache' ), 10 );

		add_action( 'admin_init', array( $this, 'admin_rewrite' ), 0 );
	}

	/**
	 * Rewrites urls in admin.
	 */
	public function admin_rewrite() {
		ob_start();
		add_action(
			'shutdown',
			function () {
				echo $this->html_rewrite( ob_get_clean() ); // phpcs:ignores WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			0
		);
	}

	/**
	 * Invalidate Theme file cache.
	 *
	 * @param string $new The new value being set.
	 *
	 * @return string
	 */
	public function clear_theme_cache( $new ) {
		if ( 'off' === $new ) {
			$theme    = wp_get_theme();
			$main_key = md5( $theme->get_stylesheet_directory() );
			delete_option( $main_key );
			if ( $theme->parent() ) {
				$parent_key = md5( $theme->parent()->get_stylesheet_directory() );
				delete_option( $parent_key );
			}
		} else {
			$this->get_theme_paths();
		}

		return $new;
	}

	/**
	 * Invalidate Plugin cache.
	 *
	 * @param string $new The new value being set.
	 *
	 * @return string
	 */
	public function clear_plugin_cache( $new ) {
		if ( 'off' === $new ) {
			$plugins        = get_plugins();
			$active_plugins = (array) get_option( 'active_plugins', array() );
			foreach ( $active_plugins as $plugin ) {
				$plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $plugin );
				$folder_key    = md5( $plugin_folder );
				delete_option( $folder_key );
			}
		} else {
			$this->get_plugin_paths();
		}

		return $new;
	}

	/** Invalidate the WP cache.
	 *
	 * @param string $new The new value being set.
	 *
	 * @return string
	 */
	public function clear_wp_cache( $new ) {
		if ( 'off' === $new ) {
			$admin    = md5( ABSPATH . 'wp-admin' );
			$includes = md5( ABSPATH . 'wp-includes' );
			delete_option( $admin );
			delete_option( $includes );
		} else {
			$this->get_wp_paths();
		}

		return $new;
	}

	/**
	 * Get paths for plugins selection, or all.
	 *
	 * @param string $all On for all, off for selected.
	 *
	 * @return array
	 */
	protected function get_plugin_selection_paths( $all ) {
		if ( 'on' === $all ) {
			return $this->get_plugin_paths();
		}

		$plugins            = $this->settings->get_value( 'plugin_files_table' );
		$plugins            = array_filter(
			$plugins,
			'is_array'
		);
		$all_paths          = array();
		$plugins_folder_len = strlen( WP_PLUGIN_DIR ) + 1;
		foreach ( $plugins as $paths ) {
			foreach ( $paths as $path ) {
				$short_part        = substr( $path, $plugins_folder_len );
				$url               = plugins_url( wp_normalize_path( $short_part ) );
				$all_paths[ $url ] = $path;
			}
		}

		return $all_paths;
	}

	/**
	 * Get the file paths for the plugins.
	 *
	 * @param string $plugin_path The plugin path.
	 *
	 * @return array
	 */
	protected function get_plugin_paths( $plugin_path = null ) {
		$paths   = array();
		$plugins = get_plugins();
		if ( ! is_null( $plugin_path ) ) {
			$active_plugins = (array) $plugin_path;
		} else {
			$active_plugins = (array) get_option( 'active_plugins', array() );
		}
		foreach ( $active_plugins as $plugin ) {
			$key = 'plugin_cache_' . basename( dirname( $plugin ) );
			if ( 'off' === $this->plugin->settings->get_value( $key ) ) {
				continue;
			}
			$plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $plugin );
			$files         = $this->get_folder_files( $plugin_folder, $plugins[ $plugin ]['Version'], 'plugins_url', false );
			$paths         = array_merge( $paths, $files );
		}

		return $paths;
	}

	/**
	 * Get the theme file paths.
	 *
	 * @return array
	 */
	protected function get_theme_paths() {

		$theme = wp_get_theme();
		$paths = $this->get_folder_files(
			$theme->get_stylesheet_directory(),
			$theme->get( 'Version' ),
			function ( $file ) use ( $theme ) {
				return $theme->get_stylesheet_directory_uri() . $file;
			}
		);
		if ( $theme->parent() ) {
			$parent = $theme->parent();
			$paths += $this->get_folder_files(
				$parent->get_stylesheet_directory(),
				$parent->get( 'Version' ),
				function ( $file ) use ( $parent ) {
					return $parent->get_stylesheet_directory_uri() . $file;
				}
			);
		}

		return $paths;
	}

	/**
	 * Get the file paths for WordPress.
	 *
	 * @return array
	 */
	protected function get_wp_paths() {
		$version = get_bloginfo( 'version' );
		$paths   = $this->get_folder_files( ABSPATH . 'wp-admin', $version, 'admin_url' );
		$paths  += $this->get_folder_files( ABSPATH . 'wp-includes', $version, 'includes_url' );

		return $paths;
	}

	/**
	 * Get the paths for scanning.
	 *
	 * @return array
	 */
	protected function get_paths() {
		$paths = array();
		if ( 'on' === $this->plugin->settings->get_value( 'enable_site_cache' ) ) {
			$paths += $this->get_plugin_selection_paths( $this->settings->get_value( 'cache_all_plugins' ) );

			if ( 'on' === $this->plugin->settings->get_value( 'cache_theme' ) ) {
				$paths += $this->get_theme_paths();
			}

			if ( 'on' === $this->plugin->settings->get_value( 'cache_wordpress' ) ) {
				$paths += $this->get_wp_paths();
			}
		}

		return $paths;
	}

	/**
	 * Rewrite urls on frontend.
	 *
	 * @param string $template The frontend template being loaded.
	 *
	 * @return string
	 */
	public function frontend_rewrite( $template ) {

		$paths = $this->get_paths();

		if ( empty( $paths ) ) {
			return $template;
		}

		ob_start();
		include $template;
		$html = ob_get_clean();

		$html = $this->html_rewrite( $html );
		// Push to output stream.
		file_put_contents( 'php://output', $html ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		return 'php://output';
	}

	/**
	 * Rewrite HTML by replacing local URLS with Remote URLS.
	 *
	 * @param string $html The HTML to rewrite.
	 *
	 * @return string
	 */
	public function html_rewrite( $html ) {
		$base_url = md5( filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL ) );
		$paths    = $this->get_paths();

		if ( empty( $paths ) ) {
			return $html;
		}
		$sources = get_transient( $base_url );
		if ( empty( $sources ) ) {
			$sources = $this->build_sources( $paths, $html );
			$expire  = 60;
			if ( empty( $sources['url'] ) && ! empty( $sources['upload_pending'] ) ) {
				// Set to a short since it's still syncing.
				$expire = 5;
			}
			set_transient( $base_url, $sources, $expire );
		}

		// Replace all sources if we have some URLS.
		if ( ! empty( $sources['url'] ) ) {
			$html = str_replace( $sources['url'], $sources['cld'], $html );
		}

		return $html;
	}

	/**
	 * Build sources for a set of paths and HTML.
	 *
	 * @param array  $paths The paths to use.
	 * @param string $html  The html to build against.
	 *
	 * @return array[]|null
	 */
	protected function build_sources( $paths, $html ) {
		$paths = array_filter(
			$paths,
			function ( $path, $url ) use ( $html ) {
				return strpos( $html, $url );
			},
			ARRAY_FILTER_USE_BOTH
		);
		preg_match_all( '#(' . implode( '|', array_keys( $paths ) ) . ')\b([-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $html, $result );
		if ( empty( $result[0] ) ) {
			return null;
		}

		$sources = array(
			'url' => array(),
			'cld' => array(),
		);

		foreach ( $result[1] as $index => $url ) {
			$params         = $this->src_version( $paths[ $url ] );
			$cloudinary_url = $this->get_cached_url( $url, $params['version'], $params['file'], false );
			if ( is_wp_error( $cloudinary_url ) ) {
				// Multiple Synced error.
				continue;
			}
			if ( $url === $cloudinary_url ) {
				// No remote yet, flag pending.
				$sources['upload_pending'] = true;
			} else {
				$sources['url'][] = $url;
				$sources['cld'][] = $cloudinary_url;
			}
		}

		if ( ! empty( $sources['upload_pending'] ) && empty( get_transient( self::META_KEYS['uploading_cache'] ) ) ) {
			$this->api->background_request( 'upload_cache' );
		}

		return $sources;
	}

	/**
	 * Get and sanitize files from a folder.
	 *
	 * @param string $folder       The folder to get from.
	 * @param string $version      The version.
	 * @param string $callback     The callback for sanitizing the url.
	 * @param bool   $strip_folder Flag for stripping the basename.
	 *
	 * @return array
	 */
	protected function get_folder_files( $folder, $version, $callback = 'home_url', $strip_folder = true ) {
		if ( ! is_callable( $callback ) ) {
			$callback = 'home_url';
		}
		$folder_key   = md5( $folder );
		$folder_cache = get_option( $folder_key, $this->file_cache_default );
		if ( empty( $folder_cache['files'] ) || $folder_cache['version'] !== $version ) {
			$folder_cache['files']   = array();
			$folder_cache['version'] = $version;
			$found                   = $this->get_files( $folder );
			foreach ( $found as $file ) {
				$strip_length                  = $strip_folder ? strlen( $folder ) : strlen( dirname( $folder ) . '/' );
				$file_part                     = substr( $file, $strip_length );
				$url                           = call_user_func( $callback, wp_normalize_path( $file_part ) );
				$folder_cache['files'][ $url ] = $file . '?ver=' . $version;
			}
			// Add files cache.
			update_option( $folder_key, $folder_cache, false );
		}

		return $folder_cache['files'];
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Register the sync endpoint.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return mixed
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['upload_cache'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'upload_cache' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		return $endpoints;
	}

	/**
	 * Admin permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public function rest_can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Start uploading files to cloudinary cache.
	 */
	public function upload_cache() {
		if ( empty( get_transient( self::META_KEYS['uploading_cache'] ) ) ) {
			set_transient( self::META_KEYS['uploading_cache'], true, 20 ); // Flag a transient to prevent multiple background uploads.
			$paths     = $this->get_paths();
			$to_upload = array();
			// Pre-get items to upload.
			foreach ( $paths as $url => $file ) {
				$params         = $this->src_version( $file );
				$cloudinary_url = $this->get_cached_url( $url, $params['version'], $params['file'], false );
				if ( $url === $cloudinary_url ) {
					$params['url'] = $url;
					// No remote yet, add to list.
					$to_upload[] = $params;
				}
			}
			foreach ( $to_upload as $index => $upload ) {
				set_transient( self::META_KEYS['uploading_cache'], true, 20 ); // Flag a transient to prevent multiple background uploads.
				do_action( '_cloudinary_queue_action', $action_message );
				$this->get_cached_url( $upload['url'], $upload['version'], $upload['file'] );
			}
		}
	}

	/**
	 * Separate a file with ver param into path and version array.
	 *
	 * @param string $file The file path to use.
	 *
	 * @return array
	 */
	protected function src_version( $file ) {
		$file_query = wp_parse_url( $file, PHP_URL_QUERY );
		parse_str( $file_query, $query );
		$file_source = remove_query_arg( 'ver', $file );

		return array(
			'file'    => $file_source,
			'version' => $query['ver'],
		);
	}

	/**
	 * Get files from a folder.
	 *
	 * @param string $path The file path.
	 *
	 * @return array
	 */
	public function get_files( $path ) {
		$exclude      = array(
			'node_modules',
			'vendor',
		);
		$excluded_ext = array(
			'php',
			'json',
			'map',
			'scss',
			'md',
			'txt',
			'xml',
			'crt',
		);
		$included_ext = array(
			'png',
			'jpg',
			'gif',
			'js',
		);
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$files  = list_files( $path, PHP_INT_MAX, $exclude );
		$return = array_filter(
			$files,
			function ( $file ) use ( $excluded_ext ) {
				return ! in_array( pathinfo( $file, PATHINFO_EXTENSION ), $excluded_ext, true );
			}
		);

		sort( $return );

		return $return;
	}

	/**
	 * Get a cached URL.
	 *
	 * @param string $local_url     The local URL.
	 * @param string $version       The version.
	 * @param string $file_location The file path.
	 * @param bool   $upload        Flag to set upload.
	 *
	 * @return string
	 */
	public function get_cached_url( $local_url, $version, $file_location, $upload = true ) {
		$option_key = $this->get_cache_option_key( $file_location );
		$cache      = get_option( $option_key, array() );
		if ( empty( $cache[ $local_url ] ) || $cache[ $local_url ]['ver'] !== $version ) {
			$cache[ $local_url ] = array(
				'ver' => $version,
				'url' => $local_url,
			);
			if ( true === $upload ) {
				$remote_url = $this->sync_static( $file_location );
				if ( ! empty( $remote_url ) ) {
					$cache[ $local_url ]['url'] = $remote_url;
					update_option( $option_key, $cache, false );
				}
			}
		}

		return $cache[ $local_url ]['url'];
	}

	/**
	 * Get the option name for where the file will be stored.
	 *
	 * @param string $file_location The file path.
	 *
	 * @return string
	 */
	protected function get_cache_option_key( $file_location ) {
		$plugins_path = WP_PLUGIN_DIR;
		$theme        = get_theme_root();
		$include      = ABSPATH . 'wp-includes';
		$admin        = ABSPATH . 'wp-admin';
		if ( false !== strpos( $file_location, $plugins_path ) ) {
			$parts = explode( '/', substr( $file_location, strlen( $plugins_path ) ) );
		} elseif ( false !== strpos( $file_location, $theme ) ) {
			$parts = explode( '/', substr( $file_location, strlen( $theme ) ) );
		} elseif ( false !== strpos( $file_location, $include ) ) {
			$parts = array( 'wp_includes' );
		} elseif ( false !== strpos( $file_location, $admin ) ) {
			$parts = array( 'wp_admin' );
		} else {
			$parts = array( 'custom' );
		}
		$parts    = array_filter( $parts );
		$location = array_shift( $parts );

		return self::META_KEYS['cached'] . '_' . $location;
	}

	/**
	 * Upload a static file.
	 *
	 * @param string $file The file path to upload.
	 *
	 * @return string|\WP_Error
	 */
	protected function sync_static( $file ) {
		$errored = get_option( self::META_KEYS['upload_error'], array() );
		if ( isset( $errored[ $file ] ) && 3 <= $errored[ $file ] ) {
			// Dont try again.
			return new \WP_Error( 'upload_error' );
		}
		$folder    = $this->media->get_cloudinary_folder() . $this->plugin->settings->get_value( 'cache_folder' );
		$file_path = $folder . '/' . substr( $file, strlen( ABSPATH ) );
		$public_id = dirname( $file_path ) . '/' . pathinfo( $file, PATHINFO_FILENAME );
		$type      = $this->media->get_file_type( $file );
		$options   = array(
			'file'          => $file,
			'resource_type' => 'auto',
			'public_id'     => wp_normalize_path( $public_id ),
		);

		if ( 'image' === $type ) {
			$options['eager'] = 'f_auto,q_auto';
		}

		$data = $this->connect->api->upload( 0, $options, array(), false );
		if ( is_wp_error( $data ) ) {
			$errored[ $file ] = isset( $errored[ $file ] ) ? $errored[ $file ] + 1 : 1;
			update_option( self::META_KEYS['upload_error'], $errored );

			return null;
		}

		$url = $data['secure_url'];
		if ( ! empty( $data['eager'] ) ) {
			$url = $data['eager'][0]['secure_url'];
		}
		// Strip out version number.
		$url = str_replace( '/v' . $data['version'], '', $url );

		return $url;
	}

	/**
	 * Get paths for plugins, filtered by type and shorted to start from plugin root.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array|void
	 */
	protected function get_filtered_plugin_paths( $slug ) {

		$paths = $this->get_plugin_paths( $slug );

		if ( empty( $paths ) ) {
			return;
		}
		$include = array(
			'jpg',
			'png',
			'svg',
			'css',
			'js',
		);
		$urls    = array_map(
			function ( $url ) use ( $include ) {
				$path = wp_parse_url( $url, PHP_URL_PATH );
				$ext  = pathinfo( $path, PATHINFO_EXTENSION );
				if ( ! in_array( $ext, $include, true ) ) {
					return false;
				}
				$path = substr( $path, strlen( WP_PLUGIN_DIR ) + 1 );
				$dir  = strstr( $path, '/', false );

				return $dir;
			},
			$paths
		);

		$urls = array_unique( array_filter( $urls ) );

		return $urls;
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array|mixed
	 */
	protected function get_plugins_table() {

		$plugins_setup = get_transient( 'cache_plugins_tree' );
		if ( ! empty( $plugins_setup ) ) {
			return $plugins_setup;
		}
		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();

		$alignment = array(
			'style' => 'text-align:right;width:20%;',
		);

		$lists  = array(
			'images'  => array(),
			'css'     => array(),
			'js'      => array(),
			'plugins' => array(),
		);
		$params = array(
			'type'    => 'table',
			'slug'    => 'plugin_files',
			'columns' => array(),
		);
		foreach ( $active as $plugin_path ) {
			$slug   = basename( dirname( $plugin_path ) );
			$plugin = $slug . '/' . basename( $plugin_path );
			$slug   = md5( $plugin );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}

			$details       = $plugins[ $plugin ];
			$plugin_params = array(
				'plugin_name' => array(
					array(
						array(
							'slug'   => 'name_' . $slug,
							'type'   => 'on_off',
							'master' => array(
								'plugins_master',
							),
						),
						array(
							'type'             => 'icon_toggle',
							'slug'             => 'toggle_' . $slug,
							'description_left' => $details['Name'],
							'off'              => 'dashicons-arrow-up',
							'on'               => 'dashicons-arrow-down',
						),
						array(
							'type'       => 'tag',
							'element'    => 'span',
							'content'    => '',
							'render'     => true,
							'attributes' => array(
								'id'    => 'name_' . $slug . '_size_wrapper',
								'class' => array(
									'file-size',
									'small',
								),
							),
						),
					),
				),
				'images'      => array(
					'attributes' => $alignment,
					array(
						'type'       => 'tag',
						'element'    => 'span',
						'content'    => '',
						'render'     => true,
						'attributes' => array(
							'id'    => 'images_' . $slug . '_size_wrapper',
							'class' => array(
								'file-size',
								'small',
							),
						),
					),
					array(
						'type'   => 'on_off',
						'slug'   => 'images_' . $slug,
						'master' => array(
							'images_master',
							'name_' . $slug,
						),
					),
				),
				'css'         => array(
					'attributes' => $alignment,
					array(
						'type'       => 'tag',
						'element'    => 'span',
						'content'    => '',
						'render'     => true,
						'attributes' => array(
							'id'    => 'css_' . $slug . '_size_wrapper',
							'class' => array(
								'file-size',
								'small',
							),
						),
					),
					array(
						'type'   => 'on_off',
						'slug'   => 'css_' . $slug,
						'master' => array(
							'name_' . $slug,
							'css_master',
						),
					),

				),
				'js'          => array(
					'attributes' => $alignment,
					array(
						'type'       => 'tag',
						'element'    => 'span',
						'content'    => '',
						'render'     => true,
						'attributes' => array(
							'id'    => 'js_' . $slug . '_size_wrapper',
							'class' => array(
								'file-size',
								'small',
							),
						),
					),
					array(
						'type'   => 'on_off',
						'slug'   => 'js_' . $slug,
						'master' => array(
							'name_' . $slug,
							'js_master',
						),
					),
				),
				'reload'      => array(
					'type' => 'icon',
					'icon' => 'dashicons-update',
				),
			);

			$lists['images'][]  = 'images_' . $slug;
			$lists['css'][]     = 'css_' . $slug;
			$lists['js'][]      = 'js_' . $slug;
			$lists['plugins'][] = 'name_' . $slug;

			$params['rows'][ $slug ]             = $plugin_params;
			$params['rows'][ $slug . '_spacer' ] = array();
			$params['rows'][ $slug . '_tree' ]   = array(
				'plugin_name' => array(
					'condition' => array(
						'toggle_' . $slug => true,
					),
					array(
						'slug'       => $slug . '_files',
						'type'       => 'file_folder',
						'base_path'  => dirname( $plugin_path ),
						'paths'      => $this->get_filtered_plugin_paths( $plugin ),
						'file_types' => array(
							'png' => 'images_' . $slug,
							'svg' => 'images_' . $slug,
							'css' => 'css_' . $slug,
							'js'  => 'js_' . $slug,
						),
					),
				),
			);

		}

		$params['columns'] = array(
			'plugin_name' => array(
				array(
					'slug'        => 'plugins_master',
					'type'        => 'on_off',
					'description' => __( 'Plugin', 'cloudinary' ),
				),
			),
			'images'      => array(
				'attributes' => $alignment,
				array(
					'slug'             => 'images_master',
					'type'             => 'on_off',
					'description_left' => __( 'Images', 'cloudinary' ),
					'master'           => array(
						'plugins_master',
					),
				),
			),
			'css'         => array(
				'attributes' => $alignment,
				array(
					'slug'             => 'css_master',
					'type'             => 'on_off',
					'description_left' => __( 'CSS', 'cloudinary' ),
					'master'           => array(
						'plugins_master',
					),
				),
			),
			'js'          => array(
				'attributes' => $alignment,
				array(
					'slug'             => 'js_master',
					'type'             => 'on_off',
					'description_left' => __( 'JS', 'cloudinary' ),
					'master'           => array(
						'plugins_master',
					),
				),
			),
		);

		set_transient( 'cache_plugins_tree', $params );

		return $params;
	}

	/**
	 * Returns the setting definitions.
	 *
	 * @return array
	 */
	public function settings() {

		$plugins_setup = $this->get_plugins_table();

		$args = array(
			'type'       => 'page',
			'menu_title' => __( 'Site Cache', 'cloudinary' ),
			'tabs'       => array(
				'cache_extras'  => array(
					'page_title' => __( 'Site Cache', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Cache Settings', 'cloudinary' ),
						array(
							'type'         => 'on_off',
							'slug'         => 'enable_site_cache',
							'title'        => __( 'Full CDN', 'cloudinary' ),
							'tooltip_text' => __(
								'Deliver all assets from Cloudinary.',
								'cloudinary'
							),
							'description'  => __( 'Enable caching site assets.', 'cloudinary' ),
							'default'      => 'off',
						),
						array(
							'type'      => 'group',
							'condition' => array(
								'enable_site_cache' => true,
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_theme',
								'title'       => __( 'Theme', 'cloudinary' ),
								'description' => __( 'Deliver assets in active theme.', 'cloudinary' ),
								'default'     => 'off',
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_wordpress',
								'title'       => __( 'WordPress', 'cloudinary' ),
								'description' => __( 'Deliver assets for WordPress.', 'cloudinary' ),
								'default'     => 'off',
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_custom',
								'title'       => __( 'Custom Folders', 'cloudinary' ),
								'description' => __( 'Deliver assets from custom folders.', 'cloudinary' ),
								'default'     => 'off',
							),
							array(
								'type'      => 'group',
								'condition' => array(
									'cache_custom' => true,
								),
								array(
									'type'       => 'tag',
									'element'    => 'div',
									'slug'       => 'custom_folder_tree',
									'attributes' => array(
										'class' => array(
											'tree',
										),
									),
								),
							),
							array(
								'type'    => 'text',
								'slug'    => 'cache_folder',
								'title'   => __( 'Cache folder', 'cloudinary' ),
								'default' => wp_parse_url( get_site_url(), PHP_URL_HOST ),
							),
						),

					),
					array(
						'type' => 'submit',
					),
				),
				'cache_plugins' => array(
					'page_title' => __( 'Plugins', 'cloudinary' ),
					array(
						'type'    => 'panel',
						'title'   => __( 'Plugins', 'cloudinary' ),
						'content' => __( 'Deliver assets in active plugins.', 'cloudinary' ),
						array(
							'type'             => 'on_off',
							'slug'             => 'cache_all_plugins',
							'description_left' => __( 'Deliver selection', 'cloudinary' ),
							'description'      => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
							'default'          => 'off',
							'disabled_color'   => '#2a0',
						),
						array(
							'type'      => 'group',
							'condition' => array(
								'cache_all_plugins' => false,
							),
							$plugins_setup,
						),
					),
					array(
						'type' => 'submit',
					),
				),
			),
		);

		return $args;
	}
}
