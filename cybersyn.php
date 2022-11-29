<?php
/**
 * Plugin Name: LS CyberSyn
 * Version: 2, 29/11/2022
 * Author: lenasterg
 * Author URI: https://github.com/lenasterg/
 * Plugin URI:
 * Description: LS CyberSyn is heavily based on  CyberSyn plugin. It' s powerful, lightweight and easy to use Atom/RSS aggregation and content curation plugin for WordPress.
 * Network: true
 */

if ( ! function_exists( 'get_option' ) || ! function_exists( 'add_filter' ) ) {
	die();
}
if ( is_admin() ) {

	global $ls_cybersyn_db_version;
	$ls_cybersyn_db_version = '1.0';

	register_activation_hook( __FILE__, 'ls_cybersyn_install_update_db_check' );
	add_action( 'plugins_loaded', 'ls_cybersyn_install_update_db_check' );


	/**
	 * Checks the ls_cybersyn_db_version on wp_option database table
	 *
	 * @global type $wpdb
	 * @global string $ls_cybersyn_db_version
	 * @author lenasterg
	 *
	 * @since 29/11/2022
	 */
function ls_cybersyn_install_update_db_check() {
	global $ls_cybersyn_db_version;
	if ( get_site_option( 'ls_cybersyn_db_version' ) !== $ls_cybersyn_db_version ) {
		ls_cybersyn_install();
	}

}

	/**
	 * Creates a DB table for logging on activation
	 *
	 * @global type $wpdb
	 * @global string $ls_cybersyn_db_version
	 * @author lenasterg
	 *
	 * @since 29/11/2022
	 */
function ls_cybersyn_install() {
	global $wpdb;
	global $ls_cybersyn_db_version;

	$table_name = $wpdb->base_prefix . 'ls_cybersyn';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		`blog_id` int(11) NOT NULL,
		`feed_url` text NOT NULL,
		`register_date` datetime NOT NULL,
		`last_updated` datetime NOT NULL,
		`deleted` tinyint(4) DEFAULT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	maybe_create_table( $table_name, $sql );

	update_site_option( 'ls_cybersyn_db_version', $ls_cybersyn_db_version );
}

	include_once( 'functions.php' );
	define( 'CSYN_MAX_CURL_REDIRECTS', 10 );
	define( 'CSYN_MAX_DONLOAD_ATTEMPTS', 10 );
	define( 'CSYN_FEED_OPTIONS', 'cxxx_feed_options' );
	define( 'CSYN_SYNDICATED_FEEDS', 'cxxx_syndicated_feeds' );
	define( 'CSYN_RSS_PULL_MODE', 'cxxx_rss_pull_mode' );

	define( 'CSYN_PSEUDO_CRON_INTERVAL', 'cxxx_pseudo_cron_interval' );
	define( 'CSYN_DISABLE_DUPLICATION_CONTROL', 'cxxx_disable_feed_duplication_control' );

	define( 'CSYN_LINK_TO_SOURCE', 'cxxx_link_to_source' );

	$classes = 'postbox';


	$csyn_banner = '<div id="welcome-panel" class="metabox-holder ">' .
	'<div class="' . esc_attr( $classes ) . '">'
	. '<div class="inside">' .
		ls_cybersyn_help_overview_content() .
	'
            </div>
            </div>
        ';

function ls_cybersyn_mk_post_data( $data ) {
	$result = '';
	foreach ( $data as $key => $value ) {
		$result .= $key . '=' . urlencode( $value ) . '&';
	}
	return $result;
}

function csyn_curl_post( $url, $data, &$info ) {
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_REFERER, $url );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, ls_cybersyn_mk_post_data( $data ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	$result = trim( curl_exec( $ch ) );
	$info   = curl_getinfo( $ch );
	curl_close( $ch );
	return $result;
}

function csyn_short_str( $url, $max = 0 ) {
	$length = strlen( $url );
	if ( $max > 1 && $length > $max ) {
		$ninety = $max * 0.9;
		$length = $length - $ninety;
		$first  = substr( $url, 0, -$length );
		$last   = substr( $url, $ninety - $max );
		$url    = $first . '&#8230;' . $last;
	}
	return $url;
}

function csyn_REQUEST_URI() {
	return strtok( $_SERVER['REQUEST_URI'], '?' ) . '?' . strtok( '?' );
}

function csyn_fix_white_spaces( $str ) {
	return preg_replace( '/\s\s+/', ' ', preg_replace( '/\s\"/', ' "', preg_replace( '/\s\'/', ' \'', $str ) ) );
}

function csyn_delete_post_images( $post_id ) {
	$post          = get_post( $post_id, ARRAY_A );
	$wp_upload_dir = wp_upload_dir();

	preg_match_all( '/<img(.+?)src=[\'\"](.+?)[\'\"](.*?)>/is', $post['post_content'] . $post['post_excerpt'], $matches );
	$image_urls = $matches[2];

	if ( count( $image_urls ) ) {
		$image_urls = array_unique( $image_urls );
		foreach ( $image_urls as $url ) {
			@unlink( str_replace( $wp_upload_dir['url'], $wp_upload_dir['path'], $url ) );
		}
	}
}

	/**
	 *
	 * @param string $url
	 * @return string
	 */
function csyn_addslash( $url ) {
	if ( '/' !== $url[ strlen( $url ) - 1 ] ) {
		$url .= '/';
	}
	return $url;
}

function csyn_file_get_contents( $url, $as_array = false ) {
	global $csyn_last_effective_url;
	if ( '' !== @parse_url( $url, PHP_URL_SCHEME ) && function_exists( 'curl_init' ) ) {
		$max_redirects = CSYN_MAX_CURL_REDIRECTS;
		$ch            = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_REFERER, $url );
		curl_setopt( $ch, CURLOPT_ENCODING, 'gzip,deflate' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		if ( '' === ini_get( 'open_basedir' ) && ( 'Off' === ini_get( 'safe_mode' ) || ! ini_get( 'safe_mode' ) ) ) {
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, $max_redirects );
		} else {
			$base_url = $url;
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
			$rch = curl_copy_handle( $ch );
			curl_setopt( $rch, CURLOPT_HEADER, true );
			curl_setopt( $rch, CURLOPT_NOBODY, true );
			curl_setopt( $rch, CURLOPT_FORBID_REUSE, false );
			curl_setopt( $rch, CURLOPT_RETURNTRANSFER, true );
			do {
				curl_setopt( $rch, CURLOPT_URL, $url );
				curl_setopt( $rch, CURLOPT_REFERER, $url );
				$header = curl_exec( $rch );
				if ( curl_errno( $rch ) ) {
					$code = 0;
				} else {
					$code = curl_getinfo( $rch, CURLINFO_HTTP_CODE );
					if ( $code === 301 || $code === 302 ) {
						preg_match( '/Location:(.*?)\n/', $header, $matches );
						$url = trim( array_pop( $matches ) );
						if ( strlen( $url ) && substr( $url, 0, 1 ) === '/' ) {
							$url = $base_url . $url;
						}
					} else {
						$code = 0;
					}
				}
			} while ( $code && -- $max_redirects );
			curl_close( $rch );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_REFERER, $url );
		}
		$attempts = 0;
		$code     = 206;
		while ( $code === 206 && $attempts ++ < CSYN_MAX_DONLOAD_ATTEMPTS ) {
			curl_setopt( $ch, CURLOPT_HEADER, false );
			$content                 = curl_exec( $ch );
			$csyn_last_effective_url = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
			$c_length                = curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
			$c_download              = curl_getinfo( $ch, CURLINFO_SIZE_DOWNLOAD );
			if ( $c_length > $c_download ) {
				$code = 206;
			} else {
				$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			}
		}
		curl_close( $ch );

		if ( $code !== 200 || $c_length > $c_download ) {
			$content = false;
		} elseif ( $as_array ) {
			$content = explode( "\n", trim( $content ) );
		}
	}
	if ( ! isset( $content ) || $content === false ) {
		if ( $as_array ) {
			$content = file( $url, FILE_IGNORE_NEW_LINES );
		} else {
			$content = file_get_contents( $url );
		}
		$csyn_last_effective_url = $url;
	}
	return $content;
}

	/**
	 *
	 * @param type $options
	 * @return int
	 * @version 2, lenasterg change default
	 */
function ls_cybersyn_update_options( &$options ) {
	global $current_user;
	wp_get_current_user();

	$defaults = array(
		'interval'                 => 0,
		'max_items'                => 5,
		'post_status'              => 'pending',
		'comment_status'           => 'open',
		'ping_status'              => 'closed',
		'post_author'              => $current_user->ID,
		'base_date'                => 'syndication',
		'duplicate_check_method'   => 'guid_and_title',
		'undefined_category'       => 'use_default',
		'synonymizer_mode'         => '0',
		'create_tags'              => '',
		'post_tags'                => '',
		'post_category'            => array(),
		'date_min'                 => 0,
		'date_max'                 => 0,
		'insert_media_attachments' => 'no',
		'convert_encoding'         => '',
		'store_images'             => '',
		'post_footer'              => '',
		'include_post_footers'     => '',
		'embed_videos'             => '',
	);

	$result = 0;

	foreach ( $defaults as $key => $value ) {
		if ( ! isset( $options[ $key ] ) ) {
			$options[ $key ] = $value;
			$result          = 1;
		}
	}

	return $result;
}

	/**
		 * @version 2 stergatu 1/4/2014, comment useless
	 */
