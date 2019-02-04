<?php

namespace DeliciousBrains\WPPostTypes\PostType;

class AbstractPostType {

	protected $args;
	protected $type;
	protected $single;
	protected $plural;
	protected $icon;
	protected $menu_position = 25;
	protected $supports = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' );
	protected $block_editor = false;

	public function __construct( $args = array() ) {
		$this->type   = self::get_post_type();
		$this->single = $this->single ? $this->single : self::get_post_type( '' );
		if ( empty( $this->plural ) ) {
			$this->plural = $this->single . 's';
		}
		$this->args = $args;
	}

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'manage_edit-' . $this->type . '_columns', array( $this, 'get_columns' ) );
		add_action( 'manage_' . $this->type . '_posts_custom_column', array( $this, 'render_columns' ) );
		add_action( 'add_meta_boxes', function () {
			remove_meta_box( 'wpseo_meta', $this->type, 'normal' );
		}, 100 );
		add_action( 'template_redirect', array( $this, 'redirect_post_type_pages' ) );
		add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'exclude_post_type_from_site_map' ), 10, 2 );
		if ( ! $this->block_editor ) {
			add_filter( 'use_block_editor_for_post_type', function ( $use_block_editor, $post_type ) {
				if ( $this->type === $post_type ) {
					return false;
				}

				return $use_block_editor;
			}, 10, 2 );
		}
	}

	public static function get_post_type( $prefix = 'dbi_' ) {
		$parts = explode( '\\', get_called_class() );

		return $prefix . strtolower( array_pop( $parts ) );
	}

	public function register() {
		$single = ucwords( $this->single );
		$plural = ucwords( $this->plural );
		$labels = array(
			'name'               => $plural,
			'singular_name'      => $single,
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $single,
			'edit_item'          => 'Edit ' . $single,
			'new_item'           => 'New ' . $single,
			'all_items'          => 'All ' . $plural,
			'view_item'          => 'View ' . $single,
			'search_items'       => 'Search ' . $plural,
			'not_found'          => 'No ' . $plural . ' found',
			'not_found_in_trash' => 'No ' . $plural . '  found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => $plural,
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => self::get_post_type( '' ) ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => $this->menu_position,
			'supports'           => $this->supports,
		);

		if ( $this->icon ) {
			$args['menu_icon'] = 'dashicons-' . $this->icon;
		}

		register_post_type( $this->type, array_merge( $args, $this->args ) );
	}

	public function get_columns( $columns ) {
		return $columns;
	}

	public function render_columns( $column ) {
	}

	/**
	 * Return a list of all published posts.
	 *
	 * @param null   $limit
	 * @param string $order
	 *
	 * @return array
	 */
	public static function all( $limit = null, $order = 'DESC' ) {
		$limit = is_null( $limit ) ? - 1 : $limit;

		$query = new \WP_Query( array(
			'post_type'      => static::get_post_type(),
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'post_date',
			'order'          => $order,
		) );

		return static::get_posts( $query );
	}

	protected static function get_model_class() {
		$parts   = explode( '\\', get_called_class() );
		$search  = 'PostType';
		$replace = 'Model';
		$parts   = array_map( function ( $part ) use ( $search, $replace ) {
			return $part == $search ? $replace : $part;
		}, $parts );

		$model = implode( '\\', $parts );

		if ( class_exists( $model ) ) {
			return $model;
		}

		return 'DeliciousBrains\\WPPostTypes\\Model\\Post';
	}

	protected static function get_posts( $query ) {
		$posts = $query->get_posts();
		foreach ( $posts as $key => $post ) {
			$class         = self::get_model_class();
			$posts[ $key ] = new $class( $post );
		}

		return $posts;
	}

	/**
	 * Redirect single and archive pages for post type to the homepage
	 */
	public function redirect_post_type_pages() {
		if ( ! is_singular( $this->type ) && ! is_post_type_archive( $this->type ) ) {
			return;
		}

		wp_redirect( home_url(), 301 );
		exit;
	}

	/**
	 * Suppress post type from Yoast sitemap.
	 *
	 * @param bool   $value
	 * @param string $post_type
	 *
	 * @return bool
	 */
	public function exclude_post_type_from_site_map( $value, $post_type ) {
		if ( $this->type === $post_type ) {
			return true;
		}

		return $value;
	}
}