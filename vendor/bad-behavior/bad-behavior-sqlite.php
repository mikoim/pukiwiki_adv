<?php
/*
Bad Behavior - detects and blocks unwanted Web accesses
Copyright (C) 2005,2006,2007,2008,2009,2010,2011,2012 Michael Hampton

Bad Behavior is free software; you can redistribute it and/or modify it under
the terms of the GNU Lesser General Public License as published by the Free
Software Foundation; either version 3 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with this program. If not, see <http://www.gnu.org/licenses/>.

Please report any problems to bad . bots AT ioerror DOT us
http://bad-behavior.ioerror.us/
*/
###############################################################################
###############################################################################

define('BB2_CWD', dirname(__FILE__));

define('BB2_SETTING_INI_FILE', dirname(__FILE__) . "/settings.ini");
define('BB2_WHITELIST_INI_FILE', dirname(__FILE__) . "/whitelist.ini");

define('BB2_DB_FILE', dirname(__FILE__) . "/bad-behavior.sqlite3");

define('BB2_MAIL_ADDRESSS', "example@example.com");	// You need to change this.

// Settings you can adjust for Bad Behavior.
// Most of these are unused in non-database mode.
// DO NOT EDIT HERE; instead make changes in settings.ini.
// These settings are used when settings.ini is not present.
$bb2_settings_defaults = array(
	'log_table' => 'bad_behavior',
	'display_stats' => true,
	'strict' => false,
	'verbose' => false,
	'logging' => true,
	'httpbl_key' => '',
	'httpbl_threat' => '25',
	'httpbl_maxage' => '30',
	'offsite_forms' => false,
	'eu_cookie' => false,
	'reverse_proxy' => false,
	'reverse_proxy_header' => 'X-Forwarded-For',
	'reverse_proxy_addresses' => array(),
);

// Bad Behavior callback functions.

// Return current time in the format preferred by your database.
function bb2_db_date() {
	return gmdate('Y-m-d H:i:s');	// Example is MySQL format
}

// Return affected rows from most recent query.
function bb2_db_affected_rows() {
	global $bb2_db;
	return $bb2_db->rowCount();
}

// Escape a string for database usage
function bb2_db_escape($string) {
	global $bb2_db;
	return $bb2_db->quote(trim($string));
}

// Return the number of rows in a particular query.
function bb2_db_num_rows($result) {
	if ($result !== FALSE)
		return count($result);
	return 0;
}

// Run a query and return the results, if any.
// Should return FALSE if an error occurred.
// Bad Behavior will use the return value here in other callbacks.
function bb2_db_query($query) {
	global $bb2_db;
	$matches = array();
	if ($query == 'SET @@session.wait_timeout = 90') return;
	if (preg_match('/OPTIMIZE/', $query)) $query = 'VACUUM';
	if (preg_match('/DELETE FROM `(.+?)` WHERE `(.+?)` < DATE_SUB\(\'(.+?)\', INTERVAL (\d+?) DAY\)/', $query, $matches)){
		$query = 'DELETE FROM `' . $matches[1] . '` WHERE date(`' . $matches[2] . '`) < date(\''. $matches[3] .'\', \'-'.$matches[4].' days\')';
	}
	try {
		return $bb2_db->query($query);
	} catch( PDOException $ex ) {
		// DBアクセス時にエラーとなった時
		throw new Exception('Bad-behavior :' . $query. '<br />' .$ex->getMessage());
	}
}

// Return all rows in a particular query.
// Should contain an array of all rows generated by calling mysql_fetch_assoc()
// or equivalent and appending the result of each call to an array.
function bb2_db_rows($result) {
	if ($result !== FALSE)
		return count($result);
	return 0;
}

