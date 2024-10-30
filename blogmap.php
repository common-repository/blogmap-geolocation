<?php
/*
Plugin Name: BlogMap Geolocation
Plugin URI: http://wordpress.org/extend/plugins/blogmap/
Description: Geotag your post to give geografical information about it and possibly share it on http://caribe1999.altervista.org/blogmap/
Version: 1.0.1
Author: Vincenzo Buttazzo
Author URI: http://caribe1999.altervista.org/blogmap/
License: GPL2
*/

/*  Copyright 2011 Vincenzo Buttazzo (email : vbuttazzo@yahoo.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Post display actions
add_action('wp_head', 'blogmap_head');
add_filter('the_content', 'blogmap_display');
add_action('publish_post', 'blogmap_publish');

// Admin settings
add_action('admin_menu', 'blogmap_admin');

// Post edit actions
add_action('admin_head-post-new.php', 'blogmap_custom_box_head');
add_action('admin_head-post.php', 'blogmap_custom_box_head');
add_action('save_post', 'blogmap_save');

// Activation
register_activation_hook(__FILE__, 'blogmap_activate');

// Translations
load_plugin_textdomain('blogmap', false, basename(dirname(__FILE__)).'/languages');


function blogmap_activate() {
	blogmap_register();
	add_option('blogmap_map_width', '320');
	add_option('blogmap_map_height', '240');
	add_option('blogmap_map_position', 'after');
	add_option('blogmap_map_zoombar', '1');
	add_option('blogmap_map_maptypes', '1');
	add_option('blogmap_map_share', '1');
}


function blogmap_admin() {
	if (is_admin()){
		add_options_page('BlogMap Geolocation Settings', 'BlogMap', 'administrator', 'blogmap.php', 'blogmap_settings_page');
		add_action('admin_init', 'blogmap_register');
	}
	add_meta_box('blogmap_custom_box', __('BlogMap Geolocation', 'blogmap'), 'blogmap_custom_box_body', 'post', 'advanced');
}



function blogmap_custom_box_body() {
	global $post;
	$post_id = $post->ID;

	$lat = get_post_meta($post_id, 'blogmap_lat', true);
	$lng = get_post_meta($post_id, 'blogmap_lng', true);
	$zoom = get_post_meta($post_id, 'blogmap_zoom', true);
	$enabled = get_post_meta($post_id, 'blogmap_enabled', true);
	$w = get_option('blogmap_map_width');
	$h = get_option('blogmap_map_height');

?>
<input type="hidden" name="blogmap_nonce" value="<?php echo wp_create_nonce(plugin_basename(__FILE__) ) ?>" />
<input type="hidden" name="blogmap_lat" value="<?php echo $lat ?>" />
<input type="hidden" name="blogmap_lng" value="<?php echo $lng ?>" />
<input type="hidden" name="blogmap_zoom" value="<?php echo $zoom ?>" />

<p><label><input type="checkbox" name="blogmap_enabled" value="1" <?php echo $enabled ? 'checked="checked"' : '' ?> onchange="BlogMap.toggle(this)"/> <?php _e('Enabled', 'blogmap') ?></label></p>

<div id="blogmap-box" style="display:none">

	<input type="text" name="blogmap_address" class="newtag form-input-tip" size="25" autocomplete="off" value="" onkeypress="return BlogMap.avoid_submit(event)" />
	<input type="button" class="button" value="<?php _e('Search place', 'blogmap') ?>" tabindex="3" onclick="BlogMap.search()" />

	<div id="blogmap-map" style="width:<?php echo $w ?>px;height:<?php echo $h ?>px"></div>

</div>

<script type="text/javascript">
BlogMap.init();
</script>

<?php
}



function blogmap_custom_box_head() {
	global $post;
	$post_id = $post->ID;
	$post_type = $post->post_type;
	$zoom = (int) get_option('geolocation_default_zoom');
	?>
		<link type="text/css" rel="stylesheet" href="<?php echo esc_url(plugins_url('style.css', __FILE__)) ?>" />
		<script src="http://api.maps.ovi.com/jsl.js" type="text/javascript" charset="utf-8"></script>
		<script type="text/javascript">
			BlogMap = {
				lat: 41.893055,
				lng: 12.482778,
				zoom: 6,
				init: function() {
					if (document.post.blogmap_lat.value) this.lat = parseFloat(document.post.blogmap_lat.value);
					if (document.post.blogmap_lng.value) this.lng = parseFloat(document.post.blogmap_lng.value);
					if (document.post.blogmap_zoom.value) this.zoom = parseFloat(document.post.blogmap_zoom.value);

					ovi.mapsapi.util.ApplicationContext.set({"appId": "hBy4ppkqkZvANckjrmu1", "authenticationToken": "b7Upe6KH4RImpmM83EkiBA=="});
					this.map = new ovi.mapsapi.map.Display(document.getElementById("blogmap-map"), {
						components: [
							new ovi.mapsapi.map.component.Behavior(),
							new ovi.mapsapi.map.component.ZoomBar()
						],
						'zoomLevel': this.zoom,
						'center': [this.lat, this.lng]
					});
					this.map.addObserver('zoomLevel', function(e) { document.post.blogmap_zoom.value = BlogMap.map.zoomLevel });

					this.manager = new ovi.mapsapi.search.Manager();
					this.manager.addObserver('state', function(manager, key, value) { BlogMap.searched(manager, key, value) });

					this.marker = new ovi.mapsapi.map.StandardMarker([this.lat, this.lng], {
						draggable: true,
					});
					this.marker.addListener('dragend', function(e) { BlogMap.moved(e) });
					this.map.objects.add(this.marker);
					this.toggle(document.post.blogmap_enabled);
				},
				moved: function(e) {
					var coordinate = this.marker.get('coordinate');
					document.post.blogmap_lat.value = coordinate.latitude;
					document.post.blogmap_lng.value = coordinate.longitude;
				},
				search: function() {
					this.manager.search(document.post.blogmap_address.value);
				},
				searched: function(manager, key, value) {
					if (value == 'finished') {
						if (manager.locations.length > 0) {
							var rs = (new ovi.mapsapi.search.component.SearchResultSet(manager.locations)).container;
							this.map.zoomTo(rs.objects.get(0).getBoundingBox());
							if (this.map.zoomLevel > 16) this.map.set("zoomLevel", 16);
							this.marker.set('coordinate', rs.objects.get(0).get('coordinate'));
						}
					}
				},
				toggle: function(checkbox) {
					document.getElementById('blogmap-box').style.display = checkbox.checked ? 'block' : 'none';
				},
				avoid_submit: function(e) {
					e = e ? e : window.event;
					var k = e.keyCode ? e.keyCode : e.which ? e.which : null;
					if (k == 13) return false;
					return true;
				}
			}
		</script>
	<?php
}



function blogmap_save($post_id) {
	// Check authorization, permissions, autosave, etc
	if (!wp_verify_nonce($_POST['blogmap_nonce'], plugin_basename(__FILE__)))
		return $post_id;

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return $post_id;

	if('page' == $_POST['post_type'] ) {
		if(!current_user_can('edit_page', $post_id))
		return $post_id;
	} else {
		if(!current_user_can('edit_post', $post_id))
		return $post_id;
	}

	update_post_meta($post_id, 'blogmap_lat', $_POST['blogmap_lat']);
	update_post_meta($post_id, 'blogmap_lng', $_POST['blogmap_lng']);
	update_post_meta($post_id, 'blogmap_zoom', (int)$_POST['blogmap_zoom']);
	update_post_meta($post_id, 'blogmap_enabled', (int)$_POST['blogmap_enabled']);

	return $post_id;
}


function blogmap_publish($post_id) {
	if (function_exists('curl_init') and get_option('blogmap_map_share') == 1) {
		$post = get_post($post_id);
		$author = get_userdata($post->post_author);

		$ch = curl_init("http://caribe1999.altervista.org/blogmap/api.php");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'cmd' => 'post',
			'url' => get_permalink($post_id),
			'title' => $post->post_title,
			'author' => $author->user_nicename,
			'email' => $author->user_email,
			'lat' => get_post_meta($post_id, 'blogmap_lat', true),
			'lng' => get_post_meta($post_id, 'blogmap_lng', true),
		));
		$result = curl_exec($ch);
		curl_close($ch);
	}
}


function blogmap_head() {
?>
<link type="text/css" rel="stylesheet" href="<?php echo esc_url(plugins_url('style.css', __FILE__)) ?>" />
<script src="http://api.maps.ovi.com/jsl.js" type="text/javascript" charset="utf-8"></script>
<script type="text/javascript">/* <![CDATA[ */
ovi.mapsapi.util.ApplicationContext.set({"appId": "hBy4ppkqkZvANckjrmu1", "authenticationToken": "b7Upe6KH4RImpmM83EkiBA=="});
/* ]]> */
</script>
<?php
}



