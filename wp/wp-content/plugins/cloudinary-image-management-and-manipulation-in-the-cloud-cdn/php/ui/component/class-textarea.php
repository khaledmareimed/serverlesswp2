<?php
/**
 * Textarea Field UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Textarea extends Text {

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$struct                        = parent::input( $struct );
		$struct['element']             = 'textarea';
		$struct['content']             = $this->setting->get_value();
		$struct['attributes']['class'] = array(
			'large-text',
			'code',
		);
		$struct['attributes']['rows']  = $this->setting->get_param( 'rows', 5 );
		unset( $struct['attributes']['type'] );
		unset( $struct['attributes']['value'] );

		return $struct;
	}
}
