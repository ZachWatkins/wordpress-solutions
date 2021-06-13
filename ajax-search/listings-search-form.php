?>
<form name="search_orders" id="search_orders" method="post" role="form">
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
	<input type="checkbox" name="pc" value="yes" <?php echo get_query_var('pc') === 'yes'?'checked ':''; ?>/><label>PC</label>
	<input type="checkbox" name="mac" value="yes" <?php echo get_query_var('mac') === 'yes'?'checked ':''; ?>/><label>Mac</label>
	<input type="checkbox" name="addon" value="yes" <?php echo get_query_var('addon') === 'yes'?'checked ':''; ?>/><label>Addon</label>
	<input type="submit" id="search" name="search" value="Search" />
</form>