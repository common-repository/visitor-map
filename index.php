<?php 
/*
	Plugin Name: Visitor Map
	Description: Displays A Visitor Map with location ip, city, and country
	Version: 1.0
	Author: Blixt Gordon
	Author URI: http://visitormap.se
	License: GPLv2
*/

        wp_enqueue_style( 'visitor_map_style', plugins_url('css/style.css',__FILE__));


		function visitor_plugin_jquery_scripts_method() {

        	$apikey = sanitize_text_field(esc_attr( get_option('mapapikey') ));

        	$apimapkey = ($apikey != '') ? $apikey : 'AIzaSyDgiem5bbeBic1L2fke9TiNU71piPtzI2o';

		    $gmaps_url = 'http://maps.googleapis.com/maps/api/js?key=' . $apimapkey . '&v=3.exp';
		    
		    wp_register_script('visitor_map_googleapi', $gmaps_url , array('jquery'));
		    wp_enqueue_script( 'visitor_map_googleapi' ,array('jquery'));

		}
		
		add_action('wp_enqueue_scripts', 'visitor_plugin_jquery_scripts_method');

		function Visitor_map_myscript() {
		    if( wp_script_is( 'visitor_map_googleapi', 'done' ) ) {
		    ?>

		    <script type="text/javascript">
		      	jQuery(document).ready( function($) {
					
					var data = {
						'action': 'visitor_map_ajax_function',
						'resp': '',
						'url':''
					};

					jQuery.get("http://ip-api.com/json",function(response) {
				       	data.resp = response;
				       
				       	data.url = '<?=$_SERVER['REQUEST_URI']?>';

				       	jQuery.get('<?php echo admin_url('admin-ajax.php'); ?>',data,function(response) {
							
						});
					});

				});

		    </script>
		    <?php
		    }
		}
		add_action( 'wp_footer', 'Visitor_map_myscript' );



		// Table Created
		global $wpdb;		
		$charset_collate = $wpdb->get_charset_collate();

		function Visitor_map_plugin_activate() {

			$sql = "CREATE TABLE visitor (
				`id` mediumint(9) NOT NULL AUTO_INCREMENT,
				`user` VARCHAR(50) NOT NULL,
				`ip` VARCHAR(20) NOT NULL UNIQUE,
				`city` VARCHAR(30) NOT NULL,
				`state` VARCHAR(30) NOT NULL,
				`country` VARCHAR(30) NOT NULL,
				`country_code` VARCHAR(5) NOT NULL,
				`zip` VARCHAR(6) NOT NULL,
				`timezone` VARCHAR(40) NOT NULL,
				`entrytime` TIMESTAMP NOT NULL,
				`lasttime` TIMESTAMP NOT NULL,
				`latitude` VARCHAR(20) NOT NULL,
				`longitude` VARCHAR(20) NOT NULL,
				`page_url` VARCHAR(40) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
		 
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			update_option( 'activetime' , 100);
			update_option( 'storedays' , 30);

		}

		// sanitized/validate ip address
		function validate_ip_address($ip_address) {
	 
		  // sanitized ip address
		  $clean_ip_address = addslashes(htmlspecialchars(strip_tags(trim($ip_address))));
		 
		  // the regular expression for valid ip addresses
		  $reg_ex = '/^((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))*$/';
		 
		  // test input against the regular expression
		  if (preg_match($reg_ex, $clean_ip_address)) { 
		    return TRUE; // it's a valid ip address
		  }
		 
		}

		// get values form API call and insert into table
		function visitor_map_ajax_function(){
				
			$ip_address = sanitize_text_field($_GET['resp']["query"]);
			$ip = (validate_ip_address($ip_address))?$ip_address:"";

			$city = sanitize_text_field($_GET['resp']["city"]);
			$state = sanitize_text_field($_GET['resp']["regionName"]);			
			$country = sanitize_text_field($_GET['resp']["country"]);	

			$countrycode = sanitize_text_field($_GET['resp']["countryCode"]);	
			$country_code = ( strlen( $countrycode ) < 4 ) ? $countrycode : ""; 

			// For ZIP Code
			$zip = intval( $_GET['resp']["zip"] );
			if ( ! $zip ) {
			  $zip = '';
			}

			if ( strlen( $zip ) > 5 ) {
			  $zip = substr( $zip, 0, 5 );
			}

			$timezone = sanitize_text_field($_GET['resp']["timezone"]);			
			$latitude = floatval($_GET['resp']["lat"]);			
			$longitude = floatval($_GET['resp']["lon"]);

			date_default_timezone_set($timezone);
			$entryTime = date('Y-m-d H:i:s');
			
			
			$page_url = esc_url($_GET['url']);			

			$current_user = wp_get_current_user();
			$user = empty($current_user->user_login) ? 'Guest': $current_user->user_login;

    		global $wpdb;

    		$data = array();
			array_push( $data, $user, $ip, $city, $state, $country, $country_code, $zip, $timezone, $entryTime, $entryTime, $latitude, $longitude, $page_url);

			$query = "INSERT INTO `visitor` (`user`,`ip`, `city`, `state`, `country`, `country_code`, `zip`, `timezone`, `entrytime`, `lasttime`, `latitude`, `longitude`, `page_url`) VALUES ('%s','%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s') ON DUPLICATE KEY UPDATE lasttime='$entryTime',page_url='$page_url',user='$user';";
			

			$wpdb->query( $wpdb->prepare($query,$data) );

		}

		add_action( 'wp_ajax_visitor_map_ajax_function', 'visitor_map_ajax_function' );
		add_action( 'wp_ajax_nopriv_visitor_map_ajax_function', 'visitor_map_ajax_function' );

		// call on when page load
		function visitor_map_plugin_func(){

			if (isset($_GET['action']) && $_GET['action'] == "visitor_map_ajax_function"){
			
				visitor_map_ajax_function();

			}
		}

		add_action('wp_load', 'visitor_map_plugin_func');

		// drop table on deactivate plugins 
		function visitor_map_plugin_deactivate(){
			global $wpdb;
			$query = "DROP TABLE visitor";

			$wpdb->query( $query );
			
		}
		
		register_activation_hook(__FILE__,'Visitor_map_plugin_activate');
		register_deactivation_hook( __FILE__, 'visitor_map_plugin_deactivate' );


		//OPtion Menu 
		add_action('admin_menu', 'visitor_map_menu');

		function visitor_map_menu() {

			add_menu_page('Visitor Map plugins Options', 'Visitor Map', 'manage_options', 'my-menu', 'visitor_map_online_page','dashicons-location' );
		    add_submenu_page('my-menu', 'online visitor', 'Online Visitors', 'manage_options', 'my-menu');

		    add_submenu_page('my-menu', 'Settings Page', 'Settings', 'manage_options', 'my-submenu2','visitor_map_settings_page' );

			add_action( 'admin_init', 'register_visitorMap_plugin_settings' );

		}

		function register_visitorMap_plugin_settings() {
			//register our settings
			register_setting( 'visitor-map-plugin-settings-group', 'activetime' );
			register_setting( 'visitor-map-plugin-settings-group', 'storedays' );
			register_setting( 'visitor-map-plugin-settings-group', 'mapwidth' );
			register_setting( 'visitor-map-plugin-settings-group', 'mapheight' );
			register_setting( 'visitor-map-plugin-settings-group', 'mapmarker' );
			register_setting( 'visitor-map-plugin-settings-group', 'mapapikey' );
			
		}

		function visitor_map_settings_page() {
		?>
			<div class="wrap">
				<h2>Visitor Map Plugin Settings</h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'visitor-map-plugin-settings-group' ); ?>
					<?php do_settings_sections( 'visitor-map-plugin-settings-group' ); ?>
					<table class="form-table">
						<tr valign="top">
							<th style="width:250px;" scope="row">Google Map API KEY</th>
							<td><input style="width:300px;" placeholder="API KEY is Required For Google Maps" type="text" name="mapapikey" value="<?php echo sanitize_text_field(esc_attr( get_option('mapapikey') )); ?>" />
								<br>
								<a class="button button-primary" href="https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend,static_maps_backend,geocoding_backend,roads,street_view_image_backend,geolocation,places_backend&amp;keyType=CLIENT_SIDE&amp;reusekey=true" target="_blank" style="text-decoration:none;margin-top: 10px;">GET API KEY FOR FREE</a>
							</td>
						</tr>
						<tr valign="top">
							<th style="width:250px;" scope="row">Visitor Active Time (in minutes)</th>
							<td><input style="width:300px;" type="text" name="activetime" value="<?php echo sanitize_text_field(esc_attr( get_option('activetime') )); ?>" /></td>
						</tr>
						<tr valign="top">
							<th style="width:250px;" scope="row">Number of Days to Store Data</th>
							<td><input style="width:300px;" type="text" name="storedays" value="<?php echo sanitize_text_field(esc_attr( get_option('storedays') )); ?>" /></td>
						</tr>
						<tr valign="top">
							<th style="width:250px;" scope="row">Map Width (in px)</th>
							<td><input style="width:300px;" type="text" name="mapwidth" value="<?php echo sanitize_text_field(esc_attr( get_option('mapwidth') )); ?>" /></td>
						</tr>
						<tr valign="top">
							<th style="width:250px;" scope="row">Map Height (in px)</th>
							<td><input style="width:300px;" type="text" name="mapheight" value="<?php echo sanitize_text_field(esc_attr( get_option('mapheight') )); ?>" /></td>
						</tr>
						<tr valign="top">
							<th style="width:250px;" scope="row">Past image URL of Map marker icon</th>
							<td><input style="width:300px;" type="text" name="mapmarker" value="<?php echo sanitize_text_field(esc_attr( get_option('mapmarker') )); ?>" /></td>
						</tr>
					</table>
					
					<?php submit_button(); ?>
				</form>
			</div>
		<?php }

		function visitor_map_online_page() {
		?>
			<div class="wrap">
				<h2>Visitor Map Plugin - View Who's Online</h2>
				<?php 
					global $wpdb;
					$activetime = sanitize_text_field(get_option('activetime'));
					$result = $wpdb->get_results( $wpdb->prepare("SELECT * FROM visitor  WHERE %d",1) ); 
					$cot = $wpdb->get_results( $wpdb->prepare("SELECT (SELECT COUNT(user) FROM `visitor` WHERE TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as total,(SELECT COUNT(user) FROM `visitor` WHERE user='Guest' AND TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as guests,(SELECT COUNT(user) FROM `visitor` WHERE user!='Guest' AND TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as users",$activetime,$activetime,$activetime) );
					foreach ($cot as $v) {       			
	        			echo $v->total." visitors online<br/>";
	        			echo $v->guests." Guests, ".$v->users." Members online (includes you)";
        			}
					 ?>
					
					<table class="visitor">
						<tr>
							<th>User</th>
							<th>IP</th>
							<th>LOCATION</th>
							<th>ZIP</th>
							<th>TIME ZONE</th>
							<th>ENTRY TIME</th>
							<th>LAST TIME</th>
							<th>Total TIME</th>
							<th>LATITUDE</th>
							<th>LONGITUDE</th>
							<th>PAGE URL</th>
						</tr>

						<?php foreach($result as $row)
							{ 
								$start = strtotime($row->entrytime); 

								$end = strtotime($row->lasttime); 

								$totaltime = ($end - $start)  ; 

								$hours = intval($totaltime / 3600);   
								$seconds_remain = ($totaltime - ($hours * 3600)); 

								$minutes = intval($seconds_remain / 60);   
								$seconds = ($seconds_remain - ($minutes * 60)); 
								
								if($hours < 10) $hours = "0".$hours;
								if($minutes < 10) $minutes = "0".$minutes;
								if($seconds < 10) $seconds = "0".$seconds;
							?>
								<tr>
									<td><?php echo $row->user ?></td>
									<td><?php echo $row->ip ?></td>
									<td><?php echo "<img class='flag flag-".strtolower($row->country_code)."'/>"." ".$row->city.", ".$row->state ?></td>
									<td><?php echo $row->zip ?></td>
									<td><?php echo $row->timezone ?></td>
									<td><?php echo $row->entrytime ?></td>
									<td><?php echo $row->lasttime ?></td>
									<td><?php echo "$hours:$minutes:$seconds" ?></td>
									<td><?php echo $row->latitude ?></td>
									<td><?php echo $row->longitude ?></td>
									<td><?php echo $row->page_url ?></td>
								</tr>
						<?php } ?>

					</table>
			</div>
		<?php }

