<?php
/**
 Plugin Name: Mendeley Related Research
 Description: Find research related to your blog posts. Adds a sidebar widget with related research pulled from Mendeley.com. Go to Widgets to adjust position and Settings to adjust values. IMPORTANT: You must add post tags for this to work!
 Version: 0.1
 Author: Jason Hoyt
 Author URI: http://www.mendeley.com/about-us/

Plugin: Copyright 2010 Mendeley Inc.
Plugin URI: http://www.mendeley.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * mendeley_research_activation() handles plugin activation
 */

function mendeley_research_activation() 
{
global $wpdb;
	$table_name = $wpdb->prefix . "mendeleyRelatedCache";
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  post_id mediumint(9) NOT NULL,
				  search text,
				  time bigint(11) DEFAULT '0' NOT NULL,
				  UNIQUE KEY id (id)
				);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

if (get_option('mrr_key')) 
	{
		//connection conversion
		$mrr_conn = array();
		
	 	update_option('mendeley_research_connection',$mrr_conn);
		
		//preference conversion
		$mrr_prefs = array();
				update_option('mendeley_research_prefs',$mrr_prefs);
		
				
		//widget update
		update_option('mendeley_research_widget_options',get_option('setup_widget_mrr'));
		delete_option('setup_widget_mrr');

	}
	//default settings
	$mendeley_research_prefs = get_option('mendeley_research_prefs');
	if (!$mendeley_research_prefs) {
		update_option('mendeley_research_prefs', $mendeley_research_prefs);
		return true;
	}
	mendeley_research_flush(); //flush the cache
}
register_activation_hook(__FILE__,'mendeley_research_activation');

/**
 * mendeley_research_flush() clears all of the caching
 * 
 * @param bool $connection_info indicates whether the widget cache needs to be cleared too
 */
function mendeley_research_flush($connection_info = true) 
{

	if ($connection_info) {
		//flush last update timestamps
		$mendeley_research_connection = get_option('mendeley_research_connection');
		$mendeley_research_connection['widget_lastupdate'] = '';
		$mendeley_research_connection['widget_cache'] = '';
		update_option('mendeley_research_connection', $mendeley_research_connection);
	}
	
}

/**
 * mendeley_research_options_init() initializes plugin options
 * 
 * @return
 */
function mendeley_research_options_init() 
{
	register_setting('mendeley_research_options','mendeley_research_connection','mendeley_research_connection_validate'); //API key and caching info 
	register_setting('mendeley_research_options','mendeley_research_prefs','mendeley_research_prefs_validate'); //generic preferences array
}
add_action('admin_init','mendeley_research_options_init');

/**
 * mendeley_research_connection_validate() handles validation of connection options
 */
function mendeley_research_connection_validate($input) 
{
	mendeley_research_flush(false); 
	$input['item_count'] = intval($input['item_count']); //item count must be integer
	return $input; //pass back to save
}

/**
 * mendeley_research_prefs_validate() handles validation of general preferences
 */
function mendeley_research_prefs_validate($input) 
{
	$input['mrr_metadata'] = ($input['mrr_metadata'] == 1) ? 1 : 0;	
	$input['new_window'] = ($input['new_window'] == 1) ? 1 : 0;
	return $input;
}

