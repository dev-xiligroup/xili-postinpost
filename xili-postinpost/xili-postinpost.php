<?php
/*
Plugin Name: xili-postinpost
Plugin URI: http://dev.xiligroup.com/xili-postinpost/
Description: xili-postinpost provides a triple tookit to insert post(s) everywhere in webpage. Template tag function, shortcode and widget are available. The post(s) are resulting of queries like those in WP loop but not interfere with main WP loop. Widget contains conditional syntax.
Author: dev.xiligroup.com - MS
Version: 1.6.0
Author URI: http://dev.xiligroup.com
Text Domain: xili_postinpost
License: GPLv2
*/

/*
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

define('XILI_PIP_VERSION', '1.6.0');

class xili_postinpost {

	var $xili_settings = array();

	var $news_id = 0; //for multi pointers
	var $news_case = array();
	var $assets_folder = 'https://ps.w.org/xili-postinpost/assets/';

	public function __construct() {
		$this->xili_postinpost();
	}

	function xili_postinpost () {
		register_activation_hook(__FILE__, array( &$this,'xili_postinpost_activate') );
		$this->xili_settings = get_option( 'xili_postinpost_settings' );
		if( empty( $this->xili_settings ) ) {
			$this->initial_settings ();
			update_option( 'xili_postinpost_settings', $this->xili_settings );
		} else {
			if ($this->xili_settings['version'] == '1.0') {
				$this->xili_settings['displayhtmltags'] = '';
				$this->xili_settings['version'] = '1.1';
				update_option('xili_postinpost_settings', $this->xili_settings);
			}
			if ($this->xili_settings['version'] == '1.1') {
				$this->xili_settings['displayeditlink'] = '';
				$this->xili_settings['version'] = '1.2';
				update_option('xili_postinpost_settings', $this->xili_settings);
			}
			if ( ! isset ( $this->xili_settings['version'] ) || $this->xili_settings['version'] != '1.2') { // repair
				$this->initial_settings ();
				update_option('xili_postinpost_settings', $this->xili_settings);
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

	function initial_settings () {
		$this->xili_settings = array(
			'widget'		=> 'enable',
			'displayperiod'		=> '',
			'displayhtmltags'   => '', // 0.9.5
			'displayeditlink'   => '', //1.1.2
			'version'           => '1.2'
		);
	}

	function xili_postinpost_activate () {
		$this->xili_settings = get_option( 'xili_postinpost_settings' );
		if( empty( $this->xili_settings ) ) {
			$this->initial_settings ();
			update_option( 'xili_postinpost_settings', $this->xili_settings );
		}
	}

	/**
 	 * register xili widgets
 	 *
 	 * @updated 0.9.4 for widget textdomain
 	 */
	function xili_widgets_init() {
		load_plugin_textdomain( 'xili_postinpost',false, 'xili-postinpost' ); // no sub folder
		if ( $this->xili_settings['widget'] == 'enable' )
				register_widget( 'xili_post_in_post_Widget' );
	}

	function get_WPLANG() {
		global $wp_version;
		if ( version_compare($wp_version, '4.0', '<') ) {
			if ( defined('WPLANG') )
				return WPLANG;
			else
				return '';
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
	function head_insertions() {
		echo "<!-- Website powered with xili-postinpost v. ".XILI_PIP_VERSION." WP plugin of dev.xiligroup.com -->\n";
	}


	/********************************** ADMIN UI ***********************************/


	function add_setting_pages() {
		$this->thehook = add_options_page(__('xili Post in Post plugin','xili_postinpost'), __('©xili Post in Post','xili_postinpost'), 'manage_options', 'xili_postinpost_page', array( &$this, 'xili_postinpost_settings' ));
		add_action( 'load-'.$this->thehook, array( &$this, 'on_load_page' ) );

		$this->insert_news_pointer ( 'xpp_new_version' ); // pointer in menu for updated version
		add_action( 'admin_print_footer_scripts', array(&$this, 'print_the_pointers_js') );
	}

	function on_load_page() {
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'postbox' );
			add_meta_box( 'xili_postinpost-sidebox-mail', __('Mail & Support','xili_postinpost'), array(&$this,'on_sidebox_mail_content'), $this->thehook , 'side', 'core');
	}

	function appearance_widget_pointer () {
		$screen = get_current_screen();
		if ( $screen->id == 'widgets' ) {
			$this->insert_news_pointer ( 'xpp_new_features_widget' ); // pointer in menu for updated version
			add_action( 'admin_print_footer_scripts', array(&$this, 'print_the_pointers_js') );
		}
	}

	function check_other_xili_plugins () {
		$list = array();
		if ( class_exists( 'xili_language' ) ) $list[] = 'xili-language' ;
		if ( class_exists( 'xili_tidy_tags' ) ) $list[] = 'xili-tidy-tags' ;
		if ( class_exists( 'xili_dictionary' ) ) $list[] = 'xili-dictionary' ;
		if ( class_exists( 'xilithemeselector' ) ) $list[] = 'xilitheme-select' ;
		if ( function_exists( 'insert_a_floom' ) ) $list[] = 'xili-floom-slideshow' ;
		//if ( class_exists( 'xili_postinpost' ) ) $list[] = 'xili-postinpost' ;
		return implode (', ',$list) ;
	}

	function on_sidebox_mail_content ( $data ) {
		extract( $data );
		global $wp_version ;
		if ( '' != $message ) { ?>
			<h4><?php _e('Note:','xili_postinpost') ?></h4>
			<p><strong><?php echo $message;?></strong></p>
		<?php } ?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php echo _e('Mail to dev.xiligroup', 'xili_postinpost'); ?></legend>
		<label for="ccmail"><?php _e('Cc:','xili_postinpost'); ?>
		<input class="widefat" id="ccmail" name="ccmail" type="text" value="<?php bloginfo ('admin_email') ; ?>" /></label><br /><br />
		<?php if ( false === strpos( get_bloginfo ('url'), 'local' ) ){ ?>
			<label for="urlenable">
				<input type="checkbox" id="urlenable" name="urlenable" value="enable" <?php if( isset($this->xili_settings['url']) && $this->xili_settings['url']=='enable') echo 'checked="checked"' ?> />&nbsp;<?php bloginfo ('url') ; ?>
			</label><br />
		<?php } else { ?>
			<input type="hidden" name="onlocalhost" id="onlocalhost" value="localhost" />
		<?php } ?>
		<label for="themeenable">
			<input type="checkbox" id="themeenable" name="themeenable" value="enable" <?php if( isset($this->xili_settings['theme']) && $this->xili_settings['theme']=='enable') echo 'checked="checked"' ?> />&nbsp;<?php printf(__("Theme name: %s", "xili_postinpost"),get_option ('stylesheet') ) ; ?>
		</label><br />
		<?php if (''!= $this->get_WPLANG() ) {?>
		<label for="wplangenable">
			<input type="checkbox" id="wplangenable" name="wplangenable" value="enable" <?php if( isset( $this->xili_settings['wplang'] ) && $this->xili_settings['wplang']=='enable') echo 'checked="checked"' ?> />&nbsp;<?php echo "WPLANG= " . $this->get_WPLANG() ; ?>
		</label><br />
		<?php } ?>
		<label for="versionenable">
			<input type="checkbox" id="versionenable" name="versionenable" value="enable" <?php if( isset( $this->xili_settings['version-wp'] ) && $this->xili_settings['version-wp']=='enable') echo 'checked="checked"' ?> />&nbsp;<?php echo "WP version: ".$wp_version ; ?>
		</label><br /><br />
		<?php $list = $this->check_other_xili_plugins();
		if (''!= $list ) {?>
		<label for="xiliplugenable">
			<input type="checkbox" id="xiliplugenable" name="xiliplugenable" value="enable" <?php if( isset ( $this->xili_settings['xiliplug'] ) && $this->xili_settings['xiliplug']=='enable') echo 'checked="checked"' ?> />&nbsp;<?php printf(__("Other xili plugins = %s", "xili_postinpost"), $list  ); ?>
		</label><br /><br />
		<?php } ?>
		<label for="webmestre"><?php _e('Type of webmaster:','xili_postinpost'); ?>
		<select name="webmestre" id="webmestre" style="width:100%;">
			<?php if ( !isset ( $this->xili_settings['webmestre-level'] ) ) $this->xili_settings['webmestre-level'] = '?' ; ?>
			<option value="?" <?php selected( $this->xili_settings['webmestre-level'], '?' ); ?>><?php _e('Define your experience as webmaster…','xili_postinpost'); ?></option>
			<option value="newbie" <?php selected( $this->xili_settings['webmestre-level'], "newbie" ); ?>><?php _e('Newbie in WP','xili_postinpost'); ?></option>
			<option value="wp-php" <?php selected( $this->xili_settings['webmestre-level'], "wp-php" ); ?>><?php _e('Good knowledge in WP and few in php','xili_postinpost'); ?></option>
			<option value="wp-php-dev" <?php selected( $this->xili_settings['webmestre-level'], "wp-php-dev" ); ?>><?php _e('Good knowledge in WP, CMS and good in php','xili_postinpost'); ?></option>
			<option value="wp-plugin-theme" <?php selected( $this->xili_settings['webmestre-level'], "wp-plugin-theme" ); ?>><?php _e('WP theme and /or plugin developper','xili_postinpost'); ?></option>
		</select></label>
		<br /><br />
		<label for="subject"><?php _e('Subject:','xili_postinpost'); ?>
		<input class="widefat" id="subject" name="subject" type="text" value="" /></label>
		<select name="thema" id="thema" style="width:100%;">
			<option value="" ><?php _e('Choose topic...','xili_postinpost'); ?></option>
			<option value="Message" ><?php _e('Message','xili_postinpost'); ?></option>
			<option value="Question" ><?php _e('Question','xili_postinpost'); ?></option>
			<option value="Encouragement" ><?php _e('Encouragement','xili_postinpost'); ?></option>
			<option value="Support need" ><?php _e('Support need','xili_postinpost'); ?></option>
		</select>
		<textarea class="widefat" rows="5" cols="20" id="mailcontent" name="mailcontent"><?php _e('Your message here…','xili_postinpost'); ?></textarea>
		</fieldset>
		<p>
		<?php _e('Before send the mail, check the infos to be sent and complete textarea. A copy (Cc:) is sent to webmaster email (modify it if needed).','xili_postinpost'); ?><br />
		<?php _e('Reply in less that 3 or 4 days…','xili_postinpost'); ?>
		</p>
		<div class='submit'>
		<input id='sendmail' name='sendmail' type='submit' tabindex='6' value="<?php _e('Send email','xili_postinpost') ?>" /></div>
		<?php //wp_nonce_field('xili-postinpost-sendmail'); ?>
		<div style="clear:both; height:1px"></div>
		<?php
	}

	function xili_postinpost_settings () {
		global $wp_version ;
		$msg = '';
		$message = '';
		if ( isset( $_POST['Submit'] ) ) {
			check_admin_referer( 'xili-postinpost-settings' );
			$this->xili_settings['widget'] = (isset ($_POST['widgetenable']) ) ? $_POST['widgetenable'] : "";
			$this->xili_settings['displayperiod'] = (isset ($_POST['displayperiod']) ) ? $_POST['displayperiod'] : "";
			$this->xili_settings['displayeditlink'] = (isset ($_POST['displayeditlink']) ) ? $_POST['displayeditlink'] : ""; // 1.1.2
			$this->xili_settings['displayhtmltags'] = (isset ($_POST['displayhtmltags']) ) ? $_POST['displayhtmltags'] : "";

			update_option('xili_postinpost_settings', $this->xili_settings);
			$msg = 1;
		}
		if ( isset($_POST['sendmail']) ) {
			check_admin_referer( 'xili-postinpost-settings' );
			$this->xili_settings['url'] = (isset ($_POST['urlenable']) ) ? $_POST['urlenable'] : "" ;
			$this->xili_settings['theme'] = (isset ($_POST['themeenable']) ) ? $_POST['themeenable'] : "";
			$this->xili_settings['wplang'] = (isset ($_POST['wplangenable']) ) ? $_POST['wplangenable'] : "";
			$this->xili_settings['version-wp'] = (isset ($_POST['versionenable']) ) ? $_POST['versionenable'] : "";
			$this->xili_settings['xiliplug'] = (isset ($_POST['xiliplugenable']) ) ? $_POST['xiliplugenable'] : "";
			$this->xili_settings['webmestre-level'] = $_POST['webmestre']; // 1.2.1
			update_option('xili_postinpost_settings', $this->xili_settings);
			$contextual_arr = array();
			if ( $this->xili_settings['url'] == 'enable' ) $contextual_arr[] = "url=[ ".get_bloginfo ('home')." ]" ;
			if ( isset($_POST['onlocalhost']) ) $contextual_arr[] = "url=local" ;
			if ( $this->xili_settings['theme'] == 'enable' ) $contextual_arr[] = "theme=[ ".get_option ('stylesheet')." ]" ;
			if ( $this->xili_settings['wplang'] == 'enable' ) $contextual_arr[] = "WPLANG=[ ". $this->get_WPLANG() ." ]" ;
			if ( $this->xili_settings['version-wp'] == 'enable' ) $contextual_arr[] = "WP version=[ ".$wp_version." ]" ;
			if ( $this->xili_settings['xiliplug'] == 'enable' ) $contextual_arr[] = "xiliplugins=[ ". $this->check_other_xili_plugins() ." ]" ;
			$contextual_arr[] = $this->xili_settings['webmestre-level'];

			$headers = 'From: Xili-PostinPost Page <' . get_bloginfo ('admin_email').'>' . "\r\n" ;
			if ( '' != $_POST['ccmail'] ) $headers .= 'Cc: <'.$_POST['ccmail'].'>' . "\r\n";
			$headers .= "\\";
			$message = "Message sent by: ".get_bloginfo ('admin_email')."\n\n" ;
			$message .= "Subject: ".$_POST['subject']."\n\n" ;
			$message .= "Topic: ".$_POST['thema']."\n\n" ;
			$message .= "Content: ".$_POST['mailcontent']."\n\n" ;
			$message .= "Checked contextual infos: ". implode ( ', ', $contextual_arr ) ."\n\n" ;
			$message .= "This message was sent by webmaster in xili-postinpost plugin settings page.\n\n";
			$message .= "\n\n";
			$result = wp_mail('contact@xiligroup.com', $_POST['thema'].' from xili-PostinPost plugin v.'.XILI_PIP_VERSION.' settings Page.' , $message, $headers );

			$msg = 2;
			$message = sprintf( __( 'Thanks for your email. A copy was sent to %s (%s)','xili_postinpost' ), $_POST['ccmail'], $result ) ;

		}
		$themessages[1] = __('Settings updated.','xili_postinpost');
		$themessages[2] = __('Email sent.','xili_postinpost');
		$data = array( 'message'=> $message );
		?>
		<div id="xili-postinpost-settings" class="wrap" style="min-width:750px">
			<?php screen_icon('options-general'); ?>
			<h2><?php _e('xili Post in Post','xili_postinpost') ?></h2>
			<?php if (0!= $msg ) { ?>
			<div id="message" class="updated fade"><p><?php echo $themessages[$msg]; ?></p></div>
			<?php } ?>
			<form name="add" id="add" method="post" action="options-general.php?page=xili_postinpost_page" >

				<?php wp_nonce_field('xili-postinpost-settings'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );
				global $wp_version;
				if ( version_compare($wp_version, '3.3.9', '<') ) {
					$poststuff_class = 'class="metabox-holder has-right-sidebar"';
					$postbody_class = "";
					$postleft_id = "";
					$postright_id = "side-info-column";
					$postleft_class = "";
					$postright_class = "inner-sidebar";
				} else { // 3.4
					$poststuff_class = "";
					$postbody_class = 'class="metabox-holder columns-2"';
					$postleft_id = 'id="postbox-container-2"';
					$postright_id = "postbox-container-1";
					$postleft_class = 'class="postbox-container"';
					$postright_class = "postbox-container";
				}
				?>
				<div id="poststuff" <?php echo $poststuff_class; ?>>
					<div id="post-body" <?php echo $postbody_class; ?> >

						<div id="<?php echo $postright_id; ?>" class="<?php echo $postright_class; ?>">
							<?php do_meta_boxes($this->thehook, 'side', $data); ?>
						</div>

						<div id="post-body-content" class="has-sidebar-content" style="min-width:360px">

							<h4><?php _e( 'xili-postinpost provides a triple tookit to insert post(s) everywhere in webpage. Template tag function, shortcode and widget are available.','xili_postinpost'); ?></h4>

							<p><?php _e( '<strong>Template tag</strong>: xi_postinpost( - array of params - )','xili_postinpost'); ?></p>



							<h5><?php _e( 'The default parameters in array before merging with yours, (from source)','xili_postinpost'); ?></h5>
							<p><code>
							<?php echo format_to_edit ( "\$defaults = array( 'query'=>'', 'showposts'=>1,
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
	'nopost' => __( 'no post', 'xili_postinpost' )
	);"); ?>
							</code><br /><br /><em>
							<?php _e( 'By default, xili_postinpost returns the latest post (linked title and content) !','xili_postinpost'); ?></em>
							<br /></p>
							<p><?php _e( "<strong>Shortcode</strong>: [xilipostinpost], as [xilipostinpost query=\"p=1\"]",'xili_postinpost'); ?><br /><br />
							<?php printf (__( "Or like: %s if xili-language active.",'xili_postinpost'), " [xilipostinpost showexcerpt=0 showtitle=1 titlelink=0 query=\"cat=14&showposts=2&lang=fr_fr\"]"); ?>
							</p>

							<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;">
								<label for="widgetenable">
								<?php _e("Insert Edit link:","xili_postinpost"); ?>
									<input type="checkbox" id="displayeditlink" name="displayeditlink" value="enable" <?php if( $this->xili_settings['displayeditlink']=='enable') echo 'checked="checked"' ?> />
								</label>&nbsp;&nbsp;
								<label for="widgetenable">
								<?php _e("Widget available:","xili_postinpost"); ?>
									<input type="checkbox" id="widgetenable" name="widgetenable" value="enable" <?php if( $this->xili_settings['widget']=='enable') echo 'checked="checked"' ?> />
								</label>&nbsp;&nbsp;
								<?php if( $this->xili_settings['widget']=='enable') { ?>
									(&nbsp;<label for="displayperiod">
									<?php _e("Display period available:","xili_postinpost"); ?>
										<input type="checkbox" id="displayperiod" name="displayperiod" value="enable" <?php if( $this->xili_settings['displayperiod']=='enable') echo 'checked="checked"' ?> />
									</label>&nbsp;&nbsp;&nbsp;
									<label for="displayhtmltags">
								<?php _e("HTML tags settings:","xili_postinpost"); ?>
									<input type="checkbox" id="displayhtmltags" name="displayhtmltags" value="enable" <?php if( $this->xili_settings['displayhtmltags']=='enable') echo 'checked="checked"' ?> />
								</label> )
								<?php } else { ?>
										<input type="hidden" id="displayperiod" name="displayperiod" value="<?php echo $this->xili_settings['displayperiod'] ?>"  />
										<input type="hidden" id="displayhtmltags" name="displayhtmltags" value="<?php echo $this->xili_settings['displayhtmltags'] ?>"  />
								<?php } ?>
							</fielset>
								<p class="submit"><input type="submit" name="Submit" id="Submit" value="<?php _e('Save Changes'); ?> &raquo;" /></p>

							<?php if( $this->xili_settings['widget']=='enable') { ?>
								<div class="widefat" style="margin:20px 0; padding:10px; width:95%;">
									<h4><?php _e( 'Syntax examples in widget setting UI','xili_postinpost'); ?></h4>
									<h5><?php _e( 'Here simple query','xili_postinpost'); ?></h5>
										<p><?php _e( 'A post display with title and excerpt','xili_postinpost'); ?></p>
										<img src="<?php
										// echo plugins_url( 'screenshot-2.png', __FILE__ );
										// https://ps.w.org/xili-postinpost/assets/screenshot-1.png?rev=907282
										echo $this->assets_folder.'screenshot-1.png'; ?>" alt=""/>
									<h5><?php _e( 'Here conditional query','xili_postinpost'); ?></h5>
										<p><?php _e( 'Three posts of category 3 displayed with title and link IF a page is displayed (with two widgets options set):','xili_postinpost'); ?></p>
										<img src="<?php echo $this->assets_folder.'screenshot-2.png'; ?>" alt=""/>
									<h5><?php _e( 'Another conditional query','xili_postinpost'); ?></h5>
										<p><?php _e( 'True and false conditions example: what happens and when ?','xili_postinpost'); ?></p>
										<img src="<?php echo $this->assets_folder.'screenshot-3.png'; ?>" alt=""/>
									<h5><?php _e( 'Another query with multilingual context','xili_postinpost'); ?></h5>
										<p><?php _e( 'A query combined with current language (requires xili-language active)','xili_postinpost'); ?></p>
										<img src="<?php echo $this->assets_folder.'screenshot-4.png'; ?>" alt=""/>
								</div>
							<?php } ?>
							<h4><a href="http://dev.xiligroup.com/xili-postinpost" title="Plugin page and docs" target="_blank" style="text-decoration:none" ><img style="vertical-align:middle" src="<?php echo plugins_url( 'xilipostinpost-logo-32.png', __FILE__ ) ; ?>" alt="xili-postinpost logo"/>  xili-postinpost</a> - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php _e('Author'); ?>" >xiligroup.com</a>™ - msc 2009-2015 - v. <?php echo XILI_PIP_VERSION; ?></h4>
						</div>

					</div>
				</div>
			</form>
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->thehook; ?>');

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
	function filter_plugin_actions( $links, $file ){
		static $this_plugin;
		if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
		if( $file == $this_plugin ){
			$settings_link = '<a href="options-general.php?page=xili_postinpost_page">' . __('Settings') . '</a>';
			$links = array_merge( array($settings_link), $links); // before other links
		}
		return $links;
	}

	/**
	 * Contextual help
	 *
	 * @since 1.7.0
	 */
	function add_help_text( $contextual_help, $screen_id, $screen ) {

	//echo $screen_id;

	if ('settings_page_xili_postinpost_page' == $screen->id ) {
		$to_remember =
		'<p>' . __('Things to remember to set xili-postinpost:','xili_postinpost') . '</p>' .
		'<ul>' .
		'<li>' . __('Verify that the theme can use widget.','xili_postinpost') . '</li>' .
		'<li>' . __('As developer, visit <a href="http://dev.xiligroup.com/?forum=other-plugins" target="_blank">dev.xiligroup forum</a> to discover powerful features and filters to customize your results.','xili_postinpost') . '</li>' .
		'<li>' . __('Visit dev.xiligroup website.','xili_postinpost') . '</li>' .


		'</ul>' ;

		$options =
		'<p>' . __('In xili-postinpost settings it possible to set general options:','xili_postinpost') . '</p>' .
		'<ul>' .
		'<li>' . __('Insert Edit link: add automatically the link after the post in the series. Can also be set as parameters in query (displayeditlink). The local parameter has priority.','xili_postinpost') . '</li>' .
		'<li>' . __('Post in post Widget available in Appearance screen','xili_postinpost') . '<ol>' .
		'<li>' . __('Display period available inside widget settings window.','xili_postinpost') . '</li>' .
		'<li>' . __('HTML tags settings inside widget window.','xili_postinpost') . '</li></ol></li>' .
		'</ul>' ;


		$more_infos =
			'<p><strong>' . __('For more information:') . '</strong></p>' .
			'<p>' . __('<a href="http://dev.xiligroup.com/xili-postinpost" target="_blank">Xili-PostinPost Plugin Documentation</a>','xili_postinpost') . '</p>' .
			'<p>' . __('<a href="http://wiki.xiligroup.org/" target="_blank">Xili Wiki Documentation</a>','xili_postinpost') . '</p>' .
		'<p>' . __('<a href="http://dev.xiligroup.com/?forum=other-plugins" target="_blank">Support Forums</a>','xili_postinpost') . '</p>' .
		'<p>' . __('<a href="http://codex.wordpress.org/" target="_blank">WordPress Documentation</a>','xili_postinpost') . '</p>' ;


		$screen->add_help_tab( array(
			'id' => 'to-remember',
			'title' => __('Things to remember','xili_postinpost'),
			'content' => $to_remember,
		));

		$screen->add_help_tab( array(
			'id' => 'options',
			'title' => __('Available options','xili_postinpost'),
			'content' => $options,
		));

		$screen->add_help_tab( array(
			'id' => 'more-infos',
			'title' => __('For more information', 'xili_postinpost'),
			'content' => $more_infos,
		));
		}
		return $contextual_help;
	}

	// called by each pointer
	function insert_news_pointer ( $case_news ) {
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer', false, array('jquery') );
			++$this->news_id;
			$this->news_case[$this->news_id] = $case_news;
	}

	// insert the pointers registered before
	function print_the_pointers_js ( ) {
		if ( $this->news_id != 0 ) {
			for ($i = 1; $i <= $this->news_id; $i++) {
				$this->print_pointer_js ( $i );
			}
		}
	}

	function print_pointer_js ( $indice ) {

		$args = $this->localize_admin_js( $this->news_case[$indice], $indice );
		if ( $args['pointerText'] != '' ) { // only if user don't read it before
		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function() {

		var strings<?php echo $indice; ?> = <?php echo json_encode( $args ); ?>;

	<?php /** Check that pointer support exists AND that text is not empty - inspired www.generalthreat.com */ ?>

	if(typeof(jQuery().pointer) != 'undefined' && strings<?php echo $indice; ?>.pointerText != '') {
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
		}).pointer('open');
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
	function localize_admin_js( $case_news, $news_id ) {
				$about = __('Docs about xili-postinpost', 'xili_postinpost');
				$pointer_edge = '';
				$pointer_at = '';
				$pointer_my = '';
				$pointer_Offset = '';
			switch ( $case_news ) {

				case 'xpp_new_version' :
					$pointer_text = '<h3>' . esc_js( sprintf( __( '%s Post in post updated', 'xili_postinpost'), '[©xili]') ) . '</h3>';
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'xili-postinpost was updated to version %s', 'xili_postinpost' ) , XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( sprintf(__( 'This version %s add a selector to choose size of image in widget. Tested with WP 4.2 Powell.', 'xili_postinpost'), XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( sprintf(__( 'The previous version of %s improves query when done in front page with xili-language active.', 'xili_postinpost'), XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( __( 'See submenu', 'xili_postinpost' ).' “<a href="options-general.php?page=xili_postinpost_page">'. __('Post in post Options Settings','xili_postinpost')."</a>”" ). '.</p>';
				$pointer_text .= '<p>' . esc_js( sprintf(__( 'Before to question dev.xiligroup support, do not forget to visit %s documentation', 'xili_postinpost' ), '<a href="http://wordpress.org/plugins/xili-postinpost/" title="'.$about.'" >wiki</a>' ) ). '.</p>';
					$pointer_dismiss = 'xpp-new-version-'.str_replace('.', '-', XILI_PIP_VERSION);

					$pointer_div = '#menu-settings';

					$pointer_edge = 'left';
					$pointer_my = 'left';
					$pointer_at = 'right';
				break;

			case 'xpp_new_features_widget' :
					$pointer_text = '<h3>' . esc_js( sprintf( __( '%s Post in post widget updated', 'xili_postinpost'), '[©xili]') ) . '</h3>';
				$pointer_text .= '<p>' . esc_js( sprintf( __( 'xili-postinpost was updated to version %s', 'xili_postinpost' ) , XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( sprintf(__( 'This version %s adds previewing feature for theme/customize (WP 3.9+)', 'xili_postinpost'), XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( sprintf(__( 'The previous version of %s adds the new params “more” for content part in widget [shortcode]', 'xili_postinpost'), XILI_PIP_VERSION) ). '.</p>';

				$pointer_text .= '<p>' . esc_js( __( 'In this example - [condition=‘is_front_page’ query=‘cat=11’ more=‘please read more’] -, the widget will be displayed only if front_page and with a content and a more link “please read more”...', 'xili_postinpost')). '</p>';


				$pointer_text .= '<p>' . esc_js( __( 'See submenu', 'xili_postinpost' ).' “<a href="options-general.php?page=xili_postinpost_page">'. __('Post in post Options Settings','xili_postinpost')."</a>”" ). '.</p>';
				$pointer_text .= '<p>' . esc_js( sprintf(__( 'Before to question dev.xiligroup support, do not forget to visit %s documentation', 'xili_postinpost' ), '<a href="http://wordpress.org/plugins/xili-postinpost/" title="'.$about.'" >wiki</a>' ) ). '.</p>';
					$pointer_dismiss = 'xpp-new-features-'.str_replace('.', '-', XILI_PIP_VERSION);

					$pointer_div = '#available-widgets';

					$pointer_edge = 'left'; // arrow
					$pointer_my = 'left bottom'; // left of pointer box
					$pointer_at = 'right'; // right of div
				break;

			default: // nothing
				$pointer_text = '';
		}

		// inspired from www.generalthreat.com
		// Get the list of dismissed pointers for the user
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		if ( in_array( $pointer_dismiss, $dismissed ) && $pointer_dismiss == 'xpp-new-version-'.str_replace('.', '-', XILI_PIP_VERSION) ) {
			$pointer_text = '';
		// Check whether our pointer has been dismissed two times
		} elseif ( in_array( $pointer_dismiss, $dismissed ) ) { /*&& in_array( $pointer_dismiss.'-1', $dismissed ) */
			$pointer_text = '';
		} //elseif ( in_array( $pointer_dismiss, $dismissed ) ) {
		// $pointer_dismiss = $pointer_dismiss.'-1';
		//}

		return array(
			'pointerText' => html_entity_decode( (string) $pointer_text, ENT_QUOTES, 'UTF-8'),
			'pointerDismiss' => $pointer_dismiss,
			'pointerDiv' => $pointer_div,
			'pointerEdge' => ( '' == $pointer_edge ) ? 'top' : $pointer_edge ,
			'pointerAt' => ( '' == $pointer_at ) ? 'left top' : $pointer_at ,
			'pointerMy' => ( '' == $pointer_my ) ? 'left top' : $pointer_my ,
			'pointerOffset' => $pointer_Offset, // seems to be unused in WP 3.8+
			'newsID' => $news_id
		);
	}

} // end class


/**
 *
 * shortcode call of function post in post
 *
 * @ updated 0.9.4
 *
 *
 */
function xi_postinpost_func ( $atts, $content = '' ) {

	$arr_result = shortcode_atts(array('query'=>'','showposts'=>1,
	'showtitle'=>1, 'titlelink'=>1, 'showexcerpt'=>1,'showcontent'=>0, // 1.2.2 add title link
	'beforeall'=>'<div class="xi_postinpost">', 'afterall'=>'</div>',
	'beforeeach'=>'', 'aftereach'=>'', // 0.9.4
	'beforetitle'=>'<h4 class="xi_postinpost_title">', 'aftertitle'=>'</h4>',
	'beforeexcerpt'=>'<object class="xi_postinpost_excerpt">', 'afterexcerpt'=>'</object>',
	'beforecontent'=>'<object class="xi_postinpost_content">', 'aftercontent'=>'</object>',
	'featuredimage' => 0, 'featuredimageaslink' => 0, 'featuredimagesize' => 'thumbnail',
	'read' => 'Read…',
	'more' => null,
	'from' => '','to' => '','expired' => '',
	'userfunction' => '',
	'lang'=>'', // 1.1
	'nopost' => __( 'no post', "xili_postinpost" ) // 1.1.2
	), $atts);
	$time_interval_ok = true ;
	$fromdate = $arr_result['from'];
	$todate = $arr_result['to'];
	if ( $fromdate != "" or $todate != "" ) {
		if ( strpos($fromdate,'****') === false && strpos($todate,'****') === false ) {
			$time = current_time('timestamp'); // wp 3.0
			if ( $fromdate != "" && $time < strtotime ( $fromdate ) ) {
				$time_interval_ok = false ;
			} elseif ( $todate != "" && $time > strtotime ( $todate ) ) {
				$time_interval_ok = false ;
			}
		} else {
			$time_interval_ok = apply_filters ( 'xili_post_in_post_crontab', $fromdate, $todate );
		}
	}
	if ( $time_interval_ok ) {// since 0.9.2
		if ( class_exists('xili_language') ) { //error_log('++++++ ' . the_curlang());
			if ( $arr_result['lang'] == 'cur' ) $arr_result['query'] .= '&lang='.the_curlang(); //1.1.0
		}
		//error_log('+++ shortcode +++ ' . serialize ( $arr_result ) );
		if ( $content == '' ) {
			return xi_postinpost($arr_result);
		} else {
			return str_replace ( 'xilipostinpostcontent', xi_postinpost($arr_result), $content );
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
	if ( is_array( $args ) )
		$r = &$args;
	else
		parse_str( $args, $r );

	$defaults = array( 'query'=>'','showposts'=>1,
	'showtitle'=>1,'titlelink'=>1,'showexcerpt'=>0,'showcontent'=>1,
	'beforeall'=>'<div class="xi_postinpost">', 'afterall'=>'</div>',
	'beforeeach'=>'', 'aftereach'=>'', // 0.9.4
	'beforetitle'=>'<h3 class="xi_postinpost_title">', 'aftertitle'=>'</h3>',
	'beforeexcerpt'=>'', 'afterexcerpt'=>'',
	'beforecontent'=>'', 'aftercontent'=>'',
	'featuredimage' => 0, 'featuredimageaslink' => 0, 'featuredimagesize' => 'thumbnail',
	'read' => 'Read…',
	'more' => null,
	'from' => '','to' => '','expired' => '',
	'userfunction' => '',
	'nopost' => __( 'no post', "xili_postinpost" ), // 1.1.2
	'is_preview' => false // WP 3.9
	);

	$r = array_merge( $defaults, $r );
	extract($r);
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

	if ( class_exists('xili_language') ) {
		global $xili_language;
		$tmp_permalink = $xili_language->lang_perma;
		$tmp_show_page_on_front = $xili_language->show_page_on_front;
		$xili_language->lang_perma = false;
		$xili_language->show_page_on_front = false;
	}


	if ( !is_array( $query ) && '' != $query ) { /* $query is here a string */
		$query = html_entity_decode( $query );
		if ( $showposts > 0 && strstr( $query, 'showposts' ) === false && substr ( $query, 0, 1 ) != '_' ) $query .= "&showposts=" . $showposts;
	}

	if ( !is_array( $args ) ) $args = array( $args );
	$query_key = 'post_in_post' . md5( serialize( $query ) );	//

	if ( $is_preview ) {
		$result = false; // WP 3.9+ && customize in theme
	} else {
		$result = wp_cache_get( $query_key, 'postinpost' );
	}

	if ( false !== $result ) { //echo 'cache used in same page because query called more than one time';
		$myposts = $result;
	} else {
		$myposts = new WP_Query ( apply_filters ( 'xili_postinpost_query', $query ) );	// 1.2	to use complex presetted queries
	}

	if ( $myposts->have_posts() ) :
		if ( '' != $userfunction && function_exists( $userfunction ) ) {
			// since 1.0.0 with $posts and params
			// function my_pip_loop ( $params, $the_posts ) { loop inside your function }
			$postinpostresult = call_user_func_array ( $userfunction, array ( $r, $myposts ) );
		} else {
			$postinpostresult .= $beforeall;
			while ( $myposts->have_posts() ) :
				$myposts->the_post();

				// add class if LI tag is used - class because multiple instantiations - 0.9.5
				$startchars = substr( $beforeeach, 0, 3 );
				$startcharsclass = substr( $beforeeach, 0, 11 );
				if ( strtolower( $startchars ) == '<li' ) {
					if ( strtolower( $startcharsclass ) == '<li class="' ) { // as set in widget
						$beforeeach_id = $startcharsclass.'xpipid-'. $post->ID .' '.substr( $beforeeach, 11 ) ;
					} else {
						$beforeeach_id = $beforeeach ;
					}
				} else {
					$beforeeach_id = $beforeeach ;
				}

				$postinpostresult .= $beforeeach_id;

				if ($showtitle) {
					if (!$titlelink) :
						$postinpostresult .= the_title($beforetitle,$aftertitle, false);
					else:
						$postinpostresult .= $beforetitle.'<a href="'.get_permalink($post->ID).'" title="'.__( $read, the_text_domain() ).'">'.the_title('','', false).'</a>'.$aftertitle;
					endif;
				}
				if ( $featuredimage and null != get_post_thumbnail_id( $post->ID )) { //
					if ( $featuredimageaslink ) { // fixed
						$postinpostresult .= '<a href="'.get_permalink($post->ID).'" title="'.__( $read, the_text_domain() ).'">'.get_the_post_thumbnail( $post->ID, $featuredimagesize ).'</a>';
					} else {
						$postinpostresult .= get_the_post_thumbnail( $post->ID, $featuredimagesize );
					}

				}

			if ( $showexcerpt )
				$postinpostresult .= $beforeexcerpt.apply_filters('the_excerpt', get_the_excerpt()).$afterexcerpt;
			if ( $showcontent )
				$postinpostresult .= $beforecontent.apply_filters('the_content',get_the_content( $r['more'] )).$aftercontent;
		// $r['more'] => $more is a WP global variable !!!
				// 1.1.2 add edit link

				$postinpostresult = xili_post_in_post_insert_edit_link ( $postinpostresult, $r, $post );

				$postinpostresult .= $aftereach;

			endwhile;
			$postinpostresult .= $afterall;
		}
	else :
		$postinpostresult = apply_filters ( 'xili_postinpost_nopost', $nopost, $r ) ; // $nopost filtered 1.4.2 ;	// 1.1.2 - $nopost
	endif;
	/*restore current loop */

	if ( class_exists('xili_language') ) {
		global $xili_language;
		$xili_language->lang_perma = $tmp_permalink;
		$xili_language->show_page_on_front = $tmp_show_page_on_front ;
	}

	$wp_query = null ; $wp_query = $tmp_query;
	$post = null ; $post = $tmp_post;
	$posts = null ; $posts = $tmp_posts;
	/* pagination 0.9.6 */
	$page = $tmp_page ;
	$numpages = $tmp_numpages ;
	$multipage = $tmp_multipage ;
	$more = $tmp_more;
	$pagenow = $tmp_pagenow ;

	wp_cache_set($query_key, $myposts, 'postinpost') ; // only the query is cached - not the format tags

	return $postinpostresult;
}
/*---------- end function post in post -----------------*/

/**
 * Insert edit link
 *
 */

function xili_post_in_post_insert_edit_link ( $postinpostresult, $r, $post ) {

	if ( !isset( $r['displayeditlink'] ) ) { // local value is check first
		global $xili_postinpost;
		$addlink = ( $xili_postinpost->xili_settings['displayeditlink'] == 'enable' ) ? true : false ;
		$displayeditlink = ( $xili_postinpost->xili_settings['displayeditlink'] == 'enable' ) ? 1 : 0 ;
	} else { //error_log ('toto='.$r['displayeditlink']);
		$addlink = ( $r['displayeditlink'] != '0' ) ? true : false ;
		$displayeditlink = $r['displayeditlink'];
	}

	if ( $addlink ) {
		$link = ( is_numeric ( $displayeditlink ) ) ? __('Edit This', the_text_domain() ) : $displayeditlink ;

		$post_type_obj = get_post_type_object( $post->post_type );

		$postinpostresult .= '<span class="xpp-editlink"><a title="' . esc_attr( $post_type_obj->labels->edit_item ) . '" href="'.get_edit_post_link( $post->ID ).'" >' . $link . '</a></span>';
	}

	return $postinpostresult;
}


function the_text_domain() {
	if ( class_exists( 'xili_language' ) ){

		return the_theme_domain(); // depending of theme .mo (multilingual site)

	} else {

		return 'xili_postinpost';	// depending of plugin .mo
	}
}

/**
 * Post in post widget
 *
 * @since 20101007
 * @updated 20101030 - 20101031 (lang)
 *
 */

class xili_post_in_post_Widget extends WP_Widget {

	function xili_post_in_post_Widget() {
		$widget_ops = array('classname' => 'xili_post_in_post_Widget', 'description' => __('Display post in widget, by ©xiligroup v.','xili_postinpost').'&nbsp;'.XILI_PIP_VERSION);
		$control_ops = array('width' => 400, 'height' => 350);
		parent::__construct('xilipostin', '[©xili] ' .__('Post in post','xili_postinpost'), $widget_ops, $control_ops);
		add_filter ( 'xili_post_in_post_crontab', 'the_xili_post_in_post_crontab', 10, 2 );
	}

	function widget( $args, $instance ) {
		global $post ;
		extract($args);
		$time_interval_ok = true ;
		/* time interval results */
		$fromdate = ( isset( $instance['fromdate'] ) ) ? $instance['fromdate']: '';
		$todate = ( isset( $instance['todate'] ) ) ? $instance['todate']:'';

		if ( $fromdate != "" or $todate != "" ) {
			if ( strpos($fromdate,'****') === false && strpos($todate,'****') === false ) {

				$time = current_time('timestamp'); // wp 3.0
				if ( $fromdate != "" && $time < strtotime ( $fromdate ) ) {
					$time_interval_ok = false ;
				} elseif ( $todate != "" && $time > strtotime ( $todate ) ) {
					$time_interval_ok = false ;
				}
			} else {
				$time_interval_ok = apply_filters ( 'xili_post_in_post_crontab', $fromdate, $todate );
			}
		}

		if ( $time_interval_ok ) {

			$title = apply_filters( 'widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);

			/**
			 * filter added to remove unwanted filter like in Karma theme :-(
			 * put add action in your child functions.php
			 */
			//remove_filter('widget_text', 'truethemes_formatter',99);

			do_action ('xpp_before_widget_text_filter', $instance );
			$text = apply_filters( 'widget_text', $instance['text'], $instance );

			//add_filter('widget_text', 'truethemes_formatter',99);
			do_action ('xpp_after_widget_text_filter', $instance );

			$pos = strpos($text, '[');
			if ($pos === false) { // classical query
					$query = $text ;
					$condition_ok = true;
			} else {

				$default_params = array( 'more'=> 'toto', 'query' =>'', 'condition'=>'', 'param'=>'', 'lang'=>'', 'beforeall'=> null, 'afterall'=> null, 'postmetakey' => '', 'postmetafrom' => ''); // null to keep defaults main function of xi_postinpost
				// detect if condition is false what to do
				$pos = strpos($text, ']:[');
				if ($pos === false) { // only one
					$flow = str_replace ('[','',str_replace(']','',$text)); // use shortcode syntax
					$noflow = "";
				} else { // there is a what to do when condition is false
					$thetwo = explode(']:[', $text);
					$flow = str_replace ('[', '' , $thetwo[0]);
					$noflow = str_replace(']', '', $thetwo[1]);
				}

					$flow_atts = shortcode_parse_atts($flow); //error_log ( 'widget atts = ' . serialize( $flow_atts ));

					$arr_result = shortcode_atts($default_params, $flow_atts);
				$thecondition = trim( $arr_result['condition'], '!');

					if ( '' != $arr_result['condition'] && function_exists( $thecondition ) ) {
						$not = ( $thecondition == $arr_result['condition'] ) ? false : true ;
						$arr_params = ('' != $arr_result['param']) ? array(explode( ',', $arr_result['param'] )) : array();
						$condition_ok = ($not) ? !call_user_func_array ($thecondition, $arr_params) : call_user_func_array ($thecondition, $arr_params);

						if ( !$condition_ok && ""!= $noflow ) { // check no condition
							$flow_atts = shortcode_parse_atts($noflow); // echo 'no='.$noflow.')';
						$arr_result = shortcode_atts($default_params, $flow_atts); // new keys of second block
						$arr_params = ('' != $arr_result['param']) ? array(explode( ',', $arr_result['param'] )) : array();
						$thecondition = trim( $arr_result['condition'], '!' );
						$not = ( $thecondition == $arr_result['condition'] ) ? false : true ;
						if ( '' != $arr_result['condition'] && function_exists( $thecondition ) ) {
								$condition_ok = ($not) ? !call_user_func_array ( $thecondition, $arr_params ) : call_user_func_array ( $thecondition, $arr_params ); // if false nothing displayed
						} else {
							$condition_ok = true; // display results of $query or postmeta
						}
						}
					} else {
					$condition_ok = true;
				}
				$query = $arr_result['query'];
				if ( '' != $arr_result['postmetakey'] ) {

						$fromID = ('' != $arr_result['postmetafrom']) ? $arr_result['postmetafrom'] : ((is_singular()) ? get_the_ID() : 0 );
						if ( 0 != $fromID ) {
							$theID = get_post_meta($fromID, $arr_result['postmetakey'], true);
							if ('' != $theID) {
								$type = get_post_type( $theID ) ;
								$query = ($type == 'page') ? 'page_id='.$theID : 'p='.$theID ;
								// $condition_ok defined above
							} else {
								$condition_ok = false;
							}
						} else {
							$condition_ok = false ;
						}
				}
			}

			if ( ! $number = (int) $instance['showposts'] )
				$number = 1;
			else if ( $number < 1 )
				$number = 1;

			if ( $condition_ok ) {
				echo $before_widget;
				if ( !empty( $title ) ) { echo $before_title . $title . $after_title; } ?>
					<div class="textwidget"><?php
					if ( class_exists('xili_language') ) {
						if ( isset ( $arr_result['lang'] ) && $arr_result['lang'] == 'cur' ) {
							$query .= '&lang='.the_curlang(); // 1.2.2
							unset ( $arr_result['lang'] );
						}
					}


					$theargs = array ( 'query'=>$query,
					'showtitle' => $instance['showtitle'], 'titlelink'=> $instance['titlelink'],
					'showexcerpt' => $instance['excerpt'], 'showcontent' => $instance['content'],
					'showposts'=> $number,
					'featuredimage' => $instance['featuredimage'],
					'featuredimageaslink' => $instance['featuredimageaslink']
								) ;
					if ( isset($instance['beforeall']) ) $theargs ['beforeall'] = $instance['beforeall'];
					if ( isset($instance['afterall']) ) $theargs ['afterall'] = $instance['afterall'];
					if ( isset($instance['beforetitle']) ) $theargs ['beforetitle'] = $instance['beforetitle'];
					if ( isset($instance['aftertitle']) ) $theargs ['aftertitle'] = $instance['aftertitle'];
					if ( isset($instance['featuredimagesize']) ) $theargs ['featuredimagesize'] = $instance['featuredimagesize']; // 1.6

					if ( isset($instance['liclass']) && $instance['liclass'] !="" ) {
						$theargs ['beforeeach'] = '<li class="'.$instance['liclass'].'">'; // 0.9.5
						$theargs ['aftereach'] = '</li>';
					}
					if ( isset($instance['userfunction']) ) $theargs ['userfunction'] = $instance['userfunction']; // 1.0.0
					if ( isset($default_params) ) {
						/* merge */
						$the_arr_result = array_filter($arr_result, array(&$this, 'delete_null')); // delete null keys
						$theargs = array_merge( $the_arr_result, $theargs );
					}

					if ( method_exists($this,'is_preview') ) { // 3.9 and customize
						if ( $this->is_preview() ) {
							$theargs['is_preview'] = true;
						} else {
							$theargs['is_preview'] = false;
						}
					} else {
						$theargs['is_preview'] = false;
					}

					echo xi_postinpost( $theargs );

					?></div>
				<?php
				echo $after_widget;
			}
		}
	}
	// delete null keys
	function delete_null ($var) {
		if (null != $var) return $var;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		if ( current_user_can('unfiltered_html') )
			$instance['text'] = $new_instance['text'];
		else
			$instance['text'] = stripslashes( wp_filter_post_kses( addslashes($new_instance['text']) ) ); // wp_filter_post_kses() expects slashed

		$instance['filter'] = isset($new_instance['filter']);
		$instance['showtitle'] = isset($new_instance['showtitle']);
		$instance['titlelink'] = isset($new_instance['titlelink']);
		$instance['content'] = isset($new_instance['content']);
		$instance['excerpt'] = isset($new_instance['excerpt']);
		$instance['featuredimage'] = isset($new_instance['featuredimage']);
		$instance['featuredimagesize'] = strip_tags($new_instance['featuredimagesize']); // 1.6
		$instance['featuredimageaslink'] = isset($new_instance['featuredimageaslink']);
		$instance['showposts'] = (int) $new_instance['showposts'];
		$instance['beforeall'] = $new_instance['beforeall'];
		$instance['afterall'] = $new_instance['afterall'];
		$instance['beforetitle'] = $new_instance['beforetitle'];
		$instance['aftertitle'] = $new_instance['aftertitle'];
		$instance['liclass'] = strip_tags($new_instance['liclass']);
		$instance['fromdate'] = strip_tags($new_instance['fromdate']);
		$instance['todate'] = strip_tags($new_instance['todate']);
		$instance['userfunction'] = isset($new_instance['userfunction']) ? strip_tags($new_instance['userfunction']) : "" ; // 1.0.0

		return $instance;
	}

	function form( $instance ) {

		global $xili_postinpost;


		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'text' => '', 'featuredimagesize' => "thumbnail" ) ); // 1.6
		$title = strip_tags($instance['title']);
		$beforeall = isset($instance['beforeall']) ? format_to_edit($instance['beforeall']) : format_to_edit('<div class="xi_postinpost">');
		$afterall = isset($instance['afterall']) ? format_to_edit($instance['afterall']) : format_to_edit('</div>');
		$beforetitle = isset($instance['beforetitle']) ? format_to_edit($instance['beforetitle']) : format_to_edit('<h4 class="xi_postinpost_title">');
		$aftertitle = isset($instance['aftertitle']) ? format_to_edit($instance['aftertitle']) : format_to_edit('</h4>') ;
		$liclass = isset($instance['liclass']) ? strip_tags($instance['liclass']) : ""; // LI CLASS
		$number = isset($instance['showposts']) ? absint($instance['showposts']) : 1;
		$text = format_to_edit($instance['text']);
		$fromdate = isset($instance['fromdate']) ? strip_tags($instance['fromdate']) : "";
		$todate = isset($instance['todate']) ? strip_tags($instance['todate']) : "" ;
		$userfunction = isset($instance['userfunction']) ? strip_tags($instance['userfunction']) : "" ; // 1.0.0

?>

		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<p><input id="<?php echo $this->get_field_id('showtitle'); ?>" name="<?php echo $this->get_field_name('showtitle'); ?>" type="checkbox" <?php checked(isset($instance['showtitle']) ? $instance['showtitle'] : 1); ?> />&nbsp;<label for="<?php echo $this->get_field_id('showtitle'); ?>"><?php _e('Show post title','xili_postinpost'); ?></label>&nbsp;&nbsp;<input id="<?php echo $this->get_field_id('titlelink'); ?>" name="<?php echo $this->get_field_name('titlelink'); ?>" type="checkbox" <?php checked(isset($instance['titlelink']) ? $instance['titlelink'] : 1); ?> />&nbsp;<label for="<?php echo $this->get_field_id('titlelink'); ?>"><?php _e('Title as link','xili_postinpost'); ?></label></p>
		<p><?php _e('Show:','xili_postinpost'); ?>
			<input id="<?php echo $this->get_field_id('content'); ?>" name="<?php echo $this->get_field_name('content'); ?>" type="checkbox" <?php checked(isset($instance['content']) ? $instance['content'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('content'); ?>"><?php _e('Content','xili_postinpost'); ?></label>&nbsp;&nbsp;<input id="<?php echo $this->get_field_id('excerpt'); ?>" name="<?php echo $this->get_field_name('excerpt'); ?>" type="checkbox" <?php checked(isset($instance['excerpt']) ? $instance['excerpt'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('excerpt'); ?>"><?php _e('Excerpt','xili_postinpost'); ?></label>
			<br /><label for="<?php echo $this->get_field_id('featuredimagesize'); ?>"><?php _e('Size of Featured image:','xili_postinpost'); ?></label>&nbsp;<select id="<?php echo $this->get_field_id('featuredimagesize'); ?>" name="<?php echo $this->get_field_name('featuredimagesize'); ?>">
			<?php
			echo '<option value="thumbnail" '.selected( $instance['featuredimagesize'], "thumbnail", false).'>'.__('thumbnail','xili_postinpost').'</option>';
			echo '<option value="medium" '.selected( $instance['featuredimagesize'], "medium", false).'>'.__('medium','xili_postinpost').'</option>';
			echo '<option value="large" '.selected( $instance['featuredimagesize'], "large", false).'>'.__('large','xili_postinpost').'</option>';
			?>
			</select>
			<br /><input id="<?php echo $this->get_field_id('featuredimage'); ?>" name="<?php echo $this->get_field_name('featuredimage'); ?>" type="checkbox" <?php checked(isset($instance['featuredimage']) ? $instance['featuredimage'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('featuredimage'); ?>"><?php _e('Featured image','xili_postinpost'); ?></label>&nbsp;
			<input id="<?php echo $this->get_field_id('featuredimageaslink'); ?>" name="<?php echo $this->get_field_name('featuredimageaslink'); ?>" type="checkbox" <?php checked(isset($instance['featuredimageaslink']) ? $instance['featuredimageaslink'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('featuredimageaslink'); ?>"><?php _e('Image as link','xili_postinpost'); ?></label>
		</p>
		<p><label for="<?php echo $this->get_field_id('showposts'); ?>"><?php _e('Number of posts to show:','xili_postinpost'); ?></label>
		<input id="<?php echo $this->get_field_id('showposts'); ?>" name="<?php echo $this->get_field_name('showposts'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
		<small><?php _e('Params and conditions:','xili_postinpost'); ?></small>
		<textarea class="widefat" rows="5" cols="20" id="<?php echo $this->get_field_id('text'); ?>" name="<?php echo $this->get_field_name('text'); ?>"><?php echo $text; ?></textarea>

		<?php if ( $xili_postinpost->xili_settings['displayhtmltags'] ) { ?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php _e('HTML settings', 'xili_postinpost'); ?></legend>
		<p><input id="<?php echo $this->get_field_id('beforeall'); ?>" name="<?php echo $this->get_field_name('beforeall'); ?>" type="text" value="<?php echo $beforeall; ?>" size="40" /><br/><label for="<?php echo $this->get_field_id('afterall'); ?>"><?php _e('Block tags','xili_postinpost'); ?></label><input id="<?php echo $this->get_field_id('afterall'); ?>" name="<?php echo $this->get_field_name('afterall'); ?>" type="text" value="<?php echo $afterall; ?>" size="15" />
		</p>
		<p><input id="<?php echo $this->get_field_id('beforetitle'); ?>" name="<?php echo $this->get_field_name('beforetitle'); ?>" type="text" value="<?php echo $beforetitle; ?>" size="40" /><br/><label for="<?php echo $this->get_field_id('aftertitle'); ?>"><?php _e('Title tags','xili_postinpost'); ?></label><input id="<?php echo $this->get_field_id('aftertitle'); ?>" name="<?php echo $this->get_field_name('aftertitle'); ?>" type="text" value="<?php echo $aftertitle; ?>" size="5" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('liclass'); ?>"><?php _e('LI class:','xili_postinpost'); ?></label><input id="<?php echo $this->get_field_id('liclass'); ?>" name="<?php echo $this->get_field_name('liclass'); ?>" type="text" value="<?php echo $liclass; ?>" size="20" /></p>
		<p><small><?php _e("Note: if LI class is empty no LI are generated around each post, if set, don't forget to set above tag's block of results to UL or OL !",'xili_postinpost'); ?></small></p>
		<p><label for="<?php echo $this->get_field_id('userfunction'); ?>"><?php _e('Function (must exists)','xili_postinpost'); ?></label><input id="<?php echo $this->get_field_id('userfunction'); ?>" name="<?php echo $this->get_field_name('userfunction'); ?>" type="text" value="<?php echo $userfunction; ?>" size="40" />
		</p>

		</fieldset>
		<?php
		} else { ?>
		<input type="hidden" id="<?php echo $this->get_field_id('beforeall'); ?>" name="<?php echo $this->get_field_name('beforeall'); ?>" value="<?php echo $beforeall; ?>"  />
		<input type="hidden" id="<?php echo $this->get_field_id('afterall'); ?>" name="<?php echo $this->get_field_name('afterall'); ?>" value="<?php echo $afterall; ?>" />
		<input type="hidden" id="<?php echo $this->get_field_id('beforetitle'); ?>" name="<?php echo $this->get_field_name('beforetitle'); ?>" value="<?php echo $beforetitle; ?>"  />
		<input type="hidden" id="<?php echo $this->get_field_id('aftertitle'); ?>" name="<?php echo $this->get_field_name('aftertitle'); ?>" value="<?php echo $aftertitle; ?>" />
		<input type="hidden" id="<?php echo $this->get_field_id('liclass'); ?>" name="<?php echo $this->get_field_name('liclass'); ?>" value="<?php echo $liclass; ?>"  />

		<?php }

		if ( $xili_postinpost->xili_settings['displayperiod'] ) { ?>
		<fieldset style="margin:2px; padding:12px 6px; border:1px solid #ccc;"><legend><?php _e('Dates of display period', 'xili_postinpost'); ?></legend>
		<small><?php _e('Leave inputs empty for permanent display.', 'xili_postinpost'); ?></small>
			<p><label for="<?php echo $this->get_field_id('fromdate'); ?>"><?php _e('From:','xili_postinpost'); ?></label>
				<input id="<?php echo $this->get_field_id('fromdate'); ?>" name="<?php echo $this->get_field_name('fromdate'); ?>" type="text" value="<?php echo $fromdate; ?>" size="20" />&nbsp;<?php _e('(aaaa-mm-dd hh:mm)','xili_postinpost'); ?></p>
			<p><label for="<?php echo $this->get_field_id('todate'); ?>"><?php _e('To:','xili_postinpost'); ?></label>
				<input id="<?php echo $this->get_field_id('todate'); ?>" name="<?php echo $this->get_field_name('todate'); ?>" type="text" value="<?php echo $todate; ?>" size="20" />&nbsp;<?php _e('(aaaa-mm-dd hh:mm)','xili_postinpost'); ?></p>
		</fieldset>
		<?php } else { ?>
		<input type="hidden" id="<?php echo $this->get_field_id('todate'); ?>" name="<?php echo $this->get_field_name('todate'); ?>" value="<?php echo $todate; ?>"  />
		<input type="hidden" id="<?php echo $this->get_field_id('fromdate'); ?>" name="<?php echo $this->get_field_name('fromdate'); ?>" value="<?php echo $fromdate; ?>" />
		<?php } ?>

<small>© dev.xiligroup.com <?php echo 'v. '.XILI_PIP_VERSION ; ?></small>

<?php
	}
} // end widget

/**
 * first filter for time only - used by add_filter ( 'xili_post_in_post_crontab', … )
 *
 * @since 0.9.2
 *
 */
function the_xili_post_in_post_crontab ( $fromdate, $todate ) {
	$time_interval_ok = true ;

	if ( $fromdate != "" or $todate != "" ) {
		if ( $fromdate != "" ) $fromdate = str_replace ("****-**-** ", "2000-01-01 ", $fromdate);
		if ( $todate != "" ) $todate = str_replace ("****-**-** ", "2000-01-01 ", $todate);

		$time = strtotime ( "2000-01-01 ".date("H:i",current_time('timestamp')) ) ; //echo '---'.date("H:i",current_time('timestamp'));
		if ( $fromdate != "" && $time < strtotime ( $fromdate ) ) {
			$time_interval_ok = false ;
		} elseif ( $todate != "" && $time > strtotime ( $todate ) ) {
			$time_interval_ok = false ;
		}
	}
	return $time_interval_ok ;
}

/**
 * instantiation of xili_postinpost class
 *
 * @since 0.8.0
 *
 */
$xili_postinpost = new xili_postinpost();


?>