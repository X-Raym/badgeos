<?php
/**
 * Submission and Nomination Functions
 *
 * @package BadgeOS
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Check if nomination form was submitted
 *
 * @since 1.0.0
 */
function badgeos_save_nomination_data() {
	global $current_user, $post;

	// If the form hasn't been submitted, bail.
	if ( ! isset( $_POST['badgeos_nomination_submit'] ) )
		return false;

	//nonce check for security
	check_admin_referer( 'badgeos_nomination_form', 'submit_nomination' );

	get_currentuserinfo();
	$nomination_content = $_POST['badgeos_nomination_content'];
	$nomination_user_id = $_POST['badgeos_nomination_user_id'];

	$nomination_type = get_post_type( absint( $post->ID ) );

	return badgeos_create_nomination(
		$post->ID,
		$nomination_type . ':' . get_the_title( absint( $post->ID ) ),
		sanitize_text_field( $nomination_content ),
		absint( $nomination_user_id ),
		absint( $current_user->ID )
	);
}

/**
 * Create a new nomination entry
 *
 * @since  1.0.0
 * @param  integer $achievement_id  The associated achievement's post ID
 * @param  string  $title           The title of the new nomination
 * @param  string  $content         The content for the new nomination
 * @param  integer $user_nominated  The user ID of the person nominated
 * @param  integer $user_nominating The user ID of the person who did the nominating
 * @return bool                     True on succesful post creation, false otherwise
 */
function badgeos_create_nomination( $achievement_id, $title, $content, $user_nominated, $user_nominating ) {

	if ( ! badgeos_check_if_user_has_nomination( absint( $user_nominated ), absint( $achievement_id ) ) ) {

		$submission_data = array(
			'post_title'	=>	sanitize_text_field( $title ),
			'post_content'	=>	sanitize_text_field( $content ),
			'post_status'	=>	'publish',
			'post_author'	=>	absint( $user_nominated ),
			'post_type'		=>	'nomination',
		);

		//insert the post into the database
		if ( $new_post_id = wp_insert_post( $submission_data ) ) {
			//save the submission status metadata
			add_post_meta( $new_post_id, '_badgeos_nomination_status', 'pending' );

			//save the achievement id metadata
			add_post_meta( $new_post_id, '_badgeos_nomination_achievement_id', absint( $achievement_id ) );

			//save the user being nominated
			add_post_meta( $new_post_id, '_badgeos_nomination_user_id', absint( $user_nominated ) );

			//save the user doing the nomination
			add_post_meta( $new_post_id, '_badgeos_nominating_user_id', absint( $user_nominating ) );

			do_action( 'badgeos_save_nomination', $new_post_id );

			//load BadgeOS settings
			$badgeos_settings = get_option( 'badgeos_settings' );

			//check if nomination emails are enabled
			if ( $badgeos_settings['submission_email'] != 'disabled' ) {

				$nominee_data = get_userdata( absint( $user_nominated ) );
				$nominating_data = get_userdata( absint( $user_nominating ) );

				//set the admin email address
				$admin_email = apply_filters( 'badgeos_nomination_notify_email', get_bloginfo( 'admin_email' ) );

				//set the email subject
				$subject = 'Nomination: '.get_the_title( absint( $achievement_id ) ). ' from ' .$nominating_data->display_name;
				$subject = apply_filters( 'badgeos_nomination_notify_subject', $subject );

				//set the email message
				$message = 'A new nomination has been received:

	In response to: ' .get_the_title( absint( $achievement_id ) ).'
	Nominee: '.$nominee_data->display_name.'
	Nominated by: '.$nominating_data->display_name.'

	Review the complete submission and approve or deny it at:
	'.html_entity_decode( esc_url_raw( get_edit_post_link( absint( $new_post_id ) ) ) ).'

	To view all submissions, visit:
	'.admin_url( 'edit.php?post_type=nomination' );

				$message = apply_filters( 'badgeos_nomination_notify_message', $message );

				//send notification email to admin
				wp_mail( $admin_email, $subject, $message );

			}

			return true;

		} else {

			return false;

		}

	}
}

/**
 * Hide action links on the submissions edit listing screen
 *
 * @since 1.0.0
 */
function badgeos_hide_quick_edit( $actions ) {
	global $post;

	if ( 'submission' == get_post_type( $post ) || 'nomination' == get_post_type( $post ) ) {
		//hide action links
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['trash'] );
		unset( $actions['view'] );
	}

	return $actions;

}
add_filter( 'post_row_actions', 'badgeos_hide_quick_edit' );

