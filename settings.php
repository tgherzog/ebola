<?php

$params = array(
  // directory and file identifiers for local files. Directories are assumed relative to script unless beginning with "/"
  'data_dir' => './data',				// path to data directory. Must be r/w by script. Used to store downloaded HDX data, as well as data before 8/29
  'target_dir' => './data',             // path to destination directory

  // Script only pays attention to these countries
  'countries' => "Liberia,Guinea,Sierra Leone",

  // cache=1 means we don't download new data, we use the previous download
  'cache' => 1,

  // status=1 generates a brief status report.  status=date just reports the most recent data in the output file
  'status' => 0,

  // run-specific settings
  'cases' => array(
	'host' => 'data.hdx.rwlabs.org',  // hostname for the HDX repository
	'id' => 'ebola-cases-2014',		// dataset ID for the source data. Script is schema dependent, so this probably shouldn't change
	'target_name' => 'ebola-cases',
  ),
);


/***********************************************************/
/* settings processing code below this point               */

// process command line
$args = $argv;
array_shift($args);
while( isset($args[0]) && substr($args[0],0,2) == '--' ) {
  $arg = substr(array_shift($args),2);
  list($key,$value) = explode('=', $arg, 2);
  if( ! $value ) $value = 1;
  $params[$key] = $value;
}

$params['countries'] = explode(',', $params['countries']);


// sanity checks on directories
foreach(array('data_dir', 'target_dir') as $key)
  if( substr($params[$key],0,1) != '/' ) $params[$key] = $root . $params[$key];