function blogmap_display($content) {
	global $post;
	$post_id = $post->ID;

	if (!get_post_meta($post_id, 'blogmap_enabled', true)) return $content;

	$lat = get_post_meta($post_id, 'blogmap_lat', true);
	$lng = get_post_meta($post_id, 'blogmap_lng', true);
	$zoom = get_post_meta($post_id, 'blogmap_zoom', true);

	if ($lat == '' or $lng == '') return $content;

	$w = get_option('blogmap_map_width');
	$h = get_option('blogmap_map_height');

	$zoombar = get_option('blogmap_map_zoombar') ? 'new ovi.mapsapi.map.component.ZoomBar(),' : '';
	$maptypes = get_option('blogmap_map_maptypes') ? 'new ovi.mapsapi.map.component.TypeSelector(),' : '';

	$map = <<<TEXTEND
<div class='blogmap-wrap' style='width:{$w}px;height:{$h}px'>
	<div id='blogmap-map-$post_id' class='blogmap-map'></div>
	<div class="blogmap-link"><a href="http://caribe1999.altervista.org/blogmap/?p=$lat,$lng" target="_blank">Search georelated posts on BlogMap</a></div>
</div>
<script type="text/javascript">

var map = new ovi.mapsapi.map.Display(document.getElementById("blogmap-map-$post_id"), {
	components: [
		$zoombar$maptypes
		new ovi.mapsapi.map.component.Behavior()
	],
	'zoomLevel': $zoom,
	'center': [$lat, $lng]
});

map.objects.add(new ovi.mapsapi.map.StandardMarker([$lat, $lng], { draggable: false }));

</script>
TEXTEND;

	switch (get_option('blogmap_map_position')) {
		case 'after':
			$content = $content.$map;
			break;
		case 'before':
			$content = $map.$content;
			break;
	}

	return $content;
}


