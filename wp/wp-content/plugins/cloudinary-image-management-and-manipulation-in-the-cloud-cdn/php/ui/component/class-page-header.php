<?php
/**
 * Page Header UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Header Component
 *
 * @package Cloudinary\UI
 */
class Page_Header extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|settings/|/wrap';

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['element']               = 'header';
		$struct['content']               = $this->setting->get_param( 'content' );
		$struct['attributes']['class'][] = 'cld-page-header';

		return $struct;
	}
}
