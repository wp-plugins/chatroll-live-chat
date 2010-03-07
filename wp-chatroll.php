<?php
/**
 * Plugin Name: Chatroll Live Chat
 * Plugin URI: http://chatroll.com
 * Description: Add <a href="http://chatroll.com">Chatroll</a> live chat to your WordPress sidebar, posts, and pages. Adds a widget to put on your sidebar, and a 'chatroll' shortcode to use in posts and pages. Includes Single Sign-On (SSO) support for integrating WordPress login.
 * Version: 1.2.4
 * Author: Chatroll
 * Author URI: http://chatroll.com
 * Text Domain: wp-chatroll
 */

/*  
	Copyright 2010-current  Chatroll / Jonathan McGee  (email : support@chatroll.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
require_once('chatroll.php');

class wpChatrollException extends Exception {}

/**
 * WP_Chatroll is the class that handles the main widget.
 */
class WP_Chatroll extends WP_Widget {
	public function WP_Chatroll () {
		$widget_ops = array(
			'classname' => 'widget_chatroll',
			'description' => __( 'Chatroll live chat', 'wp-chatroll' )
		);
		$control_ops = array(
			'width' => 450,
			'height' => 350,
			'id_base' => 'chatroll'
		);
		$name = __( 'Chatroll', 'wp-chatroll' );

		$this->WP_Widget('chatroll', $name, $widget_ops, $control_ops);
	}

	private function _getInstanceSettings ( $instance ) {
		$defaultArgs = array(	'title'		=> '',
					'errmsg'	=> '',
					'shortcode'	=> '',
					'showlink'	=> '1',
		);

		return wp_parse_args( $instance, $defaultArgs );
	}