// Insert a new record
function bb2_insert($settings, $package, $key)
{
	if (!$settings['logging']) return "";
	$ip = bb2_db_escape($package['ip']);
	$date = bb2_db_escape(bb2_db_date());
	$request_method = bb2_db_escape($package['request_method']);
	$request_uri = bb2_db_escape($package['request_uri']);
	$server_protocol = bb2_db_escape($package['server_protocol']);
	$user_agent = bb2_db_escape($package['user_agent']);
	$headers = "$request_method $request_uri $server_protocol\n";
	foreach ($package['headers'] as $h => $v) {
		$headers .= "$h: $v\n";
	}
	$headers = bb2_db_escape($headers);
	$request_entity = "";
	if (!strcasecmp($request_method, "POST")) {
		foreach ($package['request_entity'] as $h => $v) {
			$request_entity .= "$h: $v\n";
		}
	}
	$request_entity = bb2_db_escape($request_entity);
	return 'INSERT INTO `' . $settings['log_table']. '`' .
		'(`ip`, `date`, `request_method`, `request_uri`, `server_protocol`, `http_headers`, `user_agent`, `request_entity`, `key`) VALUES' . 
		'(' . $ip . ', ' . $date . ', ' . $request_method . ', ' . $request_uri . ', ' . $server_protocol . ', ' . $headers . ', ' . $user_agent . ', ' . $request_entity . ', ' . bb2_db_escape($key) .')';
}
// Return emergency contact email address.
function bb2_email() {
	return BB2_MAIL_ADDRESSS;
}

// retrieve whitelist
function bb2_read_whitelist() {
	static $bb2_whitelist;
	if (empty($bb2_whitelist) && file_exists(BB2_WHITELIST_INI_FILE)){
		$bb2_whitelist = parse_ini_file(BB2_WHITELIST_INI_FILE);
	}else{
		$bb2_whitelist = '';
	}
	return $bb2_whitelist;
}

// retrieve settings from database
// Settings are hard-coded for non-database use
function bb2_read_settings() {
	global $bb2_settings_defaults;
	static $bb2_settings;

	if (empty($bb2_settings)){
		$bb2_settings = (file_exists(BB2_SETTING_INI_FILE)) ?
			array_merge($bb2_settings_defaults, parse_ini_file(BB2_SETTING_INI_FILE)) :
			$bb2_settings_defaults;
	}

	return $bb2_settings;
}

// write settings to database
function bb2_write_settings($settings) {
	return false;
}

// installation
function bb2_install() {
	global $bb2_db;
	$settings = bb2_read_settings();

	if (! $bb2_db = new \PDO('sqlite:'.BB2_DB_FILE)) {
		die("DB Connection Failed.");
	}
	$bb2_db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

	$sql = join("\n", array(
		'CREATE TABLE IF NOT EXISTS `' . $settings['log_table'] . '` (',
		'`id` INTEGER PRIMARY KEY AUTOINCREMENT,',
		'`ip` TEXT(15) NOT NULL,',
		'`date` TEXT NOT NULL DEFAULT "0000-00-00 00:00:00",',
		'`request_method` TEXT NOT NULL,',
		'`request_uri` TEXT NOT NULL,',
		'`server_protocol` TEXT NOT NULL,',
		'`http_headers` TEXT NOT NULL,',
		'`user_agent` TEXT(10) NOT NULL,',
		'`request_entity` TEXT NOT NULL,',
		'`key` TEXT NOT NULL',
		')'
		)
	);
	return bb2_db_query($sql);
}

// Screener
// Insert this into the <head> section of your HTML through a template call
// or whatever is appropriate. This is optional we'll fall back to cookies
// if you don't use it.
function bb2_insert_head() {
	global $bb2_javascript;
	echo $bb2_javascript;
}

// Display stats? This is optional.
function bb2_insert_stats($force = false) {
	$settings = bb2_read_settings();

	if ($force || $settings['display_stats']) {
		$blocked = bb2_db_query("SELECT COUNT(*) FROM " . $settings['log_table'] . " WHERE `key` NOT LIKE '00000000'");
		if ($blocked !== FALSE) {
			echo sprintf('<p><a href="http://bad-behavior.ioerror.us/">%1$s</a> %2$s <strong>%3$s</strong> %4$s</p>', __('Bad Behavior'), __('has blocked'), $blocked[0]["COUNT(*)"], __('access attempts in the last 7 days.'));
		}
	}
}

// Return the top-level relative path of wherever we are (for cookies)
// You should provide in $url the top-level URL for your site.
function bb2_relative_path() {
	//$url = parse_url(get_bloginfo('url'));
	//return $url['path'] . '/';
	return '/';
}

// Calls inward to Bad Behavor itself.
require_once(BB2_CWD . "/bad-behavior/core.inc.php");
bb2_install();	// FIXME: see above

bb2_start(bb2_read_settings());
