<?php
/**
 * Link UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Link extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'link_tag';

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function link_tag( $struct ) {

		$struct['element']              = 'a';
		$struct['content']              = $this->setting->get_param( 'content' );
		$struct['attributes']['href']   = $this->setting->get_param( 'url' );
		$struct['attributes']['target'] = $this->setting->get_param( 'target', '_blank' );
		$struct['render']               = true;
		$struct['attributes']['class']  = $this->setting->get_param(
			'attributes:class',
			array(
				'button',
				'button-primary',
			)
		);

		return $struct;
	}
}
