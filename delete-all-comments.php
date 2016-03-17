<?php
/*
Plugin Name: Delete All Comments
Plugin URI: http://www.oviamsolutions.com/plugins/delete-all-comments
Description: Plugin to delete all comments (Approved, Pending, Spam)
Author: Ganesh Chandra
Version: 1.1
Author URI: http://www.oviamsolutions.com
*/

define( 'DAC_VERSION', '1.1' );

// Register JavaScript
wp_register_script( 'delete-all-comments.js', plugin_dir_url( __FILE__ ) . 'delete-all-comments.js', array( 'jquery' ),
	DAC_VERSION );
wp_enqueue_script( 'delete-all-comments.js' );

function dac_admin_tabs( $current = 'main' ) {
	$tabs = array( 'main' => 'All Comments', 'older' => 'Delete Older' );
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $tab => $name ) {
		$class = ( $tab == $current ) ? ' nav-tab-active' : '';
		echo '<a class="nav-tab' . $class . '" href="?page=oviam_delete_all_comments&tab=' . $tab .'">' . $name . '</a>';
	}
	echo '</h2>';
}

add_action( 'admin_menu', 'oviam_dac_admin_actions' );

function oviam_dac_admin_actions() {
	add_management_page( 'Delete All Comments',
		'Delete All Comments', 1, 'oviam_delete_all_comments', 'dac_admin_page' );
} // End of function oviam_dac_admin_actions

// Used session for async deletion
add_action( 'init', 'dac_session_start', 1 );

function dac_session_start() {
	if ( ! session_id() ) {
		session_start();
	}
}

// Register AJAX callbacks
// Get the comments count by days ago
add_action( 'wp_ajax_dac_prepare_deletion', 'dac_prepare_deletion' );

function dac_prepare_deletion() {
	global $wpdb;

	$days = dac_get_days();

	$comments_count = $wpdb->get_var( "SELECT count(comment_id)
							FROM {$wpdb->comments}
							WHERE comment_date < DATE_ADD(CURDATE(), INTERVAL -{$days} DAY)" );

	$_SESSION['dac_delete_comments_count'] = $comments_count;

	wp_send_json_success( array(
		'count' => (int) $comments_count,
	) );
}

// Async deletion
add_action( 'wp_ajax_dac_confirm_deletion', 'dac_confirm_deletion' );

function dac_confirm_deletion() {
	global $wpdb;

	$days  = dac_get_days();
	$limit = dac_get_limit();

	$comments_count = $wpdb->get_var( "SELECT count(comment_id)
                                      FROM {$wpdb->comments}
                                      WHERE comment_date < DATE_ADD(CURDATE(), INTERVAL -{$days} DAY)" );

	if ( $comments_count > 0 ) {
		$comments = $wpdb->get_results( "SELECT comment_id from {$wpdb->comments}
                        WHERE comment_date < DATE_ADD(CURDATE(), INTERVAL -{$days} DAY)
                        ORDER BY `comment_id` ASC
                        LIMIT 0, {$limit}
                        " );

		// Delete!
		foreach ( $comments as $comment ) {
			// @url https://developer.wordpress.org/reference/functions/wp_delete_comment/
			wp_delete_comment( $comment->comment_id, true );
		}

		$comments_count = $comments_count - $limit;
	}

	$total_count = $_SESSION['dac_delete_comments_count'];

	wp_send_json_success( array(
		'count' => (int) $total_count,
		'left'  => (int) $comments_count,
	) );
}

// Plugin admin page
function dac_admin_page() {
	$tab = isset ( $_GET['tab'] ) ? $_GET['tab'] : 'main';
	?>
	<div class="wrap">
		<?php
		dac_admin_tabs( $tab );

		switch ( $tab ) {
			case 'main' :
				oviam_dac_main();
				break;
			case 'older':
				dac_delete_older_page();
				break;
		}
		?>
	</div>
	<?php
}

function oviam_log_me( $message ) {
	if ( WP_DEBUG === true ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( print_r( $message, true ) );
		} else {
			error_log( $message );
		}
	}
}

function oviam_dac_main() {
	global $wpdb;
	$comments_count = $wpdb->get_var( "SELECT count(comment_id) from $wpdb->comments" );

	?>
	<div class="wrap">
	<h2>Delete All Comments</h2>
	<?php
	if ( isset( $_POST['chkdelete'] ) == 'Y' ) {
		if ( wp_verify_nonce( $_POST['ovi@m_safe_c0dr'], 'ovi@m_safe_c0dr' ) ) {
			if ( $wpdb->query( "TRUNCATE $wpdb->commentmeta" ) != false ) {
				if ( $wpdb->query( "TRUNCATE $wpdb->comments" ) != false ) {
					$wpdb->query( "Update $wpdb->posts set comment_count = 0 where post_author != 0" );
					$wpdb->query( "OPTIMIZE TABLE $wpdb->commentmeta" );
					$wpdb->query( "OPTIMIZE TABLE $wpdb->comments" );
					echo "<p style='color:green'><strong>All comments have been deleted.</strong></p>";
				} else {
					oviam_log_me( 'Error occured when deleting wpdb comments table' );
					echo "<p style='color:red'><strong>Internal error occured. Please try again later.</strong></p>";
				}
			} else {
				oviam_log_me( 'Error occured when deleting wpdb commentmeta table' );
				echo "<p style='color:red'><strong>Internal error occured. Please try again later.</strong></p>";
			}
		} // End of verify_nonce
		else {
			oviam_log_me( 'Security failure' );
			die( "Security Validation Failure" );
		} // End of Security
	} // End of if comment remove ='Y'
	else {
		echo "<h4>Total Comments : " . $comments_count . " </h4>";
		?>

		<?php if ( $comments_count > 0 ) { ?>
			<p><strong>Note: Please check the box and click Delete All.</strong></p>
			<form name="frmOviamdac" method="post"
			      action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] ); ?>">
				<input type="hidden" name="ovi@m_safe_c0dr" value="<?php echo wp_create_nonce( 'ovi@m_safe_c0dr' ); ?>">
				<input type="checkbox" name="chkdelete" value="Y"/> Delete all comments
				<p class="submit">
					<input type="submit" name="Submit" value="Delete All"/>
				</p>
			</form>
			<?php
		} // End of if comments_count > 0
		else {
			echo "<p><strong>All comments have been deleted.</strong></p>";
		} // End of else comments_count > 0
		?>
		</div>
		<?php
	} // else of if comment remove == 'Y'
} // End of function oviam_dac_main

