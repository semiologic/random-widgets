<?php
/*
Plugin Name: Random Widgets
Plugin URI: http://www.semiologic.com/software/random-widgets/
Description: WordPress widgets that let you list random posts, pages, links or comments.
Version: 3.0.1 beta
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: random-widgets
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('random-widgets', false, dirname(plugin_basename(__FILE__)) . '/lang');

if ( !defined('widget_utils_textdomain') )
	define('widget_utils_textdomain', 'random-widgets');


/**
 * random_widget
 *
 * @package Random Widgets
 **/

class random_widget extends WP_Widget {
	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		if ( get_option('widget_random_widget') === false ) {
			foreach ( array(
				'random_widgets' => 'upgrade',
				) as $ops => $method ) {
				if ( get_option($ops) !== false ) {
					$this->alt_option_name = $ops;
					add_filter('option_' . $ops, array(get_class($this), $method));
					break;
				}
			}
		}
	} # init()
	
	
	/**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include dirname(__FILE__) . '/widget-utils/widget-utils.php';
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();
		add_action('post_widget_config_affected', array('random_widget', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('random_widget', 'widget_config_affected'));
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	function widget_config_affected() {
		echo '<li>'
			. __('Random Widgets', 'random-widgets')
			. '</li>' . "\n";
	} # widget_config_affected()
	
	
	/**
	 * widgets_init()
	 *
	 * @return void
	 **/

	function widgets_init() {
		register_widget('random_widget');
	} # widgets_init()
	
	
	/**
	 * random_widget()
	 *
	 * @return void
	 **/

	function random_widget() {
		$widget_ops = array(
			'classname' => 'random_widget',
			'description' => __('Random Posts, Pages, Links or Comments.', 'random-widgets'),
			);
		$control_ops = array(
			'width' => 330,
			);
		
		$this->init();
		$this->WP_Widget('random_widget', __('Random Widget', 'random-widgets'), $widget_ops, $control_ops);
	} # random_widget()
	
	
	/**
	 * widget()
	 *
	 * @param array $args widget args
	 * @param array $instance widget options
	 * @return void
	 **/

	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		extract($instance, EXTR_SKIP);
		
		if ( is_admin() ) {
			echo $before_widget
				. ( $title
					? ( $before_title . $title . $after_title )
					: ''
					)
				. $after_widget;
			return;
		} elseif ( !in_array($type, array('pages', 'posts', 'links', 'comments', 'updates')) )
			return;
		
		if ( is_singular() ) {
			global $wp_the_query;
			$post_id = (int) $wp_the_query->get_queried_object_id();
		} elseif ( in_the_loop() ) {
			$post_id = (int) get_the_ID();
		} else {
			$post_id = false;
		}
		
		switch ( $type ) {
		case 'pages':
			$items = random_widget::get_pages($post_id, $instance);
			break;
		case 'posts':
			$items = random_widget::get_posts($post_id, $instance);
			break;
		case 'links':
			$items = random_widget::get_links($instance);
			break;
		case 'updates':
			$items = random_widget::get_updates($post_id, $instance);
			break;
		case 'comments':
			$items = random_widget::get_comments($post_id, $instance);
			break;
		}
		
		$title = apply_filters('widget_title', $title);
		
		echo str_replace('random_widget', 'random_widget random_' . $type, $before_widget);
		
		if ( $title )
			echo $before_title . $title . $after_title;
		
		echo '<ul>' . "\n";
		
		$descr = false;
		foreach ( $items as $item ) {
			switch ( $type ) {
			case 'posts':
			case 'pages':
			case 'updates':
				$label = get_post_meta($item->ID, '_widgets_label', true);
				if ( (string) $label === '' )
					$label = $item->post_title;
				if ( (string) $label === '' )
					$label = __('Untitled', 'random-widgets');
				
				$link = apply_filters('the_permalink', get_permalink($item->ID));
				
				$label = '<a href="' . esc_url($link) . '"'
						. ' title="' . esc_attr($label) . '"'
						. '>'
					. $label
					. '</a>';
				
				if ( $desc ) {
					$descr = trim(get_post_meta($item->ID, '_widgets_desc', true));
				}
				break;
			case 'links':
				$label = $item->link_name;
				if ( (string) $label === '' )
					$label = __('Untitled', 'random-widgets');
				
				$label = '<a href="' . esc_url($item->link_url) . '"'
						. ' title="' . esc_attr($label) . '"'
						. '>'
					. $label
					. '</a>';
				
				if ( $desc ) {
					$descr = trim($item->link_description);
				}
				break;
			case 'comments':
				$post_label = get_post_meta($item->ID, '_widgets_label', true);
				if ( (string) $post_label === '' )
					$post_label = $item->post_title;
				if ( (string) $post_label === '' )
					$post_label = __('Untitled', 'random-widgets');
				
				$post_link = apply_filters('the_permalink', get_permalink($item->ID));
				
				$author_label = strip_tags($item->comment_author);
				
				$author_link = $post_link . '#comment-' . $item->comment_ID;
				
				$post_label = '<a href="' . esc_url($post_link) . '"'
						. ' title="' . esc_attr($post_label) . '"'
						. '>'
					. $post_label
					. '</a>';
				
				$author_label = '<a href="' . esc_url($author_link) . '"'
						. ' title="' . esc_attr($author_label) . '"'
						. '>'
					. $author_label
					. '</a>';
				
				$label = sprintf(__('%1$s on %2$s', 'random-widgets'), $author_label, $post_label);
				break;
			}
			
			echo '<li>'
				. $label;
				
			if ( $descr )
				echo "\n\n" . wpautop(apply_filters('widget_text', $descr));
			
			echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n";
		
		echo $after_widget;
	} # widget()
	
	
	/**
	 * get_pages()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_pages($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "
				SELECT	post.*
				FROM	$wpdb->posts as post";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			if ( !get_transient('cached_section_ids') )
				random_widget::cache_section_ids();
			
			$items_sql .= "
				JOIN	$wpdb->postmeta as meta_filter
				ON		meta_filter.post_id = post.ID
				AND		meta_filter.meta_key = '_section_id'
				AND		meta_filter.meta_value = '$filter'
				";
		}
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type = 'page'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $post_id ) {
			$items_sql .= "
				AND		post.ID <> $post_id";
		}
		
		$items_sql .= "
				ORDER BY RAND()
				LIMIT $amount
				";
		
		$posts = $wpdb->get_results($items_sql);
		update_post_cache($posts);
		
		$post_ids = array();
		foreach ( $posts as $post )
			$post_ids[] = $post->ID;
		update_postmeta_cache($post_ids);
		
		return $posts;
	} # get_pages()
	
	
	/**
	 * get_posts()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_posts($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "
				SELECT	post.*
				FROM	$wpdb->posts as post";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			$items_sql .= "
				JOIN	$wpdb->term_relationships as filter_tr
				ON		filter_tr.object_id = post.ID
				JOIN	$wpdb->term_taxonomy as filter_tt
				ON		filter_tt.term_taxonomy_id = filter_tr.term_taxonomy_id
				AND		filter_tt.term_id = $filter
				AND		filter_tt.taxonomy = 'category'";
		}
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type = 'post'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $post_id ) {
			$items_sql .= "
				AND		post.ID <> $post_id";
		}
		
		$items_sql .= "
				ORDER BY RAND()
				LIMIT $amount
				";
		
		$posts = $wpdb->get_results($items_sql);
		update_post_cache($posts);
		
		$post_ids = array();
		foreach ( $posts as $post )
			$post_ids[] = $post->ID;
		update_postmeta_cache($post_ids);
		
		return $posts;
	} # get_posts()
	
	
	/**
	 * get_links()
	 *
	 * @param array $instance
	 * @return array $links
	 **/

	function get_links($instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "
				SELECT	link.*
				FROM	$wpdb->links as link";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			$items_sql .= "
				JOIN	$wpdb->term_relationships as filter_tr
				ON		filter_tr.object_id = link.link_id
				JOIN	$wpdb->term_taxonomy as filter_tt
				ON		filter_tt.term_taxonomy_id = filter_tr.term_taxonomy_id
				AND		filter_tt.term_id = $filter
				AND		filter_tt.taxonomy = 'link_category'";
		}
		
		$items_sql .= "
				WHERE	link.link_visible = 'Y'";
		
		$items_sql .= "
				ORDER BY RAND()
				LIMIT $amount
				";
		
		$links = $wpdb->get_results($items_sql);
		
		return $links;
	} # get_links()
	
	
	/**
	 * get_updates()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_updates($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "
				SELECT	post.*
				FROM	$wpdb->posts as post";
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type IN ( 'post', 'page' )
				AND		post.post_modified > DATE_ADD(post.post_date, INTERVAL 2 DAY)
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $post_id ) {
			$items_sql .= "
				AND		post.ID <> $post_id";
		}
		
		$items_sql .= "
				ORDER BY RAND()
				LIMIT $amount
				";
		
		$posts = $wpdb->get_results($items_sql);
		update_post_cache($posts);
		
		$post_ids = array();
		foreach ( $posts as $post )
			$post_ids[] = $post->ID;
		update_postmeta_cache($post_ids);
		
		return $posts;
	} # get_updates()
	
	
	/**
	 * get_comments()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_comments($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		$amount = min(max((int) $amount, 1), 10);
		
		$items_sql = "
				SELECT	post.*,
						comment.*
				FROM	$wpdb->posts as post
				JOIN	$wpdb->comments as comment
				ON		comment.comment_post_ID = post.ID";
		
		$items_sql .= "
				LEFT JOIN $wpdb->postmeta as widgets_exclude
				ON		widgets_exclude.post_id = post.ID
				AND		widgets_exclude.meta_key = '_widgets_exclude'";
		
		$items_sql .= "
				WHERE	post.post_status = 'publish'
				AND		post.post_type IN ( 'post', 'page' )
				AND		post.post_password = ''
				AND		comment.comment_approved = '1'
				AND		widgets_exclude.post_id IS NULL";
		
		if ( $post_id ) {
			$items_sql .= "
				AND		post.ID <> $post_id";
		}
		
		$items_sql .= "
				GROUP BY post.ID
				ORDER BY RAND()
				LIMIT $amount
				";
		
		$posts = $wpdb->get_results($items_sql);
		update_post_cache($posts);
		
		$post_ids = array();
		foreach ( $posts as $post )
			$post_ids[] = $post->ID;
		update_postmeta_cache($post_ids);
		
		return $posts;
	} # get_comments()
	
	
	/**
	 * update()
	 *
	 * @param array $new_instance new widget options
	 * @param array $old_instance old widget options
	 * @return array $instance
	 **/

	function update($new_instance, $old_instance) {
		$instance = random_widget::defaults();
		
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['amount'] = min(max((int) $new_instance['amount'], 1), 10);
		$instance['desc'] = isset($new_instance['desc']);
		
		$type_filter = explode('-', $new_instance['type_filter']);
		$type = array_shift($type_filter);
		$filter = array_pop($type_filter);
		$filter = intval($filter);
		
		$instance['type'] = in_array($type, array('posts', 'pages', 'links', 'comments', 'updates'))
			? $type
			: 'posts';
		if ( !in_array($instance['type'], array('comments', 'updates')) )
			$instance['filter'] = $filter ? $filter : false;
		else
			$instance['filter'] = false;
		
		return $instance;
	} # update()
	
	
	/**
	 * form()
	 *
	 * @param array $instance widget options
	 * @return void
	 **/

	function form($instance) {
		$instance = wp_parse_args($instance, random_widget::defaults());
		static $pages;
		static $categories;
		static $link_categories;
		
		if ( !isset($pages) ) {
			global $wpdb;
			$pages = $wpdb->get_results("
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				WHERE	posts.post_type = 'page'
				AND		posts.post_status = 'publish'
				AND		posts.post_parent = 0
				ORDER BY posts.menu_order, posts.post_title
				");
			update_post_cache($pages);
		}
		
		if ( !isset($categories) ) {
			$categories = get_terms('category', array('parent' => 0));
		}
		
		if ( !isset($link_categories) ) {
			$link_categories = get_terms('link_category', array('parent' => 0));
		}
		
		extract($instance, EXTR_SKIP);
		
		echo '<p>'
			. '<label>'
			. __('Title:', 'random-widgets') . '<br />' . "\n"
			. '<input type="text" size="20" class="widefat"'
				. ' id="' . $this->get_field_id('title') . '"'
				. ' name="' . $this->get_field_name('title') . '"'
				. ' value="' . esc_attr($title) . '"'
				. ' />'
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. __('Display:', 'random-widgets') . '<br />' . "\n"
			. '<select name="' . $this->get_field_name('type_filter') . '" class="widefat">' . "\n";
		
		echo '<optgroup label="' . __('Posts', 'random-widgets') . '">' . "\n"
			. '<option value="posts"' . selected($type == 'posts' && !$filter, true, false) . '>'
			. __('Random Posts / All Categories', 'random-widgets')
			. '</option>' . "\n";
		
		foreach ( $categories as $category ) {
			echo '<option value="posts-' . intval($category->term_id) . '"'
					. selected($type == 'posts' && $filter == $category->term_id, true, false)
					. '>'
				. sprintf(__('Random Posts / %s', 'random-widgets'), strip_tags($category->name))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Pages', 'random-widgets') . '">' . "\n"
			. '<option value="pages"' . selected($type == 'pages' && !$filter, true, false) . '>'
			. __('Random Pages / All Sections', 'random-widgets')
			. '</option>' . "\n";
		
		foreach ( $pages as $page ) {
			echo '<option value="pages-' . intval($page->ID) . '"'
					. selected($type == 'pages' && $filter == $page->ID, true, false)
					. '>'
				. sprintf(__('Random Pages / %s', 'random-widgets'), strip_tags($page->post_label))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Links', 'random-widgets') . '">' . "\n"
			. '<option value="links"' . selected($type == 'links' && !$filter, true, false) . '>'
			. __('Random Links / All Categories', 'random-widgets')
			. '</option>' . "\n";
		
		foreach ( $link_categories as $link_category ) {
			echo '<option value="links-' . intval($link_category->term_id) . '"'
					. selected($type == 'links' && $filter == $link_category->term_id, true, false)
					. '>'
				. sprintf(__('Random Links / %s', 'random-widgets'), strip_tags($link_category->name))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Miscellaneous', 'random-widgets') . '">' . "\n"
			. '<option value="comments"' . selected($type == 'comments', true, false) . '>'
			. __('Random Comments', 'random-widgets')
			. '</option>' . "\n"
			. '<option value="updates"' . selected($type == 'updates', true, false) . '>'
			. __('Random Updates', 'random-widgets')
			. '</option>' . "\n"
			. '</optgroup>' . "\n";
		
		echo '</select>' . "\n"
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. sprintf(__('%s Random Items', 'random-widgets'),
				'<input type="text" size="3" name="' . $this->get_field_name('amount') . '"'
					. ' value="' . intval($amount) . '"'
					. ' />')
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="checkbox" name="' . $this->get_field_name('desc') . '"'
				. checked($desc, true, false)
				. ' />'
			. '&nbsp;'
			. __('Show Descriptions (except for comments)', 'random-widgets')
			. '</label>'
			. '</p>' . "\n";
	} # form()
	
	
	/**
	 * defaults()
	 *
	 * @return array $instance default options
	 **/

	function defaults() {
		return array(
			'title' => __('Random Posts', 'random-widgets'),
			'type' => 'posts',
			'filter' => false,
			'amount' => 5,
			'desc' => false,
			);
	} # defaults()
	
	
	/**
	 * save_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function save_post($post_id) {
		$post = get_post($post_id);
		
		if ( $post->post_type != 'page' )
			return;
		
		delete_transient('cached_section_ids');
	} # save_post()
	
	
	/**
	 * cache_section_ids()
	 *
	 * @return void
	 **/

	function cache_section_ids() {
		global $wpdb;
		
		$pages = $wpdb->get_results("
			SELECT	*
			FROM	$wpdb->posts
			WHERE	post_type = 'page'
			AND		post_status <> 'trash'
			");
		
		update_post_cache($pages);
		
		$to_cache = array();
		foreach ( $pages as $page )
			$to_cache[] = $page->ID;
		
		update_postmeta_cache($to_cache);
		
		foreach ( $pages as $page ) {
			$parent = $page;
			while ( $parent->post_parent )
				$parent = get_post($parent->post_parent);
			
			if ( "$parent->ID" !== get_post_meta($page->ID, '_section_id', true) )
				update_post_meta($page->ID, '_section_id', "$parent->ID");
		}
		
		set_transient('cached_section_ids', 1);
	} # cache_section_ids()
	
	
	/**
	 * upgrade()
	 *
	 * @param array $ops
	 * @return array $ops
	 **/

	function upgrade($ops) {
		$widget_contexts = class_exists('widget_contexts')
			? get_option('widget_contexts')
			: false;
		
		foreach ( $ops as $k => $o ) {
			if ( isset($widget_contexts['random-widget-' . $k]) ) {
				$ops[$k]['widget_contexts'] = $widget_contexts['random-widget-' . $k];
			}
		}
		
		if ( is_admin() ) {
			$sidebars_widgets = get_option('sidebars_widgets', array('array_version' => 3));
		} else {
			if ( !$GLOBALS['_wp_sidebars_widgets'] )
				$GLOBALS['_wp_sidebars_widgets'] = get_option('sidebars_widgets', array('array_version' => 3));
			$sidebars_widgets =& $GLOBALS['_wp_sidebars_widgets'];
		}
		
		$keys = array_keys($ops);
		
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ( !is_array($widgets) )
				continue;
			foreach ( $keys as $k ) {
				$key = array_search("random-widget-$k", $widgets);
				if ( $key !== false ) {
					$sidebars_widgets[$sidebar][$key] = 'random_widget-' . $k;
					unset($keys[array_search($k, $keys)]);
				}
			}
		}
		
		if ( is_admin() )
			update_option('sidebars_widgets', $sidebars_widgets);
		
		return $ops;
	} # upgrade()
} # random_widget

add_action('widgets_init', array('random_widget', 'widgets_init'));

foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php') as $hook )
	add_action('load-' . $hook, array('random_widget', 'editor_init'));

add_action('save_post', array('random_widget', 'save_post'));
?>