// Shortcode
function visitor_map_shortcode(){	
	global $wpdb;
	$activetime = sanitize_text_field(get_option('activetime'));
	$latlon = $wpdb->get_results( $wpdb->prepare("SELECT latitude,longitude FROM visitor WHERE TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d",$activetime) ); 

	?>
		<div style='overflow:hidden;padding-bottom:56.25%;position:relative;height:0;'>
			<div id='gmap_canvas' style="width: <?=sanitize_text_field(get_option('mapwidth'))?>px; height: <?=sanitize_text_field(get_option('mapheight'))?>px"></div>

			<style>
				#gmap_canvas img{max-width:none!important;background:none!important;}
				#gmap_canvas{left:0;top:0;height:100%;width:100%;position:absolute;}
			</style>
		</div>

	<?php

	if( wp_script_is( 'visitor_map_googleapi', 'done' ) ) {
	    ?>

	    <script type="text/javascript">
	      	function init_map(){
	      		var myOptions = {
					zoom:3,
					center:new google.maps.LatLng(35.088975, -119.115501),
					mapTypeId: google.maps.MapTypeId.ROADMAP,
					disableDefaultUI: true
				};

				var bounds = new google.maps.LatLngBounds();

				map = new google.maps.Map(document.getElementById('gmap_canvas'), myOptions);

	      	<?php
			foreach ($latlon as $val) {	      		
	      	?>	
	      		var icon = {
				    url: "<?=sanitize_text_field(get_option('mapmarker'))?>", // url
				    scaledSize: new google.maps.Size(20, 20), // scaled size
				};
				if(icon.url == ""){
					icon.url = "<?=plugins_url('marker.png',__FILE__);?>";
				}			
				marker = new google.maps.Marker({
					map: map,
					position: new google.maps.LatLng(<?=$val->latitude?>,<?=$val->longitude?>),
					icon: icon
				});

				bounds.extend(marker.position);
				
				map.fitBounds(bounds);
			<?php } ?>


			var listener = google.maps.event.addListener(map, "idle", function () {
			    map.setZoom(2);
			    google.maps.event.removeListener(listener);
			});	

			}

			google.maps.event.addDomListener(window, 'load', init_map);

	    </script>
	<?php
	}

}

