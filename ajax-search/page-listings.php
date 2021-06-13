<?php

$query_vals = array(
  'city'      => '',
  'state'     => '',
  'minprice'  => '',
  'maxprice'  => '',
  'ordertype' => '',
);

// add meta_query elements
foreach ( $query_vals as $key => $value ) {
  if ( ! empty( get_query_var( $key ) ) ) {
    $query_vals[$key] = get_query_var( $key );
  }
}

include dirname( __FILE__ ) . '/listings-search-form.php';

$args = array(
  'posts_per_page'   => 10,
  'post_type'        => 'itorder',
  'post_status'      => 'publish',
  'paged'=>get_query_var('paged'),
  'meta_key'=> 'ModifiedTimestamp',
  'orderby' =>'meta_value',
  'order'=>'DESC',
  'meta_query' => $meta_query
);
$order_query = new WP_Query( $args ); 
if($order_query->have_posts()) {
  while($order_query->have_posts()) {
    $order_query->the_post();
  }
}
?>
<div id="page-numbers" class="paging-list">
<?php
  $paginate_args = array();
  foreach ($query_vals as $key => $value) {
    if ( $value ) {
      $paginate_args[$key] = $value;
    }
  }
  $paginate_base = str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) );
  $paginate_base = preg_replace( '/\?.*$/', '', $paginate_base );
  echo "".paginate_links(array(  
     // 'base' => get_pagenum_link(1) . '%_%',  
      'base' => $paginate_base,
      'format' => '?paged=%#%',  
      'current' => max( 1, get_query_var('paged') ),  
      'total' =>  $order_query->max_num_pages,  
      'prev_text' => 'Previous',  
      'next_text' => 'Next',
      'type'     => 'list',
      'add_args' => $paginate_args,
    )); 
  ?>

</div>
<script type="text/javascript">
(function($){
  var running       = false;
  var admin_ajax    = '<?php echo admin_url('admin-ajax.php'); ?>';
  var nonce         = '<?php echo wp_create_nonce('search_orders'); ?>';
  var paginate_base = '<?php echo $paginate_base; ?>';
  var $form         = $('#search_orders');
  var $response     = $form.find('.ajax-response');
  // Todo: make reset button work.
  $form.find('#reset').on('click',resetSearch);
  $form.submit(ajaxSearchSubmit);
  $('#page-numbers').on('click', 'a', ajaxSearchSubmit);
  function ajaxSearchSubmit(e) {
    if ( running === true ) {
      return;
    }
    e.preventDefault();
    running = true;
    $('body').addClass('ajax-order-search-running');
    $response.html('');
    var form_data = new FormData();
    if ( e.target.tagName === 'FORM' ) {
      var target_url = document.origin + document.pathname;
      var target_url_query = [];
      // Get search query parameters from form fields.
      $form.find('input:not([type="checkbox"]):not([type="file"]),select,textarea').each(function(){
        if ( this.value !== '' ) {
          form_data.append(this.name, this.value);
          target_url_query.push(this.name+'='+this.value);
        }
      });
      $form.find('input[type="checkbox"]').each(function(){
        if ( this.checked ) {
          form_data.append(this.name, 'on');
          target_url_query.push(this.name+'=on');
        }
      });
      if ( target_url_query.length > 0 ) {
        target_url += '?' + target_url_query.join('&');
      }
    } else if ( 'A' === e.target.tagName ) {
      // Get search query parameters from link href attribute.
      var target_url = e.target.href;
      var page_number_matches = e.target.href.match(/paged?[\/=]{1}(\d+)/);
      if ( page_number_matches.length > 1 ) {
        form_data.append('paged', parseInt(page_number_matches[1]));
      }
      var query_params = e.target.href.match(/\?.*$/);
      if ( query_params ) {
        query_params = query_params[0].replace(/^\?/,'').split('&');
        for ( var i=0; i < query_params.length; i++ ) {
          var parts = query_params[i].split('=');
          if ( parts.length > 1 ) {
            form_data.append(parts[0], parts[1]);
          }
        }
      }
    }
    form_data.append('action', 'search_orders');
    form_data.append('_ajax_nonce', nonce);
    form_data.append('paginate_base', paginate_base);
    $.ajax({
      type: "POST",
      url: admin_ajax,
      contentType: false,
      processData: false,
      data: form_data,
      success: function(response) {
        if ( response.success ) {
          // Update order posts.
          $('#order-posts').html(response.data.posts);
          // Update page numbers.
          $('#page-numbers').html(response.data.page_numbers);
          // Update URL.
          window.history.pushState({"posts":response.data.posts,"pagenumbers":response.data.page_numbers}, '', response.data.new_url);
          // Scroll to top.
          $(window).scrollTop($("h1").offset().top-50);
          // Reset running state.
          running = false;
          $('body').removeClass('ajax-order-search-running');
        } else {
          running = false;
          $('body').removeClass('ajax-order-search-running');
          document.location.href = target_url;
        }
      },
      error: function( jqXHR, textStatus, errorThrown ) {
        running = false;
        $('body').removeClass('ajax-order-search-running');
        document.location.href = target_url;
      }
    });
    return false;
  }
  // Part of the JavaScript History API.
  // Change the page's content to reflect the AJAX content loaded at the chosen point in the page history.
  var changeSearchState = function(e){
    if(e.state){
      $('#order-posts').html(e.state.posts);
      $('#page-numbers').html(e.state.pagenumbers);
    }
  };
  window.onpopstate = changeSearchState;
})(jQuery);
</script>