/**
 * Add columns to the Submissions and Nominations edit screen
 *
 * @since  1.0.0
 * @param  array $columns The array of columns on the edit screen
 * @return array          Our updated array of columns
 */
function badgeos_add_submission_columns( $columns ) {

	$column_content = array( 'content' => __( 'Content', 'badgeos' ) );
 	//$column_action = array( 'action' => __( 'Action', 'badgeos' ) );
	$column_status = array( 'status' => __( 'Status', 'badgeos' ) );

	$columns = array_slice( $columns, 0, 2, true ) + $column_content + array_slice( $columns, 2, NULL, true );
	//$columns = array_slice( $columns, 0, 3, true ) + $column_action + array_slice( $columns, 2, NULL, true );
	$columns = array_slice( $columns, 0, 3, true ) + $column_status + array_slice( $columns, 2, NULL, true );

	unset( $columns['comments'] );

	return $columns;

}
add_filter( 'manage_edit-submission_columns', 'badgeos_add_submission_columns', 10, 1 );
add_filter( 'manage_edit-nomination_columns', 'badgeos_add_nomination_columns', 10, 1 );

/**
 * Add columns to the Nominations edit screan
 *
 * @since  1.0.0
 * @param  array $columns The array of columns on the edit screen
 * @return array          Our updated array of columns
 */
function badgeos_add_nomination_columns( $columns ) {

	$column_content = array( 'content' => __( 'Content', 'badgeos' ) );
	$column_userid = array( 'user' => __( 'User', 'badgeos' ) );
 	//$column_action = array( 'action' => __( 'Action', 'badgeos' ) );
	$column_status = array( 'status' => __( 'Status', 'badgeos' ) );

	$columns = array_slice( $columns, 0, 2, true ) + $column_content + array_slice( $columns, 2, NULL, true );
	$columns = array_slice( $columns, 0, 3, true ) + $column_userid + array_slice( $columns, 2, NULL, true );
	//$columns = array_slice( $columns, 0, 4, true ) + $column_action + array_slice( $columns, 2, NULL, true );
	$columns = array_slice( $columns, 0, 4, true ) + $column_status + array_slice( $columns, 2, NULL, true );

	unset( $columns['comments'] );

	return $columns;

}

/**
 * Content for the custom Submission columns
 *
 * @since  1.0.0
 * @param  string $column The column name
 */
function badgeos_submission_column_action( $column ) {
	global $post, $badgeos;

	switch ( $column ) {
		case 'action':

			//if submission use the post Author ID, if nomination use the user ID meta value
			$user_id = ( isset( $_GET['post_type'] ) && 'submission' == $_GET['post_type'] ) ? $post->post_author : get_post_meta( $post->ID, '_badgeos_submission_user_id', true );

			echo '<a class="button-secondary" href="'.wp_nonce_url( add_query_arg( array( 'badgeos_status' => 'approve', 'post_id' => absint( $post->ID ), 'user_id' => absint( $user_id ) ) ), 'badgeos_status_action' ).'">'.__( 'Approve', 'badgeos' ).'</a>&nbsp;&nbsp;';
			echo '<a class="button-secondary" href="'.wp_nonce_url( add_query_arg( array( 'badgeos_status' => 'deny', 'post_id' => absint( $post->ID ), 'user_id' => absint( $user_id ) ) ), 'badgeos_status_action' ).'">'.__( 'Deny', 'badgeos' ).'</a>';
			break;

		case 'content':

			echo substr( $post->post_content, 0, 250 ) .'...';
			break;

		case 'status':

			$status = ( get_post_type( $post ) == 'submission' ) ? get_post_meta( $post->ID, '_badgeos_submission_status', true ) : get_post_meta( $post->ID, '_badgeos_nomination_status', true );
			$status = ( $status ) ? $status : __( 'pending', 'badgeos' );
			echo esc_html( $status );
			break;

		case 'user':

			$user_id = ( get_post_type( $post ) == 'submission' ) ? get_post_meta( $post->ID, '_badgeos_submission_user_id', true ) : get_post_meta( $post->ID, '_badgeos_nomination_user_id', true );

			if ( is_numeric( $user_id ) ) {
				$user_info = get_userdata( absint( $user_id ) );
				echo $user_info->display_name;
				break;
			}
	}
}
add_action( 'manage_posts_custom_column', 'badgeos_submission_column_action', 10, 1 );

