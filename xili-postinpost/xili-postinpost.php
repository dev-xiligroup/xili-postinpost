<?php
/*
Plugin Name: xili-postinpost
Plugin URI: http://dev.xiligroup.com/xili-postinpost/
Description: xili-postinpost provides a triple tookit to insert post(s) everywhere in webpage. Template tag function, shortcode and widget are available. The post(s) are resulting of queries like those in WP loop but not interfere with main WP loop. Widget contains conditional syntax.
Author: dev.xiligroup.com - MS
Version: 1.7.03
Author URI: http://dev.xiligroup.com
Text Domain: xili-postinpost
License: GPLv2
*/

/**
 * 2019-05-20 - 1.7.01 - rewritting with WPCS and PHPCS - verified with WP 5.x
 *
 * 2018-07-19 - 1.6.4 - verified with WP 4.9.x

 * 2017-06-21 - 1.6.3 - fixes widget constructor

 * 2016-02-10 - 1.6.2 - compatible with glotpress - text domain same as plugin name

 * 2015-09-27 - 1.6.1 - WP 4.3 - replyto
 * 2015-05-08 - 1.6.0 - widget now display chosen size of image (featuredimagesize)
 * 2014-12-22 - 1.5.3 - improves query if permalinks and xili_language active - thanks to acizmeli
 * 2014-12-11 - 1.5.2 - WPLANG as function - WP 4.0+ - add do_action before/after widget_text filter to patch Karma Theme
 * 2014-10-30 - 1.5.1 - fixes images in admin (from assets now)
 * 2014-05-16 - 1.5.0 - add filter 'xili_postinpost_nopost' for nopost result
 * 2014-05-02 - 1.4.1 - add is_preview to realtime update in theme customize preview
 * 2014-03-05 - 1.4.0 - new param "more" for get_the_content - Text Domain added in header - add 2 pointers
 * 2014-02-18 - 1.3.0 - new versioning (for WP 3.8+) - clean source
 * 2013-05-24 - 1.2.2 - fixes notices - widget & class __construct - tests 3.6
 * 2013-01-28 - 1.2.1 - fixes support settings - tests 3.5.1
 * 2012-11-20 - 1.2.0 - option via filter for complex presetted queries (shortcode or template_tag)
 * 2012-10-19 - 1.1.2 - add param for no post msg, default option for editlink for author
 * 2012-04-06 - 1.1.1 - pre-tests with WP 3.4: fixes metaboxes columns
 * 2012-01-17 - 1.1.0 - add param lang in shortcode (as in widget for the_curlang)
 * 2011-11-27 - 1.0.1 - serialize for cache if query is array
 * 2011-10-21 - 1.0.0 - add user function to display loop
 * 2011-06-08 - 0.9.7 - source code cleaned, support email improved
 * 2011-01-17 - 0.9.6 - fixes pagination when paginated parent has paginated children (thanks to Piotr)
 * 2010-12-12 - 0.9.5 - more settings for html tags in widget
 * 2010-12-10 - 0.9.4 - fixes load textdomain for widgets, add featuredimage in shortcode
 * 2010-11-29 - 0.9.3 - fixes message small mistake when no post (warning)
 * 2010-11-28 - 0.9.2 - from to option added
 * 2010-11-21 - 0.9.1 - more docs
 * 2010-11-14 - 0.9.0 - settings interface with help and widget optional desactivation
 * 2010-11-12 - 0.8.0 - first public release w/o interface
 *
 */

define( 'XILI_PIP_VERSION', '1.7.03' );

