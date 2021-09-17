<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Delivery_Feature;
use Cloudinary\Connect\Api;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Responsive_Breakpoints extends Delivery_Feature {

	/**
	 * The feature application priority.
	 *
	 * @var int
	 */
	protected $priority = 9;

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'media_display';

	/**
	 * Holds the enabler slug.
	 *
	 * @var string
	 */
	protected $enable_slug = 'enable_breakpoints';

	/**
	 * Setup hooks used when enabled.
	 */
	protected function setup_hooks() {
		add_action( 'cloudinary_init_delivery', array( $this, 'remove_srcset_filter' ) );
		add_filter( 'cloudinary_apply_breakpoints', '__return_false' );
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {
		$tag_element['atts']['data-responsive'] = true;

		return $tag_element;
	}

	/**
	 * Remove the legacy breakpoints sync type and filters.
	 *
	 * @param array $structs The sync types structure.
	 *
	 * @return array
	 */
	public function remove_legacy_breakpoints( $structs ) {
		unset( $structs['breakpoints'] );

		return $structs;
	}

	/**
	 * Check to see if Breakpoints are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$lazy    = $this->plugin->get_component( 'lazy_load' );
		$enabled = parent::is_enabled();

		return ! is_null( $lazy ) && $lazy->is_enabled() && $enabled;
	}

	/**
	 * Remove the srcset filter.
	 */
	public function remove_srcset_filter() {
		remove_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10 );
	}

	/**
	 * Setup the class.
	 */
	public function setup() {
		parent::setup();
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'remove_legacy_breakpoints' ) );
	}

	/**
	 * Create Settings.
	 */
	protected function create_settings() {
		$this->settings = $this->media->get_settings()->get_setting( 'image_display' );
	}

	/**
	 * Add the settings.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {

		$pages['responsive']['settings'][0][2][0] = array(
			'type'         => 'number',
			'slug'         => 'pixel_step',
			'priority'     => 9,
			'title'        => __( 'Breakpoints distance', 'cloudinary' ),
			'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
			'suffix'       => __( 'px', 'cloudinary' ),
			'default'      => 100,
		);
		$pages['responsive']['settings'][0][2][1] = array(
			'type'         => 'select',
			'slug'         => 'dpr',
			'priority'     => 8,
			'title'        => __( 'DPR settings', 'cloudinary' ),
			'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
			'default'      => 'auto',
			'options'      => array(
				'off'  => __( 'None', 'cloudinary' ),
				'auto' => __( 'Auto', 'cloudinary' ),
				'2'    => __( '2X', 'cloudinary' ),
				'3'    => __( '3X', 'cloudinary' ),
				'4'    => __( '4X', 'cloudinary' ),
			),
		);

		return $pages;
	}
}