function csyn_preset_options() {
	if ( get_option( CSYN_DISABLE_DUPLICATION_CONTROL ) === false ) {
		csyn_set_option( CSYN_DISABLE_DUPLICATION_CONTROL, '', '', 'yes' );
	}

	if ( get_option( CSYN_SYNDICATED_FEEDS ) === false ) {
		csyn_set_option( CSYN_SYNDICATED_FEEDS, array(), '', 'yes' );
	}

	if ( get_option( CSYN_RSS_PULL_MODE ) === false ) {
		csyn_set_option( CSYN_RSS_PULL_MODE, 'auto', '', 'yes' );
	}
	if ( get_option( CSYN_LINK_TO_SOURCE ) === false ) {
		csyn_set_option( CSYN_LINK_TO_SOURCE, 'auto', '', 'yes' );
	}
}

function csyn_compare_files( $file_name_1, $file_name_2 ) {
	$file1 = csyn_file_get_contents( $file_name_1 );
	$file2 = csyn_file_get_contents( $file_name_2 );
	if ( $file1 && $file2 ) {
		return ( md5( $file1 ) === md5( $file2 ) );
	}
	return false;
}

function csyn_save_image( $image_url, $preferred_name = '' ) {
	$wp_upload_dir = wp_upload_dir();
	if ( is_writable( $wp_upload_dir['path'] ) ) {
		$image_file = csyn_file_get_contents( $image_url );
		preg_match( '/.*?(\.[a-zA-Z]+$)/', $image_url, $matches );
		$ext               = $matches[1];
		$default_file_name = sanitize_file_name( sanitize_title( $preferred_name ) . $ext );
		if ( $preferred_name != '' && strpos( $default_file_name, '%' ) === false ) {
			$file_name = $default_file_name;
		} else {
			$file_name = basename( $image_url );
		}
		if ( file_exists( $wp_upload_dir['path'] . '/' . $file_name ) ) {
			if ( csyn_compare_files( $image_url, $wp_upload_dir['path'] . '/' . $file_name ) ) {
				return $wp_upload_dir['url'] . '/' . $file_name;
			}
			$file_name = wp_unique_filename( $wp_upload_dir['path'], $file_name );
		}
		$image_path      = $wp_upload_dir['path'] . '/' . $file_name;
		$local_image_url = $wp_upload_dir['url'] . '/' . $file_name;

		if ( file_put_contents( $image_path, $image_file ) ) {
			return $local_image_url;
		}
	}
	return $image_url;
}

class Ls_CyberSyn_Syndicator {

	var $post = array();
	var $insideitem;
	var $element_tag;
	var $tag;
	var $count;
	var $failure;
	var $posts_found;
	var $max;
	var $current_feed     = array();
	var $current_feed_url = '';
	var $feeds            = array();
	var $update_period;
	var $feed_title;
	var $blog_charset;
	var $feed_charset;
	var $feed_charset_convert;
	var $preview;
	var $global_options = array();
	var $edit_existing;
	var $current_category;
	var $current_custom_field;
	var $current_custom_field_attr = array();
	var $generator;
	var $xml_parse_error;
	var $show_report = false;


	function __construct() {
		$this->blog_charset = strtoupper( get_option( 'blog_charset' ) );

		$this->global_options = get_option( CSYN_FEED_OPTIONS );
		if ( ls_cybersyn_update_options( $this->global_options ) ) {
			csyn_set_option( CSYN_FEED_OPTIONS, $this->global_options, '', 'yes' );
		}

		$this->feeds = get_option( CSYN_SYNDICATED_FEEDS );
		$changed     = 0;
		for ( $i = 0; $i < count( $this->feeds ); $i ++ ) {
			$changed += ls_cybersyn_update_options( $this->feeds [ $i ]['options'] );
		}
		if ( $changed ) {
			csyn_set_option( CSYN_SYNDICATED_FEEDS, $this->feeds, '', 'yes' );
		}
	}

	function fixURL( $url ) {
		$url = trim( $url );
		if ( strlen( $url ) > 0 && ! preg_match( '!^https?://.+!i', $url ) ) {
			$url = 'http://' . $url;
		}
		return $url;
	}

	function extractEmbeddableCode( $content ) {
		preg_match( '/www\.youtube\.com\/watch\?v=(.+)&/', $content, $matches );
		if ( isset( $matches[1] ) ) {
			$id         = $matches[1];
			$video_page = csyn_file_get_contents( 'http://www.youtube.com/watch?v=' . $id );
			preg_match( '/<div id="watch-description-text">(.*?)<\/div>/is', $video_page, $matches );
			if ( isset( $matches[1] ) ) {
				return '<p><iframe class="embedded_video" width="560" height="315" src="http://www.youtube.com/embed/' . $id . '" frameborder="0" allowfullscreen></iframe></p>' . $matches[1];
			}
		}
		return $content;
	}

	function resetPost() {
		global $csyn_urls_to_check;
		$this->post ['post_title']      = '';
		$this->post ['post_content']    = '';
		$this->post ['post_excerpt']    = '';
		$this->post ['guid']            = '';
		$this->post ['post_date']       = time();
		$this->post ['post_date_gmt']   = time();
		$this->post ['post_name']       = '';
		$this->post ['categories']      = array();
		$this->post ['comments']        = array();
		$this->post ['media_content']   = array();
		$this->post ['media_thumbnail'] = array();
		$this->post ['enclosure_url']   = '';
		$this->post ['link']            = '';
		$this->post ['options']         = array();
		$csyn_urls_to_check             = array();
	}

	function parse_w3cdtf( $w3cdate ) {
		if ( preg_match( '/^\s*(\d{4})(-(\d{2})(-(\d{2})(T(\d{2}):(\d{2})(:(\d{2})(\.\d+)?)?(?:([-+])(\d{2}):?(\d{2})|(Z))?)?)?)?\s*$/', $w3cdate, $match ) ) {
			list($year , $month , $day , $hours , $minutes , $seconds) = array( $match[1], $match[3], $match[5], $match[7], $match[8], $match[10] );
			if ( is_null( $month ) ) {
				$month = (int) gmdate( 'm' );
			}
			if ( is_null( $day ) ) {
				$day = (int) gmdate( 'd' );
			}
			if ( is_null( $hours ) ) {
				$hours   = (int) gmdate( 'H' );
				$seconds = $minutes = 0;
			}
			$epoch = gmmktime( $hours, $minutes, $seconds, $month, $day, $year );
			if ( $match[14] != 'Z' ) {
				list($tz_mod , $tz_hour , $tz_min) = array( $match[12], $match[13], $match[14] );
				$tz_hour                           = (int) $tz_hour;
				$tz_min                            = (int) $tz_min;
				$offset_secs                       = ( ( $tz_hour * 60 ) + $tz_min ) * 60;
				if ( $tz_mod === '+' ) {
					$offset_secs *= - 1;
				}
				$offset = $offset_secs;
			}
			$epoch = $epoch + $offset;
			return $epoch;
		} else {
			return -1;
		}
	}

	function parseFeed( $feed_url ) {
		$this->tag                  = '';
		$this->insideitem           = false;
		$this->element_tag          = '';
		$this->feed_title           = '';
		$this->generator            = '';
		$this->current_feed_url     = $feed_url;
		$this->feed_charset_convert = '';
		$this->posts_found          = 0;
		$this->failure              = false;

		if ( $this->preview ) {
			$options = $this->global_options;
		} else {
			$options = $this->current_feed ['options'];
		}

		$feed_url = $this->current_feed_url;

		$rss_lines = csyn_file_get_contents( $feed_url, true );

		if ( is_array( $rss_lines ) && count( $rss_lines ) > 0 ) {
			preg_match( "/encoding[. ]?=[. ]?[\"'](.*?)[\"']/i", $rss_lines[0], $matches );
			if ( isset( $matches[1] ) && $matches[1] != '' ) {
				$this->feed_charset = trim( $matches[1] );
			} else {
				$this->feed_charset = 'not defined';
			}

			$xml_parser = xml_parser_create();
			xml_parser_set_option( $xml_parser, XML_OPTION_TARGET_ENCODING, $this->blog_charset );
			xml_set_object( $xml_parser, $this );
			xml_set_element_handler( $xml_parser, 'startElement', 'endElement' );
			xml_set_character_data_handler( $xml_parser, 'charData' );

			$do_mb_convert_encoding = ( $options['convert_encoding'] === 'on' && $this->feed_charset != 'not defined' && $this->blog_charset != strtoupper( $this->feed_charset ) );

			$this->xml_parse_error = 0;
			foreach ( $rss_lines as $line ) {
				if ( $this->count >= $this->max || $this->failure ) {
					break;
				}
				if ( $do_mb_convert_encoding && function_exists( 'mb_convert_encoding' ) ) {
					$line = mb_convert_encoding( $line, $this->blog_charset, $this->feed_charset );
				}

				if ( ! xml_parse( $xml_parser, $line . "\n" ) ) {
					$this->xml_parse_error = xml_get_error_code( $xml_parser );
					xml_parser_free( $xml_parser );
					return false;
				}
			}

			xml_parser_free( $xml_parser );
			return $this->count;
		} else {
			return false;
		}
	}

