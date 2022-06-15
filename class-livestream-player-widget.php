<?php
namespace IFQ\Twitch;
/**
 * Widget per la visualizzazione embedded in WordPress dei live stream del canale Twitch
 */
function register_livestream_player_widget() {
	register_widget( 'IFQ\Twitch\Livestream_Player_Widget' );
}
add_action( 'widgets_init', 'IFQ\Twitch\register_livestream_player_widget' );

class Livestream_Player_Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'ifqtw_livestream_player',
			'Twitch Livestream Player',
			array( 'description' => __( 'Widget per la visualizzazione embedded dei live stream del canale Twitch', 'ifq_twitch_widget_domain' ), ) 
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		if ( 1 == get_option( 'ifqtw_livestream_ongoing' ) ) {
			?>
			<div class="ifqtw_livestream_player_widget">
				<p style="position: relative; top: 28px; left: 157px; font-weight: bold">Segui la nostra diretta su Twitch!</p>
				<!-- test -->
				<iframe 
					src="https://player.twitch.tv/?channel=xiuder_&parent=localhost&muted=true" 
					frameborder="0" allowfullscreen="true" scrolling="no" height="349" width="620">
				</iframe>
				<!-- definitivo
				<iframe 
					src="https://player.twitch.tv/?channel=<?php /*echo esc_attr( retrieve_tw_broadcaster_id() );*/ ?>&parent=ilfattoquotidiano.it&muted=true" 
					width="620" height="349" frameborder="0" scrolling="no"
					allowfullscreen="true" webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen>
				</iframe>
				-->
			</div>
			<?php
		}
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
	}

}