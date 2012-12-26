<?php
/*------------------------------------------------------------
 *                                                            
 *   Provides a notification to the user every-time           
 *   your WordPress theme is updated                          
 *                                                            
 *   Author: Joao Araujo                                      
 *   Profile: http://themeforest.net/user/unisphere           
 *   Follow me: http://twitter.com/unispheredesign 
 *
 *   Updated by Franklin M Gitonga 
 *   For the Radium Framework	
 *	 http://radiumthemes.com 
 *
 ---------------------------------------------------------------*/
 
 class Radium_UpdatesNotifier {
 	
 	/**
 	* @var Set Update check Interval
 	*/
 	private $cache_interval;

	/**
	* @var Set remote metadata url
	*/
	private $notifier_file_url;

 	/**
 	* @var Set theme slug
 	*/
 	private $theme_slug;
 	
 	/**
 	* @var Set theme title
 	*/
 	private $theme_title;
 	
 	/**
 	* @var Set remote metadata url
 	*/
 	private $theme_url;
 	
 	/**
 	* @var Set theme version
 	*/
 	private $theme_version;
 	
 	/**
 	 * Constructor. Hooks all interactions into correct areas to start
 	 * the class.
 	 *
 	 * @since 1.0.0
 	 */
 	public function  __construct() {
 		
 		//die if in child theme or not in admin
 		if ( is_child_theme() || !is_admin() ) 
 			return; 
 			
 		$framework = radium_framework(); //get framework data
 		
 		$this->cache_interval 		= $framework->theme_updates_notifier_cache_interval;
 		$this->notifier_file_url 	= $framework->theme_updates_notifier_xml_file;
 		$this->theme_title 			= $framework->theme_title;
 		$this->theme_url 			= $framework->theme_url;
 		$this->theme_slug 			= $framework->theme_slug;
 		$this->theme_version 		= $framework->theme_version;
 			
 		add_action('admin_menu', array( $this, 'update_notifier_menu' ) );  
 		add_action( 'admin_bar_menu', array( $this, 'update_notifier_bar_menu'), 1000 );
 			
 	}
 	
 	// Adds an update notification to the WordPress Dashboard menu
 	function update_notifier_menu() {  
 	
 		if (function_exists('simplexml_load_string')) { // Stop if simplexml_load_string funtion isn't available
 		    $xml = $this->get_latest_theme_version( $this->cache_interval ); // Get the latest remote XML file on our server
 	 
 	 		if( (float)$xml->latest > (float)$this->theme_version) { // Compare current theme version with the remote XML version
 				add_dashboard_page( $this->theme_title . ' Theme Updates', $this->theme_title . ' <span class="update-plugins count-1"><span class="update-count">New Update</span></span>', 'administrator', 'theme-update-notifier', array( $this, 'update_notifier') );
 			}
 		}	
 	}
 	 	
 	// Adds an update notification to the WordPress 3.1+ Admin Bar
 	function update_notifier_bar_menu() {
  	
 		if (function_exists('simplexml_load_string')) { // Stop if simplexml_load_string function isn't available
 			global $wp_admin_bar, $wpdb;
 		
 			if ( !is_super_admin() || !is_admin_bar_showing() ) // Don't display notification in admin bar if it's disabled or the current user isn't an administrator
 			return;
 			
 			$xml = $this->get_latest_theme_version( $this->cache_interval ); // Get the latest remote XML file on our server
 	   	
 			if( (float)$xml->latest > (float)$this->theme_version) { // Compare current theme version with the remote XML version
 					$wp_admin_bar->add_menu( array( 
 						'id' => 'update_notifier', 
 						'title' => '<span>' . $this->theme_title . ' <span id="ab-updates">Updates</span></span>', 
 						'href' => get_admin_url() . 'index.php?page=theme-update-notifier' 
 					) 
 				);
 			}
 		}
 	}
 	
 	/**
 	* Get the remote XML file contents and return its data (Version and Changelog)
 	* Uses the cached version if available and inside the time interval defined
 	*
 	* TODO :: Use a json feed and wp_remote for this part and get rid of file_get_contents and curl
 	*
 	* @return array
 	*/
 	function get_latest_theme_version( $interval ) {
  	
 		$db_cache_field = 'notifier-cache';
 		
 		$db_cache_field_last_updated = 'notifier-cache-last-updated';
 		
 		$last = get_option( $db_cache_field_last_updated );
 		
 		$now = time();
 		
 		// check the cache
 		if ( !$last || (( $now - $last ) > $interval) ) {
 		
 			// cache doesn't exist, or is old, so refresh it
 			if( function_exists('curl_init') ) { // if cURL is available, use it...
 			
 				$ch = curl_init( $this->notifier_file_url );
 				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 				curl_setopt($ch, CURLOPT_HEADER, 0);
 				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
 				$cache = curl_exec($ch);
 				curl_close($ch);
 				
 			} else {
 			
 				$cache = file_get_contents( $this->notifier_file_url ); // ...if not, use the common file_get_contents()
 				
 			}
 			
 			if ($cache) {			
 			
 				// we got good results	
 				update_option( $db_cache_field, $cache );
 				update_option( $db_cache_field_last_updated, time() );
 				
 			} 
 			
 			// read from the cache file
 			$notifier_data = get_option( $db_cache_field );
 			
 		} else {
 		
 			// cache file is fresh enough, so read from it
 			$notifier_data = get_option( $db_cache_field );
 			
 		}
 		
 		// Let's see if the $xml data was returned as we expected it to.
 		// If it didn't, use the default 1.0 as the latest version so that we don't have problems when the remote server hosting the XML file is down
 		if( strpos((string)$notifier_data, '<notifier>') === false )
 			$notifier_data = '<?xml version="1.0" encoding="UTF-8"?><notifier><latest>1.0</latest><changelog></changelog></notifier>';
 		
 		// Load the remote XML data into a variable and return it
 		$xml = simplexml_load_string($notifier_data); 
 		
 		return $xml;
 	}
 	
 	/**
 	* The notifier page
 	*
 	* @return array
 	*/
 	function update_notifier() { 
  	
 		$xml = $this->get_latest_theme_version( $this->cache_interval ); // Get the latest remote XML file on our server
 	  ?>
 		
 		<style>
 			.update-nag { display: none; }
 			#instructions {max-width: 670px;}
 			h3.title {margin: 30px 0 0 0; padding: 30px 0 0 0; border-top: 1px solid #ddd;}
 		</style>
 	
 		<div class="wrap">
 		
 			<div id="icon-tools" class="icon32"></div>
 			<h2><?php echo $this->theme_title; ?> Theme Updates</h2>
 		    <div id="message" class="updated below-h2"><p><strong>There is an updated version of the <?php echo $this->theme_title; ?> Theme available.</strong> You currently have Version <?php echo $this->theme_version; ?> installed. Please update to Version <?php echo $xml->latest; ?>.</p></div>
 	
 			<img style="float: left; margin: 0 20px 20px 0; border: 1px solid #ddd;" src="<?php echo $this->theme_url . '/screenshot.png'; ?>" />
 			
 			<div id="instructions">
 			    <h3>Update Download and Instructions.</h3>
 			    <p><strong>IMPORTANT:</strong> Make a <strong>backup</strong> of this theme inside your WordPress installation folder: <strong>/wp-content/themes/<?php echo $this->theme_slug; ?>/</strong></p>
 			    <p>To update the theme, login to <a href="http://www.themeforest.net/">ThemeForest</a> and download it from the <strong>Downloads Tab</strong>.</p>
 			    <p>Extract the zip's contents, identify the theme folder, review the change log, and upload via FTP to the theme folder. This will overwrite the original files  (please backup your files).</p>
 				<p>If you have not made any changes to the theme files, you are free to overwrite them with the new ones without the risk of losing theme settings, pages, posts, etc, and backwards compatibility is guaranteed. If you have made any changes, you can use a tool like diffmerge (or similar) to compare files and see what's different.</p>		</div>
 		    <h3 class="title">The Changelog.</h3>
 		    <?php echo $xml->changelog; ?>
 	
 		</div>
 	    
 	<?php } 
 		
 }
 
 new Radium_UpdatesNotifier; //run