/**
 * Add filter select to Submissions edit screen
 *
 * @since 1.0.0
 */
function badgeos_add_submission_dropdown_filters() {
    global $typenow, $wpdb;

	if ( $typenow == 'submission' ) {
        //array of current status values available
        $submission_statuses = array( __( 'Approve', 'badgeos' ), __( 'Deny', 'badgeos' ), __( 'Pending', 'badgeos' ) );

		$current_status = ( isset( $_GET['badgeos_submission_status'] ) ) ? $_GET['badgeos_submission_status'] : '';

		//output html for status dropdown filter
		echo "<select name='badgeos_submission_status' id='badgeos_submission_status' class='postform'>";
		echo "<option value=''>" .__( 'Show All Statuses', 'badgeos' ).'</option>';
		foreach ( $submission_statuses as $status ) {
			echo '<option value="'.strtolower( $status ).'"  '.selected( $current_status, strtolower( $status ) ).'>' .$status .'</option>';
		}
		echo '</select>';
	}
}
add_action( 'restrict_manage_posts', 'badgeos_add_submission_dropdown_filters' );

/**
 * Filter the query to show submission statuses
 *
 * @since 1.0.0
 */
function badgeos_submission_status_filter( $query ) {
	global $pagenow;

	if ( $query->is_admin && ( 'edit.php' == $pagenow ) ) {
		$metavalue = ( isset($_GET['badgeos_submission_status']) && $_GET['badgeos_submission_status'] != '' ) ? $_GET['badgeos_submission_status'] : '';

		if ( '' != $metavalue ) {
			$query->set( 'orderby' , 'meta_value' );
			$query->set( 'meta_key' , '_badgeos_submission_status' );
			$query->set( 'meta_value', esc_html( $metavalue ) );
		}
	}

	return $query;
}
add_filter( 'pre_get_posts', 'badgeos_submission_status_filter' );


/**
 * Process admin submission/nomination approvals
 *
 * @since 1.0.0
 * @param integer $post_id The given post's ID
 */
function badgeos_process_submission_review( $post_id ) {

	// Confirm we're deailing with either a submission or nomination post type,
	// and our nonce is valid
	// and the user is allowed to edit the post
	// and we've gained an approved status
	if (
		!empty( $_POST )
		&& isset( $_POST['post_type'] )
		&& ( 'submission' == $_POST['post_type'] || 'nomination' == $_POST['post_type'] ) //verify post type is submission or nomination
		&& isset( $_POST['wp_meta_box_nonce'] )
		&& wp_verify_nonce( $_POST['wp_meta_box_nonce'], 'init.php' ) //verify nonce for security
		&& current_user_can( 'edit_post', $post_id ) //check if current user has permission to edit this submission/nomination
		&& ( 'approved' == $_POST['_badgeos_submission_status'] || 'approved' == $_POST['_badgeos_nomination_status']  ) //verify user is approving a submission or nomination
		&& get_post_meta( $post_id, '_badgeos_nomination_status', true ) != 'approved' //if nomination is already approved, skip it
		&& get_post_meta( $post_id, '_badgeos_submission_status', true ) != 'approved' //if submission is already approved, skip it
	) {

		// Get the achievement and user attached to this Submission
		$achievement_id = ( get_post_type( $post_id ) == 'submission' ) ? get_post_meta( $post_id, '_badgeos_submission_achievement_id', true ) : get_post_meta( $post_id, '_badgeos_nomination_achievement_id', true );
		$user_id = isset( $_POST['_badgeos_nominated_user_id'] ) ? $_POST['_badgeos_nominated_user_id'] : $_POST['post_author'];

		// Give the achievement to the user
		if ( $achievement_id && $user_id ) {

			badgeos_award_achievement_to_user( absint( $achievement_id ), absint( $user_id ) );

		}

	}

}
add_action( 'save_post', 'badgeos_process_submission_review' );

/**
 * Check if nomination form has been submitted and save data
 */