function mendeley_research_fetch_items() 
{
	global $post;
	
	//Get preferences and consumer key if stored
	$mrrp_prefs = get_option('mendeley_research_prefs'); 
	$mrrp_conn = get_option('mendeley_research_connection');
	$mrrp_new_window = ($mrrp_prefs['new_window']) ? ' target="_blank"' : ' ';
	$metadata = ($mrrp_prefs['mrr_metadata']);
	
	//retrieve connection info and stop this if core connection id has not been set
	$mrrp_conn = get_option('mendeley_research_connection');  
	
	if($mrrp_conn['id']=='') {
		?>
			<br>Your Mendeley API key is missing.
		<?php
	exit;
	}
	
	//First check if there is a cache and use that
	$post_id = $post->ID;

	$cacheResult = getMrrCache($post_id);
	
	//If no cache then go fetch what is needed
	if (is_null($cacheResult)) {
		$tags = get_the_tags();
		//Check for tags in the post
		if($tags=='') {
			?>
			<br>There are no blog post tags to find related research.
			<div id="mendeley_logo">
				<a href="http://www.mendeley.com"><img src="<?php echo WP_PLUGIN_URL; ?>/<?php echo basename(dirname(__FILE__)); ?>/images/MLogo.png" width="120px"></a>
			</div>
			<?php
			exit;
		}
		
		foreach($tags as $t) {
			$searchTerms .= $t->name.' ';
		}
		$searchEncoded = urlencode($searchTerms);
		
		$url ="http://www.mendeley.com/oapi/documents/search/".$searchEncoded."/?consumer_key=".$mrrp_conn['id'];	
		$searchList = wp_remote_fopen($url);
		$resp = wp_remote_request($url);
		//Check response code
		if($resp['response']['code']!=200) {
			?>
			<br>There was an error contacting Mendeley.
			<div id="mendeley_logo">
				<a href="http://www.mendeley.com"><img src="<?php echo WP_PLUGIN_URL; ?>/<?php echo basename(dirname(__FILE__)); ?>/images/MLogo.png" width="120px"></a>
			</div>
			<?php
			exit;
		}
		$list = json_decode($searchList, true);
		
		//Check to see if the number of results is less than the number of desired results to display. If yes, then change display value to lower value.
		if($list[total_results]<$mrrp_conn['item_count']) {
			$iterateNum = $list[total_results];
		} else {
			$iterateNum = $mrrp_conn['item_count'];
		}
		
		for($i=0;$i<$iterateNum;$i++) {
			if($list[total_results]==0) {
				echo '<br>No related research found';
			exit;
			}
			//Get the reader details for each article
			$readersURL = "http://www.mendeley.com/oapi/documents/details/".$list[documents][$i][uuid]."/?consumer_key=".$mrrp_conn['id'];
			$readerJSON = wp_remote_fopen($readersURL);
			
			$readers = json_decode($readerJSON, true);
			//Build the json object that we will cache and eventually display
			$final[] = array(
				'title'=>$list[documents][$i][title],
				'readers'=>$readers[stats][readers],
				'year'=>$readers[year],
				'url'=>stripslashes($list[documents][$i][mendeley_url]),
				'firstauthor' => $list[documents][$i][authors],
				'uuid' => $list[documents][$i][uuid]
			);
		}
		$final = json_encode(array('docs'=>$final));

		//Now store the JSON object in the cache  
		mrrSearchCache($post_id, $final);
		
	}
	//Cache is present, so put it into a string
    else {
    	$final = $cacheResult->search;
    }
    
    //Phew! We have all we need and can start building the output to display
	$output = json_decode($final,true);
	
	echo '<div id="mendeley_container">';

		for($i=0;$i<$mrrp_conn['item_count'];$i++) {
			$article = '<a href="'.stripslashes($output[docs][$i][url]).'?mrr_wp=0.1" '.$mrrp_new_window.'>'.$output[docs][$i]['title'].'</a>';			
		
			echo '<div id="mendeley_item">';
				echo'<div id="mendeley_readers">'.$output[docs][$i][readers].'<br><span id="m_read_label">Readers</span></div>';
				echo '<div id="mendeley_right">';
					echo  '<div id="mendeley_list">'.$article;
					if($metadata==1) {
						echo ' <div id="mendeley_metadata">'.$output[docs][$i][firstauthor].' ('.$output[docs][$i][year].')</div>';
					}
				echo '</div></div>';
			echo '<div style="clear:both"></div></div>';
		}
		?>
		<div id="mendeley_logo">
			<a href="http://www.mendeley.com"><img src="<?php echo WP_PLUGIN_URL; ?>/<?php echo basename(dirname(__FILE__)); ?>/images/MLogo.png" width="120px"></a>
		</div>
	</div>
	<?php
	if(is_wp_error($mrrp_feed)) return "No results returned. Please verify your Mendeley API key.";	//error catch


	//cache storing for widget
	else {
		$mrrp_conn['widget_cache'] = $mrr_cache;
		$mrrp_conn['widget_lastupdate'] = $feedupdate;
		update_option('mendeley_research_connection',$mrrp_conn);
	}
	
	return $mrr_cache;
}
/**
 *That was the end of the main function
 */
/**
 * mendeley_research_widget_control() initializes the widget for admin
 */
function mendeley_research_widget_control() {
	// We need to grab any preset options
	$options = get_option("mendeley_research_widget_options");
	if (!is_array($options)) $options = array('title' => 'Mendeley Related Research'); // No options? No problem! We set them here.

	if (isset($_POST['mendeley_research_widget_submit'])) {
		$options['title'] = htmlspecialchars($_POST['mendeley_research_widget_title']);
		update_option("mendeley_research_widget_options", $options); // And we also update the options in the Wordpress Database
	}
	
	echo '
		<label for="mendeley_research_widget_title">Widget Title:</label>
		<input type="text" id="mendeley_research_widget_title" name="mendeley_research_widget_title" value="'.$options['title'].'" />
		<input type="hidden" id="mendeley_research_widget_submit" name="mendeley_research_widget_submit" value="1" />
	';
} 

