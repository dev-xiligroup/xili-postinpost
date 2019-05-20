<?php

/**
 * XPIP main class
 *
 * @package Xili-Postinpost
 * @subpackage core
 * @since 1.7
 */


class Xili_Postinpost {

	public $xili_settings = array();

	public $news_id = 0; //for multi pointers
	public $news_case = array();
	public $assets_folder = 'https://ps.w.org/xili-postinpost/assets/';

	public function __construct() {

		register_activation_hook( __FILE__, array( &$this, 'xili_postinpost_activate' ) );
		$this->xili_settings = get_option( 'xili_postinpost_settings' );
		if ( empty( $this->xili_settings ) ) {
			$this->initial_settings();
			update_option( 'xili_postinpost_settings', $this->xili_settings );
		} else {
			if ( $this->xili_settings['version'] == '1.0' ) {
				$this->xili_settings['displayhtmltags'] = '';
				$this->xili_settings['version'] = '1.1';
				update_option( 'xili_postinpost_settings', $this->xili_settings );
			}
			if ( $this->xili_settings['version'] == '1.1' ) {
				$this->xili_settings['displayeditlink'] = '';
				$this->xili_settings['version'] = '1.2';
				update_option( 'xili_postinpost_settings', $this->xili_settings );
			}
			if ( ! isset( $this->xili_settings['version'] ) || $this->xili_settings['version'] != '1.2' ) {
				// repair
				$this->initial_settings();
				update_option( 'xili_postinpost_settings', $this->xili_settings );
			}
		}

		add_action( 'wp_head', array( &$this, 'head_insertions' ) );
		add_action( 'widgets_init', array( &$this, 'xili_widgets_init' ) ); // call in default-widgets

		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this, 'add_setting_pages' ) );
			add_filter( 'plugin_action_links', array( &$this, 'filter_plugin_actions' ), 10, 2 );
			add_action( 'contextual_help', array( &$this, 'add_help_text' ), 10, 3 );
			add_action( 'admin_head', array( &$this, 'appearance_widget_pointer' ) );
		}
	}

	public function initial_settings() {
		$this->xili_settings = array(
			'widget' => 'enable',
			'displayperiod' => '',
			'displayhtmltags'   => '', // 0.9.5
			'displayeditlink'   => '', //1.1.2
			'version'           => '1.2',
		);
	}

	public function xili_postinpost_activate() {
		$this->xili_settings = get_option( 'xili_postinpost_settings' );
		if ( empty( $this->xili_settings ) ) {
			$this->initial_settings();
			update_option( 'xili_postinpost_settings', $this->xili_settings );
		}
	}

	/**
	 * register xili widgets
	 *
	 * @updated 0.9.4 for widget textdomain
	 */
	public function xili_widgets_init() {
		load_plugin_textdomain( 'xili-postinpost', false, 'xili-postinpost' ); // no sub folder
		if ( 'enable' == $this->xili_settings['widget'] ) {
			register_widget( 'Xili_Post_In_Post_Widget' );
		}
	}

	public function get_wplang() {
		global $wp_version;
		if ( version_compare( $wp_version, '4.0', '<' ) ) {
			if ( defined( 'WPLANG' ) ) {
				return WPLANG;
			} else {
				return '';
			}
		} else {
			return get_option( 'WPLANG', '' );
		}
	}

	/**
	 * add ©
	 *
	 * @since 0.9.1
	 * @param no
	 */
	public function head_insertions() {
		echo "<!-- Website powered with xili-postinpost v. " . XILI_PIP_VERSION . " WP plugin of dev.xiligroup.com -->\n";
	}


	/********************************** ADMIN UI ***********************************/


	public function add_setting_pages() {
		$this->thehook = add_options_page( esc_html__( 'xili Post in Post plugin', 'xili-postinpost' ), esc_html__( '©xili Post in Post', 'xili-postinpost' ), 'manage_options', 'xili_postinpost_page', array( &$this, 'xili_postinpost_settings' ) );
		add_action( 'load-' . $this->thehook, array( &$this, 'on_load_page' ) );

		$this->insert_news_pointer( 'xpp_new_version' ); // pointer in menu for updated version
		add_action( 'admin_print_footer_scripts', array( &$this, 'print_the_pointers_js' ) );
	}

	public function on_load_page() {
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'postbox' );
			add_meta_box( 'xili_postinpost-sidebox-mail', esc_html__( 'Mail & Support', 'xili-postinpost' ), array( &$this, 'on_sidebox_mail_content' ), $this->thehook, 'side', 'core' );
	}

	public function appearance_widget_pointer() {
		$screen = get_current_screen(); error_log ( '--- '. $screen->id );
		if ( 'widgets' == $screen->id ) {
			$this->insert_news_pointer( 'xpp_new_features_widget' ); // pointer in menu for updated version
			add_action( 'admin_print_footer_scripts', array( &$this, 'print_the_pointers_js' ) );
		}
	}

	public function check_other_xili_plugins() {
		$list = array();
		if ( class_exists( 'xili_language' ) ) {
			$list[] = 'xili-language';
		}
		if ( class_exists( 'xili_tidy_tags' ) ) {
			$list[] = 'xili-post-in-post';
		}
		if ( class_exists( 'xili_dictionary' ) ) {
			$list[] = 'xili-dictionary';
		}
		if ( class_exists( 'xilithemeselector' ) ) {
			$list[] = 'xilitheme-select';
		}
		if ( function_exists( 'insert_a_floom' ) ) {
			$list[] = 'xili-floom-slideshow';
		}

		return implode( ', ', $list );
	}

	public function on_sidebox_mail_content( $data ) {
		extract( $data );
		global $wp_version;
		$emessage = '';
		$theme = ( isset( $this->xili_settings['theme'] ) ) ? $this->xili_settings['theme'] : '';
		$wplang = ( isset( $this->xili_settings['wplang'] ) ) ? $this->xili_settings['wplang'] : '';
		$xiliplug = ( isset( $this->xili_settings['xiliplug'] ) ) ? $this->xili_settings['xiliplug'] : '';
		if ( '' != $emessage ) {
			?>
			<h4><?php esc_html_e( 'Note:', 'xili-post-in-post' ); ?></h4>
			<p><strong><?php echo $emessage; ?></strong></p>
		<?php
		}
		?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php echo esc_html_e( 'Mail to dev.xiligroup', 'xili-post-in-post' ); ?></legend>
		<label for="ccmail"><?php esc_html_e( 'Cc: (Reply to:)', 'xili-post-in-post' ); ?>
		<input class="widefat" id="ccmail" name="ccmail" type="text" value="<?php bloginfo( 'admin_email' ); ?>" /></label><br /><br />
		<?php
		if ( false === strpos( get_bloginfo( 'url' ), 'local' ) ) {
				?>
			<label for="urlenable">
				<input type="checkbox" id="urlenable" name="urlenable" value="enable" <?php checked( ( isset( $this->xili_settings['url'] ) && 'enable' == $this->xili_settings['url'] ), true, true ); ?> />&nbsp;<?php bloginfo( 'url' ); ?>
			</label><br />
			<?php
		} else {
			?>
			<input type="hidden" name="onlocalhost" id="onlocalhost" value="localhost" />
			<?php
		}
		?>
		<br /><em><?php esc_html_e( 'When checking and giving detailled infos, support will be better !', 'xili-post-in-post' ); ?></em><br />
		<label for="themeenable">
			<input type="checkbox" id="themeenable" name="themeenable" value="enable" <?php checked( $theme, 'enable', true ); ?> />&nbsp;<?php echo 'Theme name= ' . get_option( 'stylesheet' ); ?>
		</label><br />
		<?php if ( '' != $this->get_wplang() ) { ?>
		<label for="wplangenable">
			<input type="checkbox" id="wplangenable" name="wplangenable" value="enable" <?php checked( $wplang, 'enable', true ); ?> />&nbsp;<?php echo 'WPLANG= ' . $this->get_wplang(); ?>
		</label><br />
		<?php } ?>
		<label for="versionenable">
			<input type="checkbox" id="versionenable" name="versionenable" value="enable" <?php if ( isset( $this->xili_settings['version-wp'] ) ) checked( $this->xili_settings['version-wp'], 'enable', true); ?> />&nbsp;<?php echo 'WP version: ' . $wp_version; ?>
		</label><br /><br />
		<?php
		$list = $this->check_other_xili_plugins();
		if ( '' != $list ) {
			?>
		<label for="xiliplugenable">
			<input type="checkbox" id="xiliplugenable" name="xiliplugenable" value="enable" <?php checked( $xiliplug, 'enable', true ); ?> />&nbsp;<?php echo 'Other xili plugins = ' . $list; ?>
		</label><br /><br />
		<?php } ?>
		<label for="webmestre"><?php esc_html_e( 'Type of webmaster:', 'xili-postinpost' ); ?>
		<select name="webmestre" id="webmestre" style="width:100%;">
		<?php
		if ( ! isset( $this->xili_settings['webmestre-level'] ) ) {
			$this->xili_settings['webmestre-level'] = '?';
		}
		?>
			<option value="?" <?php selected( $this->xili_settings['webmestre-level'], '?' ); ?>><?php esc_html_e( 'Define your experience as webmaster…', 'xili-post-in-post' ); ?></option>
			<option value="newbie" <?php selected( $this->xili_settings['webmestre-level'], 'newbie' ); ?>><?php esc_html_e( 'Newbie in WP', 'xili-post-in-post' ); ?></option>
			<option value="wp-php" <?php selected( $this->xili_settings['webmestre-level'], 'wp-php' ); ?>><?php esc_html_e( 'Good knowledge in WP and few in php', 'xili-post-in-post' ); ?></option>
			<option value="wp-php-dev" <?php selected( $this->xili_settings['webmestre-level'], 'wp-php-dev' ); ?>><?php esc_html_e( 'Good knowledge in WP, CMS and good in php', 'xili-post-in-post' ); ?></option>
			<option value="wp-plugin-theme" <?php selected( $this->xili_settings['webmestre-level'], 'wp-plugin-theme' ); ?>><?php esc_html_e( 'WP theme and /or plugin developper', 'xili-post-in-post' ); ?></option>
		</select></label>
		<br /><br />
		<label for="subject"><?php esc_html_e( 'Subject:', 'xili-post-in-post' ); ?>
		<input class="widefat" id="subject" name="subject" type="text" value='' /></label>
		<select name="thema" id="thema" style="width:100%;">
			<option value='' ><?php esc_html_e( 'Choose topic... ', 'xili-post-in-post' ); ?></option>
			<option value="Message" ><?php esc_html_e( 'Message', 'xili-post-in-post' ); ?></option>
			<option value="Question" ><?php esc_html_e( 'Question', 'xili-post-in-post' ); ?></option>
			<option value="Encouragement" ><?php esc_html_e( 'Encouragement', 'xili-post-in-post' ); ?></option>
			<option value="Support need" ><?php esc_html_e( 'Support need', 'xili-post-in-post' ); ?></option>
		</select>
		<textarea class="widefat" rows="5" cols="20" id="mailcontent" name="mailcontent"><?php esc_html_e( 'Your message here…', 'xili-post-in-post' ); ?></textarea>
		</fieldset>
		<p>
		<?php esc_html_e( 'Before send the mail, check the infos to be sent and complete textarea. A copy (Cc:) is sent to webmaster email (modify it if needed).', 'xili-postinpost' ); ?><br />
		<?php esc_html_e( 'Reply in less that 3 or 4 days…', 'xili-postinpost' ); ?>
		</p>
		<div class='submit'>
		<input id='sendmail' name='sendmail' type='submit' tabindex='6' value="<?php esc_html_e( 'Send email', 'xili-postinpost' ); ?>" /></div>
		<?php //wp_nonce_field( 'xili-postinpost-sendmail' ); ?>
		<div style="clear:both; height:1px"></div>
		<?php
	}

	public function xili_postinpost_settings() {
		global $wp_version;
		$msg = '';
		$message = '';
		if ( isset( $_POST['Submit'] ) ) {
			check_admin_referer( 'xili-postinpost-settings' );
			$this->xili_settings['widget'] = ( isset( $_POST['widgetenable'] ) ) ? $_POST['widgetenable'] : '';
			$this->xili_settings['displayperiod'] = ( isset( $_POST['displayperiod'] ) ) ? $_POST['displayperiod'] : '';
			$this->xili_settings['displayeditlink'] = ( isset( $_POST['displayeditlink'] ) ) ? $_POST['displayeditlink'] : ''; // 1.1.2
			$this->xili_settings['displayhtmltags'] = ( isset( $_POST['displayhtmltags'] ) ) ? $_POST['displayhtmltags'] : '';

			update_option( 'xili_postinpost_settings', $this->xili_settings );
			$msg = 1;
		}
		if ( isset( $_POST['sendmail']) ) {
			check_admin_referer( 'xili-postinpost-settings' );
			$this->xili_settings['url'] = ( isset( $_POST['urlenable'] ) ) ? $_POST['urlenable'] : '';
			$this->xili_settings['theme'] = ( isset( $_POST['themeenable'] ) ) ? $_POST['themeenable'] : '';
			$this->xili_settings['wplang'] = ( isset( $_POST['wplangenable'] ) ) ? $_POST['wplangenable'] : '';
			$this->xili_settings['version-wp'] = ( isset( $_POST['versionenable'] ) ) ? $_POST['versionenable'] : '';
			$this->xili_settings['xiliplug'] = ( isset( $_POST['xiliplugenable'] ) ) ? $_POST['xiliplugenable'] : '';
			$this->xili_settings['webmestre-level'] = $_POST['webmestre']; // 1.2.1
			update_option( 'xili_postinpost_settings', $this->xili_settings );
			$contextual_arr = array();
			if ( 'enable' == $this->xili_settings['url'] ) {
				$contextual_arr[] = 'url=[ ' . get_bloginfo( 'url' ) . ' ]';
			}
			if ( isset( $_POST['onlocalhost'] ) ) {
				$contextual_arr[] = 'url=local';
			}
			if ( 'enable' == $this->xili_settings['theme'] ) {
				$contextual_arr[] = 'theme=[ ' . get_option( 'stylesheet' ) . ' ]';
			}
			if ( 'enable' == $this->xili_settings['wplang'] ) {
				$contextual_arr[] = 'WPLANG=[ ' . $this->get_wplang() . ' ]';
			}
			if ( 'enable' == $this->xili_settings['version-wp'] ) {
				$contextual_arr[] = 'WP version=[ ' . $wp_version . ' ]';
			}
			if ( 'enable' == $this->xili_settings['xiliplug'] ) {
				$contextual_arr[] = 'xiliplugins=[ ' . $this->check_other_xili_plugins() . ' ]';
			}

			$contextual_arr[] = $this->xili_settings['webmestre-level'];  // 1.9.1

			$headers = 'From: xili-tidy-tags plugin page <' . get_bloginfo( 'admin_email' ) . '>' . "\r\n";
			if ( '' != $_POST['ccmail'] ) {
				$headers .= 'Cc: <' . $_POST['ccmail'] . '>' . "\r\n";
				$headers .= 'Reply-To: <' . $_POST['ccmail'] . '>' . "\r\n";
			}
			$headers .= '\\';
			$message = 'Message sent by: ' . get_bloginfo( 'admin_email' ) . "\n\n";
			$message .= 'Subject: ' . $_POST['subject'] . "\n\n";
			$message .= 'Topic: ' . $_POST['thema'] . "\n\n";
			$message .= 'Content: ' . $_POST['mailcontent'] . "\n\n";
			$message .= 'Checked contextual infos: ' . implode( ', ', $contextual_arr ) . "\n\n";
			$message .= "This message was sent by webmaster in xili-postinpost plugin settings page.\n\n";
			$message .= "\n\n";
			$result = wp_mail( 'contact@xiligroup.com', $_POST['thema'] . ' from xili-PostinPost plugin v.' . XILI_PIP_VERSION . ' settings Page.', $message, $headers );

			$msg = 2;
			/* translators: */
			$message = sprintf( esc_html__( 'Thanks for your email. A copy was sent to %s (%s)', 'xili-postinpost' ), $_POST['ccmail'], $result );

		}
		$themessages[1] = __( 'Settings updated.', 'xili-postinpost' );
		$themessages[2] = __( 'Email sent.', 'xili-postinpost' );
		$data = array( 'message' => $message );
		?>
		<div id="xili-postinpost-settings" class="wrap" style="min-width:750px">

			<h2><?php esc_html_e( 'xili Post in Post', 'xili-postinpost' ); ?></h2>
			<?php if ( 0 != $msg ) { ?>
			<div id="message" class="updated fade"><p><?php echo $themessages[ $msg ]; ?></p></div>
			<?php } ?>
			<form name="add" id="add" method="post" action="options-general.php?page=xili_postinpost_page" >

				<?php wp_nonce_field( 'xili-postinpost-settings' ); ?>
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				global $wp_version;
				if ( version_compare( $wp_version, '3.3.9', '<' ) ) {
					$poststuff_class = 'class="metabox-holder has-right-sidebar"';
					$postbody_class = '';
					$postleft_id = '';
					$postright_id = 'side-info-column';
					$postleft_class = '';
					$postright_class = 'inner-sidebar';
				} else { // 3.4
					$poststuff_class = '';
					$postbody_class = 'class="metabox-holder columns-2"';
					$postleft_id = 'id="postbox-container-2"';
					$postright_id = 'postbox-container-1';
					$postleft_class = 'class="postbox-container"';
					$postright_class = 'postbox-container';
				}
				?>
				<div id="poststuff" <?php echo $poststuff_class; ?>>
					<div id="post-body" <?php echo $postbody_class; ?> >

						<div id="<?php echo $postright_id; ?>" class="<?php echo $postright_class; ?>">
							<?php do_meta_boxes( $this->thehook, 'side', $data ); ?>
						</div>

						<div id="post-body-content" class="has-sidebar-content" style="min-width:360px">

							<h4><?php esc_html_e( 'xili-postinpost provides a triple tookit to insert post(s) everywhere in webpage. Template tag function, shortcode and widget are available.', 'xili-postinpost' ); ?></h4>

							<p><?php _e( '<strong>Template tag</strong>: xi_postinpost( - array of params - )', 'xili-postinpost' ); ?></p>



							<h5><?php esc_html_e( 'The default parameters in array before merging with yours, (from source)', 'xili-postinpost' ); ?></h5>
							<p><code>
							<?php
							echo format_to_edit(
								"\$defaults = array( 'query'=>'', 'showposts'=>1,
	'showtitle'=>1, 'titlelink'=>1, 'showexcerpt'=>0, 'showcontent'=>1,
	'beforeall'=>'<div class=\"xi_postinpost\">', 'afterall'=>'</div>',
	'beforeeach'=>'', 'aftereach'=>'',
	'beforetitle'=>'<h3 class=\"xi_postinpost_title\">', 'aftertitle'=>'</h3>',
	'beforeexcerpt'=>'', 'afterexcerpt'=>'',
	'beforecontent'=>'', 'aftercontent'=>'',
	'featuredimage' => 0, 'featuredimageaslink' => 0, 'featuredimagesize' => 'thumbnail',
	'read' => 'Read…',
	'more' => null,
	'from' => '', 'to' => '', 'expired' => '',
	'userfunction' => '',
	'nopost' => __( 'no post', 'xili-postinpost' )
	);"
							);
							?>
							</code><br /><br /><em>
							<?php esc_html_e( 'By default, xili_postinpost returns the latest post (linked title and content) !', 'xili-postinpost' ); ?></em>
							<br /></p>
							<p><?php _e( '<strong>Shortcode</strong>: [xilipostinpost], as [xilipostinpost query="p=1"]', 'xili-postinpost' ); ?><br /><br />
							<?php /* translators: */ printf( esc_html__( 'Or like: %s if xili-language active.', 'xili-postinpost' ), ' [xilipostinpost showexcerpt=0 showtitle=1 titlelink=0 query="cat=14&showposts=2&lang=fr_fr" ]' ); ?>
							</p>

							<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;">
								<label for="widgetenable">
								<?php esc_html_e( 'Insert Edit link:', 'xili_postinpost' ); ?>
									<input type="checkbox" id="displayeditlink" name="displayeditlink" value="enable"
									<?php
									if ( 'enable' == $this->xili_settings['displayeditlink'] ) {
										echo ' checked="checked"';
									}
									?>
									/>
								</label>&nbsp;&nbsp;
								<label for="widgetenable">
								<?php esc_html_e( 'Widget available:', 'xili_postinpost' ); ?>
									<input type="checkbox" id="widgetenable" name="widgetenable" value="enable"
									<?php
									if ( 'enable' == $this->xili_settings['widget'] ) {
										echo ' checked="checked"';
									}
									?>
									/>
								</label>&nbsp;&nbsp;
								<?php
								if ( 'enable' == $this->xili_settings['widget'] ) {
									?>
									(&nbsp;<label for="displayperiod">
									<?php esc_html_e( 'Display period available:', 'xili_postinpost' ); ?>
										<input type="checkbox" id="displayperiod" name="displayperiod" value="enable"
										<?php
										if ( 'enable' == $this->xili_settings['displayperiod'] ) {
											echo ' checked="checked"';
										}
										?>
										/>
									</label>&nbsp;&nbsp;&nbsp;
									<label for="displayhtmltags">
								<?php esc_html_e( 'HTML tags settings:', 'xili_postinpost' ); ?>
									<input type="checkbox" id="displayhtmltags" name="displayhtmltags" value="enable"
									<?php
									if ( 'enable' == $this->xili_settings['displayhtmltags'] ) {
										echo ' checked="checked"';
									}
									?>
									/>
								</label> )
								<?php } else { ?>
										<input type="hidden" id="displayperiod" name="displayperiod" value="<?php echo $this->xili_settings['displayperiod']; ?>"  />
										<input type="hidden" id="displayhtmltags" name="displayhtmltags" value="<?php echo $this->xili_settings['displayhtmltags']; ?>"  />
								<?php } ?>
							</fielset>
								<p class="submit"><input type="submit" name="Submit" id="Submit" value="<?php esc_html_e( 'Save Changes' ); ?> &raquo;" /></p>

							<?php
							if ( 'enable' == $this->xili_settings['widget'] ) {
								?>
								<div class="widefat" style="margin:20px 0; padding:10px; width:95%;">
									<h4><?php esc_html_e( 'Syntax examples in widget setting UI', 'xili-postinpost' ); ?></h4>
									<h5><?php esc_html_e( 'Here simple query', 'xili-postinpost' ); ?></h5>
										<p><?php esc_html_e( 'A post display with title and excerpt', 'xili-postinpost' ); ?></p>
										<img src="
										<?php
										// echo plugins_url( 'screenshot-2.png', __FILE__ );
										// https://ps.w.org/xili-postinpost/assets/screenshot-1.png?rev=907282
										echo $this->assets_folder . 'screenshot-1.png';
										?>
										" alt=''/>
									<h5><?php esc_html_e( 'Here conditional query', 'xili-postinpost' ); ?></h5>
										<p><?php esc_html_e( 'Three posts of category 3 displayed with title and link IF a page is displayed (with two widgets options set):', 'xili-postinpost' ); ?></p>
										<img src="<?php echo $this->assets_folder . 'screenshot-2.png'; ?>" alt=''/>
									<h5><?php esc_html_e( 'Another conditional query', 'xili-postinpost' ); ?></h5>
										<p><?php esc_html_e( 'True and false conditions example: what happens and when ?', 'xili-postinpost' ); ?></p>
										<img src="<?php echo $this->assets_folder . 'screenshot-3.png'; ?>" alt=''/>
									<h5><?php esc_html_e( 'Another query with multilingual context', 'xili-postinpost' ); ?></h5>
										<p><?php esc_html_e( 'A query combined with current language (requires xili-language active)', 'xili-postinpost' ); ?></p>
										<img src="<?php echo $this->assets_folder . 'screenshot-4.png'; ?>" alt=''/>
								</div>
							<?php } ?>
							<h4><a href="http://dev.xiligroup.com/xili-postinpost" title="Plugin page and docs" target="_blank" style="text-decoration:none" ><img style="vertical-align:middle" src="<?php echo plugins_url( 'xilipostinpost-logo-32.png', __FILE__ ); ?>" alt="xili-postinpost logo"/>  xili-postinpost</a> - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php esc_html_e( 'Author' ); ?>" >xiligroup.com</a>™ - msc 2009-2019 - v. <?php echo XILI_PIP_VERSION; ?></h4>
						</div>

					</div>
				</div>
			</form>
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
				// postboxes setup
				postboxes.add_postbox_toggles( '<?php echo $this->thehook; ?>' );

			});
			//]]>
		</script>
		<?php
	}

	/**
	 * Add action link(s) to plugins page
	 *
	 * @since 0.9.0
	 * @author MS
	 * @copyright Dion Hulse, http://dd32.id.au/wordpress-plugins/?configure-link and scripts@schloebe.de
	 */
	public function filter_plugin_actions( $links, $file ) {
		static $this_plugin;
		if ( ! $this_plugin ) {
			$this_plugin = plugin_basename( __FILE__ );
		}
		if ( $file == $this_plugin ) {
			$settings_link = '<a href="options-general.php?page=xili_postinpost_page">' . esc_html__( 'Settings' ) . '</a>';
			$links = array_merge( array( $settings_link ), $links ); // before other links
		}
		return $links;
	}

	/**
	 * Contextual help
	 *
	 * @since 1.7.0
	 */
	public function add_help_text( $contextual_help, $screen_id, $screen ) {

		if ( 'settings_page_xili_postinpost_page' == $screen->id ) {
			$to_remember =
			'<p>' . esc_html__( 'Things to remember to set xili-postinpost:', 'xili-postinpost' ) . '</p>' .
			'<ul>' .
			'<li>' . esc_html__( 'Verify that the theme can use widget.', 'xili-postinpost' ) . '</li>' .
			'<li>' . __( 'As developer, visit <a href="https://wordpress.org/support/plugin/xili-postinpost" target="_blank">dev.xiligroup forum</a> to esc_html__ powerful features and filters to customize your results.', 'xili-postinpost' ) . '</li>' .
			'<li>' . esc_html__( 'Visit dev.xiligroup website.', 'xili-postinpost' ) . '</li>' .

			'</ul>';

			$options =
			'<p>' . esc_html__( 'In xili-postinpost settings it possible to set general options:', 'xili-postinpost' ) . '</p>' .
			'<ul>' .
			'<li>' . esc_html__( 'Insert Edit link: add automatically the link after the post in the series. Can also be set as parameters in query (displayeditlink). The local parameter has priority.', 'xili-postinpost' ) . '</li>' .
			'<li>' . esc_html__( 'Post in post Widget available in Appearance screen', 'xili-postinpost' ) . '<ol>' .
			'<li>' . esc_html__( 'Display period available inside widget settings window.', 'xili-postinpost' ) . '</li>' .
			'<li>' . esc_html__( 'HTML tags settings inside widget window.', 'xili-postinpost' ) . '</li></ol></li>' .
			'</ul>';

			$more_infos =
				'<p><strong>' . esc_html__( 'For more information:' ) . '</strong></p>' .
				'<p>' . esc_html__( '<a href="http://dev.xiligroup.com/xili-postinpost" target="_blank">Xili-PostinPost Plugin Documentation</a>', 'xili-postinpost' ) . '</p>' .
				'<p>' . esc_html__( '<a href="http://wiki.xiligroup.org/" target="_blank">Xili Wiki Documentation</a>', 'xili-postinpost' ) . '</p>' .
			'<p>' . esc_html__( '<a href="https://wordpress.org/support/plugin/xili-postinpost" target="_blank">Support Forums</a>', 'xili-postinpost' ) . '</p>' .
			'<p>' . esc_html__( '<a href="http://codex.wordpress.org/" target="_blank">WordPress Documentation</a>', 'xili-postinpost' ) . '</p>';

			$screen->add_help_tab(
				array(
					'id' => 'to-remember',
					'title' => esc_html__( 'Things to remember', 'xili-postinpost' ),
					'content' => $to_remember,
				)
			);

			$screen->add_help_tab(
				array(
					'id' => 'options',
					'title' => esc_html__( 'Available options', 'xili-postinpost' ),
					'content' => $options,
				)
			);

			$screen->add_help_tab(
				array(
					'id' => 'more-infos',
					'title' => esc_html__( 'For more information', 'xili-postinpost' ),
					'content' => $more_infos,
				)
			);
		}
		return $contextual_help;
	}

	// called by each pointer
	public function insert_news_pointer( $case_news ) {
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer', false, array( 'jquery' ) );
			++$this->news_id;
			$this->news_case[ $this->news_id ] = $case_news;
	}

	// insert the pointers registered before
	public function print_the_pointers_js( ) {
		if ( 0 != $this->news_id ) {
			for ( $i = 1; $i <= $this->news_id; $i++ ) {
				$this->print_pointer_js( $i );
			}
		}
	}

	public function print_pointer_js( $indice ) {

		$args = $this->localize_admin_js( $this->news_case[ $indice ], $indice );
		if ( '' != $args['pointerText'] ) {
			// only if user don't read it before
		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function() {

		var strings<?php echo $indice; ?> = <?php echo json_encode( $args ); ?>;

	<?php /** Check that pointer support exists AND that text is not empty - inspired www.generalthreat.com */ ?>

	if(typeof(jQuery().pointer) != 'undefined' && strings<?php echo $indice; ?>.pointerText != '' ) {
		jQuery( strings<?php echo $indice; ?>.pointerDiv ).pointer({
			content : strings<?php echo $indice; ?>.pointerText,
			position: { edge: strings<?php echo $indice; ?>.pointerEdge,
				at: strings<?php echo $indice; ?>.pointerAt,
				my: strings<?php echo $indice; ?>.pointerMy,
				offset: strings<?php echo $indice; ?>.pointerOffset
			},
			close : function() {
				jQuery.post( ajaxurl, {
					pointer: strings<?php echo $indice; ?>.pointerDismiss,
					action: 'dismiss-wp-pointer'
				});
			}
		}).pointer( 'open' );
	}
});
		//]]>
		</script>
		<?php
		}
	}

	/**
	 * News pointer for tabs
	 *
	 * @since 1.4.0
	 *
	 */
	public function localize_admin_js( $case_news, $news_id ) {
		$about = __( 'Docs about xili-postinpost', 'xili-postinpost' );
		$pointer_edge = '';
		$pointer_at = '';
		$pointer_my = '';
		$pointer_offset = '';
		$news_val = '';
		switch ( $case_news ) {

			case 'xpp_new_version':
				/* translators: */
				$pointer_text = '<h3>' . esc_js( sprintf( __( '%s Post in post updated', 'xili-postinpost' ), '[©xili]' ) ) . '</h3>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'xili-postinpost was updated to version %s', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'This version %s add a selector to choose size of image in widget. Tested with WP 4.4.2 .', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'The previous version of %s improves query when done in front page with xili-language active.', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';

				$pointer_text .= '<p>' . esc_js( __( 'See submenu', 'xili-postinpost' ) . ' “<a href="options-general.php?page=xili_postinpost_page">' . __( 'Post in post Options Settings', 'xili-postinpost' ) . '</a>”' ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'Before to question dev.xiligroup support, do not forget to visit %s documentation', 'xili-postinpost' ), '<a href="https://wordpress.org/plugins/xili-postinpost/" title="' . $about . '" >wiki</a>' ) ) . '.</p>';

				$pointer_div = '#menu-settings';
				$pointer_dismiss = sanitize_key( 'xpp-new-version-' . str_replace( '.', '-', XILI_PIP_VERSION ) );
				$news_val = 'xpp-new-version-';
				$pointer_edge = 'left';
				$pointer_my = 'left';
				$pointer_at = 'right';
				break;

			case 'xpp_new_features_widget':
				/* translators: */
				$pointer_text = '<h3>' . esc_js( sprintf( __( '%s Post in post widget updated', 'xili-postinpost' ), '[©xili]' ) ) . '</h3>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'xili-postinpost was updated to version %s', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'This version %s is confirmed to be compatible with 4.9+', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'The previous version of %s adds the new params “more” for content part in widget [shortcode]', 'xili-postinpost' ), XILI_PIP_VERSION ) ) . '.</p>';

				$pointer_text .= '<p>' . esc_js( __( 'In this example - [condition=‘is_front_page’ query=‘cat=11’ more=‘please read more’] -, the widget will be displayed only if front_page and with a content and a more link “please read more”...', 'xili-postinpost' ) ) . '</p>';

				$pointer_text .= '<p>' . esc_js( __( 'See submenu', 'xili-postinpost' ) . ' “<a href="options-general.php?page=xili_postinpost_page">' . __( 'Post in post Options Settings', 'xili-postinpost' ) . '</a>”' ) . '.</p>';
				/* translators: */
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'Before to question dev.xiligroup support, do not forget to visit %s documentation', 'xili-postinpost' ), '<a href="https://wordpress.org/plugins/xili-postinpost/" title="' . $about . '" >wiki</a>' ) ) . '.</p>';

				$pointer_div = '#available-widgets';
				$pointer_dismiss = sanitize_key( 'xpp-new-version-widget-' . str_replace( '.', '-', XILI_PIP_VERSION ) );
				$news_val = 'xpp-new-version-widget-';
				$pointer_edge = 'left'; // arrow
				$pointer_my = 'left bottom'; // left of pointer box
				$pointer_at = 'right'; // right of div
				break;

			default: // nothing
				$pointer_text = '';
		}
		// $pointer_dismiss = sanitize_key( 'xpp-new-version-' . str_replace( '.', '-', XILI_PIP_VERSION ) );
		// inspired from www.generalthreat.com
		// Get the list of dismissed pointers for the user
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

		if ( in_array( $pointer_dismiss, $dismissed ) && sanitize_key( $news_val . str_replace( '.', '-', XILI_PIP_VERSION ) ) == $pointer_dismiss ) {
			$pointer_text = '';

			// Check whether our pointer has been dismissed two times
		} elseif ( in_array( $pointer_dismiss, $dismissed ) ) { /*&& in_array( $pointer_dismiss.'-1', $dismissed ) */
			$pointer_text = '';

		}

		return array(
			'pointerText' => html_entity_decode( (string) $pointer_text, ENT_QUOTES, 'UTF-8' ),
			'pointerDismiss' => $pointer_dismiss,
			'pointerDiv' => $pointer_div,
			'pointerEdge' => ( '' == $pointer_edge ) ? 'top' : $pointer_edge,
			'pointerAt' => ( '' == $pointer_at ) ? 'left top' : $pointer_at,
			'pointerMy' => ( '' == $pointer_my ) ? 'left top' : $pointer_my,
			'pointerOffset' => $pointer_offset, // seems to be unused in WP 3.8+
			'newsID' => $news_id,
		);
	}

} // end class
