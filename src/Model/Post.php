<?php

namespace DeliciousBrains\WPPostTypes\Model;

class Post {

	/**
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * @param $data int|array|object
	 */
	public function __construct( $data = null ) {
		$this->post = self::get_post_object( $data );
	}

	/**
	 * Converts the data into a wordpress post object
	 *
	 * @static
	 *
	 * @param mixed $data
	 *
	 * @return \WP_Post
	 */
	public static function get_post_object( $data = null ) {
		if ( is_object( $data ) && is_a( $data, get_called_class() ) ) {
			return $data->post;
		}

		if ( is_array( $data ) ) {
			return new \WP_Post( (object) $data );
		}

		if ( is_object( $data ) && $data instanceof \WP_Post ) {
			return $data;
		}
		if ( is_object( $data ) ) {
			return new \WP_Post( $data );
		}

		if ( is_numeric( $data ) && is_integer( $data + 0 ) ) {
			return get_post( $data );

		}

		global $post;

		return $post;
	}

	public function __call( $name, $args ) {
		if ( function_exists( $name ) ) {
			global $post;
			$post = $this;
			setup_postdata( $this );

			return call_user_func_array( $name, $args );
		} elseif ( function_exists( "wp_" . $name ) ) {
			$name = "wp_" . $name;
			global $post;
			$post = $this;
			setup_postdata( $this );

			return call_user_func_array( $name, $args );
		} else {
			trigger_error( 'Attempt to call non existent method ' . $name . ' on class ' . get_class( $this ) );
		}
	}

	/**
	 * Proxy magic properties to WP_Post
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->post->$name;
	}

	/**
	 * Proxy magic properties to WP_Post
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function __set( $name, $value ) {
		return $this->post->$name = $value;
	}

	/**
	 * Proxy magic properties to WP_Post
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __isset( $name ) {
		return isset( $this->post->$name );
	}

	public function title() {
		return apply_filters( 'the_title', $this->post_title, $this->ID );
	}

	public function content() {
		return apply_filters( 'the_content', $this->post_content );
	}

	public function permalink() {
		return get_permalink( $this->ID );
	}

	/**
	 * Gets the metadata (custom fields) for the post
	 *
	 * @param string $name
	 * @param bool   $default
	 * @param bool   $single
	 *
	 * @return array|string
	 */
	public function meta( $name, $default = false, $single = true ) {
		$meta = get_post_meta( $this->ID, $name, $single );

		if ( ! $meta && ! $single ) {
			$meta = $default;
		}

		return $meta;
	}

	/**
	 * Get the URL of the featured image
	 *
	 * @param string $image_size
	 *
	 * @return string|false
	 */
	public function featured_image_url( $image_size = 'thumbnail' ) {
		$attachment_id = $this->meta( '_thumbnail_id' );
		if ( ! $attachment_id ) {
			return false;
		}

		$image = wp_get_attachment_image_src( $attachment_id, $image_size );
		if ( $image && isset( $image[0] ) ) {
			return $image[0];
		}

		return false;
	}

	protected static function get_post_type() {
		$parts   = explode( '\\', get_called_class() );
		$search  = 'Model';
		$replace = 'PostType';
		$parts   = array_map( function ( $part ) use ( $search, $replace ) {
			return $part == $search ? $replace : $part;
		}, $parts );

		$type = implode( '\\', $parts );

		if ( class_exists( $type ) ) {
			return call_user_func( array( $type, 'get_post_type' ) );
		}

		return 'post';
	}

	/**
	 * Create new post in the database.
	 *
	 * @param string  $title
	 * @param  string $content
	 * @param array   $args
	 *
	 * @return int|\WP_Error
	 */
	public static function create( $title, $content, $args = array() ) {
		$defaults = array(
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_type'    => self::get_post_type(),
			'post_content' => $content,
		);

		return wp_insert_post( array_merge( $args, $defaults ) );
	}
}