	function syndicateFeeds( $feed_ids, $check_time ) {
		$this->preview = false;
		$feeds_cnt     = count( $this->feeds );
		if ( count( $feed_ids ) > 0 ) {
			if ( $this->show_report ) {
				ob_end_flush();
				ob_implicit_flush();
				echo "<div id=\"message\" class=\"updated fade\"><p>\n";
				flush();
			}
			set_time_limit( 60 * 60 );
			for ( $i = 0; $i < $feeds_cnt; $i ++ ) {
				if ( in_array( $i, $feed_ids ) ) {
					if ( ! $check_time || $this->getUpdateTime( $this->feeds [ $i ] ) === 'asap' ) {
						$this->feeds [ $i ]['updated'] = time();
						csyn_set_option( CSYN_SYNDICATED_FEEDS, $this->feeds, '', 'yes' );
						$this->current_feed = $this->feeds [ $i ];

						$this->resetPost();
						$this->max = (int) $this->current_feed ['options']['max_items'];
						if ( $this->show_report ) {
							_e( 'Syndicating', 'cybersyn' );
							echo ' <a href="' . htmlspecialchars( $this->current_feed ['url'] ) . '" target="_blank"><strong>' . $this->current_feed ['title'] . "</strong></a>...<img src='/wp-admin/images/wpspin_light.gif' alt='' class='loading'> \n";
							flush();
						}
						if ( $this->current_feed ['options']['undefined_category'] === 'use_global' ) {
							$this->current_feed ['options']['undefined_category'] = $this->global_options ['undefined_category'];
						}
						$this->count = 0;

						$result = $this->parseFeed( $this->current_feed ['url'] );

						if ( $this->show_report ) {
							echo '<script>
  jQuery(function(){
    jQuery(".loading").hide();
  });
</script>
';
							//document.getElementById("loading").style.visibility = "hidden";</script>
							if ( 1 === $this->count ) {
								echo $this->count . ' ' . __( 'post was added', 'cybersyn' );
							} else {
								echo $this->count . ' ' . __( 'posts were added', 'cybersyn' );
							}

							if ( false === $result ) {
								echo ' [!]';
							} else {
								ls_cybersyn_log( $this->current_feed );
							}
							echo "<br />\n";
							flush();
						}
					}
				}
			}
			if ( isset( $save_options ) ) {
				csyn_set_option( CSYN_SYNDICATED_FEEDS, $this->feeds, '', 'yes' );
			}
			if ( $this->show_report ) {
				echo "</p></div>\n";
			}
		}
	}

	/**
	 * @version 2, 1/4/2014  stergatu added  class="alignleft"
	 */
	function displayPost() {
		echo '<p><strong>' . __( 'Feed Title', 'cybersyn' ) . ':</strong> ' . $this->feed_title . "<br />\n";
		echo '<strong>URL:</strong> ' . htmlspecialchars( $this->current_feed_url ) . "<br />\n";
		if ( $this->generator != '' ) {
			echo '<strong>' . __( 'Generator', 'cybersyn' ) . ':</strong> ' . $this->generator . "<br />\n";
		}
		echo '<strong>Charset Encoding:</strong> ' . $this->feed_charset . "</p>\n";
		echo '<strong>' . __( 'Title', 'cybersyn' ) . ':</strong> ' . csyn_fix_white_spaces( trim( $this->post ['post_title'] ) ) . "<br />\n";
		echo '<strong>' . __( 'Date', 'cybersyn' ) . ':</strong> ' . gmdate( 'Y-m-d H:i:s', (int) $this->post ['post_date'] ) . "<br />\n";
		if ( mb_strlen( 0 === trim( $this->post ['post_content'] ) ) ) {
			$this->post ['post_content'] = $this->post ['post_excerpt'];
		}

		echo '<div style="overflow:auto; max-height:250px; border:1px #ccc solid; background-color:white; padding:8px; margin:8px 0 8px; 0;">' . "\n";
		echo csyn_fix_white_spaces( trim( $this->post ['post_content'] ) );
		echo '</div>' . "\n";

		$attachment = '';
		if ( sizeof( $this->post ['media_thumbnail'] ) ) {
			$attachment .= '<div class="media_block">' . "\n";
			for ( $i = 0; $i < sizeof( $this->post ['media_thumbnail'] ); $i ++ ) {
				if ( isset( $this->post ['media_content'][ $i ] ) ) {
					$attachment .= '<a href="' . $this->post ['media_content'][ $i ] . '"><img src="' . $this->post ['media_thumbnail'][ $i ] . '" class="media_thumbnail" class="alignleft"></a>' . "\n";
				} else {
					$attachment .= '<img src="' . $this->post ['media_thumbnail'][ $i ] . '" class="media_thumbnail" class="alignleft">' . "\n";
				}
			}
			$attachment .= '</div>';
		}
		if ( $this->post ['enclosure_url'] != '' && $this->post ['link'] != '' ) {
			$attachment .= '<div class="media_block">' . "\n";
			$attachment .= '<a href="' . $this->post ['link'] . '"><img src="' . $this->post ['enclosure_url'] . '" class="alignleft"></a>';
			$attachment .= '</div>';
		}
		if ( $attachment != '' ) {
			echo "<br /><br /><strong>Attachments </strong> (adjust the \"Media Attachments\" settings to handle them):<br /><hr />\n" . $attachment . "<hr />\n";
		}
	}

