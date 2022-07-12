<?php 
/*
Plugin Name: Gutenberg Notices
Plugin URI: 
Description: Show custom Gutenberg notices on post save
Version: 1.0
Author: Lax Mariappan
Author URI: https://www.webdevstudios.com
Text Domain: gnotice
*/

/**
 * Email notification callback, hooks after publishing a post.
 *
 * @param  Integer $post_id Post ID.
 * @param  Object  $post post object.
 * @return void
 * @since  1.0
 * @author Lax Mariappan <lax@webdevstudios.com>
 */
function email_notification_response_cb( $post_id, $post ) {

	if ( did_action( 'publish_post' ) === 1 ) {
		set_notification_status( $post_id, $post );
	}
}
add_action( 'publish_post', 'email_notification_response_cb', 20, 2 );

/**
 * Set error message to post meta.
 *
 * @param  Integer $post_id Post ID.
 * @param  Object  $post post object.
 * @return void
 * @since  1.0
 * @author Lax Mariappan <lax@webdevstudios.com>
 */
function set_notification_status( $post_id, $post ) {
	if ( ! $post ) {
		return; // Exit if there is no response.
	}

	// @see https://developer.wordpress.org/reference/hooks/new_status_post-post_type/#comment-4335

	$author    = $post->post_author;
	$name      = get_the_author_meta( 'display_name', $author );
	$email     = get_the_author_meta( 'user_email', $author );
	$title     = $post->post_title;
	$permalink = get_permalink( $post_id );
	$to[]      = sprintf( '%s <%s>', $name, $email );
	$subject   = sprintf( 'Published: %s', $title );
	$message   = sprintf( 'Congratulations, %s! Your article "%s" has been published.' . "\n\n", $name, $title );
	$message  .= sprintf( 'View: %s', $permalink );
	$headers[] = '';
	$sent      = wp_mail( $to, $subject, $message, $headers );

	if ( $sent ) {
		$response = array(
			'code'    => 'success',
			'message' => 'Email notification(s) sent sucessfully',
		);
	} else {
		$response = array(
			'code'    => 'error',
			'message' => 'Failed to send email notification(s)',
		);
	}

	update_post_meta( $post_id, 'email_notification', wp_json_encode( $response ) );
}


/**
 * Custom route to check email notification status.
 *
 * @return void
 * @since  1.0
 * @author Lax Mariappan <lax@webdevstudios.com>
 */
function gnotice_rest_init() {

	$namespace = 'api-gnotice/v1';
	$route     = 'check-email-response';

	register_rest_route(
		$namespace,
		$route,
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => 'get_email_notification',
			'permission_callback' => '__return_true',
		)
	);
}

add_action( 'rest_api_init', 'gnotice_rest_init' );

add_action( 'admin_footer-post.php', 'email_notification_script' );
add_action( 'admin_footer-post-new.php', 'email_notification_script' );

/**
 * Adds script to make ajax call - checking notifications via REST.
 *
 * @return void
 * @since 1.0
 * @author Lax Mariappan <lax@webdevstudios.com>
 */
function email_notification_script() {
	// @see https://wordpress.stackexchange.com/a/398805/103640
	// @see https://github.com/WordPress/gutenberg/issues/17632#issuecomment-583772895
	?>
	<script type="text/javascript">

		const { subscribe,select } = wp.data;
		const { isSavingPost } = select( 'core/editor' );
		jQuery(document).ready(function($) {

		var checked = true;
		subscribe( () => {
			if ( isSavingPost() ) {
				checked = false;
			} else {
				if ( ! checked ) {
					checkNotificationAfterPublish();
					checked = true;
				}

			}
		} );

		function checkNotificationAfterPublish(){
			$.ajax({
				type: 'GET',
				dataType: 'json',
				crossDomain : true,
				url: '<?php echo esc_html( get_site_url() ); ?>/wp-json/api-gnotice/v1/check-email-response',
				data: {id:	wp.data.select("core/editor").getCurrentPostId()},
				success: function(response){
					if(response.message){

						wp.data.dispatch("core/notices").createNotice(
							response.code,
							response.message,
							{
								id: 'email_status_notice',
								isDismissible: true
							}
						);
					}
				}

			});
		};

		});
	</script>
	<?php
}



/**
 * Send error response to REST endpoint.
 *
 * @return Error object
 * @since  1.0
 * @author Lax Mariappan <lax@webdevstudios.com>
 */
function get_email_notification() {
	if ( isset( $_GET['id'] ) ) {
		$id = sanitize_text_field(
			wp_unslash( $_GET['id'] )
		);

		$error = get_post_meta( $id, 'email_notification', true );

		if ( $error ) {
			$data = json_decode( $error );
			return new \WP_REST_Response(
				array(
					'code'    => $data->code,
					'message' => wp_unslash( $data->message ),
				)
			);
		}
	}

}