function badgeos_save_submission_data() {
	global $current_user, $post;

	//if form items don't exist, bail.
	if ( ! isset( $_POST['badgeos_submission_submit'] ) || ! isset( $_POST['badgeos_submission_content'] ) )
		return;

	//nonce check for security
	check_admin_referer( 'badgeos_submission_form', 'submit_submission' );

	get_currentuserinfo();

	$submission_content = $_POST['badgeos_submission_content'];
	$submission_type = get_post_type( absint( $post->ID ) );

	return badgeos_create_submission(
		$post->ID,
		$submission_type . ':' . get_the_title( absint( $post->ID ) ),
		sanitize_text_field( $submission_content ),
		absint( $current_user->ID )
	);
}

function badgeos_create_submission( $achievement_id, $title, $content, $user_id ) {

	$submission_data = array(
		'post_title'	=>	$title,
		'post_content'	=>	$content,
		'post_status'	=>	'publish',
		'post_author'	=>	$user_id,
		'post_type'		=>	'submission',
	);

	//insert the post into the database
	if ( $new_post_id = wp_insert_post( $submission_data ) ) {

		// Check if submission is auto approved or not
		$submission_status = ( get_post_meta( $achievement_id, '_badgeos_earned_by', true ) == 'submission_auto' ) ? 'approved' : 'pending';

		// Set the submission approval status
		add_post_meta( $new_post_id, '_badgeos_submission_status', sanitize_text_field( $submission_status ) );

		// save the achievement ID related to the submission
		add_post_meta( $new_post_id, '_badgeos_submission_achievement_id', $achievement_id );

		//if submission is set to auto-approve, award the achievement to the user
		if ( get_post_meta( $achievement_id, '_badgeos_earned_by', true ) == 'submission_auto' )
			badgeos_award_achievement_to_user( absint( $achievement_id ), absint( $user_id ) );

		//process attachment upload if a file was submitted
		if( ! empty($_FILES['document_file'] ) ) {

			if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );

			$file   = $_FILES['document_file'];
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			if( ! isset( $upload['error'] ) && isset($upload['file'] ) ) {

				$filetype   = wp_check_filetype( basename( $upload['file'] ), null );
				$title      = $file['name'];
				$ext        = strrchr( $title, '.' );
				$title      = ( $ext !== false ) ? substr( $title, 0, -strlen( $ext ) ) : $title;

				$attachment = array(
					'post_mime_type'    => $filetype['type'],
					'post_title'        => addslashes( $title ),
					'post_content'      => '',
					'post_status'       => 'inherit',
					'post_parent'       => $new_post_id
				);

				$attach_id  = wp_insert_attachment( $attachment, $upload['file'] );

			}
		}

		do_action( 'save_submission', $new_post_id );

		//load BadgeOS settings
		$badgeos_settings = get_option( 'badgeos_settings' );

		//check if submission emails are enabled
		if ( $badgeos_settings['submission_email'] != 'disabled' ) {

			$user_data = get_userdata( absint( $user_id ) );

			//set the admin email address
			$admin_email = apply_filters( 'badgeos_submission_notify_email', get_bloginfo( 'admin_email' ) );

			//set the email subject
			$subject = 'Submission: '.get_the_title( absint( $achievement_id ) ). ' from ' .$user_data->display_name;
			$subject = apply_filters( 'badgeos_submission_notify_subject', $subject );

			//set the email message
			$message = 'A new submission has been received:

In response to: ' .get_the_title( absint( $achievement_id ) ).'
Submitted by: '.$user_data->display_name.'

Review the complete submission and approve or deny it at:
'.html_entity_decode( esc_url_raw( get_edit_post_link( absint( $new_post_id ) ) ) ).'

To view all submissions, visit:
'.admin_url( 'edit.php?post_type=submission' );

			$message = apply_filters( 'badgeos_submission_notify_message', $message );

			//send notification email to admin
			wp_mail( $admin_email, $subject, $message );

		}

		return true;

	} else {

		return false;

	}
}

/**
 * Returns the comment form for Submissions
 *
 *
 */