	function feedPreview( $feed_url, $edit_existing = false ) {
		echo "<br />\n";
		$this->edit_existing = $edit_existing;
		$no_feed_dupes       = get_option( CSYN_DISABLE_DUPLICATION_CONTROL ) != 'on';
		if ( ! $this->edit_existing ) {
			for ( $i = 0; $i < count( $this->feeds ); $i ++ ) {
				if ( $no_feed_dupes && $this->feeds [ $i ]['url'] === $feed_url ) {
					echo '<div id="message" class="error"><p><strong>' . __( 'This feed is already in use', 'cybersyn' ) . '</strong></p></div>' . "\n";
					return false;
				}
			}
		}
		$this->max     = 1;
		$this->preview = true;
		?>
		<table class="widefat" width="100%">
			<thead>
				<tr valign="top">
					<th><?php _e( 'Feed Info and Preview', 'cybersyn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
					<?php
					$this->resetPost();
					$this->count = 0;
					$result      = $this->parseFeed( $feed_url );
					if ( ! $result ) {
						echo '<div id="message"><p><strong>' . __( 'No feed found at', 'cybersyn' ) . '</strong> <a href="http://validator.w3.org/feed/check.cgi?url=' . urlencode( $feed_url ) . '" target="_blank">' . htmlspecialchars( $feed_url ) . '</a><br />' . "\n";
						echo 'XML parse error: ' . $this->xml_parse_error . ' (' . xml_error_string( $this->xml_parse_error ) . ')</p></div>';
					}
					?>
					</td>
				</tr>
			</tbody>
		</table>
			<?php
			return ( $result != 0 );
	}

	function startElement( $parser, $name, $attribs ) {
		$this->tag = $name;

		if ( 'MEDIA:CONTENT' === $this->insideitem && $name ) {
			$this->post ['media_content'][] = $attribs['URL'];
		}

		if ( $this->insideitem && $name === 'MEDIA:THUMBNAIL' ) {
			$this->post ['media_thumbnail'][] = $attribs['URL'];
		}

		if ( $name === 'ENCLOSURE' ) {
			$this->post ['enclosure_url'] = $attribs['URL'];
		}

		if ( $name === 'LINK' && isset( $attribs['HREF'] ) && isset( $attribs ['REL'] ) ) {
			if ( stripos( $attribs ['REL'], 'enclosure' ) !== false ) {
				$this->post['enclosure_url'] = $attribs['HREF'];
			} elseif ( stripos( $attribs ['REL'], 'alternate' ) !== false && $this->post['link'] === '' ) {
				$this->post['link'] = $attribs['HREF'];
			}
		}

		if ( $name === 'ITEM' || $name === 'ENTRY' ) {
			$this->insideitem = true;
		} elseif ( ! $this->insideitem && $name === 'TITLE' && 0 !== strlen( trim( $this->feed_title ) )  ) {
			$this->tag = '';
		}
	}

	function endElement( $parser, $name ) {
		if ( ( $name === 'ITEM' || $name === 'ENTRY' ) ) {
			$this->posts_found ++;
			if ( ( $this->count < $this->max ) ) {
				if ( $this->preview ) {
					$this->displayPost();
					$this->count ++;
				} else {
					$this->insertPost();
				}
				$this->resetPost();
				$this->insideitem = false;
			}
		} elseif ( $name === 'CATEGORY' ) {
			$category = trim( csyn_fix_white_spaces( $this->current_category ) );
			if ( strlen( $category ) > 0 ) {
				$this->post ['categories'][] = $category;
			}
			$this->current_category = '';
		} elseif ( $this->count >= $this->max ) {
			$this->insideitem = false;
		}
	}

	function charData( $parser, $data ) {
		if ( $this->insideitem ) {
			switch ( $this->tag ) {
				case 'TITLE':
					$this->post ['post_title'] .= $data;
					break;
				case 'DESCRIPTION':
					$this->post ['post_excerpt'] .= $data;
					break;
				case 'SUMMARY':
					$this->post ['post_excerpt'] .= $data;
					break;
				case 'LINK':
					if ( trim( $data ) != '' ) {
						$this->post ['link'] .= trim( $data );
					}
					break;
				case 'CONTENT:ENCODED':
					$this->post ['post_content'] .= $data;
					break;
				case 'CONTENT':
					$this->post ['post_content'] .= $data;
					break;
				case 'CATEGORY':
					$this->current_category .= trim( $data );
					break;
				case 'GUID':
					$this->post ['guid'] .= trim( $data );
					break;
				case 'ID':
					$this->post ['guid'] .= trim( $data );
					break;
				case 'ATOM:ID':
					$this->post ['guid'] .= trim( $data );
					break;
				case 'DC:IDENTIFIER':
					$this->post ['guid'] .= trim( $data );
					break;
				case 'DC:DATE':
					$this->post ['post_date'] = $this->parse_w3cdtf( $data );
					if ( $this->post ['post_date'] ) {
						$this->tag = '';
					}
					break;
				case 'DCTERMS:ISSUED':
					$this->post ['post_date'] = $this->parse_w3cdtf( $data );
					if ( $this->post ['post_date'] ) {
						$this->tag = '';
					}
					break;
				case 'PUBLISHED':
					$this->post ['post_date'] = $this->parse_w3cdtf( $data );
					if ( $this->post ['post_date'] ) {
						$this->tag = '';
					}
					break;
				case 'ISSUED':
					$this->post ['post_date'] = $this->parse_w3cdtf( $data );
					if ( $this->post ['post_date'] ) {
						$this->tag = '';
					}
					break;
				case 'PUBDATE':
					$this->post ['post_date'] = strtotime( $data );
					if ( $this->post ['post_date'] ) {
						$this->tag = '';
					}
					break;
			}
		} elseif ( $this->tag === 'TITLE' ) {
			$this->feed_title .= csyn_fix_white_spaces( $data );
		} elseif ( $this->tag === 'GENERATOR' ) {
			$this->generator .= trim( $data );
		}
	}

	/**
	 *
	 * @global type $wpdb
	 * @param type $feed_ids
	 * @param type $delete_posts
	 * @param type $defele_feeds
	 * @version 2, 1/7/2014, lenasterg add log
	 *
	 */
	function deleteFeeds( $feed_ids, $delete_posts = false, $defele_feeds = false ) {
		global $wpdb;
		$feeds_cnt = count( $feed_ids );
		if ( $feeds_cnt > 0 ) {

			set_time_limit( 60 * 60 );
			ob_end_flush();
			ob_implicit_flush();
			echo "<div id=\"message\" class=\"updated fade\"><p>\n";
			_e( 'Deleting. Please wait...', 'cybersyn' );
			flush();

			if ( $delete_posts ) {
				$to_delete = '(';
				$cnt       = count( $feed_ids );
				for ( $i = 0; $i < $cnt; $i ++ ) {
					$to_delete .= "'" . $this->feeds [ $feed_ids[ $i ] ]['url'] . "', ";
				}
				$to_delete .= ')';
				$to_delete  = str_replace( ', )', ')', $to_delete );
				$post_ids   = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'cyberseo_rss_source' AND meta_value IN {$to_delete}" );
				if ( count( $post_ids ) > 0 ) {
					foreach ( $post_ids as $post_id ) {
						wp_delete_post( $post_id, true );
						echo( str_repeat( ' ', 512 ) );
						flush();
					}
				}
			}
			if ( $defele_feeds ) {
				$feeds     = array();
				$feeds_cnt = count( $this->feeds );
				for ( $i = 0; $i < $feeds_cnt; $i ++ ) {
					if ( ! in_array( $i, $feed_ids ) ) {
						$feeds[] = $this->feeds [ $i ];
					} else {
						ls_cybersyn_log( $this->feeds [ $i ], true ); //log the deletion
					}
				}
				$this->feeds = $feeds;
				sort( $this->feeds );
			}
			csyn_set_option( CSYN_SYNDICATED_FEEDS, $this->feeds, '', 'yes' );

			echo ' ' . __( 'Done', 'cybersyn' ) . "!</p></div>\n";
		}
	}

	function insertPost() {
		global $wpdb , $wp_version , $csyn_last_effective_url;

		if ( $this->show_report ) {
			echo( str_repeat( ' ', 512 ) );
			flush();
		}

		if ( mb_strlen( trim( $this->post ['post_content'] ) ) === 0 ) {
			$this->post ['post_content'] = $this->post ['post_excerpt'];
		}

		$this->post['post_title'] = trim( $this->post['post_title'] );

		if ( mb_strlen( $this->post ['post_title'] ) ) {
			$cat_ids = $this->getCategoryIds( $this->post ['categories'] );
			if ( empty( $cat_ids ) && $this->current_feed ['options']['undefined_category'] === 'drop' ) {
				return;
			}
			$post = array();

			if ( isset( $this->post['tags_input'] ) && is_array( $this->post['tags_input'] ) ) {
				$post['tags_input'] = $this->post['tags_input'];
			} else {
				$post['tags_input'] = array();
			}

			if ( mb_strlen( $this->post['guid'] ) < 8 ) {
				if ( strlen( $this->post['link'] ) ) {
					$components = parse_url( $this->post ['link'] );
					$guid       = 'tag:' . $components['host'];
				} else {
					$guid = 'tag:' . md5( $this->post['post_content'] . $this->post['post_excerpt'] );
				}
				if ( $this->post['post_date'] != '' ) {
					$guid .= '://post.' . $this->post['post_date'];
				} else {
					$guid .= '://' . md5( $this->post['link'] . '/' . $this->post['post_title'] );
				}
			} else {
				$guid = $this->post['guid'];
			}

			$post['post_title'] = csyn_fix_white_spaces( $this->post['post_title'] );
			$post['post_name']  = sanitize_title( $post['post_title'] );
			$post['guid']       = addslashes( $guid );

			switch ( $this->current_feed ['options']['duplicate_check_method'] ) {
				case 'guid':
					$result_dup = $wpdb->query( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE guid = "' . $post['guid'] . '"' );
					break;
				case 'title':
					$result_dup = $wpdb->query( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = "' . $post['post_name'] . '"' );
					break;
				default:
					$result_dup = $wpdb->query( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE guid = "' . $post['guid'] . '" OR post_name = "' . $post['post_name'] . '"' );
			}

			if ( ! $result_dup ) {
				if ( $this->current_feed ['options']['base_date'] === 'syndication' ) {
					$post_date = time();
				} else {
					$post_date = ( (int) $this->post ['post_date'] );
				}
				$post_date                += 60 * ( $this->current_feed ['options']['date_min'] + mt_rand( 0, $this->current_feed ['options']['date_max'] - $this->current_feed ['options']['date_min'] ) );
				$post['post_date']         = addslashes( gmdate( 'Y-m-d H:i:s', $post_date + 3600 * (int) get_option( 'gmt_offset' ) ) );
				$post['post_date_gmt']     = addslashes( gmdate( 'Y-m-d H:i:s', $post_date ) );
				$post['post_modified']     = addslashes( gmdate( 'Y-m-d H:i:s', $post_date + 3600 * (int) get_option( 'gmt_offset' ) ) );
				$post['post_modified_gmt'] = addslashes( gmdate( 'Y-m-d H:i:s', $post_date ) );
				$post['post_status']       = $this->current_feed ['options']['post_status'];
				$post['comment_status']    = $this->current_feed ['options']['comment_status'];
				$post['ping_status']       = $this->current_feed ['options']['ping_status'];
				$post['post_type']         = 'post';
				$post['post_author']       = $this->current_feed ['options']['post_author'];

				$attachment = '';
				if ( $this->current_feed ['options']['insert_media_attachments'] != 'no' ) {
					if ( sizeof( $this->post ['media_thumbnail'] ) ) {
						$attachment .= '<div class="media_block">' . "\n";
						for ( $i = 0; $i < sizeof( $this->post ['media_thumbnail'] ); $i ++ ) {
							if ( $this->current_feed ['options']['store_images'] === 'on' ) {
								$this->post ['media_thumbnail'][ $i ] = csyn_save_image( $this->post ['media_thumbnail'][ $i ], $post['post_title'] );
							}
							if ( isset( $this->post ['media_content'][ $i ] ) ) {
								$attachment .= '<a href="' . $this->post ['media_content'][ $i ] . '"><img src="' . $this->post ['media_thumbnail'][ $i ] . '" class="media_thumbnail" class="alignleft" alt="' . $post['post_title'] . '"></a>' . "\n";
							} else {
								$attachment .= '<img src="' . $this->post ['media_thumbnail'][ $i ] . '" class="media_thumbnail" class="alignleft" alt="' . $post['post_title'] . '">' . "\n";
							}
						}
						$attachment .= "</div>\n";
					}

					if ( $this->post ['enclosure_url'] != '' && $this->post ['link'] != '' ) {
						$attachment .= '<div class="media_block">' . "\n";
						$attachment .= '<a href="' . $this->post ['link'] . '"><img src="' . $this->post ['enclosure_url'] . '" class="media_thumbnail" class="alignleft" media_thumbnail></a>' . "\n";
						$attachment .= "</div>\n";
					}
				}

				$attachment_status = $this->current_feed['options']['insert_media_attachments'];

				$post['post_content'] = $this->post['post_content'];
				$post['post_excerpt'] = $this->post['post_excerpt'];

				if ( $this->current_feed ['options']['embed_videos'] === 'on' ) {
					$post['post_excerpt'] = $post['post_content'] = $this->extractEmbeddableCode( $post['post_content'] );
				}

				if ( $this->current_feed ['options']['store_images'] === 'on' ) {
					preg_match_all( '/<img(.+?)src=[\'\"](.+?)[\'\"](.*?)>/is', $post['post_content'] . $post['post_excerpt'], $matches );
					$image_urls = array_unique( $matches[2] );
					$home       = get_option( 'home' );
					for ( $i = 0; $i < count( $image_urls ); $i ++ ) {
						if ( strpos( $image_urls[ $i ], $home ) === false ) {
							$new_image_url        = csyn_save_image( $image_urls[ $i ], $post['post_title'] );
							$post['post_content'] = str_replace( $image_urls[ $i ], $new_image_url, $post['post_content'] );
							$post['post_excerpt'] = str_replace( $image_urls[ $i ], $new_image_url, $post['post_excerpt'] );
							if ( $this->show_report ) {
								echo( str_repeat( ' ', 256 ) );
								flush();
							}
						}
					}
				}

				$inc_footerss = $this->current_feed ['options']['include_post_footers'];

				$title   = $post['post_title'];
				$content = csyn_fix_white_spaces( $post['post_content'] );
				$excerpt = csyn_fix_white_spaces( $post['post_excerpt'] );
				$divider = ' 888011000110888 ';
				$packet  = csyn_spin_content( $title . $divider . $content . $divider . $excerpt );
				if ( substr_count( $packet, $divider ) === 2 ) {
					list($title , $content , $excerpt) = explode( $divider, $packet );
				}
				$post['post_title']   = addslashes( $title );
				$post['post_content'] = addslashes( csyn_touch_post_content( $content, $attachment, $attachment_status ) );
				$post['post_excerpt'] = addslashes( csyn_touch_post_content( $excerpt, $attachment, $attachment_status, $inc_footerss ) );

				$post_categories = array();
				if ( is_array( $this->current_feed ['options']['post_category'] ) ) {
					$post_categories = $this->current_feed ['options']['post_category'];
				}

				if ( ! empty( $cat_ids ) ) {
					$post_categories = array_merge( $post_categories, $cat_ids );
				} elseif ( $this->current_feed ['options']['undefined_category'] === 'use_default' && empty( $post_categories ) ) {
					$post_categories[] = get_option( 'default_category' );
				}

				$post_categories = array_unique( $post_categories );

				$post['post_category'] = $post_categories;

				if ( $this->current_feed ['options']['create_tags'] === 'on' ) {
					$post['tags_input'] = array_merge( $post['tags_input'], $this->post ['categories'] );
				}

				if ( $this->current_feed ['options']['post_tags'] != '' ) {
					$tags               = explode( ',', $this->current_feed ['options']['post_tags'] );
					$post['tags_input'] = array_merge( $post['tags_input'], $tags );
				}

				$post['tags_input'] = array_unique( $post['tags_input'] );

				remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
				remove_filter( 'excerpt_save_pre', 'wp_filter_post_kses' );
				$post_id = wp_insert_post( $post, true );

				if ( is_wp_error( $post_id ) && $this->show_report ) {
					$this->failure = true;
					echo '<br /><b>' . __( 'Error', 'cybersyn' ) . ':</b> ' . $post_id->get_error_message( $post_id->get_error_code() ) . "<br />\n";
				} else {

					$this->count ++;
					$this->failure = false;

					add_post_meta( $post_id, 'cyberseo_rss_source', $this->current_feed ['url'] );
					add_post_meta( $post_id, 'cyberseo_post_link', $this->post ['link'] );

					if ( version_compare( $wp_version, '3.0', '<' ) ) {
						if ( function_exists( 'wp_set_post_categories' ) ) {
							wp_set_post_categories( $post_id, $post_categories );
						} elseif ( function_exists( 'wp_set_post_cats' ) ) {
							wp_set_post_cats( '1', $post_id, $post_categories );
						}
					}
				}
			}
		}
	}

	function getCategoryIds( $category_names ) {
		global $wpdb;

		$cat_ids = array();
		foreach ( $category_names as $cat_name ) {
			if ( function_exists( 'term_exists' ) ) {
				$cat_id = term_exists( $cat_name, 'category' );
				if ( $cat_id ) {
					$cat_ids[] = $cat_id['term_id'];
				} elseif ( $this->current_feed ['options']['undefined_category'] === 'create_new' ) {
					$term      = wp_insert_term( $cat_name, 'category' );
					$cat_ids[] = $term['term_id'];
				}
			} else {
				$cat_name_escaped = addslashes( $cat_name );
				$results          = $wpdb->get_results( "SELECT cat_ID FROM $wpdb->categories WHERE (LOWER(cat_name) = LOWER('$cat_name_escaped'))" );

				if ( $results ) {
					foreach ( $results as $term ) {
						$cat_ids[] = (int) $term->cat_ID;
					}
				} elseif ( $this->current_feed ['options']['undefined_category'] === 'create_new' ) {
					if ( function_exists( 'wp_insert_category' ) ) {
						$cat_id = wp_insert_category( array( 'cat_name' => $cat_name ) );
					} else {
						$cat_name_sanitized = sanitize_title( $cat_name );
						$wpdb->query( "INSERT INTO $wpdb->categories SET cat_name='$cat_name_escaped', category_nicename='$cat_name_sanitized'" );
						$cat_id = $wpdb->insert_id;
					}
					$cat_ids[] = $cat_id;
				}
			}
		}
		if ( ( count( $cat_ids ) != 0 ) ) {
			$cat_ids = array_unique( $cat_ids );
		}
		return $cat_ids;
	}

	function categoryChecklist( $post_id = 0, $descendents_and_self = 0, $selected_cats = false ) {
		wp_category_checklist( $post_id, $descendents_and_self, $selected_cats );
	}

	function categoryListBox( $checked, $title ) {
		echo '<div id="categorydiv" class="postbox">' . "\n";
		echo '<ul id="category-tabs">' . "\n";
		echo '<li class="ui-tabs-selected">' . "\n";
		echo '<p>' . $title . '</p>' . "\n";
		echo '</li>' . "\n";
		echo '</ul>' . "\n";

		echo '<div id="categories-all" class="cybersyn-ui-tabs-panel">' . "\n";
		echo '<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">' . "\n";
		$this->categoryChecklist( null, false, $checked );
		echo '</ul>' . "\n";
		echo '</div><br />' . "\n";
		echo '</div>' . "\n";
	}

	/**
	 *
	 * @global type $wp_version
	 * @global type $wpdb
	 * @global type $csyn_bs_options
	 * @param type $islocal
	 * @param type $settings
	 * @version 2 stergatu
	 */
	function showSettings( $islocal, $settings ) {
		global $wp_version , $wpdb;
		//$csyn_bs_options;
		if ( version_compare( $wp_version, '2.5', '<' ) ) {
			echo "<hr>\n";
		}
		echo '<form action="' . preg_replace( '/\&edit-feed-id\=[0-9]+/', '', csyn_REQUEST_URI() ) . '" method="post">' . "\n";
		?>
		<table class="widefat" style="margin-top: .8em" width="100%">
			<thead>
				<tr valign="top">
				<?php
				if ( $islocal ) {
					echo '<th colspan="2">' . __( 'Syndication settings for', 'cybersyn' ) . '"' . trim( $this->feed_title ) . '"</th>';
				} else {
					echo '<th colspan="2">' . __( 'Default syndication settings', 'cybersyn' ) . '</th>';
				}
				?>
				</tr>
			</thead>
			<tbody>
				<?php if ( $islocal ) { ?>
					<tr>
						<td><?php _e( 'Feed title', 'cybersyn' ); ?>:</td>
						<td>
							<input type="text" name="feed_title" size="132" value="<?php echo ( $this->edit_existing ) ? $this->feeds [ (int) $_GET['edit-feed-id'] ]['title'] : $this->feed_title; ?>">
						</td>
					</tr>
					<tr>
						<td><?php _e( 'Feed URL', 'cybersyn' ); ?></td>
						<td><input type="text" name="new_feed_url" size="132" value="<?php echo htmlspecialchars( $this->current_feed_url ); ?>"
																								<?php
																								if ( ! $this->edit_existing ) {
																									echo ' disabled';
																								}
																								?>
							>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<td width="280">
					<?php
					if ( $islocal ) {
						_e( 'Syndicate this feed to the following categories', 'cybersyn' );
					} else {
						_e( 'Syndicate new feeds to the following categories', 'cybersyn' );
					}
					?>
					</td>
					<td>
						<div id="categorydiv">
							<div id="categories-all" class="cybersyn-ui-tabs-panel">
								<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
								<?php
								$this->categoryChecklist( null, false, $settings['post_category'] );
								?>
								</ul>
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<td><?php _e( 'Attribute all posts to the following user', 'cybersyn' ); ?></td>
					<td>
						<?php
						wp_dropdown_users(
							array(
								'capability'       => array( 'edit_posts' ),
								'name'             => 'post_author',
								'selected'         => $settings['post_author'],
								'include_selected' => true,
							)
						);
						?>
					</td>
				</tr>

				<tr>
					<td><?php _e( 'Undefined categories', 'cybersyn' ); ?></td>
					<td><select name="undefined_category" size="1">
						<?php
						if ( $islocal ) {
							echo '<option ' . ( ( $settings['undefined_category'] === 'use_global' ) ? 'selected ' : '' ) . 'value="use_global">';
							_e( 'Use RSS/Atom default settings', 'cybersyn' );
							echo '</option>' . "\n";
						}
						echo '<option ' . ( ( $settings['undefined_category'] === 'use_default' ) ? 'selected ' : '' ) . 'value="use_default">';
						_e( 'Post to default WordPress category', 'cybersyn' );
						echo '</option>' . "\n";
						echo '<option ' . ( ( $settings['undefined_category'] === 'create_new' ) ? 'selected ' : '' ) . 'value="create_new">';
						_e( 'Create new categories defined in syndicating post', 'cybersyn' );
						echo '</option>' . "\n";
						echo '<option ' . ( ( $settings['undefined_category'] === 'drop' ) ? 'selected ' : '' ) . 'value="drop">';
						_e( 'Do not syndicate post that doesn\'t match at least one category defined above', 'cybersyn' );
						echo '</option>' . "\n";
						?>
						</select></td>
				</tr>
				<tr>
					<td><?php _e( 'Create tags from category names', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="checkbox" name="create_tags" ' . ( ( $settings['create_tags'] === 'on' ) ? 'checked ' : '' ) . '>';
					?>
					</td>
				</tr>
				<tr>
					<td><?php _e( 'Post tags (separate with commas)', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="text" name="post_tags" value="' . stripslashes( $settings['post_tags'] ) . '" size="60">';
					?>
					</td>
				</tr>
				<tr>
					<td><?php _e( 'Check for duplicate posts by', 'cybersyn' ); ?></td>
					<td><select name="duplicate_check_method" size="1">
						<?php
						echo '<option ' . ( ( $settings['duplicate_check_method'] === 'guid_and_title' ) ? 'selected ' : '' ) . 'value="guid_and_title">' . __( 'GUID and title', 'cybersyn' ) . '</option>' . "\n";
						echo '<option ' . ( ( $settings['duplicate_check_method'] === 'guid' ) ? 'selected ' : '' ) . 'value="guid">' . __( 'GUID only', 'cybersyn' ) . '</option>' . "\n";
						echo '<option ' . ( ( $settings['duplicate_check_method'] === 'title' ) ? 'selected ' : '' ) . 'value="title">' . __( 'Title only', 'cybersyn' ) . '</option>' . "\n";
						?>
						</select></td>
				</tr>
			<tr>
					<td><?php _e( 'Maximum number of posts to be syndicated from each feed at once', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="text" name="max_items" value="' . $settings['max_items'] . '" size="3"> - ' . __( 'use low values to decrease the syndication time and improve SEO of your blog.', 'cybersyn' );
					?>
					</td>
				</tr>
				<tr>
					<td><?php _e( 'Comments', 'cybersyn' ); ?></td>
					<td><select name="post_comments" size="1">
						<?php
						echo '<option ' . ( ( 'open' === $settings['comment_status'] ) ? 'selected ' : '' ) . 'value="open">';
						_e( 'Allow comments on syndicated posts', 'cybersyn' );
						echo '</option>' . "\n";
						echo '<option ' . ( ( $settings['comment_status'] === 'closed' ) ? 'selected ' : '' ) . 'value="closed">';
						_e( 'Disallow comments on syndicated posts', 'cybersyn' );
						echo '</option>' . "\n";
						?>
						</select></td>
				</tr>

				<tr>
					<td><?php _e( 'Media attachments', 'cybersyn' ); ?></td>
					<td><select name="insert_media_attachments" size="1">
							<?php
							echo '<option ' . ( ( $settings['insert_media_attachments'] === 'no' ) ? 'selected ' : '' ) . 'value="no">' . __( 'Do not insert attachments', 'cybersyn' ) . '</option>' . "\n";
							echo '<option ' . ( ( $settings['insert_media_attachments'] === 'top' ) ? 'selected ' : '' ) . 'value="top">' . __( 'Insert attachments at the top of the post', 'cybersyn' ) . '</option>' . "\n";
							echo '<option ' . ( ( $settings['insert_media_attachments'] === 'bottom' ) ? 'selected ' : '' ) . 'value="bottom">' . __( 'Insert attachments at the bottom of the post', 'cybersyn' ) . '</option>' . "\n";
							?>
						</select> - <?php _e( 'if enabled CyberSyn syndicator will insert media attachments (if available) into the aggregating post. The following types of attachments are supported: <strong>&lt;media:content&gt;</strong>, <strong>&lt;media:thumbnail&gt;</strong> and <strong>&lt;enclosure&gt;</strong> (type "image" only) All the aggregated images will contain <strong>class="media_thumbnail"</strong> in the <strong>&lt;img&gt;</strong> tag', 'cybersyn' ); ?>
					</td>
				</tr>
				<tr>
					<td><?php _e( 'Convert character encoding', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="checkbox" name="convert_encoding" ' . ( ( $settings['convert_encoding'] === 'on' ) ? 'checked ' : '' ) . '> -'
					. __( 'enables character encoding conversion. This option might be useful when parsing XML/RSS feeds in national charsets different than UTF-8.', 'cybersyn' );
					?>
					</td>
				</tr>

				<tr>
					<td><?php _e( 'Store images locally', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="checkbox" name="store_images" ' . ( ( $settings['store_images'] === 'on' ) ? 'checked ' : '' ) . '> - '
					. __( 'if enabled, all images from the syndicating feeds will be copied into the default uploads folder of this blog. Make sure that your /wp-content/uploads folder is writable.', 'cybersyn' );
					?>
					</td>
				</tr>

																																																																																																				<!--                                <tr>
																																																																													<td><?php _e( 'Post date adjustment range', 'cybersyn' ); ?></td>
																																																																											<td>
																																																																										<?php
																																																																										echo '[<input type="hidden" name="date_min" value="' . $settings['date_min'] . '" size="6"> .. <input type="hidden" name="date_max" value="' . $settings['date_max'] . '" size="6">]';
																																																																										?>
					<?php
					_e(
						'- here you can set the syndication date adjustment range in minutes. This range will be used to randomly adjust the publication date for every aggregated post. For example, if you set
                                the adjustment range as [0..60], the post dates will be increased by a random value between 0 and 60 minutes.',
						'cybersyn'
					);
					?>
																																																																																																									</td>
																																																																														</tr>-->
				<tr>
					<td><?php _e( 'Post footer', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="text" name="post_footer" value="' . htmlspecialchars( stripslashes( $settings['post_footer'] ), ENT_QUOTES ) . '" size="60">';
					_e( ' - the HTML code wich will be inserted into the bottom of each syndicated post.', 'cybersyn' );
					?>
					</td>
				</tr>

				<tr>
					<td><?php _e( 'Insert post footer into excerpts', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="checkbox" name="include_post_footers" ' . ( ( $settings['include_post_footers'] === 'on' ) ? 'checked ' : '' ) . '> - ' . __( 'enable this option if you want to insert the post footer into the post excerpt.', 'cybersyn' );
					?>
					</td>
				</tr>

				<tr>
					<td><?php _e( 'Embed videos', 'cybersyn' ); ?></td>
					<td>
					<?php
					echo '<input type="checkbox" name="embed_videos" ' . ( ( $settings['embed_videos'] === 'on' ) ? 'checked ' : '' ) . '> - ' .
					__( 'the embeddable videos will be automatically extracted and inserted into the posts. Feed sources supported: YouTube only.', 'cybersyn' );
					?>
					</td>
				</tr>

			</tbody>
		</table>
			<?php
			echo '<div class="submit">' . "\n";
			if ( $islocal ) {
				if ( $this->edit_existing ) {
					echo '<input class="button-primary" name="update_feed_settings" value="' . __( 'Update Feed Settings', 'cybersyn' ) . '" type="submit">' . "\n";
					echo '<input class="button" name="cancel" value="' . __( 'Cancel', 'cybersyn' ) . '" type="submit">' . "\n";
					echo '<input type="hidden" name="feed_id" value="' . (int) $_GET['edit-feed-id'] . '">' . "\n";
				} else {
					echo '<input class="button-primary" name="syndicate_feed" value="' . __( 'Syndicate This Feed', 'cybersyn' ) . '" type="submit">' . "\n";
					echo '<input class="button" name="cancel" value="' . __( 'Cancel', 'cybersyn' ) . '" type="submit">' . "\n";
					echo '<input type="hidden" name="feed_url" value="' . $this->current_feed_url . '">' . "\n";
				}
			} else {
				echo '<input class="button-primary" name="update_default_settings" value="' . __( 'Update Default Settings', 'cybersyn' ) . '" type="submit">' . "\n";
			}
			echo "</div>\n";

			echo "</form>\n";
	}

	function getUpdateTime( $feed ) {
		$time     = time();
		$interval = 60 * (int) $feed['options']['interval'];
		$updated  = (int) $feed['updated'];
		if ( $feed['options']['interval'] === 0 ) {
			return 'never';
		} elseif ( ( $time - $updated ) >= $interval ) {
			return 'asap';
		} else {
			return 'in ' . (int) ( ( $interval - ( $time - $updated ) ) / 60 ) . ' minutes';
		}
	}

	/**
	 * @version 2.0 1/4/2014 stergatu change
	 * @global type $wp_version
	 * @param type $showsettings
	 */
	function showMainPage( $showsettings = true ) {
		echo '<form action="' . csyn_REQUEST_URI() . '" method="post">' . "\n";
		echo '<table class="form-table" width="100%">';
		echo "<tr>
		<td align=\"left\"><h3>\n";
		echo '<label for="feed_url">Δώστε το RSS feed url της νέας εξωτερικής πηγής που θέλετε να προσθέσετε:</label><br/> <input type="text" name="feed_url" value="" size="100">' . "\n";
		echo '&nbsp;<input class="button-primary" name="new_feed" value="' . __( 'Syndicate', 'cybersyn' ) . ' &raquo;" type="submit">' . "</h3>\n";
		echo "</td></tr>\n";
		echo "</table>\n";
		echo '</form>';
		echo '<form id="syndycated_feeds" action="' . csyn_REQUEST_URI() . '" method="post">' . "\n";
		if ( count( $this->feeds ) > 0 ) {
			echo '<h3>Πηγές που έχετε ρυθμίσει:</h3>
			<table class="widefat" style="margin-top: .5em" width="100%">' . "\n";
			echo '<thead>' . "\n";
			echo '<tr>' . "\n";
			echo '<th width="3%" align="center"><input type="checkbox" onclick="checkAll(document.getElementById(\'syndycated_feeds\'));"></th>' . "\n";
			echo '<th width="25%">' . __( 'Feed title', 'cybersyn' ) . '</th>' . "\n";
			echo '<th width="50%">URL</th>' . "\n";
			//            echo '<th width="10%">Next update</th>' . "\n" ;
			echo '<th width="12%">' . __( 'Last update', 'cybersyn' ) . '</th>' . "\n";
			echo "</tr>\n";
			echo '</thead>' . "\n";
			for ( $i = 0; $i < count( $this->feeds ); $i ++ ) {
				if ( $i % 2 ) {
					echo "<tr>\n";
				} else {
					echo '<tr class="alternate">' . "\n";
				}
				echo '<td align="center"><input name="feed_ids[]" value="' . $i . '" type="checkbox"></td>' . "\n";
				echo '<td>' . $this->feeds [ $i ]['title'] . ' [<a href="' . csyn_REQUEST_URI() . '&edit-feed-id=' . $i . '">' . __( 'edit', 'cybersyn' ) . '</a>]</td>' . "\n";
				echo '<td>' . '<a href="' . $this->feeds [ $i ]['url'] . '" target="_blank">' . csyn_short_str( htmlspecialchars( $this->feeds [ $i ]['url'] ), 100 ) . '</a></td>' . "\n";
				//                echo "<td>" . $this->getUpdateTime( $this->feeds [ $i ] ) . "</td>\n" ;
				$last_update = $this->feeds [ $i ]['updated'];
				if ( $last_update ) {
					echo '<td>' . intval( ( time() - $last_update ) / 60 ) . ' ' . __( 'minutes ago', 'cybersyn' ) . "</td>\n";
				} else {
					echo "<td> - </td>\n";
				}
				echo "</tr>\n";
			}
			echo "</table>\n";
			?>

			<table width="100%">
				<tr>
					<td>
						<div align="left">
							<input class="button-primary" name="check_for_updates" value="<?php _e( 'Pull selected feeds now', 'cybersyn' ); ?>!" type="submit">
						</div>
					</td>
					<td>
						<div align="right">
							<input class="button secondary" name="delete_feeds_and_posts" value="<?php _e( 'Delete selected feeds and syndicated posts', 'cybersyn' ); ?>" type="submit">
							<input class="button secondary" name="delete_feeds" value="<?php _e( 'Delete selected feeds', 'cybersyn' ); ?>" type="submit">
							<input class="button secondary" name="delete_posts" value="<?php _e( 'Delete posts syndycated from selected feeds', 'cybersyn' ); ?>" type="submit">
						</div>
					</td>
				</tr>
			</table>

			<?php } ?>

		<table width="100%">
			<td></td>
			<td><br />
				<div align="right">
					<input class="button secondary" name="alter_default_settings" value="<?php _e( 'Alter default settings', 'cybersyn' ); ?>" type="submit">
				</div>
			</td>
		</tr>
		</table>
		</form>

			<?php
			if ( $showsettings ) {
				$this->showSettings( false, $this->global_options );
			}
	}

}

function csyn_set_option( $option_name, $newvalue, $deprecated, $autoload ) {
	if ( get_option( $option_name ) === false ) {
		add_option( $option_name, $newvalue, $deprecated, $autoload );
	} else {
		update_option( $option_name, $newvalue );
	}
}

	/**
	 *
	 * @global type $csyn_bs_options
	 * @param type $content
	 * @return type
	 * @ stergatu 1/4/2014 useless
	 */
function csyn_thebestspinner( $content ) {
	global $csyn_bs_options;

	if ( strlen( $csyn_bs_options['username'] ) && strlen( $csyn_bs_options['password'] ) ) {
		$url              = 'http://thebestspinner.com/api.php';
		$data             = array();
		$data['action']   = 'authenticate';
		$data['format']   = 'php';
		$data['username'] = $csyn_bs_options['username'];
		$data['password'] = $csyn_bs_options['password'];
		$result           = unserialize( csyn_curl_post( $url, $data, $info ) );
		if ( isset( $result['success'] ) && $result['success'] === 'true' ) {
			$data['session']        = $result['session'];
			$data['action']         = 'rewriteText';
			$data['protectedterms'] = $csyn_bs_options['protectedterms'];
			$data['text']           = $content;
			$result                 = unserialize( csyn_curl_post( $url, $data, $info ) );
			if ( $result['success'] === 'true' ) {
				return $result['output'];
			}
		}
	}
	return $content;
}

	/**
	 *
	 * @global Ls_CyberSyn_Syndicator $csyn_syndicator
	 * @param type $content
	 * @return type
	 * @version 2, stergatu
	 */
function csyn_spin_content( $content ) {
	global $csyn_syndicator;

	if ( count( $csyn_syndicator->current_feed ) ) {
		$synonymizer_mode = $csyn_syndicator->current_feed ['options']['synonymizer_mode'];
	} else {
		$synonymizer_mode = $csyn_syndicator->global_options ['synonymizer_mode'];
	}
	return $content;
}

function csyn_parse_special_words( $content ) {
	global $csyn_syndicator;
	return str_replace( '####post_link####', $csyn_syndicator->post ['link'], $content );
}

	/**
	 *
	 * @global Ls_CyberSyn_Syndicator $csyn_syndicator
	 * @param type $content
	 * @param type $attachment
	 * @param type $attachment_status
	 * @param type $inc_footers
	 * @return type
	 * @version 2, stergatu, 1/4/2014, add post_link into footer
	 */
function csyn_touch_post_content( $content, $attachment = '', $attachment_status = 'no', $inc_footers = true ) {
	global $csyn_syndicator;

	if ( $attachment != '' ) {
		if ( $attachment_status === 'top' ) {
			$content = $attachment . $content;
		} elseif ( $attachment_status === 'bottom' ) {
			$content .= $attachment;
		}
	}

	$footer = 'Πηγή - περισσότερα: <a href="' . $csyn_syndicator->post ['link'] . '">' . $csyn_syndicator->post ['link'] . '</a>' . stripslashes( $csyn_syndicator->current_feed ['options']['post_footer'] );

	if ( strlen( $footer ) ) {
		$content .= csyn_parse_special_words( trim( $footer ) );
	}

	return $content;
}

	/**
	 *
	 */
function ls_cybersyn_main_menu() {
	if ( function_exists( 'add_posts_page' ) ) {
		add_posts_page(
			'Άντληση άρθρων από εξωτερικές πηγές',
			'Άντληση άρθρων από εξωτερικές πηγές',
			'add_users',
			DIRNAME( __FILE__ ) . '/cybersyn-syndicator.php'
		);
	}
}


function ls_cybersyn_help() {

	$screen = get_current_screen();

	if ( $screen->id === 'ls_cybersyn/cybersyn-syndicator' ) {
		// help tabs
		$screen->add_help_tab(
			array(
				'id'      => 'ls_cybersyn_overview',
				'title'   => __( 'General' ),
				'content' => ls_cybersyn_help_overview_content(),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'ls_cybersyn_add',
				'title'   => 'Ορισμός νέας εξωτερικής πηγής',
				'content' => ls_cybersyn_help_add_content(),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'ls_cybersyn_existed',
				'title'   => __( 'Άντληση άρθρων από καταχωρημένες πηγές' ),
				'content' => ls_cybersyn_help_existed_content(),
			)
		);
	}
}

	add_filter( 'current_screen', 'ls_cybersyn_help' );

function csyn_update_feeds() {
	global $csyn_syndicator;
	$feed_cnt = count( $csyn_syndicator->feeds );
	if ( $feed_cnt > 0 ) {
		$feed_ids                     = range( 0, $feed_cnt - 1 );
		$csyn_syndicator->show_report = false;
		$csyn_syndicator->syndicateFeeds( $feed_ids, true );
	}
}

function csyn_generic_ping( $post_id ) {
	global $wpdb , $csyn_syndicator;
	$dates = $wpdb->get_row( "SELECT post_date, post_modified FROM $wpdb->posts WHERE id=$post_id" );
	if ( $csyn_syndicator->count <= 1 && $dates->post_modified === $dates->post_date && ( strtotime( $dates->post_modified < time() ) || strtotime( $dates->post_date ) < time() ) ) {
		if ( function_exists( '_publish_post_hook' ) ) {
			_publish_post_hook( $post_id );
		} else {
			generic_ping();
		}
	}
}

function csyn_deactivation() {
	wp_clear_scheduled_hook( 'update_by_wp_cron' );
}

	register_deactivation_hook( __FILE__, 'csyn_deactivation' );

function csyn_permalink( $permalink ) {
	global $post;
	list($link) = get_post_custom_values( 'cyberseo_post_link' );
	if ( filter_var( $link, FILTER_VALIDATE_URL ) ) {
		$permalink = $link;
	} elseif ( filter_var( $post->guid, FILTER_VALIDATE_URL ) ) {
		$permalink = $post->guid;
	}
	return $permalink;
}

	add_action( 'admin_menu', 'ls_cybersyn_main_menu' );
	add_action( 'before_delete_post', 'csyn_delete_post_images' );
	remove_action( 'publish_post', 'generic_ping' );
	remove_action( 'do_pings', 'do_all_pings', 10, 1 );
	remove_action( 'publish_post', '_publish_post_hook', 5, 1 );
	add_action( 'publish_post', 'csyn_generic_ping' );

if ( get_option( CSYN_LINK_TO_SOURCE ) === 'on' ) {
	add_filter( 'post_link', 'csyn_permalink', 1 );
}

if ( function_exists( 'wp_clear_scheduled_hook' ) && wp_next_scheduled( 'update_by_wp_cron' ) ) {
	wp_clear_scheduled_hook( 'update_by_wp_cron' );
}

	/**
	 *
	 *
	 * @version 2, use masorny from WordPress /wp-includes/js folder
	 * @author lenasterg
	 *
	 */
function ls_cybersyn_admin_enqueue() {
	wp_register_style(
		'ls_cybersyn_admin_style', // Style handle
		plugins_url( '/ls_cybersyn.css', __FILE__ ), // Style URL
		null, // Dependencies
		null, // Version
		'all' // Media
	);
	wp_enqueue_style( 'ls_cybersyn_admin_style' );

	wp_register_script(
		'ls_cybersyn_admin_classie_js', // Script handle
		plugins_url( '/js/classie.js', __FILE__ ), // Script URL
		array( 'jquery' ), // Dependencies. jQuery is enqueued by default in admin
		null, // Version
		false // In footer
	);
	wp_enqueue_script( 'masonry' );
}

	/**
	 * @version 1
	 * @author Stergatu Lena <stergatu@cti.gr>
	 */
function ls_cybersyn_init() {

	load_plugin_textdomain( 'cybersyn', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	csyn_preset_options();
}

	add_action( 'admin_init', 'ls_cybersyn_init' );

	add_action( 'admin_enqueue_scripts', 'ls_cybersyn_admin_enqueue' );

	add_filter( 'views_edit-post', 'ls_cybersyn_go' );

	/**
	 *
	 * @param type $views
	 * @return array
	 */
function ls_cybersyn_go( $views ) {

	$views1['ls_cybersyn'] = '<a href="?page=ls_cybersyn/cybersyn-syndicator.php#syndycated_feeds" class=button-primary>' . __( 'RSS/Atom Syndicator', 'cybersyn' ) . ' &raquo;</a>';
	$views                 = array_merge( $views, $views1 );
	return $views;
}

	/**
	 * Logs the feed usage into a global table
	 * @global type $wp
	 * @global type $wpdb
	 * @param type $feed string
	 * @param type $deleted bool
	 */
function ls_cybersyn_log( $feed, $deleted = null ) {
	global  $wpdb;

	$table   = $wpdb->base_prefix . 'ls_cybersyn';
	$blog_id = get_current_blog_id();

	$feed_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM $table
                    WHERE blog_id = %d AND feed_url= %s",
			array(
				$blog_id,
				$feed['url'],
			)
		)
	);
	if ( $deleted ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET deleted =  1 WHERE blog_id=%d AND feed_url=%s",
				array(
					$blog_id,
					$feed['url'],
				)
			)
		);
	} else {
		if ( ! $feed_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"
		INSERT INTO $table
		( blog_id,  feed_url, register_date, last_updated )
		VALUES ( %d, %s, now() , now()  )
	",
					array(
						$blog_id,
						$feed['url'],
					)
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE  $table SET last_updated =  now() , deleted=NULL   WHERE blog_id=%d AND feed_url=%s",
					array(
						$blog_id,
						$feed['url'],
					)
				)
			);
		}
	}
}

	/* * ****************FUNCTIONS FOR ADDING A COLUMN INTO POSTS TABLE***************************** */

	add_filter( 'manage_post_posts_columns', 'ls_cybersyn_columns', 10 );
	add_action( 'manage_post_posts_custom_column', 'ls_cybersyn_columns_content', 10, 2 );
	add_filter( 'manage_edit-post_sortable_columns', 'ls_cybersyn_columns_sortable' );
	add_filter( 'request', 'ls_cybersyn_columns_orderby' );

	/**
	 * Add a column into the wp_posts table
	 * @param array $defaults
	 * @return type
	 */
function ls_cybersyn_columns( $defaults ) {
	$defaults['cybersyn'] = __( 'Source', 'cybersyn' );
	return $defaults;
}

	/**
	 * Fill the column with the post feed source
	 * @param type $column_name
	 * @param type $post_ID
	 */
function ls_cybersyn_columns_content( $column_name, $post_ID ) {
	if ( 'cybersyn' === $column_name ) {
		$rss_source = get_post_meta( $post_ID, 'cyberseo_rss_source', true );
		echo $rss_source;
	}
}

function ls_cybersyn_columns_sortable( $sortable_columns ) {
	$sortable_columns['cybersyn'] = 'cybersyn';
	return $sortable_columns;
}

function ls_cybersyn_columns_orderby( $vars ) {
	if ( isset( $vars['orderby'] ) && 'cybersyn' === $vars['orderby'] ) {
		$vars = array_merge(
			$vars,
			array(
				'meta_key' => 'cyberseo_rss_source', //Custom field key
			//'orderby' => 'meta_value_num') //Custom field value (number)
			)
		);
	}
	return $vars;
}

	add_filter( 'parse_query', 'ls_admin_posts_filter' );
	add_action( 'restrict_manage_posts', 'ls_admin_posts_filter_restrict_manage_posts' );

	/**
	 *
	 * @global type $pagenow
	 * @param type $query
	 */
function ls_admin_posts_filter( $query ) {
	global $pagenow;
	if ( is_admin() && 'edit.php' === $pagenow && isset( $_GET['ls_source'] ) && '' !== $_GET['ls_source'] ) {
		$query->query_vars['meta_key']   = 'cyberseo_rss_source';
		$query->query_vars['meta_value'] = $_GET['ls_source'];
	}
}
	/**
	 *
	 * @global type $wpdb
	 */
	function ls_admin_posts_filter_restrict_manage_posts() {
		global $wpdb;
		$sql    = 'SELECT DISTINCT meta_value FROM ' . $wpdb->postmeta . ' where meta_key="cyberseo_rss_source" ORDER BY 1';
		$fields = $wpdb->get_results( $sql, ARRAY_N );
?>
	<select name="ls_source">
		<option value=""><?php _e( 'Source', 'cybersyn' ); ?></option>
		<?php
		$current = isset( $_GET['ls_source'] ) ? $_GET['ls_source'] : '';
		foreach ( $fields as $field ) {
			if ( '_' !== substr( $field[0], 0, 1 ) ) {
				printf(
					'<option value="%s"%s>%s</option>',
					$field[0],
					$field[0] === $current ? ' selected="selected"' : '',
					$field[0]
				);
			}
		}
		?>
	</select>
	<?php }
}
