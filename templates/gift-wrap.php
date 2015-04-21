<p class="gift-wrapping" style="clear:both; padding-top: .5em;">
	<label for="gift_wrap"><input type="checkbox" id="gift_wrap" name="gift_wrap" value="yes" <?php checked( $current_value, 1, false ); ?>> <?php echo str_replace( '{price}', $price_text, wp_kses_post( $product_gift_wrap_message ) ); ?></label>
</p>