function badgeos_get_comment_form( $post_id = 0 ) {
	global $current_user;

	// user must be logged in to see the submission form
	if ( !is_user_logged_in() )
		return '';

	$defaults = array(
		'heading'    => '<h4>' . sprintf( __( 'Comment on Submission #%1$d', 'badgeos' ), $post_id ) . '</h4>',
		'attachment' => __( 'Attachment:', 'badgeos' ),
		'submit'     => __( 'Submit Comment', 'badgeos' )
	);
	// filter our text
	$new_defaults = apply_filters( 'badgeos_comment_form_language', $defaults );
	// fill in missing data
	$language = wp_parse_args( $new_defaults, $defaults );

	$sub_form = '<form class="badgeos-comment-form" method="post" enctype="multipart/form-data">';

		// comment form heading
		$sub_form .= '<legend>'. $language['heading'] .'</legend>';

		// submission file upload
		$sub_form .= '<fieldset class="badgeos-file-submission">';
		$sub_form .= '<p><label>'. $language['attachment'] .' <input type="file" name="document_file" id="document_file" /></label></p>';
		$sub_form .= '</fieldset>';

		// submission comment
		$sub_form .= '<fieldset class="badgeos-submission-comment">';
		$sub_form .= '<p><textarea name="badgeos_comment"></textarea></p>';
		$sub_form .= '</fieldset>';

		// submit button
		$sub_form .= '<p class="badgeos-submission-submit"><input type="submit" name="badgeos_comment_submit" value="'. $language['submit'] .'" /></p>';

		// Hidden Fields
		$sub_form .= wp_nonce_field( 'submit_comment', 'badgeos_comment_nonce', true, false );
		$sub_form .= '<input type="hidden" name="user_id" value="' . $current_user->ID . '">';
		$sub_form .= '<input type="hidden" name="submission_id" value="' . $post_id . '">';

	$sub_form .= '</form>';

	return apply_filters( 'badgeos_get_comment_form', $sub_form, $post_id );

}

/**
 * Listener for saving submission comments
 *
 * @since 1.0.0
 */
function badgeos_save_comment_data() {

	// If our submission data is empty, or we don't pass security, bail
	if ( empty( $_POST ) || ! wp_verify_nonce( $_POST['badgeos_comment_nonce'], 'submit_comment' ) )
		return;

	// Process comment data
	$comment_data = array(
		'user_id'         => absint( $_POST['user_id'] ),
		'comment_post_ID' => absint( $_POST['submission_id'] ),
		'comment_content' => sanitize_text_field( $_POST['badgeos_comment'] ),
	);

	if ( $comment_id = wp_insert_comment( $comment_data ) ) {

		// Process attachment upload if a file was submitted
		if( ! empty($_FILES['document_file'] ) ) {

			if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );

			$file   = $_FILES['document_file'];
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			if( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {

				$filetype = wp_check_filetype( basename( $upload['file'] ), null );
				$title    = $file['name'];
				$ext      = strrchr( $title, '.' );
				$title    = ( $ext !== false ) ? substr( $title, 0, -strlen( $ext ) ) : $title;

				$attachment = array(
					'post_mime_type' => $filetype['type'],
					'post_title'     => addslashes( $title ),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_parent'    => absint( $_REQUEST['submission_id'] ),
					'post_author'    => absint( $_REQUEST['user_id'] )
				);
				wp_insert_attachment( $attachment, $upload['file'] );
			}
		}
	}
}
add_action( 'init', 'badgeos_save_comment_data' );

/**
 * Returns all comments for a Submission entry
 *
 * @since  1.0.0
 * @param  integer $submission_id The submission's post ID
 * @return string                 Concatenated markup for comments
 */
function badgeos_get_comments_for_submission( $submission_id = 0 ) {

	// Get our comments
	$comments = get_comments( array(
		'post_id' => absint( $submission_id ),
		'orderby' => 'date',
		'order'   => 'ASC',
	) );

	// If we have no comments, bail
	if ( empty( $comments ) )
		return;

	// Concatenate our output
	$output = '<h4>' . sprintf( __( 'Submission #%1$d Comments', 'badgeos' ), $submission_id ) . '</h4>';
	$output .= '<ul class="badgeos-submission-comments-list">';
	foreach( $comments as $comment ) {
		// Setup an alternating odd/even class
		$odd_even = ( isset( $odd_even ) && 'odd' == $odd_even ) ? 'even' : 'odd';

		// Render the comment
		$output .= badgeos_render_submission_comment( $comment, $odd_even );
	}
	$output .= '</ul><!-- .badgeos-submission-comments-list -->';

	return apply_filters( 'badgeos_get_comments_for_submission', $output, $submission_id, $comments );

}


/**
 * Check if a user has an existing submission for an achievement
 *
 * @since  1.0.0
 * @param  integer $user_id        The user's ID
 * @param  integer $achievement_id The achievement's post ID
 * @return bool                    True if the user has sent a submission, false otherwise
 */
