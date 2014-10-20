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
  'status' => 1,

  // run-specific settings
  'cases' => array(
	'host' => 'data.hdx.rwlabs.org',  // hostname for the HDX repository
	'id' => 'ebola-cases-2014',		// dataset ID for the source data. Script is schema dependent, so this probably shouldn't change
	'target_name' => 'ebola-cases',
  ),

  'econ' => array(
	'target_name' => 'ebola-economy',
    'sources' => array(
	  'Imported Rice' => array(
		'Liberia' => array(
		  'name' => 'Prices of Imported Rice.xlsx',
		  'factor' => 0.02, // conversion factor to price/kg
		  'first_row' => 4, // first row of data
		),
		'Sierra Leone' => array(
		  'name' => 'SL Imported Rice Prices Monthly 2013-14.xlsx',
		  'factor' => 1,
		  'first_row' => 6,
		),
	  ),
	  'Diesel' => array(
		'Liberia' => array(
		  'name' => 'Liberia Diesel Sales (Volume).xlsx',
		  'factor' => 1,
		  'first_row' => 3,
		),
		'Sierra Leone' => array(
		  'name' => 'SL Diesel Volumes Monthly 2013-14.xlsx',
		  'factor' => 0.264172 * 0.000001,
		  'first_row' => 6,
		),
	  ),
	),
  ),
);


/***********************************************************/
/* settings processing code below this point               */

// process command line. Doesn't support nested settings, so beware
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
