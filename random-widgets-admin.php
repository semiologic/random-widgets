<?php
if ( !class_exists('widget_utils') )
{
	include dirname(__FILE__) . '/widget-utils/widget-utils.php';
}

class random_widgets_admin
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('random_widgets_admin', 'meta_boxes'));

		if ( version_compare(mysql_get_server_info(), '4.1', '<') )
		{
			add_action('admin_notices', array('random_widgets_admin', 'mysql_warning'));
			remove_action('widgets_init', array('random_widgets', 'widgetize'));
		}
	} # init()
	
	
	#
	# mysql_warning()
	#
	
	function mysql_warning()
	{
		echo '<div class="error">'
			. '<p><b style="color: firebrick;">Random Widgets Error</b><br /><b>Your MySQL version is lower than 4.1.</b> It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.</p>'
			. '</div>';
	} # mysql_warning()
	
	
	#
	# meta_boxes()
	#
	
	function meta_boxes()
	{
		if ( !class_exists('widget_utils') ) return;
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();

		add_action('post_widget_config_affected', array('random_widgets_admin', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('random_widgets_admin', 'widget_config_affected'));
	} # meta_boxes()
	
	
	#
	# widget_config_affected()
	#
	
	function widget_config_affected()
	{
		echo '<li>'
			. 'Random Widgets'
			. '</li>';
	} # widget_config_affected()


	#
	# widget_control()
	#

	function widget_control($widget_args)
	{
		global $wpdb;
		global $post_stubs;
		global $page_stubs;
		global $link_stubs;

		if ( !isset($post_stubs) )
		{
			$post_stubs = (array) $wpdb->get_results("
				SELECT	terms.term_id as value,
						terms.name as label
				FROM	$wpdb->terms as terms
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_id = terms.term_id
				AND		term_taxonomy.taxonomy = 'category'
				WHERE	parent = 0
				ORDER BY terms.name
				");
		}

		if ( !isset($page_stubs) )
		{
			$page_stubs = (array) $wpdb->get_results("
				SELECT	posts.ID as value,
						posts.post_title as label
				FROM	$wpdb->posts as posts
				WHERE	post_parent = 0
				AND		post_type = 'page'
				AND		post_status = 'publish'
				ORDER BY posts.post_title
				");
		}

		if ( !isset($link_stubs) )
		{
			$link_stubs = (array) $wpdb->get_results("
				SELECT	terms.term_id as value,
						terms.name as label
				FROM	$wpdb->terms as terms
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_id = terms.term_id
				AND		term_taxonomy.taxonomy = 'link_category'
				WHERE	parent = 0
				ORDER BY terms.name
				");
		}

		
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP ); // extract number

		$options = random_widgets::get_options();

		if ( !$updated && !empty($_POST['sidebar']) )
		{
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();

			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id )
			{
				if ( array('random_widgets', 'display_widget') == $wp_registered_widgets[$_widget_id]['callback']
					&& isset($wp_registered_widgets[$_widget_id]['params'][0]['number'])
					)
				{
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "random-widget-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
				}
			}

			foreach ( (array) $_POST['random-widget'] as $num => $opt ) {
				$title = strip_tags(stripslashes($opt['title']));
				$type = $opt['type'];
				$amount = intval($opt['amount']);
				$desc = isset($opt['desc']);

				if ( !preg_match("/^([a-z_]+)(?:-(\d+))?$/", $type, $match) )
				{
					$type = 'posts';
					$filter = false;
				}
				else
				{
					$type = $match[1];
					$filter = isset($match[2]) ? $match[2] : false;
				}

				if ( $amount <= 0 )
				{
					$amount = 5;
				}

				if ( $type == 'comments' )
				{
					$desc = false;
				}

				$options[$num] = compact( 'title', 'type', 'filter', 'amount', 'desc' );
			}

			update_option('random_widgets', $options);
			$updated = true;
		}

		if ( -1 == $number )
		{
			$ops = random_widgets::default_options();
			$number = '%i%';
		}
		else
		{
			$ops = $options[$number];
		}

		extract($ops);

		
		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="random-widget-title-' . $number . '">'
			. __('Title', 'random-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 320px;"'
			. ' id="random-widget-title-' . $number . '" name="random-widget[' . $number . '][title]"'
			. ' type="text" value="' . esc_attr($title) . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="random-widget-type-' . $number . '">'
			. __('Recent', 'random-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">';

		$type = $type
			. ( $filter
				? ( '-' . $filter )
				: ''
				);

		echo '<select'
				. ' style="width: 320px;"'
				. ' id="random-widget-type-' . $number . '" name="random-widget[' . $number . '][type]"'
				. '>';

		echo '<optgroup label="' . __('Posts', 'random-widgets') . '">'
			. '<option'
			. ' value="posts"'
			. ( $type == 'posts'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Posts', 'random-widgets') . ' / ' . __('All categories', 'random-widgets')
			. '</option>';

		foreach ( $post_stubs as $option )
		{
			echo '<option'
				. ' value="posts-' . $option->value . '"'
				. ( $type == ( 'posts-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Posts', 'random-widgets') . ' / ' . esc_attr($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Pages', 'random-widgets') . '">'
			. '<option'
			. ' value="pages"'
			. ( $type == 'pages'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Pages', 'random-widgets') . ' / ' . __('All Parents', 'random-widgets')
			. '</option>';

		foreach ( $page_stubs as $option )
		{
			echo '<option'
				. ' value="pages-' . $option->value . '"'
				. ( $type == ( 'pages-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Pages', 'random-widgets') . ' / ' . esc_attr($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Links', 'random-widgets') . '">'
			. '<option'
			. ' value="links"'
			. ( $type == 'links'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Links', 'random-widgets') . ' / ' . __('All Categories', 'random-widgets')
			. '</option>';

		foreach ( $link_stubs as $option )
		{
			echo '<option'
				. ' value="links-' . $option->value . '"'
				. ( $type == ( 'links-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Links', 'random-widgets') . ' / ' . esc_attr($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Comments', 'random-widgets') . '">'
			. '<option'
			. ' value="comments"'
			. ( $type == 'comments'
				? 'selected="selected"'
				: ''
				)
			. '>'
			. __('Comments', 'random-widgets')
			. '</option>';

		echo '</optgroup>';

		echo '</select>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="random-widget-amount-' . $number . '">'
			. __('Quantity', 'random-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 30px;"'
			. ' id="random-widget-amount-' . $number . '" name="random-widget[' . $number . '][amount]"'
			. ' type="text" value="' . $amount . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="random-widget-desc-' . $number . '">'
			. '<input'
			. ' id="random-widget-desc-' . $number . '" name="random-widget[' . $number . '][desc]"'
			. ' type="checkbox"'
			. ( $desc
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Descriptions (posts, pages and links)', 'random-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';
		
		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. __('Notice', 'random-widgets')
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. __('While fun, this plugin cannot be cached, and Google tends to hate randomness. Avoid like plague if performance and SEO are of any importance to you.', 'random-widgets')
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';
	} # widget_control()
} # random_widgets_admin

random_widgets_admin::init();
?>