add_shortcode('visitor_map','visitor_map_shortcode');

// Widget
class Visitor_map extends WP_Widget {

    function __construct() {
        parent::__construct(
         
            // base ID of the widget
            'Visitor_map_widget',
             
            // name of the widget
            __('Visitor Map', 'Visitor_map_widget' ),
             
            // widget options
            array (
                'desc' => __( 'Widget to display Online Visitors', 'Visitor_map' ),
                'title'=>__('Visitor_map')
            )
             
        );

    }

	function form( $instance ) {
		extract($instance);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if(isset($title)) echo esc_attr($title);?>"></input>
		</p>

		<?php
	}

    function widget( $args, $instance ) {
        extract($instance);
        extract($args);
        global $wpdb;
        $activetime = sanitize_text_field(get_option('activetime'));
		$res = $wpdb->get_results( $wpdb->prepare("SELECT (SELECT COUNT(user) FROM `visitor` WHERE TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as total,(SELECT COUNT(user) FROM `visitor` WHERE user='Guest' AND TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as guests,(SELECT COUNT(user) FROM `visitor` WHERE user!='Guest' AND TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d) as users",$activetime,$activetime,$activetime) );

		$latlon = $wpdb->get_results( $wpdb->prepare("SELECT latitude,longitude FROM visitor WHERE TIME_TO_SEC(TIMEDIFF(NOW(),lasttime))/60 < %d",$activetime) ); 

        echo $before_widget;
        if ( ! empty( $title ) ){
        		echo $before_title .'<a href="http://visitormap.se" target="_blank">'. $title .'</a>'. $after_title;
		}else{
			echo $before_title .'<a href="http://visitormap.se" target="_blank">'. 'Visitor Map' .'</a>'. $after_title;
        			}

        		?>
        			<div style='overflow:hidden;padding-bottom:56.25%;position:relative;height:0;'>
						<div id='map_canvas' style="width: <?=sanitize_text_field(get_option('mapwidth'))?>px; height: <?=sanitize_text_field(get_option('mapheight'))?>px"></div>

						<style>
							#map_canvas img{max-width:none!important;background:none!important;}
							#map_canvas{left:0;top:0;height:100%;width:100%;position:absolute;}
						</style>
					</div>
					<?php if( wp_script_is( 'visitor_map_googleapi', 'done' ) ) {
	  				?>
				    <script type="text/javascript">
				      	function init_map(){
							var myOptions = {
								zoom:1,
								center:new google.maps.LatLng(24.9206,67.0703),
								mapTypeId: google.maps.MapTypeId.ROADMAP,
								disableDefaultUI: true
							};
							var mybounds = new google.maps.LatLngBounds();
							mymap = new google.maps.Map(document.getElementById('map_canvas'), myOptions);
					      	<?php
							foreach ($latlon as $val) {	      		
					      	?>	
					      		var icon = {
								    url: "<?=sanitize_text_field(get_option('mapmarker'))?>", // url
								    scaledSize: new google.maps.Size(20, 20), // scaled size
								};
								if(icon.url == ""){
									icon.url = "<?=plugins_url('marker.png',__FILE__);?>";
								}			
								mymarker = new google.maps.Marker({
									map: mymap,
									position: new google.maps.LatLng(<?=$val->latitude?>,<?=$val->longitude?>),
									icon: icon
								});

								mybounds.extend(mymarker.position);
								
								mymap.fitBounds(mybounds);
							<?php } ?>

							var mylistener = google.maps.event.addListener(mymap, "idle", function () {
							    mymap.setZoom(1);
							    google.maps.event.removeListener(mylistener);
							});	
						
						}

						google.maps.event.addDomListener(window, 'load', init_map);

				    </script>
        		<?php }
        		foreach ($res as $v) {       			
        			echo $v->total." visitors online<br/>";
        			echo $v->guests." Guests, ".$v->users." Members online";
        		}

        	echo $desc;
        echo $after_widget;


    }
     
}


function visitor_map_widget() {
	register_widget( 'Visitor_map' );
}

add_action( 'widgets_init', 'visitor_map_widget' );



// remove rows
if ( ! wp_next_scheduled( 'remove_rows_hook' ) ) {
  wp_schedule_event( time(), 'daily', 'remove_rows_hook' );
}

add_action( 'remove_rows_hook', 'visitor_map_remove_rows_function' );

function visitor_map_remove_rows_function() {
	global $wpdb;
	$storedays = sanitize_text_field(get_option('storedays'));
	$query = "DELETE FROM visitor WHERE DATEDIFF(NOW(),lasttime) > %d";

	$wpdb->query( $wpdb->prepare($query,$storedays) );
}

?>
