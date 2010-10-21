<?php
@require_once("../../../wp-config.php");
global $wpdb;
$table_name = $wpdb->prefix . "mendeleyRelatedCache";
$time = time();
$wpdb->query("DELETE FROM $table_name WHERE time<$time");

/**
	 * Redirect back to the settings page that was submitted
	 */

$goback = add_query_arg( 'updated', 'true',  wp_get_referer() );
wp_redirect( $goback );
exit;
?>