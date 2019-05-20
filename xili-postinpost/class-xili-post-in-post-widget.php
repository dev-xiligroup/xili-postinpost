<?php

/**
 * XPIP widget class
 *
 * @package Xili-Postinpost
 * @subpackage core
 * @since 1.7
 */


/**
 * Post in post widget
 *
 * @since 20101007
 * @updated 20101030 - 20101031 (lang)
 *
 */

class Xili_Post_In_Post_Widget extends WP_Widget {

	public function __construct() {

		$widget_ops = array(
			'classname' => 'xili_post_in_post_Widget',
			'description' => __( 'Display post in widget, by ©xiligroup v.', 'xili-postinpost' ) . '&nbsp;' . XILI_PIP_VERSION,
		);
		$control_ops = array(
			'width' => 400,
			'height' => 350,
		);
		parent::__construct( 'xilipostin', '[©xili] ' . __( 'Post in post', 'xili-postinpost' ), $widget_ops, $control_ops );
		add_filter( 'xili_post_in_post_crontab', 'the_xili_post_in_post_crontab', 10, 2 );
	}

	public function widget( $args, $instance ) {
		global $post;
		extract( $args );
		$time_interval_ok = true;
		/* time interval results */
		$fromdate = ( isset( $instance['fromdate'] ) ) ? $instance['fromdate'] : '';
		$todate = ( isset( $instance['todate'] ) ) ? $instance['todate'] : '';

		if ( '' != $fromdate || '' != $todate ) {
			if ( false === strpos( $fromdate, '****' ) && false === strpos( $todate,' ****' ) ) {

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

		if ( $time_interval_ok ) {

			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

			/**
			 * filter added to remove unwanted filter like in Karma theme :-(
			 * put add action in your child functions.php
			 */
			//remove_filter( 'widget_text', 'truethemes_formatter',99);

			do_action( 'xpp_before_widget_text_filter', $instance );
			$text = apply_filters( 'widget_text', $instance['text'], $instance );

			//add_filter( 'widget_text', 'truethemes_formatter',99);
			do_action( 'xpp_after_widget_text_filter', $instance );

			$pos = strpos( $text, '[' );
			if ( false == $pos ) {
				// classical query
				$query = $text;
				$condition_ok = true;
			} else {

				$default_params = array(
					'more' => 'toto',
					'query' => '',
					'condition' => '',
					'param' => '',
					'lang' => '',
					'beforeall' => null,
					'afterall' => null,
					'postmetakey' => '',
					'postmetafrom' => '',
				);
				// null to keep defaults main function of xi_postinpost
				// detect if condition is false what to do
				$pos = strpos( $text, ']:[' );
				if ( false === $pos ) {
					// only one
					$flow = str_replace( '[', '', str_replace( ']', '', $text ) ); // use shortcode syntax
					$noflow = '';
				} else { // there is a what to do when condition is false
					$thetwo = explode( ']:[', $text );
					$flow = str_replace( '[', '', $thetwo[0] );
					$noflow = str_replace( ']', '', $thetwo[1] );
				}

				$flow_atts = shortcode_parse_atts( $flow ); //error_log ( 'widget atts = ' . serialize( $flow_atts ));

				$arr_result = shortcode_atts( $default_params, $flow_atts );
				$thecondition = trim( $arr_result['condition'], '!' );

				if ( '' != $arr_result['condition'] && function_exists( $thecondition ) ) {
					$not = ( $thecondition == $arr_result['condition'] ) ? false : true;
					$arr_params = ( '' != $arr_result['param'] ) ? array(explode( ',', $arr_result['param'] ) ) : array();
					$condition_ok = ( $not ) ? !call_user_func_array( $thecondition, $arr_params ) : call_user_func_array ( $thecondition, $arr_params );

					if ( ! $condition_ok && ''!= $noflow ) {
						// check no condition
						$flow_atts = shortcode_parse_atts( $noflow ); // echo 'no='.$noflow.' )';
						$arr_result = shortcode_atts( $default_params, $flow_atts ); // new keys of second block
						$arr_params = ( '' != $arr_result['param'] ) ? array( explode( ',', $arr_result['param'] ) ) : array();
						$thecondition = trim( $arr_result['condition'], '!' );
						$not = ( $thecondition == $arr_result['condition'] ) ? false : true;
						if ( '' != $arr_result['condition'] && function_exists( $thecondition ) ) {
							$condition_ok = ( $not ) ? ! call_user_func_array( $thecondition, $arr_params ) : call_user_func_array( $thecondition, $arr_params ); // if false nothing displayed
						} else {
						$condition_ok = true; // display results of $query or postmeta
						}
					}
				} else {
					$condition_ok = true;
				}
				$query = $arr_result['query'];
				if ( '' != $arr_result['postmetakey'] ) {

					$fromid = ( '' != $arr_result['postmetafrom'] ) ? $arr_result['postmetafrom'] : ( ( is_singular() ) ? get_the_ID() : 0 );
					if ( 0 != $fromid ) {
						$theid = get_post_meta( $fromid, $arr_result['postmetakey'], true );
						if ( '' != $theid ) {
							$type = get_post_type( $theid );
							$query = ( 'page' == $type ) ? 'page_id=' . $theid : 'p=' . $theid;
							// $condition_ok defined above
						} else {
							$condition_ok = false;
						}
					} else {
						$condition_ok = false;
					}
				}
			}

			if ( ! $number = (int) $instance['showposts'] ) {
				$number = 1;
			} else if ( $number < 1 ) {
				$number = 1;
			}

			if ( $condition_ok ) {
				echo $before_widget;
				if ( ! empty( $title ) ) {
					echo $before_title . $title . $after_title;
				}
				?>
					<div class="textwidget">
				<?php
				if ( class_exists( 'xili_language' ) ) {
					if ( isset( $arr_result['lang'] ) && 'cur' == $arr_result['lang'] ) {
						$query .= '&lang=' . the_curlang();
						// 1.2.2
						unset( $arr_result['lang'] );
					}
				}

				$theargs = array(
					'query' => $query,
					'showtitle' => $instance['showtitle'],
					'titlelink' => $instance['titlelink'],
					'showexcerpt' => $instance['excerpt'],
					'showcontent' => $instance['content'],
					'showposts' => $number,
					'featuredimage' => $instance['featuredimage'],
					'featuredimageaslink' => $instance['featuredimageaslink'],
				);
				if ( isset( $instance['beforeall'] ) ) {
					$theargs ['beforeall'] = $instance['beforeall'];
				}
				if ( isset( $instance['afterall'] ) ) {
					$theargs ['afterall'] = $instance['afterall'];
				}
				if ( isset( $instance['beforetitle'] ) ) {
					$theargs ['beforetitle'] = $instance['beforetitle'];
				}
				if ( isset( $instance['aftertitle'] ) ) {
					$theargs ['aftertitle'] = $instance['aftertitle'];
				}
				if ( isset( $instance['featuredimagesize'] ) ) {
					$theargs ['featuredimagesize'] = $instance['featuredimagesize']; // 1.6
				}

				if ( isset( $instance['liclass'] ) && '' != $instance['liclass'] ) {
					$theargs ['beforeeach'] = '<li class="' . $instance['liclass'] . '">'; // 0.9.5
					$theargs ['aftereach'] = '</li>';
				}
				if ( isset( $instance['userfunction'] ) ) {
					$theargs ['userfunction'] = $instance['userfunction']; // 1.0.0
				}
				if ( isset( $default_params ) ) {
					/* merge */
					$the_arr_result = array_filter( $arr_result, array( &$this, 'delete_null' ) ); // delete null keys
					$theargs = array_merge( $the_arr_result, $theargs );
				}

				if ( method_exists( $this, 'is_preview' ) ) { // 3.9 and customize
					if ( $this->is_preview() ) {
						$theargs['is_preview'] = true;
					} else {
						$theargs['is_preview'] = false;
					}
				} else {
					$theargs['is_preview'] = false;
				}

				echo xi_postinpost( $theargs );

					?>
					</div>
				<?php
				echo $after_widget;
			}
		}
	}
	// delete null keys
	public function delete_null( $var ) {
		if ( null != $var ) {
			return $var;
		}
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		if ( current_user_can( 'unfiltered_html' ) ) {
			$instance['text'] = $new_instance['text'];
		} else {
			$instance['text'] = stripslashes( wp_filter_post_kses( addslashes( $new_instance['text'] ) ) ); // wp_filter_post_kses() expects slashed
		}

		$instance['filter'] = isset( $new_instance['filter'] );
		$instance['showtitle'] = isset( $new_instance['showtitle'] );
		$instance['titlelink'] = isset( $new_instance['titlelink'] );
		$instance['content'] = isset( $new_instance['content'] );
		$instance['excerpt'] = isset( $new_instance['excerpt'] );
		$instance['featuredimage'] = isset( $new_instance['featuredimage'] );
		$instance['featuredimagesize'] = strip_tags( $new_instance['featuredimagesize'] ); // 1.6
		$instance['featuredimageaslink'] = isset( $new_instance['featuredimageaslink'] );
		$instance['showposts'] = (int) $new_instance['showposts'];
		$instance['beforeall'] = ( isset( $new_instance['beforeall'] ) ) ? $new_instance['beforeall'] : '';
		$instance['afterall'] = ( isset( $new_instance['afterall'] ) ) ? $new_instance['afterall'] : '';
		$instance['beforetitle'] = ( isset( $new_instance['beforetitle'] ) ) ? $new_instance['beforetitle'] : '';
		$instance['aftertitle'] = ( isset( $new_instance['aftertitle'] ) ) ? $new_instance['aftertitle'] : '';
		$instance['liclass'] = strip_tags( $new_instance['liclass'] );
		$instance['fromdate'] = strip_tags( $new_instance['fromdate'] );
		$instance['todate'] = strip_tags( $new_instance['todate'] );
		$instance['userfunction'] = isset( $new_instance['userfunction'] ) ? strip_tags( $new_instance['userfunction'] ) : ''; // 1.0.0

		return $instance;
	}

	public function form( $instance ) {

		global $xili_postinpost;

		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title' => '',
				'text' => '',
				'featuredimagesize' => 'thumbnail',
			)
		); // 1.6
		$title = strip_tags( $instance['title'] );
		$beforeall = isset( $instance['beforeall'] ) ? format_to_edit( $instance['beforeall'] ) : format_to_edit( '<div class="xi_postinpost">' );
		$afterall = isset( $instance['afterall'] ) ? format_to_edit( $instance['afterall'] ) : format_to_edit( '</div>' );
		$beforetitle = isset( $instance['beforetitle'] ) ? format_to_edit( $instance['beforetitle'] ) : format_to_edit( '<h4 class="xi_postinpost_title">' );
		$aftertitle = isset( $instance['aftertitle'] ) ? format_to_edit( $instance['aftertitle'] ) : format_to_edit( '</h4>' );
		$liclass = isset( $instance['liclass'] ) ? strip_tags( $instance['liclass'] ) : ''; // LI CLASS
		$number = isset( $instance['showposts'] ) ? absint( $instance['showposts'] ) : 1;
		$text = format_to_edit( $instance['text'] );
		$fromdate = isset( $instance['fromdate'] ) ? strip_tags( $instance['fromdate'] ) : '';
		$todate = isset( $instance['todate'] ) ? strip_tags( $instance['todate'] ) : '';
		$userfunction = isset( $instance['userfunction'] ) ? strip_tags( $instance['userfunction'] ) : ''; // 1.0.0

?>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>
		<p><input id="<?php echo esc_attr( $this->get_field_id( 'showtitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'showtitle' ) ); ?>" type="checkbox" <?php checked( isset( $instance['showtitle'] ) ? $instance['showtitle'] : 1); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'showtitle' ) ); ?>"><?php esc_html_e( 'Show post title', 'xili-postinpost' ); ?></label>&nbsp;&nbsp;<input id="<?php echo esc_attr( $this->get_field_id( 'titlelink' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'titlelink' ) ); ?>" type="checkbox" <?php checked(isset( $instance['titlelink'] ) ? $instance['titlelink'] : 1); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'titlelink' ) ); ?>"><?php esc_html_e( 'Title as link', 'xili-postinpost' ); ?></label></p>
		<p><?php esc_html_e( 'Show:', 'xili-postinpost' ); ?>
			<input id="<?php echo esc_attr( $this->get_field_id( 'content' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'content' ) ); ?>" type="checkbox" <?php checked( isset( $instance['content'] ) ? $instance['content'] : 0); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'content' ) ); ?>"><?php esc_html_e( 'Content', 'xili-postinpost' ); ?></label>&nbsp;&nbsp;<input id="<?php echo esc_attr( $this->get_field_id( 'excerpt' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'excerpt' ) ); ?>" type="checkbox" <?php checked( isset( $instance['excerpt'] ) ? $instance['excerpt'] : 0 ); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'excerpt' ) ); ?>"><?php esc_html_e( 'Excerpt', 'xili-postinpost' ); ?></label>
			<br /><label for="<?php echo esc_attr( $this->get_field_id( 'featuredimagesize' ) ); ?>"><?php esc_html_e( 'Size of Featured image:', 'xili-postinpost' ); ?></label>&nbsp;<select id="<?php echo esc_attr( $this->get_field_id( 'featuredimagesize' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'featuredimagesize' ) ); ?>">
			<?php
			echo '<option value="thumbnail" ' . selected( $instance['featuredimagesize'], "thumbnail", false) . '>' . __( 'thumbnail', 'xili-postinpost' ) . '</option>';
			echo '<option value="medium" ' . selected( $instance['featuredimagesize'], "medium", false) . '>' . __( 'medium', 'xili-postinpost' ) . '</option>';
			echo '<option value="large" ' . selected( $instance['featuredimagesize'], "large", false).'>' . __( 'large', 'xili-postinpost' ) . '</option>';
			?>
			</select>
			<br /><input id="<?php echo esc_attr( $this->get_field_id( 'featuredimage' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'featuredimage' ) ); ?>" type="checkbox" <?php checked( isset( $instance['featuredimage'] ) ? $instance['featuredimage'] : 0 ); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'featuredimage' ) ); ?>"><?php esc_html_e( 'Featured image', 'xili-postinpost' ); ?></label>&nbsp;
			<input id="<?php echo esc_attr( $this->get_field_id( 'featuredimageaslink' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'featuredimageaslink' ) ); ?>" type="checkbox" <?php checked( isset( $instance['featuredimageaslink'] ) ? $instance['featuredimageaslink'] : 0 ); ?> />&nbsp;<label for="<?php echo esc_attr( $this->get_field_id( 'featuredimageaslink' ) ); ?>"><?php esc_html_e( 'Image as link', 'xili-postinpost' ); ?></label>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showposts' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'xili-postinpost' ); ?></label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'showposts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'showposts' ) ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
		<small><?php esc_html_e( 'Params and conditions:', 'xili-postinpost' ); ?></small>
		<textarea class="widefat" rows="5" cols="20" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>"><?php echo $text; ?></textarea>

		<?php
		if ( $xili_postinpost->xili_settings['displayhtmltags'] ) {
			?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php esc_html_e( 'HTML settings', 'xili-postinpost' ); ?></legend>
		<p><input id="<?php echo esc_attr( $this->get_field_id( 'beforeall' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'beforeall' ) ); ?>" type="text" value="<?php echo $beforeall; ?>" size="40" /><br/><label for="<?php echo esc_attr( $this->get_field_id( 'afterall' ) ); ?>"><?php esc_html_e( 'Block tags', 'xili-postinpost' ); ?></label><input id="<?php echo esc_attr( $this->get_field_id( 'afterall' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'afterall' ) ); ?>" type="text" value="<?php echo $afterall; ?>" size="15" />
		</p>
		<p><input id="<?php echo esc_attr( $this->get_field_id( 'beforetitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'beforetitle' ) ); ?>" type="text" value="<?php echo $beforetitle; ?>" size="40" /><br/><label for="<?php echo esc_attr( $this->get_field_id( 'aftertitle' ) ); ?>"><?php esc_html_e( 'Title tags', 'xili-postinpost' ); ?></label><input id="<?php echo esc_attr( $this->get_field_id( 'aftertitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'aftertitle' ) ); ?>" type="text" value="<?php echo $aftertitle; ?>" size="5" />
		</p>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'liclass' ) ); ?>"><?php esc_html_e( 'LI class:', 'xili-postinpost' ); ?></label><input id="<?php echo esc_attr( $this->get_field_id( 'liclass'  ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'liclass' ) ); ?>" type="text" value="<?php echo $liclass; ?>" size="20" /></p>
		<p><small><?php _e("Note: if LI class is empty no LI are generated around each post, if set, don't forget to set above tag's block of results to UL or OL !", 'xili-postinpost' ); ?></small></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'userfunction' ) ); ?>"><?php esc_html_e( 'Function (must exists)', 'xili-postinpost' ); ?></label><input id="<?php echo esc_attr( $this->get_field_id( 'userfunction' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'userfunction' ) ); ?>" type="text" value="<?php echo $userfunction; ?>" size="40" />
		</p>
		</fieldset>
		<?php
		} else {
			?>
			<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'beforeall' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'beforeall' ) ); ?>" value="<?php echo $beforeall; ?>"  />
			<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'afterall' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'afterall' ) ); ?>" value="<?php echo $afterall; ?>" />
			<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'beforetitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'beforetitle' ) ); ?>" value="<?php echo $beforetitle; ?>"  />
			<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'aftertitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'aftertitle' ) ); ?>" value="<?php echo $aftertitle; ?>" />
			<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'liclass' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'liclass' ) ); ?>" value="<?php echo $liclass; ?>"  />
			<?php
		}
		if ( $xili_postinpost->xili_settings['displayperiod'] ) {
			?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php esc_html_e( 'Dates of display period', 'xili-postinpost' ); ?></legend>
		<small><?php esc_html_e( 'Leave inputs empty for permanent display.', 'xili-postinpost' ); ?></small>
			<p><label for="<?php echo esc_attr( $this->get_field_id( 'fromdate' ) ); ?>"><?php esc_html_e( 'From:', 'xili-postinpost' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'fromdate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'fromdate' ) ); ?>" type="text" value="<?php echo $fromdate; ?>" size="20" />&nbsp;<?php esc_html_e( '(aaaa-mm-dd hh:mm)', 'xili-postinpost' ); ?></p>
			<p><label for="<?php echo esc_attr( $this->get_field_id( 'todate' ) ); ?>"><?php esc_html_e( 'To:', 'xili-postinpost' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'todate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'todate' ) ); ?>" type="text" value="<?php echo $todate; ?>" size="20" />&nbsp;<?php esc_html_e( '(aaaa-mm-dd hh:mm)', 'xili-postinpost' ); ?></p>
		</fieldset>
		<?php } else { ?>
		<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'todate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'todate' ) ); ?>" value="<?php echo $todate; ?>"  />
		<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'fromdate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'fromdate' ) ); ?>" value="<?php echo $fromdate; ?>" />
		<?php } ?>

<small>© dev.xiligroup.com <?php echo 'v. ' . XILI_PIP_VERSION; ?></small>

<?php
	}

} // end widget
