<?php
/**
 * Created by PhpStorm.
 * User: mbitson
 * Date: 9/29/2014
 * Time: 11:38 AM
 * This scrip assumes a property post type with
 * custom fields already configured.
 * You can configure the fields
 * that are mapped to in: RETS::formatForNexes
 * Please note that your RETS server may use different fields labels.
 */

// Include RETS config
require_once 'rets-config.php';
require_once 'rets.php';
require_once 'wp-manager.php';

// Init a rets class
$rets   = new RETS;

// Init a wordpress manager class
$wp     = new WPManager;

// Set params required to run on our server
$rets->__set('host',                        RETS_HOST); // Specify full path to cookie file.
$rets->__set('username',                    RETS_USERNAME); // Specify full path to cookie file.
$rets->__set('password',                    RETS_PASSWORD); // Specify full path to cookie file.
$rets->__set('Login',                       RETS_LOGIN); // Specify full path to cookie file.
$rets->__set('cookie_file',                 COOKIE_FILE); // Specify full path to cookie file.
$rets->__set('disable_follow_location',     DISABLE_FOLLOW_LOCATION); // Disable follow location as open_basedir restirction is in effect.
$rets->__set('debug_file',                  DEBUG_FILE); // Set debug file path.
$rets->__set('debug',                       DEBUG); // Enable debug mode
$rets->__set("compression_enabled",         COMPRESSION_ENABLED); // Set supported options for FlexMLS
$rets->__set("offset_support",              OFFSET_SUPPORT); // Set supported options for FlexMLS

// Connect to RETS server
$login = $rets->login();