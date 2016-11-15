<?php
/*
Plugin Name: WPPM Test Plugin
Plugin URI: http://hotsource.io/
Description: Composer package management for WordPress
Author: Matthew Zalewski @ HotSource.io
Author URI: http://www.hotsource.io/
Version: 1.0
License: GNU General Public License v2.0 or later
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

require('wppm.php');
$result = WPPM::autoload( __FILE__ );