	public function form( $instance ) {
		$instance = $this->_getInstanceSettings( $instance );
		$wpChatroll = wpChatroll::getInstance();
?>

			<p>
				<label for="<?php echo $this->get_field_id('shortcode'); ?>"><?php _e('<b>Shortcode</b> (<a href="http://chatroll.com/create" target="_blank">Click here</a> to create a Chatroll and get your Shortcode. Choose the "WordPress Self-Hosted" instructions):', 'wp-chatroll'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('shortcode'); ?>" name="<?php echo $this->get_field_name('shortcode'); ?>" type="text" value="<?php esc_attr_e($instance['shortcode']); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('<b>Title</b> (optional):', 'wp-chatroll'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php esc_attr_e($instance['title']); ?>" />
			</p>
			<p>
				<i>To make your Chatroll bigger or smaller, change 'width' and 'height' values in the Shortcode above.</i>
			</p>
            		<p>To access all other settings, sign in to <a href="http://chatroll.com/" target="_blank">Chatroll</a>, click on your Chatroll's name, then click the <b>Settings</b> tab. Settings include:</p>
			<ul style='list-style-type:disc;margin-left:20px;'>
			<li><b>Customization</b> &ndash; Change colors, layout and sound</li>
			<li><b>Moderation Tools</b> &ndash; Manage privacy, users and content</li>
			<li><b>WordPress Profile Integration</b> &ndash; Let your visitors sign into the chat automatically using their WordPress login. Go to the <b>Settings &rarr; Widget</b> tab and enable the Single Sign-On (SSO) feature.</li>
			</ul>
			<p style='padding-top:15px;'>Need Help? <?php echo $wpChatroll->getContactSupportLink(); ?></p>
			<p>
				<input id="<?php echo $this->get_field_id('showlink'); ?>" name="<?php echo $this->get_field_name('showlink'); ?>" type="checkbox" <?php if( $instance['showlink'] ) { echo "checked='checked'"; } ?>" value='1'/>
				<label for="<?php echo $this->get_field_id('showlink'); ?>"><?php _e('Show link to chatroll.com', 'wp-chatroll'); ?></label>
			</p>
<?php
		return;
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $this->_getInstanceSettings( $new_instance );

		// Clean up the free-form areas
		$instance['title'] = stripslashes($new_instance['title']);
		$instance['errmsg'] = stripslashes($new_instance['errmsg']);

		// If the current user isn't allowed to use unfiltered HTML, filter it
		if ( !current_user_can('unfiltered_html') ) {
			$instance['title'] = strip_tags($new_instance['title']);
			$instance['errmsg'] = strip_tags($new_instance['errmsg']);
		}

        // Explicitly set it to 0 if checkbox is unchecked
        // Otherwise, value will be null for unchecked, and the attribute will be set with the default value.
        if (empty($new_instance['showlink'])) {
            $instance['showlink'] = '0';
        }

		return $instance;
	}

	public function flush_widget_cache() {
		wp_cache_delete('widget_chatroll', 'widget');
	}

	public function widget( $args, $instance ) {
		$instance = $this->_getInstanceSettings( $instance );
		$wpChatroll = wpChatroll::getInstance();
        $wpChatroll->showlink = $instance['showlink'];

		echo $wpChatroll->displayWidget( wp_parse_args( $instance, $args ) );
	}
}


/**
 * wpChatroll is the class that handles everything outside the widget.
 */
class wpChatroll extends Chatroll
{
	/**
	 * Static property to hold our singleton instance
	 */
	static $instance = false;

    /**
     * Option to show WP chat link
     */
    public $showlink = 0;

	/**
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * This is our constructor, which is private to force the use of getInstance()
	 * @return void
	 */
	private function __construct() {
		/**
		 * Add filters and actions
		 */
		add_filter( 'init', array( $this, 'init_locale' ) );
		add_action( 'widgets_init', array( $this, 'register' ) );
		add_filter( 'plugin_action_links', array( $this, 'addPluginPageLinks' ), 10, 2 );
		add_action ( 'in_plugin_update_message-'.plugin_basename ( __FILE__ ) , array ( $this , '_changelog' ), null, 2 );
		add_shortcode( 'chatroll', array( $this, 'handleShortcode' ) );
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function init_locale() {
		$lang_dir = basename(dirname(__FILE__)) . '/languages';
		load_plugin_textdomain('wp-chatroll', 'wp-content/plugins/' . $lang_dir, $lang_dir);
	}

	public function _changelog ($pluginData, $newPluginData) {
		require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

		$plugin = plugins_api( 'plugin_information', array( 'slug' => $newPluginData->slug ) );

		if ( !$plugin || is_wp_error( $plugin ) || empty( $plugin->sections['changelog'] ) ) {
			return;
		}

		$changes = $plugin->sections['changelog'];

		$pos = strpos( $changes, '<h4>' . $pluginData['Version'] );
		$changes = trim( substr( $changes, 0, $pos ) );
		$replace = array(
			'<ul>'	=> '<ul style="list-style: disc inside; padding-left: 15px; font-weight: normal;">',
			'<h4>'	=> '<h4 style="margin-bottom:0;">',
		);
		echo str_replace( array_keys($replace), $replace, $changes );
	}

	public function addPluginPageLinks( $links, $file ){
		if ( $file == plugin_basename(__FILE__) ) {
			// Create Chatroll link
			$link = '<a href="http://chatroll.com/create" target="_blank">' . __('Create Chatroll', 'wp-chatroll') . '</a>';
			array_push( $links, $link );

			// Add Support link to our plugin
			$link = $this->getContactSupportLink();
			array_push( $links, $link );
		}
		return $links;
	}

	public function getContactSupportLink() {
		return '<a href="http://chatroll.com/help/support?r=wordpress-org" target="_blank">' . __('Contact Support', 'wp-chatroll') . '</a>';
	}

	public function register() {
		register_widget('WP_Chatroll');
	}

	public function displayWidget( $args ) {
		$args = wp_parse_args( $args );

		$widgetContent = $args['before_widget'];

		// Widget title, if specified
		if (!empty($args['title'])) {
			$widgetContent .= $args['before_title'] . $args['title'] . $args['after_title'];
		}

		// Use the shortcode to generate iframe
		$widgetContent .= do_shortcode($args['shortcode']);

		$widgetContent .= $args['after_widget'];

		return $widgetContent;
	}

	/**
	 * We need special handling for Gravatar image URLs because:
	 *  1) Chatroll requires image URLs to have valid filename extensions (.png, .jpg, etc.)
	 *  2) Chatroll does not support image URLs that redirect or have GET parameters
	 * Both these issues should be fixed in a future Chatroll service update.
	 * Please contact support@chatroll.com for more information.
	 */
	public function getGravatarUrl($url)
	{
		// Gravatar URL pattern
		$gpattern = "http:\/\/www\.gravatar\.com\/avatar\/[a-z0-9]+";

		// Decode URL-encoded characters in the Gravatar URL for processing
		$url = urldecode($url);

		// Extract the user image URL and default image URL from the combined Gravatar URL
		$userUrl = "";
		$defaultUrl = "";
		if (preg_match("/(" . $gpattern . ").*d=(" . $gpattern . ").*/", $url, $matches)) {
			$userUrl = $matches[1];
			$defaultUrl = $matches[2];
		}

		// Use CURL to check if the Gravatar exists, otherwise use the default image URL
		$ch = curl_init($userUrl . "?d=404");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; it; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6");
		$html = curl_exec($ch);
		if(!curl_errno($ch)) {
			return $userUrl . ".jpg";
		} else {
			return $defaultUrl . ".jpg";
		}
	}

	/**
	 * OVERRIDE Chatroll::appendPlatformDefaultAttr()
	 *  Set user parameters for SSO integration
	 */
	public function appendPlatformDefaultAttr($attr) {
		$attr['platform'] = 'wordpress-org';

		if ($this->showlink) {
			$attr['linkurl'] = "/solutions/wordpress-chat-plugin";
			$attr['linktxt'] = "Wordpress chat";
		} else {
			$attr['linkurl'] = "";
			$attr['linktxt'] = "";
		}

		// Generate SSO attributes that were not specified
		global $current_user;
		get_currentuserinfo();
		if (empty($attr['uid'])) {
			$attr['uid'] = $current_user->ID;
		}
		if (empty($attr['uname'])) {
			$attr['uname'] = $current_user->display_name;
		}
		if (empty($attr['upic'])) {
			// Set the picture using 'get_avatar' (available in WordPress 2.5 and up)
			// This ONLY takes effect when the Single Sign-On (SSO) check box is turned on via the Chatroll's Settings page!
			if (function_exists('get_avatar')) {
				$avtr = get_avatar($current_user->ID, 38);
				$avtr_src = preg_replace("/.*src='([^']*)'.*/", "$1", $avtr);
				if (strlen($avtr_src) > 0) {
					if ($avtr_src[0] == '/') {
						// Turn local image URIs into full URLs.
						$url = get_bloginfo('url');
						$domain = preg_replace("/^(http[s]?:\/\/[^\/]+).*/", "$1", $url);
						$avtr_src = $domain . $avtr_src;
					} else if (preg_match("/gravatar\.com/", $avtr_src)) {
						// Get full Gravatar image URL (with extension)
						$avtr_src = $this->getGravatarUrl($avtr_src);
					}
					$attr['upic'] = $avtr_src;
				}
			}
		}
		if (empty($attr['ulink'])) {
			$attr['ulink'] = $current_user->user_url;
		}
		if (empty($attr['ismod'])) {
			// By default, if the user can moderate comments, they can moderate the chat
			$attr['ismod'] = current_user_can('moderate_comments') ? '1' : '0';
		}
		return $attr;
	}

	/**
	 * Replace our shortCode with the Chatroll iframe
	 *
	 * @param array $attr - array of attributes from the shortCode
	 * @param string $content - Content of the shortCode
	 * @return string - formatted XHTML replacement for the shortCode
	 */
	public function handleShortcode($attr, $content = '') {
		return $this->renderChatrollHtml($attr);
	}
}

/**
 * Instantiate our class
 */
$wpChatroll = wpChatroll::getInstance();
?>
