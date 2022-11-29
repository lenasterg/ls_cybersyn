<?php
/**
* @last edit lenasterg 11/11/2021
*/
function ls_cybersyn_recent_pending( $pending = false ) {
	global $csyn_syndicator;
	?>
		<script>
		jQuery(document).on('ready', function () {
		jQuery('a#contextual-help-link').trigger('click');
		});
	</script>
	<?php
	$i = 0;
	if ( count( $csyn_syndicator->feeds ) > 0 ) {
		$table_pending_posts = " <div id='cyber_container'>";
					$v       = 0;
		for ( $i = 0; $i < count( $csyn_syndicator->feeds ); $i ++ ) {
			$last_update = $csyn_syndicator->feeds [ $i ]['updated'];
			unset( $pending );
			if ( $last_update ) {
				$pending_query = new WP_Query(
					array(
						'post_type'      => 'post',
						'post_status'    => 'pending',
						'posts_per_page' => 10,
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'meta_key'       => 'cyberseo_rss_source',
						'meta_value'     => $csyn_syndicator->feeds [ $i ]['url'],
					)
				);
				$pending       = & $pending_query->posts;
				if ( $pending && is_array( $pending ) ) {
					$v = 0;

					foreach ( $pending as $draft ) {
						$v ++;
						$url   = get_edit_post_link( $draft->ID );
						$title = _draft_or_post_title( $draft->ID );
						$item  = show_buttons( $draft ) . '<h3>' . esc_html( $title ) . "</a> </h3><abbr title='" . get_the_time( __( 'Y/m/d g:i:s A' ), $draft ) . "'>" . get_the_time( get_option( 'date_format' ), $draft ) . '</abbr> από <a href="' . $csyn_syndicator->feeds [ $i ]['url'] . '" '
							. 'target="_blank">' . $csyn_syndicator->feeds [ $i ]['title'] . '</a> ';

						$item .= $draft->post_excerpt;

						if ( $v % 2 ) {
							$table_pending_posts .= '<div class="box col1"><div class="box-content">' . $item . '</div></div>';
						}
						if ( ! ( $v % 2 ) ) {
							$table_pending_posts .= '<div class="box col2"><div class="box-content">' . $item . '</div></div>';
						}
					}
				}
			}
		}
		$table_pending_posts .= '</div>  '
			. '<p class="textright"><a href="edit.php?post_status=pending" >' . __( 'View all' ) . '</a></p>';
		if ( $v > 0 ) {
			echo '<h3>Πρόσφατα άρθρα από εξωτερικές πηγές που αναμένουν έγκριση σας για δημοσίευση</h3>';
			echo $table_pending_posts;
		}
		?>
		<script>
			jQuery(window).load(function ($) {
			var container = document.querySelector('#cyber_container');
			var msnry = new Masonry(container, {
				isInitLayout: false,
						isAnimated: true,
						itemSelector: '.box',
						animationOptions: {
							duration: 750,
							easing: 'linear',
							queue: false
						},
					});
					msnry._isLayoutInited = true;
					msnry.layout();
				});
		</script>
		<?php
	}
}

function ls_cybersyn_help_overview_content() {
	$ret = '<h2>Αξιοποιήστε την δυνατότητα άντλησης και αναδημοσίευσης εξωτερικού περιεχομένου</h2>
	<p class="about-description">
		Ανακαλύψτε στο διαδίκτυο επιλεγμένες ειδήσεις που θα θέλατε να αναδημοσιεύσετε.  <br/>
         Φιλτράρετε και παρουσιάστε με τον δικό σας τρόπο την επιλεγμένη είδηση. <br/>&nbsp;
    </p>';
	return $ret;
}

