<?php
/**
 * Cloudinary non media library assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Assets\Rest_Assets;
use Cloudinary\Connect\Api;
use Cloudinary\Sync;
use Cloudinary\Traits\Params_Trait;
use Cloudinary\Utils;

/**
 * Class Assets
 *
 * @package Cloudinary
 */
class Assets extends Settings_Component {

	use Params_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	public $media;

	/**
	 * Holds the Delivery instance.
	 *
	 * @var Delivery
	 */
	public $delivery;

	/**
	 * Post type.
	 *
	 * @var \WP_Post_Type
	 */
	protected $post_type;

	/**
	 * Holds registered asset parents.
	 *
	 * @var \WP_Post[]
	 */
	protected $asset_parents;

	/**
	 * Holds active asset parents.
	 *
	 * @var \WP_Post[]
	 */
	protected $active_parents = array();

	/**
	 * Holds a list of found urls that need to be created.
	 *
	 * @var array
	 */
	protected $to_create;

	/**
	 * Holds the ID's of assets.
	 *
	 * @var array
	 */
	protected $asset_ids;

	/**
	 * Holds the Assets REST instance.
	 *
	 * @var Rest_Assets
	 */
	protected $rest;

	/**
	 * Holds the post type.
	 */
	const POST_TYPE_SLUG = 'cloudinary_asset';

	/**
	 * Holds the meta keys.
	 *
	 * @var array
	 */
	const META_KEYS = array(
		'excludes' => '_excluded_urls',
		'lock'     => '_asset_lock',
	);

	/**
	 * Static instance of this class.
	 *
	 * @var self
	 */
	public static $instance;

	/**
	 * Assets constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->media    = $plugin->get_component( 'media' );
		$this->delivery = $plugin->get_component( 'delivery' );
		// Add activation hooks.
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );
		self::$instance = $this;

		// Set separator.
		$this->separator = '/';
	}

	/**
	 * Init the class.
	 */
	public function init() {
		$this->register_post_type();
		$this->init_asset_parents();
		$this->register_hooks();
		$this->rest = new Rest_Assets( $this );
	}