/**
 * mendeley_research_widget_output() handles widget output
 */
function mendeley_research_widget_output($args) 
{
	extract($args);  
	$options = get_option("mendeley_research_widget_options");  
  	if (!is_array($options)) $options = array('title' => 'Mendeley Related Research');
	if (!$options['title']) $options['title'] = "Mendeley Related Research"; 
  	
	echo $before_widget;
	echo $before_title.$options['title'].$after_title;  
	echo mendeley_research_fetch_items();
	echo $after_widget;  
}

/**
 * mendeley_research_widget_init() registers the widget
 */
function mendeley_research_widget_init() 
{
  register_sidebar_widget('Mendeley Papers', 'mendeley_research_widget_output');
  register_widget_control('Mendeley Papers', 'mendeley_research_widget_control');
}

add_action("plugins_loaded", "mendeley_research_widget_init");



function mendeley_css() 
{
	wp_enqueue_style('mendeley_research_styles',WP_PLUGIN_URL.'/'.basename(dirname(__FILE__)).'/css/mendeleyWPrr.css');
}
add_action('get_header', 'mendeley_css');


function initializeDatabase() {
	global $wpdb;
	$table_name = $wpdb->prefix . "mendeleyRelatedCache";
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  post_id mediumint(9) NOT NULL,
				  search text,
				  time bigint(11) DEFAULT '0' NOT NULL,
				  UNIQUE KEY id (id)
				);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$this->settings['db_version'] = PLUGIN_DB_VERSION;
		update_option($this->adminOptionsName, $this->settings);
	}

}
function getMrrCache($post_id) {
	global $wpdb;
	if ("$post_id" == "") return NULL;
	$table_name = $wpdb->prefix . "mendeleyRelatedCache";
	$results = $wpdb->get_row("SELECT * FROM $table_name WHERE post_id=$post_id");
	if ($results) {
		// check timestamp
		$delta = 43200000;
		if ($results->time + $delta > time()) {
			return $results;
		}
	}
	return NULL;
}
function mrrSearchCache($post_id, $relatedResults) {
	global $wpdb;
	$table_name = $wpdb->prefix . "mendeleyRelatedCache";
	$results = $wpdb->get_row("SELECT * FROM $table_name WHERE post_id=$post_id");
	if ($results) {
		$wpdb->update($table_name, array('time' => time(), 'search' => $relatedResults));
		return;
	}
	$wpdb->insert($table_name, array('time' => time(), 'post_id' => intval($post_id), 'search' => $relatedResults));
}

/**
 * mendeley_research_plugin_actlinks() adds the settings link to the plugin page
 */
function mendeley_research_plugin_actlinks( $links ) { 
 // Add a link to this plugin's settings page
 $plugin = plugin_basename(__FILE__);
 $settings_link = sprintf( '<a href="options-general.php?page=%s">%s</a>', $plugin, __('Settings') ); 
 array_unshift( $links, $settings_link ); 
 return $links; 
}
if(is_admin()) add_filter("plugin_action_links_".$plugin, 'mendeley_research_plugin_actlinks' );

/**
 * mendeley_research_admin_menu() sets up the menu link in the admin
 */ 
function mendeley_research_admin_menu() 
{
	$plugin_page = add_options_page('Mendeley Related Research Settings', 'Mendeley Papers', 8, __FILE__, 'mendeley_research_options_page');
	add_action('admin_head-'.$plugin_page,'mendeley_research_header');
}

function mendeley_research_header()
{
	add_filter('contextual_help','mendeley_research_context_help');
}

function mendeley_research_context_help() 
{
	echo '
		<h5>Mendley Related Research</h5>
			
		<div class="metabox-prefs">
			<p>For feedback or questions, please write to wordpress@mendeley.com.</p>	
		</div>
		
		<h5>More Information</h5>
			
		<div class="metabox-prefs">
			<p>Visit the <a href="http://dev.mendeley.com">Mendeley Developer Portal</a>.</p>	
		</div>
	';	
}

add_action('admin_menu', 'mendeley_research_admin_menu');


/**
 * mendeley_research_options_page() displays the settings page under Settings in WP admin console
 */