function badgeos_check_if_user_has_submission( $user_id = 0, $achievement_id = 0 ) {

	$submissions = get_posts( array(
		'post_type'   => 'submission',
		'author'      => absint( $user_id ),
		'post_status' => 'publish',
		'meta_key'    => '_badgeos_submission_achievement_id',
		'meta_value'  => absint( $achievement_id ),
	) );

	// User DOES have a submission for this achievement
	if ( ! empty( $submissions ) )
		return true;

	// User does NOT have a submission
	else
		return false;

}

/**
 * Check if a user has an existing nomination for an achievement
 *
 * @since  1.0.0
 * @param  integer $user_id        The user's ID
 * @param  integer $achievement_id The achievement's post ID
 * @return bool                    True if the user has sent a submission, false otherwise
 */
function badgeos_check_if_user_has_nomination( $user_id = 0, $achievement_id = 0 ) {

	$nomination = get_posts( array(
		'post_type'   => 'nomination',
		'author'      => absint( $user_id ),
		'post_status' => 'publish',
		'meta_key'    => '_badgeos_nomination_achievement_id',
		'meta_value'  => absint( $activity_id ),
	) );

	// User DOES have a nomination for this achievement
	if ( ! empty( $nomination ) )
		return true;

	// User does NOT have a nomination
	else
		return false;

}

function badgeos_get_nomination_form( $args = array() ) {

	$defaults = array(
		'heading' => sprintf( '<h4>%s</h4>', __( 'Nomination Form', 'badgeos' ) ),
		'submit' => __( 'Submit', 'badgeos' )
	);
	// filter our text
	$new_defaults = apply_filters( 'badgeos_submission_form_language', $defaults );
	// fill in missing data
	$language = wp_parse_args( $new_defaults, $defaults );

	$sub_form = '<form class="badgeos-nomination-form" method="post" enctype="multipart/form-data">';
		$sub_form .= wp_nonce_field( 'badgeos_nomination_form', 'submit_nomination', true, false );
		// nomination form heading
		$sub_form .= '<legend>'. $language['heading'] .'</legend>';
		// nomination user
		$sub_form .= '<label>'.__( 'User to nominate', 'badgeos' ).'</label>';
		$sub_form .= '<p>' .wp_dropdown_users( array( 'name' => 'badgeos_nomination_user_id', 'echo' => '0' ) ). '</p>';
		// nomination content
		$sub_form .= '<label>'.__( 'Reason for nomination', 'badgeos' ).'</label>';
		$sub_form .= '<fieldset class="badgeos-nomination-content">';
		$sub_form .= '<p><textarea name="badgeos_nomination_content"></textarea></p>';
		$sub_form .= '</fieldset>';
		// submit button
		$sub_form .= '<p class="badgeos-nomination-submit"><input type="submit" name="badgeos_nomination_submit" value="'. esc_attr( $language['submit'] ) .'" /></p>';
	$sub_form .= '</form>';

	return apply_filters( 'badgeos_get_nomination_form', $sub_form );
}

function badgeos_get_submission_form( $args = array() ) {


	$defaults = array(
		'heading'    => sprintf( '<h4>%s</h4>', __( 'Submission Form', 'badgeos' ) ),
		'attachment' => __( 'Attachment:', 'badgeos' ),
		'submit'     => __( 'Submit', 'badgeos' )
	);
	// filter our text
	$new_defaults = apply_filters( 'badgeos_submission_form_language', $defaults );
	// fill in missing data
	$language = wp_parse_args( $new_defaults, $defaults );

	$sub_form = '<form class="badgeos-submission-form" method="post" enctype="multipart/form-data">';
		$sub_form .= wp_nonce_field( 'badgeos_submission_form', 'submit_submission', true, false );
		// submission form heading
		$sub_form .= '<legend>'. $language['heading'] .'</legend>';
		// submission file upload
		$sub_form .= '<fieldset class="badgeos-file-submission">';
		$sub_form .= '<p><label>'. $language['attachment'] .' <input type="file" name="document_file" id="document_file" /></label></p>';
		$sub_form .= '</fieldset>';
		// submission comment
		$sub_form .= '<fieldset class="badgeos-submission-comment">';
		$sub_form .= '<p><textarea name="badgeos_submission_content"></textarea></p>';
		$sub_form .= '</fieldset>';
		// submit button
		$sub_form .= '<p class="badgeos-submission-submit"><input type="submit" name="badgeos_submission_submit" value="'. $language['submit'] .'" /></p>';
	$sub_form .= '</form>';

	return apply_filters( 'badgeos_get_submission_form', $sub_form );
}