define( 'XILI_PIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once XILI_PIP_PLUGIN_DIR . 'class-xili-postinpost.php';
require_once XILI_PIP_PLUGIN_DIR . 'class-xili-post-in-post-widget.php';


/**
 * instantiation of xili_postinpost class
 *
 * @since 0.8.0
 *
 */
$xili_postinpost = new Xili_Postinpost();

/**
 *
 * shortcode call of function post in post
 *
 * @ updated 0.9.4
 *
 *
 */
function xi_postinpost_func( $atts, $content = '' ) {

	$arr_result = shortcode_atts(
		array(
			'query' => '',
			'showposts' => 1,
			'showtitle' => 1,
			'titlelink' => 1,
			'showexcerpt' => 1,
			'showcontent' => 0, // 1.2.2 add title link
			'beforeall' => '<div class="xi_postinpost">',
			'afterall' => '</div>',
			'beforeeach' => '',
			'aftereach' => '', // 0.9.4
			'beforetitle' => '<h4 class="xi_postinpost_title">',
			'aftertitle' => '</h4>',
			'beforeexcerpt' => '<object class="xi_postinpost_excerpt">',
			'afterexcerpt' => '</object>',
			'beforecontent' => '<object class="xi_postinpost_content">',
			'aftercontent' => '</object>',
			'featuredimage' => 0,
			'featuredimageaslink' => 0,
			'featuredimagesize' => 'thumbnail',
			'read' => 'Read…',
			'more' => null,
			'from' => '',
			'to' => '',
			'expired' => '',
			'userfunction' => '',
			'lang' => '', // 1.1
			'nopost' => __( 'no post', 'xili_postinpost' ), // 1.1.2
		),
		$atts
	);
	$time_interval_ok = true;
	$fromdate = $arr_result['from'];
	$todate = $arr_result['to'];
	if ( '' != $fromdate || '' != $todate ) {
		if ( false === strpos( $fromdate, '****' ) && false === strpos( $todate, '****' ) ) {
			$time = current_time( 'timestamp' ); // wp 3.0
			if ( '' != $fromdate && $time < strtotime( $fromdate ) ) {
				$time_interval_ok = false;
			} elseif ( '' != $todate && $time > strtotime( $todate ) ) {
				$time_interval_ok = false;
			}
		} else {
			$time_interval_ok = apply_filters( 'xili_post_in_post_crontab', $fromdate, $todate );
		}
	}
	if ( $time_interval_ok ) {// since 0.9.2
		if ( class_exists( 'xili_language' ) ) { //error_log('++++++ ' . the_curlang());
			if ( 'cur' == $arr_result['lang'] ) {
				$arr_result['query'] .= '&lang=' . the_curlang(); //1.1.0
			}
		}
		//error_log('+++ shortcode +++ ' . serialize ( $arr_result ) );
		if ( '' == $content ) {
			return xi_postinpost( $arr_result );
		} else {
			return str_replace( 'xilipostinpostcontent', xi_postinpost( $arr_result ), $content );
			// when content is by example html tags enclosing this special code
		}
	} else {
		return $arr_result['expired']; // message when out of border
	}
}

add_shortcode( 'xilipostinpost', 'xi_postinpost_func' );

/** for syntax compatibility **/
function xili_postinpost( $args = '' ) {
	return xi_postinpost( $args ); // old name
}
/**
 * ---------- function post in post or everywhere ---------- 080629 101006 -----
 *
 * @updated 0.9.4, 0.9.5, 0.9.6
 *
 */
function xi_postinpost( $args = '' ) {
	if ( is_array( $args ) ) {
		$r = &$args;
	} else {
		parse_str( $args, $r );
	}

	$defaults = array(
		'query' => '',
		'showposts' => 1,
		'showtitle' => 1,
		'titlelink' => 1,
		'showexcerpt' => 0,
		'showcontent' => 1,
		'beforeall' => '<div class="xi_postinpost">',
		'afterall' => '</div>',
		'beforeeach' => '',
		'aftereach' => '', // 0.9.4
		'beforetitle' => '<h3 class="xi_postinpost_title">',
		'aftertitle' => '</h3>',
		'beforeexcerpt' => '',
		'afterexcerpt' => '',
		'beforecontent' => '',
		'aftercontent' => '',
		'featuredimage' => 0,
		'featuredimageaslink' => 0,
		'featuredimagesize' => 'thumbnail',
		'read' => 'Read…',
		'more' => null,
		'from' => '',
		'to' => '',
		'expired' => '',
		'userfunction' => '',
		'nopost' => __( 'no post', 'xili-postinpost' ), // 1.1.2
		'is_preview' => false, // WP 3.9
	);

	$r = array_merge( $defaults, $r );
	extract( $r );
	global $wp_query, $posts, $post;
	global $page, $numpages, $multipage, $more, $pagenow; // 0.9.6
	$postinpostresult = '';
	/* save current loop */
	$tmp_query = $wp_query;
	$tmp_post = $post;
	$tmp_posts = $posts;
	/* save current pagination vars used in wp_link_pages */
	// global $page, $numpages, $multipage, $more, $pagenow;
	$tmp_page = $page;
	$tmp_numpages = $numpages;
	$tmp_multipage = $multipage;
	$tmp_more = $more;
	$tmp_pagenow = $pagenow;

	if ( class_exists( 'xili_language' ) ) {
		global $xili_language;
		$tmp_permalink = $xili_language->lang_perma;
		$tmp_show_page_on_front = $xili_language->show_page_on_front;
		$xili_language->lang_perma = false;
		$xili_language->show_page_on_front = false;
	}

	if ( ! is_array( $query ) && '' != $query ) { /* $query is here a string */
		$query = html_entity_decode( $query );
		if ( $showposts > 0 && false === strstr( $query, 'showposts' ) && '_' != substr( $query, 0, 1 ) ) {
			$query .= '&showposts=' . $showposts;
		}
	}

	if ( ! is_array( $args ) ) {
		$args = array( $args );
	}
	$query_key = 'post_in_post' . md5( serialize( $query ) );

	if ( $is_preview ) {
		$result = false; // WP 3.9+ && customize in theme
	} else {
		$result = wp_cache_get( $query_key, 'postinpost' );
	}

	if ( false !== $result ) { //echo 'cache used in same page because query called more than one time';
		$myposts = $result;
	} else {
		$myposts = new WP_Query( apply_filters( 'xili_postinpost_query', $query ) ); // 1.2	to use complex presetted queries
	}

	if ( $myposts->have_posts() ) :
		if ( '' != $userfunction && function_exists( $userfunction ) ) {
			// since 1.0.0 with $posts and params
			// function my_pip_loop ( $params, $the_posts ) { loop inside your function }
			$postinpostresult = call_user_func_array( $userfunction, array( $r, $myposts ) );
		} else {
			$postinpostresult .= $beforeall;
			while ( $myposts->have_posts() ) :
				$myposts->the_post();

				// add class if LI tag is used - class because multiple instantiations - 0.9.5
				$startchars = substr( $beforeeach, 0, 3 );
				$startcharsclass = substr( $beforeeach, 0, 11 );
				if ( strtolower( $startchars ) == '<li' ) {
					if ( strtolower( $startcharsclass ) == '<li class="' ) { // as set in widget
						$beforeeach_id = $startcharsclass . 'xpipid-' . $post->ID . ' ' . substr( $beforeeach, 11 );
					} else {
						$beforeeach_id = $beforeeach;
					}
				} else {
					$beforeeach_id = $beforeeach;
				}

				$postinpostresult .= $beforeeach_id;

				if ( $showtitle ) {
					if ( ! $titlelink ) :
						$postinpostresult .= the_title( $beforetitle, $aftertitle, false );
					else :
						$postinpostresult .= $beforetitle . '<a href="' . get_permalink( $post->ID ) . '" title="' . __( $read, the_text_domain() ) . '">' . the_title( '', '', false ) . '</a>' . $aftertitle;
					endif;
				}
				if ( $featuredimage && null != get_post_thumbnail_id( $post->ID ) ) {
					if ( $featuredimageaslink ) {
						$postinpostresult .= '<a href="' . get_permalink( $post->ID ) . '" title="' . __( $read, the_text_domain() ) . '">' . get_the_post_thumbnail( $post->ID, $featuredimagesize ) . '</a>';
					} else {
						$postinpostresult .= get_the_post_thumbnail( $post->ID, $featuredimagesize );
					}
				}

				if ( $showexcerpt ) {
					$postinpostresult .= $beforeexcerpt . apply_filters( 'the_excerpt', get_the_excerpt() ) . $afterexcerpt;
				}
				if ( $showcontent ) {
					$postinpostresult .= $beforecontent . apply_filters( 'the_content', get_the_content( $r['more'] ) ) . $aftercontent;
				}

				$postinpostresult = xili_post_in_post_insert_edit_link( $postinpostresult, $r, $post );

				$postinpostresult .= $aftereach;

			endwhile;
			$postinpostresult .= $afterall;
		}
	else :
		$postinpostresult = apply_filters( 'xili_postinpost_nopost', $nopost, $r ); // $nopost filtered 1.4.2;	// 1.1.2 - $nopost
	endif;
	/*restore current loop */

	if ( class_exists( 'xili_language' ) ) {
		global $xili_language;
		$xili_language->lang_perma = $tmp_permalink;
		$xili_language->show_page_on_front = $tmp_show_page_on_front;
	}

	$wp_query = null;
	$wp_query = $tmp_query;
	$post = null;
	$post = $tmp_post;
	$posts = null;
	$posts = $tmp_posts;
	/* pagination 0.9.6 */
	$page = $tmp_page;
	$numpages = $tmp_numpages;
	$multipage = $tmp_multipage;
	$more = $tmp_more;
	$pagenow = $tmp_pagenow;

	wp_cache_set( $query_key, $myposts, 'postinpost' ); // only the query is cached - not the format tags

	return $postinpostresult;
}
/*---------- end function post in post -----------------*/

/**
 * Insert edit link
 *
 */

function xili_post_in_post_insert_edit_link( $postinpostresult, $r, $post ) {

	if ( ! isset( $r['displayeditlink'] ) ) { // local value is check first
		global $xili_postinpost;
		$addlink = ( 'enable' == $xili_postinpost->xili_settings['displayeditlink'] ) ? true : false;
		$displayeditlink = ( 'enable' == $xili_postinpost->xili_settings['displayeditlink'] ) ? 1 : 0;
	} else {
		//error_log ('toto='.$r['displayeditlink']);
		$addlink = ( '0' != $r['displayeditlink'] ) ? true : false;
		$displayeditlink = $r['displayeditlink'];
	}

	if ( $addlink ) {
		$link = ( is_numeric ( $displayeditlink ) ) ? __( 'Edit This', the_text_domain() ) : $displayeditlink;

		$post_type_obj = get_post_type_object( $post->post_type );

		$postinpostresult .= '<span class="xpp-editlink"><a title="' . esc_attr( $post_type_obj->labels->edit_item ) . '" href="' . get_edit_post_link( $post->ID ) . '" >' . $link . '</a></span>';
	}

	return $postinpostresult;
}


function the_text_domain() {
	if ( class_exists( 'xili_language' ) ) {

		return the_theme_domain(); // depending of theme .mo (multilingual site)

	} else {

		return 'xili-postinpost';
		// depending of plugin .mo
	}
}


/**
 * first filter for time only - used by add_filter ( 'xili_post_in_post_crontab', … )
 *
 * @since 0.9.2
 *
 */
function the_xili_post_in_post_crontab( $fromdate, $todate ) {
	$time_interval_ok = true;

	if ( '' != $fromdate || '' != $todate ) {
		if ( '' != $fromdate ) {
			$fromdate = str_replace( '****-**-** ', '2000-01-01 ', $fromdate );
		}
		if ( '' != $todate ) {
			$todate = str_replace( '****-**-** ', '2000-01-01 ', $todate );
		}

		$time = strtotime( '2000-01-01 ' . date( 'H:i', current_time( 'timestamp' ) ) ); //echo '---'.date("H:i",current_time('timestamp'));
		if ( '' != $fromdate && $time < strtotime( $fromdate ) ) {
			$time_interval_ok = false;
		} elseif ( '' != $todate && $time > strtotime( $todate ) ) {
			$time_interval_ok = false;
		}
	}
	return $time_interval_ok;
}