function mendeley_research_options_page() { 
?>
	<script type="text/javascript" language="javascript">
	
		function pulseMessage() {
			jQuery('#message').css("background","#FFFFE0 url(<?php echo WP_PLUGIN_URL; ?>/<?php echo basename(dirname(__FILE__)); ?>/images/loading.gif) 0 0 repeat-x");
	   	}
		
	</script>


	<div class="wrap">
		<h2>Mendeley Related Research Settings</h2>
	
		<div class="updated" id="message" style="display: none;"></div>

		<div id="poststuff" style="margin-top: 20px;">
	
		<form method="post" action="options.php">
			<?php 
				settings_fields('mendeley_research_options');
				$mrrp_conn = get_option('mendeley_research_connection');
				$mrrp_prefs = get_option('mendeley_research_prefs');

			?>
		<div class="postbox" style="width: 500px;">
			<h3 class="hndle">My Mendeley API Consumer Key</h3>
			<div class="inside">
				<table class="form-table" style="clear: none;" >
					<tr valign="top">
						<th scope="row">Consumer Key [<a href="#" onclick="alert('Please go to http://dev.mendeley.com to obtain your consumer key. For security, do not share this key with anyone.'); return false;" style="cursor: help;">?</a>]</th>
						<td style="padding-bottom: 2px;"><input type="text" size="50" name="mendeley_research_connection[id]" id="mrr_key" value="<?php echo $mrrp_conn['id']; ?>"  /></td>
					</tr>
								
				</table>
			</div>
		</div>
	
	<div class="postbox" style="width: 500px;">
		<h3 class="hndle">Display Settings</h3>
		<div class="inside">
			<table class="form-table">
			
				
				<tr valign="top">
					<th scope="row" valign="top" style="padding-top: 15px; border-top: 1px dashed #DFDFDF;">Number of Papers [<a href="#" onclick="alert('Default is five. Maximum is 20.'); return false;" style="cursor: help;">?</a>]</th>
					<td style="padding-top: 10px; border-top: 1px dashed #DFDFDF;"><input type="text" name="mendeley_research_connection[item_count]" id="mrr_num" value="<?php if($mrrp_conn['item_count']) {echo $mrrp_conn['item_count'];} else {echo '5';} ; ?>" /></td>
				</tr>
				
				<tr valign="top">
					<th scope="row" valign="top" style="border-top: 1px dashed #DFDFDF;">Show Authors and Year</th>
					<td style="padding-top: 10px; border-top: 1px dashed #DFDFDF;"><input type="checkbox" value="1" name="mendeley_research_prefs[mrr_metadata]" id="mrr_content"<?php if ($mrrp_prefs['mrr_metadata']) { echo ' checked="true"'; } ?> onchange="secondaryOption(this);" /></td>
				</tr>
				
				
				<tr valign="top">
					<th scope="row" valign="top" style="border-top: 1px dashed #DFDFDF;">Open Links in New Window</th>
					<td style="padding-top: 10px; border-top: 1px dashed #DFDFDF;"><input type="checkbox" value="1" name="mendeley_research_prefs[new_window]" id="mrr_open"<?php if ($mrrp_prefs['new_window']) { echo ' checked="true"'; } ?> /></td>
				</tr>
				
				<div>
					<p valign="top" style="border-top: 1px dashed #DFDFDF;">To change the output style, modify mendeleyWPrr.css located in the CSS folder of the plugin.</p>
				</div>
				
			</table>
		</div>
	</div>
	

	
	<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" /></p>
	
	</form>
		<form method="post" action="<?php echo WP_PLUGIN_URL; ?>/<?php echo basename(dirname(__FILE__)); ?>/mrrCache.php">
		<div class="postbox" style="width: 500px;">
				<h3 class="hndle">Caching</h3>
				<div class="inside">
					<table class="form-table" style="clear: none;" >
						<tr valign="top">
							<th scope="row" valign="top" style="border-top: 1px dashed #DFDFDF;">Clear cache [<a href="#" onclick="alert('Recommended if you increase number of papers to display. Current cache is set to 12 hours to reduce number of API calls made.'); return false;" style="cursor: help;">?</a>]</th>
							<td style="padding-top: 10px; border-top: 1px dashed #DFDFDF;"><input type="submit" class="button-primary" value="<?php _e('Clear Now') ?>" /></td>
						</tr>		
					</table>
				</div>
		</div>
		</form>	
	</div>
  
<?php 
	} 
?>