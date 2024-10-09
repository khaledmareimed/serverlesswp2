<?php
/**
 * Checkbox Field UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Number Component
 *
 * @package Cloudinary\UI
 */
class Checkbox extends Radio {

	/**
	 * Get the field name.
	 *
	 * @return string
	 */
	protected function get_name() {
		return parent::get_name() . '[]';
	}

	/**
	 * Sanitize the values.
	 *
	 * @param array $value The array of values to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_value( $value ) {

		$sanitized_items = array();
		foreach ( $value as $value_key => $value_item ) {
			$sanitized_items[ $value_key ] = parent::sanitize_value( $value_item );
		}

		return $sanitized_items;
	}
}
