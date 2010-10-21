<?php
//uninstall script

//protection
if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) exit();

//remove options
delete_option('mendeley_connection_connection');
delete_option('mendeley_connection_prefs');
delete_option('mendeley_connection_widget_options');
?>