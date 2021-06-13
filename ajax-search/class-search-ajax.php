<?php

namespace ZachWatkins;

// Redirect users home who try to view this file directly.
if ( ! defined( 'ABSPATH' ) ) {
    // Call dirname() 4 times as this file is in '/wp-contents/plugins/plugin-dir/' and wp-config.php is in '/'
    require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-config.php';
    // Permanent redirect home.
    wp_safe_redirect( home_url(), 301 );
    die;

}

class Search_Ajax {

	private $vars = array( 'city', 'minprice', 'maxprice', 'pc', 'mac', 'addon' );

	function __construct(){
		add_filter( 'query_vars', array( $this, 'register_search_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect_search_page_missing_args' ) );
		add_action( 'wp_ajax_search_orders', array( $this, 'search_orders' ) );
		add_action( 'wp_ajax_nopriv_search_orders', array( $this, 'search_orders' ) );
	}

	public function register_search_query_vars( $vars ) {

		$vars = array_merge( $vars, $this->vars );
		return $vars;

	}

	/**
	 * Ensure active search query parameters are included in the URL. This is usually only needed after submitting the search form on the home page or on the search page.
	 * 
	 * @return void
	 */
	public function redirect_search_page_missing_args() {
		$search_page_templates = array( home_url(), home_url('/'), '/our-listings/', '/our-listings', '/all-listings/', '/all-listings' );
		if ( in_array( $_SERVER['REQUEST_URI'], $search_page_templates ) ) {
			// If the search page has an empty query string and $_POST parameters then redirect the request to the same URL with query parameters.
			// Build an array of key/value pairs for the current query string, if present.
			$current_query_string = array();
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$queries = explode( '&', $_SERVER['QUERY_STRING'] );
				foreach ($queries as $value) {
					if ( false !== strpos( $value, '=' ) ){
						$pair = explode( '=', $value );
						$current_query_string[$pair[0]] = $pair[1];	
					} elseif ( $value ) {
						$current_query_string[$value] = '';
					}
				}
			}
			// Loop through all possible query vars and check for their use and if they are not empty.
			$query_options = $this->vars;
			$query_args    = $current_query_string;
			foreach ( $query_options as $key ) {
				$query_var = urlencode( get_query_var( $key ) );
				if ( 
					(
						! array_key_exists( $key, $current_query_string )
						&& $query_var
					)
					|| (
						array_key_exists( $key, $current_query_string )
						&& $current_query_string[ $key ] !== $query_var
					)
				) {
					// The query option is active and is either not in the URL or is not equal to the URL's value.
					$query_args[ $key ] = $query_var;
				}
			}
			// If coming from the home page, which has a different way of searching for meta values, convert those POST variables into query vars and URL parameters if this has not already been done.
			// The OrderType parameter is a dropdown so its key/value pair in the $_POST parameter aren't like the search page's form field and its key/value pair.
			if (
				isset( $_POST['home_search'] )
				&& $_POST['home_search']
				&& isset( $_POST['OrderType'] )
				&& is_string( $_POST['OrderType'] )
				&& $_POST['OrderType']
			) {
				$key          = strtolower( str_replace( '-', '', $_POST['OrderType'] ) );
				$pt_query_var = get_query_var( $key );
				if ( ! $pt_query_var || $pt_query_var !== 'yes' ) {
					set_query_var( $key, 'yes' );
				}
				if ( ! array_key_exists( $key, $query_args ) || $query_args[ $key ] !== 'yes' ) {
					$query_args[ $key ] = 'yes';
				}
			}
			// Now $query_args is populated with key/value pairs for both active URL parameters and query vars found in the registered list of vars.
			if ( $query_args ) {
				foreach ($query_args as $key => $value) {
					$query_args[ $key ] = "{$key}={$value}";
				}
				$query_args = implode('&', $query_args);
				if ( $query_args !== $_SERVER['QUERY_STRING'] ) {
					$url = $_SERVER['HTTP_ORIGIN'] . $_SERVER['REQUEST_URI'] . '?' . $query_args;
					if ( wp_safe_redirect( $url ) ) {
						exit;
					}
				}
			}
		}
	}

	/**
	 * 
	 */
	public function search_orders() {
		// Ensure nonce is valid.
		check_ajax_referer( 'search_orders' );
		// Ensure referer is valid.
		$url = wp_get_referer();
		if ( false === $url ) {
			wp_send_json_error( array( 'message' => 'The referer was not found.' ), 401 );
		} else {
			$url = preg_replace('/\?.*$/', '', $url);
			$url = preg_replace('/page\/\d+\//', '', $url);
			$url = preg_replace('/\/$/', '', $url) . '/';
			if ( $url && isset($_POST) && wp_doing_ajax() ) {
				$valid_urls = array(
					home_url() . '/our-listings/',
					home_url() . '/all-listings/',
				);
				if ( in_array( $url, $valid_urls ) ) {
					global $wp_query;
					$json_out   = array( 'status' => 'success' );
					$query_vars = $this->get_query_vars( $_POST );
					$page       = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
					$meta_args  = $this->convert_to_meta_query( $query_vars );
					$post_query               = $this->get_active_order_query( $meta_args, $page );
	    		$GLOBALS['wp_query']      = $post_query;
					$content                  = $this->make_listings( $post_query->posts );
					$page_numbers             = $this->make_page_numbers( $_POST, $post_query );
					$json_out['new_url']      = $this->get_new_url( $_POST['paginate_base'], $page, $query_vars );
					$json_out['page_numbers'] = $page_numbers;
					$json_out['posts']        = $content;
					$json_out['total']        = $post_query->found_posts;
					wp_send_json_success( $json_out );
				} else {
					wp_send_json_error( array( 'message' => 'You cannot run that action on this page.' ), 405 );
				}
			} else {
				wp_send_json_error( array( 'message' => 'You did not submit a complete request.' ), 404 );
			}
		}
	}

	/**
	 * Get the query vars from the $_POST object.
	 */
	private function get_query_vars( $post ) {
		$query_vars = array();
    foreach ( $this->vars as $key ) {
      if ( isset( $post[ $key ] ) && $post[ $key ] ) {
        $query_vars[ $key ] = sanitize_text_field( wp_unslash( $post[ $key ] ) );
      }
    }
    return $query_vars;
  }

  /**
   * Run a query on active order posts.
   * 
   * @param array $meta_query The meta query populated by active query vars in the $_POST object.
   * @param int   $page       The requested page number.
   * 
   * @return object WP_Query
   */
	public function get_active_order_query( $meta_query, $page=1 ) {

		$meta_query[] = array(
			'key'     => 'Status',
			'value'   => array('Active','Pending','Pending Logistics Approval'),
			'compare' => 'IN',
		);
		
		$args = array(
			'posts_per_page' => 10,
			'post_type'      => 'order',
			'post_status'    => 'publish',
			'paged'          => intval( $page ),
			'meta_key'       => 'ModifiedTimestamp',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => $meta_query,
			'fields'         => 'ids',
		);

		return new \WP_Query( $args );

	}

	/**
	 * Translates an associative array into a URL query string.
	 */
	public function get_new_url( $paginate_base, $page, $query_vars ) {
		if ( $page > 1 ) {
			$base_url = str_replace('%#%', $page, $paginate_base);
		} else {
			$base_url = str_replace('page/%#%/', '', $paginate_base);
		}
		if ( $query_vars ) {
			foreach ( $query_vars as $key => $value ) {
				$query_vars[$key] = $key . '=' . urlencode( $value );
			}
			return $base_url . '?' . implode('&', $query_vars);
		} else {
			return $base_url;
		}
	}

	/**
	 * Translates an associative array into WP_Query meta query parameters.
	 * 
	 * @param array $order_query_vals Associative array of query vars and their values.
	 */
	public function convert_to_meta_query( $order_query_vals ) {
		
		$meta_query = array();

		if ( isset( $order_query_vals['city'] ) ) {
			$meta_query[] = array(
				'key'     => 'City',
				'value'   => $order_query_vals['city'],
				'compare' => '=',
			);		
		}	
					
		if ( isset( $order_query_vals['minprice'] ) || isset( $order_query_vals['maxprice'] ) ) {
      $price_param = array( 0, 9999999999 );
      if ( $order_query_vals['minprice'] ) {
        $price_param[0] = intval( $order_query_vals['minprice'] );
      }
      if ( $order_query_vals['maxprice'] ) {
        $price_param[1] = intval( $order_query_vals['maxprice'] );
      }
			$meta_query[] = array(
				'key'     => 'ListPrice',
				'value'   => $price_param,
				'type'    => 'numeric',
				'compare' => 'BETWEEN',
			);		
		}
					
		$order_type_query_vals = array();
    if( isset( $order_query_vals['pc'] ) ) {
      $order_type_query_vals[] = 'PC';
    }
    if( isset( $order_query_vals['mc'] ) ) {
      $order_type_query_vals[] = 'Mac';
    }
    if( isset( $order_query_vals['addon'] ) ) {
      $order_type_query_vals[] = 'Add-On';
    }
    if ( $order_type_query_vals ) {
			$meta_query[] = array(
				'key'     => 'OrderType',
				'value'   => $order_type_query_vals,
				'compare' => 'IN',
			);		
		}

		return $meta_query;

	}

	// Todo.
	private function make_page_numbers( $order_query_vals, $query ){
		$paginate_args = array();
    foreach ( $this->vars as $key ) {
      if ( $order_query_vals[ $key ] ) {
        $paginate_args[ $key ] = $order_query_vals[ $key ];
      }
    }
    $content = paginate_links(
    	array(
        'base'      => sanitize_text_field( wp_unslash( $order_query_vals['paginate_base'] ) ),
				'format'    => '?paged=%#%',
        'current'   => max( 1, $query->query_vars['paged'] ), 
        'total'     =>  $query->max_num_pages,
        'prev_text' => 'Previous',
        'next_text' => 'Next',
        'type'      => 'list',
        'add_args'  => $paginate_args,
      )
    );
		return $content;
	}

	private function make_listings( $post_ids ) {
		$content = '';
		if ( count( $post_ids ) > 0 ) {
			foreach ( $post_ids as $key => $post_id ) {
				$content .= '<a href="' . get_permalink($post_id) . '">' . get_the_content( $post_id ) . '</a>';
			}
		} else {
			$content = 'No orders found.';
		}
		return $content;
	}
}