// Wizard for deletions older comments
function dac_delete_older_page() {
	?>
	<h2>Delete older comments</h2>
	<p>Comments are deleted asynchronously.</p>

	<form id="dacFormDeleteOlder" name="dacFormDeleteOlder" method="post" action="">
		<table class="form-table">
			<tbody>
			<tr>
				<th><label for="dacOlderDays">Delete comments older than x days</label></th>
				<td>
					<input type="number" id="dacOlderDays" name="dacOlderDays" value="365"
					       class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="dacOlderLimit">Delete per step</label></th>
				<td>
					<input type="number" id="dacOlderLimit" name="dacOlderLimit" value="50"
					       class="small-text">
					<p class="description">How many comments delete per step.<br>
						<strong>Caution!</strong> Do not use a very large value.</p>
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" value="Next" class="button button-primary">
		</p>
	</form>

	<form id="dacFormDeleteOlderConfirm" name="dacFormDeleteOlderConfirm" method="post" action=""
	      style="display: none">

		<p><strong>"<span id="deleteCount"></span>"</strong> comments wil bee deleted. Confirm?</p>

		<div id="dacProgress" style="display: none">
			<progress id="dacProgressBar" max="100" value="0"></progress>
			<p>Left: <strong>"<span id="dacLeftCount"></span>"</strong></p>
		</div>

		<p class="submit">
			<button type="button" id="dacCancelConfirm" class="button">Cancel</button>
			<input type="submit" id="dacSubmitConfirm" value="Confirm Deletion"
			       class="button button-primary">
		</p>
	</form>
	<?php
}

function dac_get_days() {
	return intval( isset( $_POST['dacOlderDays'] ) ? $_POST['dacOlderDays'] : 0 );
}

function dac_get_limit() {
	return intval( isset( $_POST['dacOlderLimit'] ) ? $_POST['dacOlderLimit'] : 50 );
}