/**
 * Get achievement-based feedback
 *
 * @since  1.1.0
 * @param  array  $args An array of arguments to limit or alter output
 * @return string       Conatenated output for feedback
 */
function badgeos_get_feedback( $args = array() ) {

	// Setup our default args
	$defaults = array(
		'post_status' => 'publish',
		'post_type'   => 'submission'
	);
	$args = wp_parse_args( $args, $defaults );

	// If we want feedback connected to a specific achievement
	if ( isset( $args['achievement_id'] ) ) {
		$args['meta_key']   = '_badgeos_submission_achievement_id';
		$args['meta_value'] = absint( $args['achievement_id'] );
	}

	// Get our feedback
	$feedback = get_posts( $args );

	if ( ! empty( $feedback ) ) {

		$output = '<div class="badgeos-submissions">';

		foreach( $feedback as $submission ) {

			// Setup our output
			$output .= badgeos_render_submission( $submission );

			// Include any attachments
			if ( isset( $args['show_attachments'] ) && $args['show_attachments'] ) {
				$output .= badgeos_get_submission_attachments( $submission->ID );
			}

			// Include comments and comment form
			if ( isset( $args['show_comments'] ) && $args['show_comments'] ) {
				$output .= badgeos_get_comments_for_submission( $submission->ID );
				$output .= badgeos_get_comment_form( $submission->ID );
			}

		}; // End: foreach( $feedback )

		$output .= '</div><!-- badgeos-submissions -->';

	} // End: if ( $feedback )

	// Return our filterable output
	return apply_filters( 'badgeos_get_submissions', $output, $args, $feedback );
}

/**
 * Get achievement-based submission posts
 *
 * @since  1.1.0
 * @param  array  $args An array of arguments to limit or alter output
 * @return string       Conatenated output for submission, attachments and comments
 */
function badgeos_get_submissions( $args = array() ) {

	// Setup our default args
	$defaults = array(
		'post_type'        => 'submission',
		'show_attachments' => true,
		'show_comments'    => true
	);
	$args = wp_parse_args( $args, $defaults );

	// Grab our submissions
	$submissions = badgeos_get_feedback( $args );

	// Return our filterable output
	return apply_filters( 'badgeos_get_submissions', $submissions, $args );
}

/**
 * Get submissions attached to a specific achievement by a specific user
 *
 * @since  1.0.0
 * @param  integer $achievement_id The achievement's post ID
 * @param  integer $user_id        The user's ID
 * @return string                  Conatenated output for submission, attachments and comments
 */
function badgeos_get_user_submissions( $user_id = 0, $achievement_id = 0) {
	global $user_ID, $post;

	// Setup our empty args array
	$args = array();

	// Setup our author limit
	if ( ! empty( $user_id ) ) {
		// Use the provided user ID
		$args['author'] = absint( $user_id );
	} else {
		// If we're not an admin, limit results to the current user
		$badgeos_settings = get_option( 'badgeos_settings' );
		if ( ! current_user_can( $badgeos_settings['minimum_role'] ) ) {
			$args['author'] = $user_ID;
		}
	}

	// If we were not given an achievement ID,
	// use the current post's ID
	$args['achievement_id'] = ( absint( $achievement_id ) )
		? absint( $achievement_id )
		: $post->ID;

	// Grab our submissions for the current user
	$submissions = badgeos_get_submissions( $args );

	// Return filterable output
	return apply_filters( 'badgeos_get_user_submissions', $submissions, $achievement_id, $user_id );
}

/**
 * Render a given submission
 *
 * @since  1.1.0
 * @param  object $submission A submission post object
 * @return string             Concatenated output
 */
