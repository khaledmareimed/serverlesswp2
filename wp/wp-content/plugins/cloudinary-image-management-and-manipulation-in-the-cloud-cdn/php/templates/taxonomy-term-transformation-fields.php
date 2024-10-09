<?php
/**
 * Edit term, global transformations template.
 *
 * @package Cloudinary
 */

?>
<?php foreach ( $this->taxonomy_fields as $context => $set ) : ?>
	<tr>
		<td colspan="2">
			<h3>
				<?php
				// translators: variable is context.
				echo esc_html( sprintf( __( 'Global %s Transformations', 'cloudinary' ), ucwords( $context ) ) );
				?>
			</h3>
		</td>
	</tr>
	<?php foreach ( $set as $setting ) : ?>
		<tr class="form-field term-<?php echo esc_attr( $setting->get_slug() ); ?>-wrap">
			<th scope="row">
				<label for="cloudinary_<?php echo esc_attr( $setting->get_slug() ); ?>"><?php echo esc_html( $setting->get_param( 'title' ) ); ?></label>
			</th>
			<td>
				<?php $setting->set_param( 'title', null ); ?>
				<?php $setting->set_param( 'tooltip_text', null ); ?>
				<?php $setting->get_component()->render( true ); ?>
			</td>
		</tr>
	<?php endforeach; ?>
<?php endforeach; ?>
