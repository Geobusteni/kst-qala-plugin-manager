#!/usr/bin/env php
<?php

/**
 * phpcs:ignoreFile
 *
 * This is the script that is used to get the plugin version
 * as provided in index.php. This file will be read when creating
 * a tag in GitLab.
 */
$file_data = file_get_contents( dirname( dirname( dirname( __FILE__ ) ) ) . '/index.php' );

// This is just nicked from get_file_data() in WP Core.
preg_match( '/^[ \t\/*#@]*' . preg_quote( 'Version', '/' ) . ':(.*)$/mi', $file_data, $match );

echo trim( $match[1] );

die;