	/**
	 * Register the hooks.
	 */
	protected function register_hooks() {

		// Filters.
		add_filter( 'cloudinary_is_content_dir', array( $this, 'check_asset' ), 10, 2 );
		add_filter( 'cloudinary_is_media', array( $this, 'is_media' ), 10, 2 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'add_sync_type' ) );
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'no_sizes' ), PHP_INT_MAX, 3 );
		add_filter( 'cloudinary_can_sync_asset', array( $this, 'can_sync' ), 10, 2 );
		add_filter( 'cloudinary_local_url', array( $this, 'local_url' ), 10, 2 );
		add_filter( 'cloudinary_is_folder_synced', array( $this, 'filter_folder_sync' ), 10, 2 );
		add_filter( 'cloudinary_asset_state', array( $this, 'filter_asset_state' ), 10, 2 );
		// Actions.
		add_action( 'cloudinary_init_settings', array( $this, 'setup' ) );
		add_action( 'cloudinary_thread_queue_details_query', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_build_queue_query', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'add_url_replacements' ), 20 );
		add_action( 'shutdown', array( $this, 'meta_updates' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_cache' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Filter the asset state to allow syncing in manual.
	 *
	 * @param int $state         The current state.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function filter_asset_state( $state, $attachment_id ) {
		if ( self::is_asset_type( $attachment_id ) || ! $this->media->sync->been_synced( $attachment_id ) ) {
			$state = 0;
		}

		return $state;
	}

	/**
	 * Filter to ensure an asset type is never identified as folder synced.
	 *
	 * @param bool $is            Flag to indicate is folder synced.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function filter_folder_sync( $is, $attachment_id ) {
		if ( self::is_asset_type( $attachment_id ) ) {
			$is = false;
		}

		return $is;
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		if ( 'on' === $this->plugin->settings->image_settings->_overlay ) {
			wp_enqueue_script( 'front-overlay', $this->plugin->dir_url . 'js/front-overlay.js', array(), $this->plugin->version, true );
			wp_enqueue_style( 'front-overlay', $this->plugin->dir_url . 'css/front-overlay.css', array(), $this->plugin->version );
		}
	}

	/**
	 * Get the local url for an asset.
	 *
	 * @hook cloudinary_local_url
	 *
	 * @param string|false $url      The url to filter.
	 * @param int          $asset_id The asset ID.
	 *
	 * @return string|false
	 */
	public function local_url( $url, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) ) {
			$url = get_the_title( $asset_id );
		}

		return $url;
	}

	/**
	 * Add Cloudinary menu to admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar The admin bar object.
	 */
	public function admin_bar_cache( $admin_bar ) {
		if ( ! Utils::user_can( 'clear_cache' ) || is_admin() ) {
			return;
		}

		$parent = array(
			'id'    => 'cloudinary-cache',
			'title' => __( 'Cloudinary Cache', 'cloudinary' ),
			'meta'  => array(
				'title' => __( 'Cloudinary Cache', 'cloudinary' ),
			),
		);
		$admin_bar->add_menu( $parent );

		$nonce = wp_create_nonce( 'cloudinary-cache-clear' );
		$clear = array(
			'id'     => 'cloudinary-clear-cache',
			'parent' => 'cloudinary-cache',
			'title'  => '{cld-cache-counter}',
			'href'   => '?cloudinary-cache-clear=' . $nonce,
			'meta'   => array(
				'title' => __( 'Purge', 'cloudinary' ),
				'class' => 'cloudinary-{cld-cache-status}',
			),
		);
		$admin_bar->add_menu( $clear );

		$nonce   = wp_create_nonce( 'cloudinary-cache-overlay' );
		$overlay = array(
			'id'     => 'cloudinary-overlay',
			'parent' => 'cloudinary-cache',
			'title'  => __( 'Show overlay', 'cloudinary' ),
			'href'   => '?cloudinary-cache-overlay=' . $nonce,
			'meta'   => array(
				'title' => __( 'Show overlay', 'cloudinary' ),
				'class' => 'cloudinary-{cld-overlay-status}',
			),
		);
		$admin_bar->add_menu( $overlay );
	}

	/**
	 * Sets the autosync to work on cloudinary_assets even when the autosync is disabled.
	 *
	 * @hook cloudinary_can_sync_asset
	 *
	 * @param bool $can      The can sync check value.
	 * @param int  $asset_id The asset ID.
	 *
	 * @return bool
	 */
	public function can_sync( $can, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) || 'off' === $this->settings->get_value( 'auto_sync' ) && 'on' === $this->settings->get_value( 'content.enabled' ) ) {
			$can = true;
		}

		return $can;
	}

	/**
	 * Check if the post is a asset post type.
	 *
	 * @param int $post_id The ID to check.
	 *
	 * @return bool
	 */
	public static function is_asset_type( $post_id ) {
		$post = get_post( $post_id );

		return ! in_array( $post, self::$instance->get_asset_parents(), true ) && self::POST_TYPE_SLUG === get_post_type( $post_id );
	}

	/**
	 * Filter out sizes for assets.
	 *
	 * @hook intermediate_image_sizes_advanced
	 *
	 * @param array    $new_sizes     The sizes to remove.
	 * @param array    $image_meta    The image meta.
	 * @param int|null $attachment_id The asset ID.
	 *
	 * @return array
	 */
	public function no_sizes( $new_sizes, $image_meta, $attachment_id = null ) {
		if ( is_null( $attachment_id ) ) {
			$attachment_id = $this->plugin->settings->get_param( '_currrent_attachment', 0 );
		}
		if ( self::is_asset_type( $attachment_id ) ) {
			$new_sizes = array();
		}

		return $new_sizes;
	}

	/**
	 * Compiles all metadata and preps upload at shutdown.
	 *
	 * @hook shutdown
	 */
	public function meta_updates() {
		if ( $this->is_locked() ) {
			return;
		}

		if ( ! empty( $this->delivery->unusable ) ) {
			$assets = array();
			foreach ( $this->delivery->unusable as $unusable ) {
				if ( isset( $this->active_parents[ $unusable['parent_path'] ] ) && ! in_array( $unusable['post_id'], $assets, true ) ) {
					$asset_id = (int) $unusable['post_id'];
					$this->media->sync->set_signature_item( $asset_id, 'cld_asset', 'reset' );
					$this->media->sync->add_to_sync( $asset_id );
					$assets[] = $unusable['post_id'];
				}
			}
		}

		// Create found asset that's not media library.
		if ( ! empty( $this->to_create ) && ! empty( $this->delivery->unknown ) ) {
			foreach ( $this->delivery->unknown as $url ) {
				if ( isset( $this->to_create[ $url ] ) ) {
					$this->create_asset( $url, $this->to_create[ $url ] );
				}
			}
		}
	}

	/**
	 * Set urls to be replaced.
	 *
	 * @hook cloudinary_string_replace
	 */
	public function add_url_replacements() {
		$clear   = filter_input( INPUT_GET, 'cloudinary-cache-clear', FILTER_SANITIZE_STRING );
		$overlay = filter_input( INPUT_GET, 'cloudinary-cache-overlay', FILTER_SANITIZE_STRING );
		$setting = $this->plugin->settings->image_settings->overlay;

		if ( $clear && wp_verify_nonce( $clear, 'cloudinary-cache-clear' ) ) {
			$referrer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL );
			if ( ! empty( $this->delivery->known ) ) {
				$delete = array();
				foreach ( $this->delivery->known as $set ) {
					if ( is_int( $set ) || empty( $set['public_id'] ) || 'asset' !== $set['sync_type'] || in_array( $set['post_id'], $delete, true ) ) {
						continue;
					}
					Delivery::update_size_relations_public_id( $set['post_id'], null );
					$delete[] = $set['post_id'];
				}
			}
			wp_safe_redirect( $referrer );
			exit;
		}

		if ( $overlay && wp_verify_nonce( $overlay, 'cloudinary-cache-overlay' ) ) {
			$referrer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL );
			if ( $setting->get_value() === 'on' ) {
				$setting->save_value( 'off' );
			} else {
				$setting->save_value( 'on' );
			}
			wp_safe_redirect( $referrer );
			exit;
		}
		$total = 0;
		if ( ! empty( $this->delivery->known ) ) {

			foreach ( $this->delivery->known as $url => $set ) {
				if ( is_int( $set ) || empty( $set['public_id'] ) ) {
					continue;
				}
				$public_id = $set['public_id'];
				if ( ! empty( $set['format'] ) ) {
					$public_id .= '.' . $set['format'];
				}
				$cloudinary_url = $this->media->cloudinary_url( $set['post_id'], array( $set['width'], $set['height'] ), null, $public_id );
				if ( $cloudinary_url ) {
					// Late replace on unmatched urls (links, inline styles etc..), both http and https.
					String_Replace::replace( 'http:' . $url, $cloudinary_url );
					String_Replace::replace( 'https:' . $url, $cloudinary_url );
				}
				$total ++;
			}
			String_Replace::replace( '{cld-cache-status}', 'on' );
		} else {
			String_Replace::replace( '{cld-cache-status}', 'off' );
		}
		// translators: Placeholders are the number of items.
		$message = sprintf( _n( '%s cached item', '%s cached items', $total, 'cloudinary' ), number_format_i18n( $total ) );
		String_Replace::replace( '{cld-cache-counter}', $message );
		String_Replace::replace( '{cld-overlay-status}', ! empty( $setting->get_value() ) ? $setting->get_value() : 'off' );

	}

	/**
	 * Connect our post type to the sync query, to allow it to be queued.
	 *
	 * @hook cloudinary_thread_queue_details_query, cloudinary_build_queue_query
	 *
	 * @param array $query The Query.
	 *
	 * @return array
	 */
	public function connect_post_type( $query ) {

		$query['post_type'] = array_merge( (array) $query['post_type'], (array) self::POST_TYPE_SLUG );

		return $query;
	}

	/**
	 * Register an asset path.
	 *
	 * @param string $path    The path/URL to register.
	 * @param string $version The version.
	 */
	public static function register_asset_path( $path, $version ) {
		$assets = self::$instance;
		if ( $assets && ! $assets->is_locked() ) {
			$asset_path = $assets->get_asset_parent( $path );
			if ( null === $asset_path ) {
				$asset_parent_id = $assets->create_asset_parent( $path, $version );
				if ( is_wp_error( $asset_parent_id ) ) {
					return; // Bail.
				}
				$asset_path = get_post( $asset_parent_id );
			}
			// Check and update version if needed.
			if ( $assets->media->get_post_meta( $asset_path->ID, Sync::META_KEYS['version'], true ) !== $version ) {
				$assets->media->update_post_meta( $asset_path->ID, Sync::META_KEYS['version'], $version );
			}
			$assets->activate_parent( $path );
		}
	}

	/**
	 * Activate a parent asset path.
	 *
	 * @param string $url The path to activate.
	 */
	public function activate_parent( $url ) {
		$url = $this->clean_path( $url );
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$this->active_parents[ $url ] = $this->asset_parents[ $url ];
			$this->set_param( trim( $url, $this->separator ), $this->asset_parents[ $url ] );
		}
	}

	/**
	 * Clean a path for saving as a title.
	 *
	 * @param string $path The path to clean.
	 *
	 * @return string
	 */
	public function clean_path( $path ) {
		$home = Delivery::clean_url( trailingslashit( home_url() ) );
		$path = str_replace( $home, '', Delivery::clean_url( $path ) );
		if ( empty( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$path = trailingslashit( $path );
		}

		return $path;
	}

	/**
	 * Create an asset parent.
	 *
	 * @param string $path    The path to create.
	 * @param string $version The version.
	 *
	 * @return int|\WP_Error
	 */
	public function create_asset_parent( $path, $version ) {
		$path      = $this->clean_path( $path );
		$args      = array(
			'post_title'  => $path,
			'post_name'   => md5( $path ),
			'post_type'   => self::POST_TYPE_SLUG,
			'post_status' => 'publish',
		);
		$parent_id = wp_insert_post( $args );
		if ( $parent_id ) {
			$this->media->update_post_meta( $parent_id, Sync::META_KEYS['version'], $version );
			$this->media->update_post_meta( $parent_id, self::META_KEYS['excludes'], array() );
			$this->asset_parents[ $path ] = get_post( $parent_id );
		}

		return $parent_id;
	}

	/**
	 * Purge a single asset parent.
	 *
	 * @param int $parent_id The Asset parent to purge.
	 */
	public function purge_parent( $parent_id ) {
		$query_args     = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'posts_per_page'         => 100,
			'post_parent'            => $parent_id,
			'post_status'            => array( 'inherit', 'draft' ),
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$query          = new \WP_Query( $query_args );
		$previous_total = $query->found_posts;
		do {
			$this->lock_assets();
			$posts = $query->get_posts();
			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id );
			}

			$query_args = $query->query_vars;
			$query      = new \WP_Query( $query_args );
			if ( $previous_total === $query->found_posts ) {
				break;
			}
		} while ( $query->have_posts() );

		// Clear out excludes.
		wp_delete_post( $parent_id );
	}

	/**
	 * Lock asset creation for performing things like purging that require no changes.
	 */
	public function lock_assets() {
		set_transient( self::META_KEYS['lock'], true, 10 );
	}

	/**
	 * Unlock asset creation.
	 */
	public function unlock_assets() {
		delete_transient( self::META_KEYS['lock'] );
	}

	/**
	 * Check if assets are locked.
	 *
	 * @return bool
	 */
	public function is_locked() {
		return get_transient( self::META_KEYS['lock'] );
	}

	/**
	 * Generate the signature for sync.
	 *
	 * @param int $asset_id The attachment/asset ID.
	 *
	 * @return string
	 */
	public function generate_file_signature( $asset_id ) {
		$path   = $this->clean_path( $this->media->local_url( $asset_id ) );
		$parent = $this->get_param( $path );
		$str    = $asset_id;
		if ( $parent ) {
			$str .= $parent->post_date;
		}

		return $str;
	}

	/**
	 * Upload an asset.
	 *
	 * @param int $asset_id The asset ID to upload.
	 *
	 * @return array|\WP_Error
	 */
	public function upload( $asset_id ) {
		$connect = $this->plugin->get_component( 'connect' );

		if ( self::is_asset_type( $asset_id ) ) {
			$asset = get_post( $asset_id );
			$url   = $asset->post_title;
		} else {
			$url = Delivery::clean_url( $this->media->local_url( $asset_id ) );
		}
		$path      = trim( wp_normalize_path( str_replace( home_url(), '', $url ) ), '/' );
		$info      = pathinfo( $path );
		$public_id = $info['dirname'] . '/' . $info['filename'];
		$options   = array(
			'unique_filename' => false,
			'overwrite'       => true,
			'resource_type'   => $this->media->get_resource_type( $asset_id ),
			'public_id'       => $public_id,
		);
		$result    = $connect->api->upload( $asset_id, $options, array() );
		if ( ! is_wp_error( $result ) && isset( $result['public_id'] ) ) {
			Delivery::update_size_relations_public_id( $asset_id, $public_id );
			Delivery::update_size_relations_state( $asset_id, 'enable' );
			$this->media->sync->set_signature_item( $asset_id, 'file' );
			$this->media->sync->set_signature_item( $asset_id, 'cld_asset' );
		}

		return $result;
	}

	/**
	 * Validate if sync type is valid.
	 *
	 * @param int $attachment_id The attachment id to validate.
	 *
	 * @return bool
	 */
	public function validate_asset_sync( $attachment_id ) {

		// Default is either a asset type or auto sync off, if it's a media library item.
		$valid = self::is_asset_type( $attachment_id ) || ! $this->media->sync->is_auto_sync_enabled();

		// Check to see if there is a parent. If there is, then the asset is enabled to be synced.
		if ( true === $valid ) {
			$parent = $this->find_parent( $attachment_id );
			if ( ! $parent ) {
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Register our sync type.
	 *
	 * @hook  cloudinary_sync_base_struct
	 *
	 * @param array $structs The structure of all sync types.
	 *
	 * @return array
	 */
	public function add_sync_type( $structs ) {
		$structs['cld_asset'] = array(
			'generate'    => array( $this, 'generate_file_signature' ),
			'priority'    => 2,
			'sync'        => array( $this, 'upload' ),
			'validate'    => array( $this, 'validate_asset_sync' ),
			'state'       => 'disabled',
			'note'        => __( 'Caching', 'cloudinary' ),
			'required'    => true,
			'asset_state' => 0,
		);

		return $structs;
	}

	/**
	 * Init asset parents.
	 */
	protected function init_asset_parents() {

		$args                = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'post_parent'            => 0,
			'posts_per_page'         => 100,
			'paged'                  => 1,
			'post_status'            => 'publish',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$query               = new \WP_Query( $args );
		$this->asset_parents = array();

		do {
			foreach ( $query->get_posts() as $post ) {
				$this->asset_parents[ $post->post_title ] = $post;
			}
			$args = $query->query_vars;
			$args['paged'] ++;
			$query = new \WP_Query( $args );
		} while ( $query->have_posts() );

	}

	/**
	 * Check if the non-local URL should be added as an asset.
	 *
	 * @hook cloudinary_is_content_dir
	 *
	 * @param bool   $is_local The is_local flag.
	 * @param string $url      The URL to check.
	 *
	 * @return bool
	 */
	public function check_asset( $is_local, $url ) {
		if ( $is_local || ! $this->syncable_asset( $url ) ) {
			return $is_local;
		}

		$found = null;
		$try   = $this->clean_path( $url );
		while ( false !== strpos( $try, $this->separator ) ) {
			$try = substr( $try, 0, strripos( $try, $this->separator ) );
			if ( ! empty( $try ) && $this->has_param( $try ) ) {
				$found = $this->get_param( $try );
				break;
			}
		}
		if ( $found instanceof \WP_Post ) {
			$is_local                = true;
			$this->to_create[ $url ] = $found->ID;
		}

		return $is_local;
	}

	/**
	 * Check if the asset is syncable.
	 *
	 * @param string $filename The filename to check.
	 *
	 * @return bool
	 */
	protected function syncable_asset( $filename ) {
		static $allowed_kinds = array();
		if ( empty( $allowed_kinds ) ) {
			// Check with paths.
			$types         = wp_get_ext_types();
			$allowed_kinds = array_merge( $allowed_kinds, $types['image'], $types['audio'], $types['video'] );
		}
		$type = pathinfo( $filename, PATHINFO_EXTENSION );

		return in_array( $type, $allowed_kinds, true );
	}

	/**
	 * Get the asset src file.
	 *
	 * @hook get_attached_file
	 *
	 * @param string $file     The file as from the filter.
	 * @param int    $asset_id The asset ID.
	 *
	 * @return string
	 */
	public function get_attached_file( $file, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) && ! file_exists( $file ) ) {
			$dirs = wp_get_upload_dir();
			$file = str_replace( trailingslashit( $dirs['basedir'] ), ABSPATH, $file );
		}

		return $file;
	}

	/**
	 * Check to see if the post is a media item.
	 *
	 * @hook cloudinary_is_media
	 *
	 * @param bool $is_media      The is_media flag.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_media( $is_media, $attachment_id ) {
		if ( false === $is_media && self::is_asset_type( $attachment_id ) ) {
			$is_media = true;
		}

		return $is_media;
	}

	/**
	 * Get all asset parents.
	 *
	 * @return \WP_Post[]
	 */
	public function get_asset_parents() {
		$parents = array();
		if ( ! empty( $this->asset_parents ) ) {
			$parents = $this->asset_parents;
		}

		return $parents;
	}

	/**
	 * Get all asset parents.
	 *
	 * @return \WP_Post[]
	 */
	public function get_active_asset_parents() {
		$parents = array();
		if ( ! empty( $this->active_parents ) ) {
			$parents = $this->active_parents;
		}

		return $parents;
	}

	/**
	 * Find a parent for an asset.
	 *
	 * @param int $asset_id The asset id.
	 *
	 * @return \WP_Post|null;
	 */
	public function find_parent( $asset_id ) {
		$path   = $this->clean_path( $this->media->local_url( $asset_id ) );
		$parent = $this->get_param( $path );

		return $parent instanceof \WP_Post ? $parent : null;
	}

	/**
	 * Get an asset parent.
	 *
	 * @param string $url The URL of the parent.
	 *
	 * @return \WP_Post|null
	 */
	public function get_asset_parent( $url ) {
		$url    = $this->clean_path( $url );
		$parent = null;
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$parent = $this->asset_parents[ $url ];
		}

		return $parent;
	}

	/**
	 * Create a new asset item.
	 *
	 * @param string $url       The assets url.
	 * @param int    $parent_id The asset parent ID.
	 *
	 * @return false|int|\WP_Error
	 */
	protected function create_asset( $url, $parent_id ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$full_url  = home_url() . wp_parse_url( $url, PHP_URL_PATH );
		$file_path = str_replace( home_url(), untrailingslashit( ABSPATH ), $full_url );
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		$base        = get_post( $parent_id )->post_title;
		$size        = getimagesize( $file_path );
		$size        = $size[0] . 'x' . $size[1];
		$hash_name   = md5( $url );
		$wp_filetype = wp_check_filetype( basename( $url ), wp_get_mime_types() );
		$args        = array(
			'post_title'     => $url,
			'post_content'   => '',
			'post_name'      => $hash_name,
			'post_mime_type' => $wp_filetype['type'],
			'post_type'      => self::POST_TYPE_SLUG,
			'post_parent'    => $parent_id,
			'post_status'    => 'inherit',
		);
		$id          = wp_insert_post( $args );

		// Create attachment meta.
		update_attached_file( $id, $file_path );
		wp_generate_attachment_metadata( $id, $file_path );

		// Init the auto sync.
		Delivery::create_size_relation( $id, $url, $url, $size, $base );
		Delivery::update_size_relations_state( $id, 'enable' );
		$this->media->sync->set_signature_item( $id, 'delivery' );
		$this->media->sync->get_sync_type( $id );
		$this->media->sync->add_to_sync( $id );

		return $id;
	}

	/**
	 * Register the post type.
	 */
	protected function register_post_type() {
		$args            = array(
			'label'               => __( 'Cloudinary Asset', 'cloudinary' ),
			'description'         => __( 'Post type to represent a non-media library asset.', 'cloudinary' ),
			'labels'              => array(),
			'supports'            => false,
			'hierarchical'        => true,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capability_type'     => 'page',
		);
		$this->post_type = register_post_type( self::POST_TYPE_SLUG, $args );
	}

	/**
	 * Setup the class.
	 *
	 * @hook cloudinary_init_settings
	 */
	public function setup() {

		$assets = $this->settings->get_setting( 'assets' )->get_settings();
		foreach ( $assets as $asset ) {

			$paths = $asset->get_setting( 'paths' );

			foreach ( $paths->get_settings() as $path ) {
				if ( 'on' === $path->get_value() ) {
					$conf = $path->get_params();
					self::register_asset_path( trailingslashit( $conf['url'] ), $conf['version'] );
				}
			}
		}

		// Get the disabled items.
		foreach ( $this->asset_parents as $url => $parent ) {
			if ( isset( $this->active_parents[ $url ] ) ) {
				continue;
			}
			$this->purge_parent( $parent->ID );
		}
	}

	/**
	 * Returns the setting definitions.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {
		$pages['connect']['settings'][] = array(
			'type'                => 'panel',
			'title'               => __( 'Site Asset Sync Settings', 'cloudinary' ),
			'slug'                => 'cache',
			'option_name'         => 'site_cache',
			'requires_connection' => true,
			'collapsible'         => 'open',
			'attributes'          => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'               => 'on_off',
				'slug'               => 'enable',
				'optimisation_title' => __( 'Non-media library files optimisation', 'cloudinary' ),
				'tooltip_text'       => __( 'Enabling site asset syncing will sync the toggled assets with Cloudinary to make use of advanced optimization and CDN delivery functionality.', 'cloudinary' ),
				'description'        => __( 'Enable site asset syncing', 'cloudinary' ),
				'default'            => 'off',
			),
			array(
				'type'       => 'button',
				'slug'       => 'cld_purge_all',
				'attributes' => array(
					'type'        => 'button',
					'html_button' => array(
						'disabled' => 'disabled',
						'style'    => 'width: 100px',
					),
				),
				'label'      => 'Purge all',
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_plugin_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_theme_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_wp_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_content_settings(),
			),
		);

		$pages['connect']['settings'][] = array(
			'type'        => 'panel',
			'title'       => __( 'External Asset Sync Settings', 'cloudinary' ),
			'option_name' => 'additional_domains',
			'collapsible' => 'open',
			array(
				'slug' => 'cache_external',
				'type' => 'frame',
				$this->add_external_settings(),
			),
		);

		return $pages;
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array
	 */
	protected function get_plugins_table() {

		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();
		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Plugin', 'cloudinary' ),
			'main'  => array(
				'cache_all_plugins',
			),
		);
		foreach ( $active as $plugin_path ) {
			$dir    = basename( dirname( $plugin_path ) );
			$plugin = $dir . '/' . basename( $plugin_path );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}
			$slug       = sanitize_file_name( pathinfo( $plugin, PATHINFO_FILENAME ) );
			$plugin_url = plugins_url( $plugin );
			$details    = $plugins[ $plugin ];
			$rows[]     = array(
				'slug'    => $slug,
				'title'   => $details['Name'],
				'url'     => dirname( $plugin_url ),
				'version' => $details['Version'],
				'main'    => array(
					'plugins.enabled',
				),
			);
		}

		return $rows;

	}

	/**
	 * Add the plugin cache settings page.
	 */
	protected function add_plugin_settings() {

		$plugins_setup = $this->get_plugins_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Plugins', 'cloudinary' ),
			'collapsible' => 'closed',
			'slug'        => 'plugins',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$plugins_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the theme table.
	 *
	 * @return array
	 */
	protected function get_theme_table() {

		$theme  = wp_get_theme();
		$themes = array(
			$theme,
		);
		if ( $theme->parent() ) {
			$themes[] = $theme->parent();
		}
		$rows = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Theme', 'cloudinary' ),
			'main'  => array(
				'cache_all_themes',
			),
		);
		// Active Theme.
		foreach ( $themes as $theme ) {
			$theme_location = $theme->get_stylesheet_directory();
			$theme_slug     = basename( dirname( $theme_location ) ) . '/' . basename( $theme_location );
			$slug           = sanitize_file_name( pathinfo( $theme_slug, PATHINFO_FILENAME ) );
			$rows[]         = array(
				'slug'    => $slug,
				'title'   => $theme->get( 'Name' ),
				'url'     => $theme->get_stylesheet_directory_uri(),
				'version' => $theme->get( 'Version' ),
				'main'    => array(
					'themes.enabled',
				),
			);
		}

		return $rows;
	}

	/**
	 * Add Theme Settings page.
	 */
	protected function add_theme_settings() {

		$theme_setup = $this->get_theme_table();
		$params      = array(
			'type'        => 'panel',
			'title'       => __( 'Themes', 'cloudinary' ),
			'slug'        => 'themes',
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all assets from active theme.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$theme_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_wp_table() {

		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'WordPress', 'cloudinary' ),
			'main'  => array(
				'cache_all_wp',
			),
		);
		$version = get_bloginfo( 'version' );
		// Admin folder.
		$rows[] = array(
			'slug'    => 'wp_admin',
			'title'   => __( 'WordPress Admin', 'cloudinary' ),
			'url'     => admin_url(),
			'version' => $version,
		);
		// Includes folder.
		$rows[] = array(
			'slug'    => 'wp_includes',
			'title'   => __( 'WordPress Includes', 'cloudinary' ),
			'url'     => includes_url(),
			'version' => $version,
			'main'    => array(
				'wordpress.enabled',
			),
		);

		return $rows;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_wp_settings() {

		$wordpress_setup = $this->get_wp_table();
		$params          = array(
			'type'        => 'panel',
			'title'       => __( 'WordPress', 'cloudinary' ),
			'slug'        => 'wordpress',
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all assets from WordPress core.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$wordpress_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_content_table() {

		$uploads = wp_get_upload_dir();
		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Content', 'cloudinary' ),
			'main'  => array(
				'cache_all_content',
			),
		);
		$rows[]  = array(
			'slug'    => 'wp_content',
			'title'   => __( 'Uploads', 'cloudinary' ),
			'url'     => $uploads['baseurl'],
			'version' => 0,
			'main'    => array(
				'content.enabled',
			),
		);

		return $rows;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_content_settings() {

		$content_setup = $this->get_content_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Content', 'cloudinary' ),
			'slug'        => 'content',
			'collapsible' => 'closed',
			'enabled'     => function () {
				return 'off' === get_plugin_instance()->settings->get_value( 'auto_sync' );
			},
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all content assets from WordPress Media Library.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$content_setup,
			),
		);

		return $params;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_external_settings() {

		$params = array(
			array(
				'type'         => 'on_off',
				'slug'         => 'external_assets',
				'description'  => __( 'Enable external assets', 'cloudinary' ),
				'tooltip_text' => __( 'Enabling external assets allows you to sync assets from specific external sources with Cloudinary.', 'cloudinary' ),
				'default'      => 'off',
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'external_assets' => true,
				),
				array(
					'type'  => 'textarea',
					'title' => __( 'List the domains for each external source (one domain per line)', 'cloudinary' ),
					'slug'  => 'uploadable_domains',
				),
			),
		);

		return $params;
	}

}
