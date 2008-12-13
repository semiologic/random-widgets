<?php
/*
Plugin Name: Random Widgets
Plugin URI: http://www.semiologic.com/software/widgets/random-widgets/
Description: WordPress widgets that let you list random selections of posts, pages, links, or comments.
Author: Denis de Bernardy
Version: 2.1
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/wordpress
Update Tag: random_widgets
Update Package: http://www.semiologic.com/media/software/widgets/random-widgets/random-widgets.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts Ltd, and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('random-widgets','wp-content/plugins/random-widgets');

class random_widgets
{
	#
	# init()
	#

	function init()
	{
		add_action('widgets_init', array('random_widgets', 'widgetize'));
	} # init()


	#
	# widgetize()
	#

	function widgetize()
	{
		$options = random_widgets::get_options();
		
		$widget_options = array('classname' => 'random_widget', 'description' => __( "A random selection of posts, pages, links or comments") );
		$control_options = array('width' => 500, 'id_base' => 'random-widget');
		
		$id = false;

		# registered widgets
		foreach ( array_keys($options) as $o )
		{
			if ( !is_numeric($o) ) continue;
			$id = "random-widget-$o";

			wp_register_sidebar_widget($id, __('Random Widget'), array('random_widgets', 'display_widget'), $widget_options, array( 'number' => $o ));
			wp_register_widget_control($id, __('Random Widget'), array('random_widgets_admin', 'widget_control'), $control_options, array( 'number' => $o ) );
		}
		
		# default widget if none were registered
		if ( !$id )
		{
			$id = "random-widget-1";
			wp_register_sidebar_widget($id, __('Random Widget'), array('random_widgets', 'display_widget'), $widget_options, array( 'number' => -1 ));
			wp_register_widget_control($id, __('Random Widget'), array('random_widgets_admin', 'widget_control'), $control_options, array( 'number' => -1 ) );
		}
	} # widgetize()


	#
	# display_widget()
	#

	function display_widget($args, $widget_args = 1)
	{
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		$number = intval($number);
		
		# get options
		$options = random_widgets::get_options();
		$options = $options[$number];
		
		# admin area: serve a formatted title
		if ( is_admin() )
		{
			echo $args['before_widget']
				. $args['before_title'] . $options['title'] . $args['after_title']
				. $args['after_widget'];

			return;
		}

		# initialize
		$o = '';

		# fetch items
		switch ( $options['type'] )
		{
		case 'posts':
			$items = random_widgets::get_posts($options);
			break;

		case 'pages':
			$items = random_widgets::get_pages($options);
			break;

		case 'links':
			$items = random_widgets::get_links($options);
			break;

		case 'comments':
			$items = random_widgets::get_comments($options);
			break;

		case 'updates':
			$items = random_widgets::get_updates($options);
			break;

		default:
			$items = array();
			break;
		}

		# fetch output
		if ( $items )
		{
			$o .= $args['before_widget'] . "\n"
				. ( $options['title']
					? ( $args['before_title'] . $options['title'] . $args['after_title'] . "\n" )
					: ''
					);

			$o .= '<ul>' . "\n";

			foreach ( $items as $item )
			{
				$o .= '<li>'
					. $item->item_label
					. '</li>' . "\n";
			}

			$o .= '</ul>' . "\n";

			$o .= $args['after_widget'] . "\n";
		}

		# display
		echo $o;
	} # display_widget()


	#
	# get_posts()
	#

	function get_posts($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc
			FROM	$wpdb->posts as posts
			"
			. ( $options['filter']
				? ( "
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = posts.ID
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			AND		term_taxonomy.taxonomy = 'category'
			AND		term_taxonomy.term_id = " . intval($options['filter'])
			)
				: ''
				)
			. "
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'post'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )
			ORDER BY RAND()
			LIMIT " . intval($options['amount'])
			;

		$items = (array) $wpdb->get_results($items_sql);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_posts()


	#
	# get_pages()
	#

	function get_pages($options)
	{
		global $wpdb;
		global $page_filters;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";

		if ( $options['filter'] )
		{
			if ( isset($page_filters[$options['filter']]) )
			{
				$parent_sql = $page_filters[$options['filter']];
			}
			else
			{
				$parents = array($options['filter']);

				do
				{
					$old_parents = $parents;

					$parents_sql = implode(', ', $parents);

					$parents = (array) $wpdb->get_col("
						SELECT	posts.ID
						FROM	$wpdb->posts as posts
						WHERE	posts.post_status = 'publish'
						AND		posts.post_type = 'page'
						AND		posts.ID IN ( $parents_sql )
						UNION
						SELECT	posts.ID
						FROM	$wpdb->posts as posts
						WHERE	posts.post_status = 'publish'
						AND		posts.post_type = 'page'
						AND		posts.post_parent IN ( $parents_sql )
						");
					
					sort($parents);
				} while ( $parents != $old_parents );

				$page_filters[$options['filter']] = $parents_sql;
			}
		}

		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'page'
			AND		posts.post_password = ''
			"
			. ( $options['filter']
				? ( "
			AND		posts.post_parent IN ( $parents_sql )
			" )
				: ''
				)
			. "
			AND		posts.ID NOT IN ( $exclude_sql )
			ORDER BY RAND()
			LIMIT " . intval($options['amount'])
			;

		$items = (array) $wpdb->get_results($items_sql);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_pages()


	#
	# get_links()
	#

	function get_links($options)
	{
		global $wpdb;

		$items_sql = "
			SELECT	links.*
			FROM	$wpdb->links as links
			"
			. ( $options['filter']
				? ( "
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = links.link_id
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			AND		term_taxonomy.taxonomy = 'link_category'
			AND		term_taxonomy.term_id = " . intval($options['filter'])
			)
				: ''
				)
			. "
			WHERE	links.link_visible = 'Y'
			ORDER BY RAND()
			LIMIT " . intval($options['amount'])
			;

		$items = (array) $wpdb->get_results($items_sql);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars($items[$key]->link_url)
				. '">'
				. $items[$key]->link_name
				. '</a>'
				. ( $options['desc'] && $items[$key]->link_description
					? ( wpautop($items[$key]->link_description) )
					: ''
					);
		}

		return $items;
	} # get_links()


	#
	# get_comments()
	#

	function get_comments($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$items_sql = "
			SELECT	posts.*,
					comments.*,
					COALESCE(post_label.meta_value, post_title) as post_label
			FROM	$wpdb->posts as posts
			INNER JOIN $wpdb->comments as comments
			ON		comments.comment_post_ID = posts.ID
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type IN ('post', 'page')
			AND		posts.post_password = ''
			AND		comments.comment_approved = '1'
			AND		posts.ID NOT IN ( $exclude_sql )
			ORDER BY RAND()
			LIMIT " . intval($options['amount'])
			;

		$items = (array) $wpdb->get_results($items_sql);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = ( $options['trim'] && strlen($items[$key]->comment_author) > $options['trim']
					? ( substr($items[$key]->comment_author, 0, $options['trim']) . '...' )
					: $items[$key]->comment_author
					)
				. ' ' . __('on', 'random-widgets') . ' '
				. '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)) . '#comment-' . $items[$key]->comment_ID)
				. '">'
				. 	$items[$key]->post_label
					. '</a>';
		}

		return $items;
	} # get_comments()


	#
	# get_updates()
	#

	function get_updates($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		$items_sql = "
			SELECT	posts.*,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type IN ('post', 'page')
			AND		posts.post_password = ''
			AND		posts.post_modified > DATE_ADD(posts.post_date, INTERVAL 2 DAY)
			AND		posts.ID NOT IN ( $exclude_sql )
			ORDER BY RAND()
			LIMIT " . intval($options['amount'])
			;

		$items = (array) $wpdb->get_results($items_sql);

		update_post_cache($items);

		foreach ( array_keys($items) as $keys )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_updates()


	#
	# get_options()
	#

	function get_options()
	{
		if ( ( $o = get_option('random_widgets') ) === false )
		{
			$o = array();

			update_option('random_widgets', $o);
		}

		return $o;
	} # get_options()


	#
	# default_options()
	#

	function default_options()
	{
		return array(
			'title' => __('Random Posts'),
			'type' => 'posts',
			'amount' => 5,
			'desc' => false,
			);
	} # default_options()
} # random_widgets

random_widgets::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/random-widgets-admin.php';
}
?>