function blogmap_register() {
	register_setting('blogmap-settings-group', 'blogmap_map_width', 'intval');
	register_setting('blogmap-settings-group', 'blogmap_map_height', 'intval' );
	register_setting('blogmap-settings-group', 'blogmap_map_position' );
	register_setting('blogmap-settings-group', 'blogmap_map_zoombar', 'intval');
	register_setting('blogmap-settings-group', 'blogmap_map_maptypes', 'intval');
	register_setting('blogmap-settings-group', 'blogmap_map_share', 'intval');
}



function blogmap_is_value($field, $value) {
	if (get_option($field) == $value) echo ' checked="checked" ';
}



function blogmap_default_settings() {
	if (get_option('blogmap_map_width') === false) update_option('blogmap_map_width', '320');
	if (get_option('blogmap_map_height') === false) update_option('blogmap_map_height', '240');
	if (get_option('blogmap_map_position') === false) update_option('blogmap_map_position', 'after');
	if (get_option('blogmap_map_zoombar') === false) update_option('blogmap_map_zoombar', '1');
	if (get_option('blogmap_map_maptypes') === false) update_option('blogmap_map_maptypes', '1');
	if (get_option('blogmap_map_share') === false) update_option('blogmap_map_share', '1');
}



function blogmap_settings_page() {

	blogmap_default_settings();

?>
<div class="wrap">
	<h2>Geolocation Plugin Settings</h2>

	<form method="post" action="options.php">
		<?php settings_fields('blogmap-settings-group'); ?>
		<input type="hidden" name="page_options" value="blogmap_map_width,blogmap_map_height,blogmap_map_zoombar,blogmap_map_maptypes,blogmap_map_share" />
		<table class="form-table">
			<tr>
				<th>Dimensions</th>
				<td>
					<p>
						<strong>Width:</strong>
						<input type="text" name="blogmap_map_width" value="<?php echo esc_attr(get_option('blogmap_map_width')); ?>" size="6" />px
					</p>
					<p>
						<strong>Height:</strong>
						<input type="text" name="blogmap_map_height" value="<?php echo esc_attr(get_option('blogmap_map_height')); ?>" size="6" />px
					</p>
				</td>
			</tr>
			<tr>
				<th>Position</th>
				<td>
					<p>
						<label>
							<input type="radio" name="blogmap_map_position" value="before" <?php blogmap_is_value('blogmap_map_position', 'before'); ?> />
							Before the post
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="blogmap_map_position" value="after" <?php blogmap_is_value('blogmap_map_position', 'after'); ?> />
							After the post
						</label>
					</p>
				</td>
			</tr>
			<tr>
				<th>Options</th>
				<td>
					<p>
						<label>
							<input type="checkbox" name="blogmap_map_share" value="1" <?php blogmap_is_value('blogmap_map_share', '1'); ?> />
							<strong>Share your post's position on <a href="http://caribe1999.altervista.org/blogmap/" target="_blank">BlogMap</a></strong>
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="blogmap_map_zoombar" value="1" <?php blogmap_is_value('blogmap_map_zoombar', '1'); ?> />
							Display zoom bar (requires at least 360px height)
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="blogmap_map_maptypes" value="1" <?php blogmap_is_value('blogmap_map_maptypes', '1'); ?> />
							Display map type selector
						</label>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>

	</form>
</div>
<?php
}

?>