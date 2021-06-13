?>
<form name="home_search" id="home_search" method="post" role="form" action="<?php echo site_url('our-listings') ?>">
	<input type="hidden" name="home_search" value="1" />
	<label for="city">CITY</label>
	<select id="city" name="city">
		<option value="">Select City</option>
		<?php
			$metakey = 'City';
			$cities = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key ='City' ORDER BY meta_value ASC", $metakey) );
			if ($cities) {
				foreach ($cities as $city) {
					echo "<option value=\"" . $city . "\"".($city===get_query_var('city')?'selected':'')." >" . $city . "</option>";
				}
			}
		?>
	</select>
	<label for="OrderType">ORDER TYPE</label>
	<select id="OrderType" name="OrderType">
		<option value="">Select Order Type</option>
		<?php
			$metakey = 'OrderType';
			$order_types = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key ='OrderType' ORDER BY meta_value ASC", $metakey) );
			if ($order_types) {
				foreach ($order_types as $order_type) {
					echo "<option value=\"" . $order_type . "\"".($order_type===get_query_var('ordertype')?'selected':'')." >" . $order_type . "</option>";
				}
			}
		?>
	</select>
	<input type="submit" id="search" name="search" value="Search" />
</form>