function badgeos_render_submission( $submission = null ) {
	global $post;

	// If we weren't given a submission, use the current post
	if ( empty( $submission ) ) {
		$submission = $post;
	}

	// Concatenate our output
	$output = '<h4>' . sprintf( __( 'Submission #%1$d', 'badgeos' ), $submission->ID ) . '</h4>';
	$output .= '<div class="badgeos-original-submission">';
		$output .= wpautop( $submission->post_content );
		$output .= '<p class="badgeos-comment-date-by">';
			$output .= sprintf( __( '%1$s by %2$s', 'badgeos' ),
				'<span class="badgeos-comment-date">' . get_the_time( 'F j, Y', $submission ) . '<span>',
				'<cite class="badgeos-comment-author">'. get_userdata( $submission->post_author )->display_name .'</cite>'
			);
			$output .= '<br/>';
			$output .= '<span class="badgeos-submission-label">' . __( 'Status:', 'badgeos' ) . '</span>&nbsp;';
			$output .= get_post_meta( $submission->ID, '_badgeos_submission_status', true );

			// If we're not on the achievement page, link to the connected achievement
			$achievement_id = get_post_meta( $submission->ID, '_badgeos_submission_achievement_id', true );
			if ( $achievement_id != $post->ID ) {
				$output .= '<br/>';
				$output .= '<span class="badgeos-connected-achievement">';
				$output .= sprintf( __( 'Achievement: %s' ), '<a href="' . get_permalink( $achievement_id ) .'">' . get_the_title( $achievement_id ) . '</a>' );
				$output .= '</span>';
			}
		$output .= '</p>';
	$output .= '</div><!-- .badgeos-original-submission -->';

	// Return our filterable output
	return apply_filters( 'badgeos_render_submission', $output, $submission );
}

/**
 * Get attachments connected to a specific achievement
 *
 * @since  1.1.0
 * @param  integer $submission_id The submission's post ID
 * @return string                 The concatenated attachment output
 */
function badgeos_get_submission_attachments( $submission_id = 0 ) {

	// Get attachments
	$attachments = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => -1,
		'post_parent'    => $submission_id,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );

	// If we have attachments
	if ( ! empty( $attachments ) ) {
		$output = '<h4>' . sprintf( __( 'Submission #%1$d Attachments', 'badgeos' ), $submission_id ) . '</h4>';
		$output .= '<ul class="badgeos-attachments-list">';
		foreach ( $attachments as $attachment ) {
			$output .= badgeos_render_submission_attachments( $attachment );
		}
		$output .= '</ul><!-- .badgeos-attachments-list -->';
	}

	// Return out filterable output
	return apply_filters( 'badgeos_get_submission_attachments', $output, $submission_id, $attachments );
}

/**
 * Renter a given submission attachment
 *
 * @since  1.1.0
 * @param  object $attachment The attachment post object
 * @return string             Concatenated markup
 */
function badgeos_render_submission_attachments( $attachment = null ) {
	// If we weren't given an attachment, use the current post
	if ( empty( $attachment ) ) {
		global $post;
		$attachment = $post;
	}

	// Concatenate the markup
	$output = '<li class="badgeos-attachment">';
	$output .= '<span class="badgeos-submission-label">' . __( 'Attachment:', 'badgeos' ) . '</span>&nbsp;';
	$output .= sprintf( __( '%1$s - uploaded %2$s by %3$s', 'badgeos' ),
		wp_get_attachment_link( $attachment->ID, 'thumbnail-size', false, null, $attachment->post_title ),
		get_the_time( 'F j, Y g:i a', $attachment ),
		get_userdata( $attachment->post_author )->display_name
	);
	$output .= '</li><!-- .badgeos-attachment -->';

// Return our filterable output
	return apply_filters( 'badgeos_render_submission_attachments', $output, $attachment );
}

/**
 * Render a given submission comment
 *
 * @since  1.1.0
 * @param  object $comment  The comment object
 * @param  string $odd_even Custom class to use for alternating comments (e.g. "odd" or "even")
 * @return string           Concatenated markup
 */
function badgeos_render_submission_comment( $comment = null, $odd_even = 'odd' ) {

	// Concatenate our output
	$output = '<li class="badgeos-submission-comment ' . $odd_even . '">';

		// Content
		$output .= '<div class="badgeos-comment-text">';
		$output .= wpautop( $comment->comment_content );
		$output .= '</div>';

		// Author and Meta info
		$output .= '<p class="badgeos-comment-date-by alignright">';
		$output .= sprintf( __( '%1$s by %2$s', 'badgeos' ),
			'<span class="badgeos-comment-date">' . get_comment_date( 'F j, Y g:i a', $comment->comment_ID ) . '<span>',
			'<cite class="badgeos-comment-author">' . get_userdata( $comment->user_id )->display_name . '</cite>'
		);
		$output .= '</p>';

	$output .= '</li><!-- badgeos-submission-comment -->';

	// Return our filterable output
	return apply_filters( 'badgeos_render_submission_comment', $output, $comment, $odd_even );
}