function ls_cybersyn_help_add_content() {
	$ret = '<h4>Ορισμός νέας εξωτερικής πηγής</h4>
                            <ol>
                                <li>Ορίστε το "<b>RSS feed url νέας εξωτερικής πηγής</b>" του δικτυακού τόπου από τον οποίο θέλετε να αντλείτε και
                                    να αναδημοσιεύσετε  άρθρα και πατήστε το "<b>Ρύθμιση</b>". </li>
                                <li> Στην επόμενη σελίδα ορίστε κριτήρια κατηγοριών, πλήθος άρθρων που θέλετε να ανακτώνται και άλλες παραμετροποιήσεις που τυχόν θέλετε και πατήστε το "<b>Αποθήκευση ρυθμίσεων</b>"</li>
                                <li> Επιλέξτε την πηγή και πατήστε "<b>Άντληση άρθρων από τις επιλεγμένες πηγές</b>" (Η διαδικασία παίρνει κάποιο χρόνο).</li>
                                <li>Τα αντληθέντα άρθρα αποθηκεύονται σε κατάσταση "Προς έγκριση" και εμφανίζονται στο κάτω μέρος της σελίδας.</li>
                                <li> Για κάθε άρθρο έχετε τις εξής δυνατότητες:
<ul><li><b>Προεπισκόπηση</b>: Να δείτε πως θα φαίνεται το άρθρο μέσα στο ιστολόγιο σας δίχως να το δημοσιεύσετε.</li>
<li><b>Δημοσίευση</b>:  Άμεση δημοσίευση του άρθρου δίχως να το επεξεργαστείτε.</li>
<li><b>Επεξεργασία</b>: Να αλλάξετε στοιχεία του άρθρου (πχ. τίτλο, περιεχόμενο, κατηγορία στην οποία ανήκει. Το κουμπί αυτό σας οδηγεί στην σελίδα επεξεργασίας άρθρου</li>
<li><b>Διαγραφή</b>: Να το διαγράψετε. </li>
</ul>
</li>
                            </ol>';
	return $ret;
}

function ls_cybersyn_help_existed_content() {
	$ret = '  <h4>Άντληση άρθρων από καταχωρημένες πηγές</h4>
                            <ol>
			    <li> Η άντληση άρθρων δεν γίνεται αυτόματα</li>
                                <li>Κάθε φορά που θέλετε να αντλήσετε άρθρα από κάποια πηγή που έχετε καταχωρήσει, μεταβείτε στο διαχειριστικό περιβάλλον και στην σελίδα «Άρθρα» --> «Άντληση άρθρων από εξωτερικές πηγές».Eπιλέξτε την πηγή και πατήστε "Άντληση άρθρων από τις επιλεγμένες πηγές" (Η διαδικασία παίρνει κάποιο χρόνο ).</li>
                                <li> Για κάθε άρθρο έχετε τις εξής δυνατότητες:
                                    <ul>
                                        <li><b>Προεπισκόπηση</b>: Να δείτε πως θα φαίνεται το άρθρο μέσα στο ιστολόγιο σας δίχως να το δημοσιεύσετε.</li>
                                        <li><b>Δημοσίευση</b>:  Άμεση δημοσίευση του άρθρου δίχως να το επεξεργαστείτε.</li>
                                        <li><b>Επεξεργασία</b>: Να αλλάξετε στοιχεία του άρθρου (πχ. τίτλο, περιεχόμενο, κατηγορία στην οποία ανήκει. Το κουμπί αυτό σας οδηγεί στην σελίδα επεξεργασίας άρθρου</li>
                                        <li><b>Διαγραφή</b>: Να το διαγράψετε. </li>
                                    </ul>
                                </li>
                            </ol>';
	return $ret;
}

function show_buttons( $draft ) {
	if ( current_user_can( 'manage_options' ) ) {//only print fi admin
		return '<form name="front_end_publish" method="POST" action="">'
		. '<div align="right"><input type="hidden" name="wp-preview" id="wp-preview" value="" />&nbsp;&nbsp;'
		. '<input type="hidden" name="pid" id="pid" value="' . $draft->ID . '">' .
		show_preview_button( $draft ) . show_publish_button( $draft ) . show_edit_button( $draft ) .
		show_delete_button( $draft ) . '</div></form>';
	}
}

/*
	 * function to print preview button
	 */

function show_preview_button( $post ) {
	$preview_link   = set_url_scheme( get_permalink( $post->ID ) );
	$preview_link   = esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', $preview_link ) ) );
	$preview_button = __( 'Preview' );
	return '<a class="button button-small " href="' . $preview_link . '" target="wp-preview" id="post-preview">' . $preview_button . '</a>';
}

/**
	 * function to print preview button
	 *
	 */
function show_delete_button( $post ) {
	if ( current_user_can( 'delete_post', $post->ID ) ) {
		if ( ! EMPTY_TRASH_DAYS ) {
			$delete_text = __( 'Delete Permanently' );
		} else {
			$delete_text = __( 'Move to Trash' );
		}
		return '&nbsp;&nbsp; <a class="button button-small delete button-delete" href="' . get_delete_post_link( $post->ID ) . '" >' . $delete_text . '</a>';
	}
}

/* function to print edit button
	 *
	 */

function show_edit_button( $post ) {
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		$edit_text = __( 'Edit' ) . '...';
		return '&nbsp;&nbsp;<a  class="button edit button-small" href="' . get_edit_post_link( $post->ID ) . '" title="' . sprintf( __( 'Edit &#8220;%s&#8221;' ), esc_attr( _draft_or_post_title( $post->ID ) ) ) . '">' . $edit_text . '</a>';
	}
}

/*
	 * function to print publish button
	 *
	 */

function show_publish_button( $post ) {
	return ' <input type="hidden" name="FE_PUBLISH" id="FE_PUBLISH" value="FE_PUBLISH">' .
		get_submit_button( __( 'Publish' ), 'primary button-small', 'publish', false, array( 'accesskey' => 'p' ) );
}

//function to update post status
function change_post_status() {

	$time         = current_time( 'mysql' );
	$post_id      = (int) $_POST['pid'];
	$current_post = get_post( $post_id, ARRAY_A );

	$current_post['post_content']      = $current_post['post_content'] . ' ';
	$current_post['post_status']       = 'publish';
	$current_post['post_date']         = $time;
	$current_post['post_modified']     = $time;
	$current_post['post_date_gmt']     = get_gmt_from_date( $time );
	$current_post['post_modified_gmt'] = get_gmt_from_date( $time );
	$current_post['edit_date']         = true;
	if ( wp_update_post( $current_post ) > 0 ) {
		remove_action( 'admin_notices', 'wpsites_admin_notice_delete' );
		add_action( 'admin_notices', 'wpsites_admin_notice' );
	}
}

function wpsites_admin_notice_delete() {
	$trashed = absint( $_REQUEST['trashed'] );
	?>
		<div class="update-nag">
			<p>
	<?php printf( _n( 'Item moved to the Trash.', '%s items moved to the Trash.', $trashed ), number_format_i18n( $trashed ) ); ?>

				</p>
		</div>
	<?php
}

function wpsites_admin_notice() {
	?>
		<div class="update-nag">
			<p><?php printf( __( 'Post published. <a href="%s">View post</a>' ), esc_url( get_permalink( $_POST['pid'] ) ) ); ?>
			</p>
		</div>
	<?php
}

if ( isset( $_REQUEST['trashed'] ) && $trashed = absint( $_REQUEST['trashed'] ) ) {
	add_action( 'admin_notices', 'wpsites_admin_notice_delete' );
}

if ( isset( $_POST['FE_PUBLISH'] ) && $_POST['FE_PUBLISH'] == 'FE_PUBLISH' ) {
	if ( isset( $_POST['pid'] ) && ! empty( $_POST['pid'] ) ) {
		add_action( 'admin_init', 'change_post_status' );
	}
}
