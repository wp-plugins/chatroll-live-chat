<?php
/**
 * Plugin Name: Chatroll Live Chat
 * Plugin URI: http://chatroll.com
 * Description: Add <a href="http://chatroll.com">Chatroll</a> live chat to your WordPress sidebar, posts, and pages. Adds a widget to put on your sidebar, and a 'chatroll' shortcode to use in posts and pages. Includes Single Sign-On (SSO) support for integrating WordPress login.
 * Version: 1.2.1
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
			'width' => 400,
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
				<label for="<?php echo $this->get_field_id('shortcode'); ?>"><?php _e('Shortcode (From your Chatroll\'s <b>Settings</b> page on <a href="http://chatroll.com">chatroll.com</a>):', 'wp-chatroll'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('shortcode'); ?>" name="<?php echo $this->get_field_name('shortcode'); ?>" type="text" value="<?php esc_attr_e($instance['shortcode']); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title (optional):', 'wp-chatroll'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php esc_attr_e($instance['title']); ?>" />
			</p>
			<p>
				<input id="<?php echo $this->get_field_id('showlink'); ?>" name="<?php echo $this->get_field_name('showlink'); ?>" type="checkbox" <?php if( $instance['showlink'] ) { echo "checked='checked'"; } ?>" value='1'/>
				<label for="<?php echo $this->get_field_id('showlink'); ?>"><?php _e('Show link below widget', 'wp-chatroll'); ?></label>
			</p>
            <p>All other chat settings and moderation tools can be found on your Chatroll's <b>Settings</b> page on <a href="http://chatroll.com">chatroll.com</a>:
                <ul style='list-style-type:disc;margin-left:20px;font-weight:bold;'><li>Colors</li><li>Sound</li><li>Single Sign On</li><li>White-Label</li></ul>
            </p>
			<p style='padding-top:15px;'>Need Help? <?php echo $wpChatroll->getContactSupportLink(); ?></p>
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
			// Add Widget Page link to our plugin
			$link = '<a href="widgets.php">' . __('Manage Widgets', 'wp-chatroll') . '</a>';
			array_unshift( $links, $link );

			// Add Support link to our plugin
			$link = $this->getContactSupportLink();
			array_push( $links, $link );
		}
		return $links;
	}

	public function getContactSupportLink() {
		return '<a href="http://chatroll.com/help/support">' . __('Contact Support', 'wp-chatroll') . '</a>';
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
            // Customize this depending on what avatar system is used. There is no Wordpress standard.
            // e.g. urlencode($current_user->user_pic);
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
