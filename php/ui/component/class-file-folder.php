<?php
/**
 * Color Field UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;
use Cloudinary\UI\Branch;
use function Cloudinary\get_plugin_instance;

/**
 * Class Color Component
 *
 * @package Cloudinary\UI
 */
class File_Folder extends On_Off {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'tree|primary_input/|ul|folder/|/ul|/tree';

	/**
	 * Flags the component as a primary.
	 *
	 * @var Setting | null
	 */
	protected $primary = null;

	/**
	 * Holds the tree object.
	 *
	 * @var Branch
	 */
	protected $tree;

	/**
	 * Holds the handler types.
	 *
	 * @var array
	 */
	protected $handler_files = array();

	/**
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {

		parent::__construct( $setting );

		$this->primary = $this->primary_setting();

		$paths       = (array) $this->setting->get_param( 'paths', array() );
		$checked     = (array) $this->setting->get_value();
		$clean_value = array();
		$base_path   = $this->setting->get_param( 'base_path' );
		$this->tree  = new Branch( $this->setting->get_slug() );
		$handlers    = $this->setting->get_param( 'file_types', array() );

		foreach ( $paths as $path ) {
			$parts    = explode( '/', ltrim( $path, '/' ) );
			$previous = $this->tree;
			$length   = count( $parts ) - 1;
			foreach ( $parts as $index => $folder ) {
				$full_path = $base_path . $path;
				$previous  = $previous->get_path( $folder );
				if ( $length === $index ) {
					$previous->value = $full_path;
					if ( in_array( $full_path, $checked, true ) ) {
						$previous->checked = true;
						$clean_value[]     = $full_path;
					}
					$ext = pathinfo( $folder, PATHINFO_EXTENSION );
					if ( isset( $handlers[ $ext ] ) ) {
						$this->set_master( $handlers[ $ext ], $previous->id );
					}
				}
			}
		}
		$this->setting->set_param( 'clean_value', $clean_value );
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'cld-file-tree', get_plugin_instance()->dir_url . 'js/file-tree.js', array( 'cloudinary' ), get_plugin_instance()->version, true );
	}

	/**
	 * Get the folder part struct.
	 *
	 * @param array $struct The structure.
	 *
	 * @return mixed
	 */
	protected function folder( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'tree',
		);

		// Set the main tree item.
		$this->tree->name                  = __( 'Select All Assets', 'cloudinary' );
		$struct['children']['tree']        = $this->tree->render();
		$struct['attributes']['data-slug'] = $this->primary->get_slug();

		return $struct;
	}

	/**
	 * Get the tree part struct.
	 *
	 * @param array $struct The structure.
	 *
	 * @return mixed
	 */
	protected function tree( $struct ) {

		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'tree';

		return $struct;
	}

	/**
	 * Structure for the primary input.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return mixed
	 */
	protected function primary_input( $struct ) {

		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'hidden';
		$struct['attributes']['name']  = $this->get_name();
		$struct['attributes']['id']    = $this->primary->get_slug();
		$struct['attributes']['value'] = wp_json_encode( $this->setting->get_param( 'clean_value', array() ) );
		$struct['render']              = true;

		return $struct;
	}

	/**
	 * Get the primary setting (master that created the tree).
	 *
	 * @return Setting
	 */
	protected function primary_setting() {
		return $this->setting->get_param( 'primary_setting', $this->setting );
	}

	/**
	 * Set the maaster control.
	 *
	 * @param string $master The slug of the master setting.
	 * @param string $slug   The slug of the setting to be controlled.
	 */
	protected function set_master( $master, $slug ) {
		$master = $this->setting->find_setting( $master );
		$list   = $master->get_param( 'master', array() );
		$list[] = $slug;
		$master->set_param( 'master', $list );
	}

	/**
	 * Decode the serialised value.
	 *
	 * @param string $value The string to decode.
	 *
	 * @return array|bool|string
	 */
	public function sanitize_value( $value ) {
		$files = json_decode( $value, true );

		return array_map( 'esc_url', (array) $files );
	}
}
