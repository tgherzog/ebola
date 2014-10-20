#!/usr/bin/php
<?php

$root = dirname(__FILE__) . '/';
require_once('PHPExcel/Classes/PHPExcel.php');
require_once("settings.php");

# print_r($params); exit;

// create destination spreadsheet
$dst = new PHPExcel();

// run build routines
$url = 'https://' . $params['cases']['host'] . '/api/3/action/package_show?id=' . $params['cases']['id'];
$params['_src'] = $params['cases']['host'] . '/dataset/' . $params['cases']['id'];
if( ! ($data = @file_get_contents($url)) ) {
  msg("Can't access $url");
  exit -1;
};

$data = json_decode($data);
$url = null;
foreach($data->result->resources as $row) {
  if( $row->name == 'ebola-data-db-format.xls' ) {
    $url = $row->url;
	break;
  }
}

if( ! $url ) {
  msg("No Excel file found");
  exit -1;
}

$params['_url'] = $url;
$dst->getActiveSheet()
  ->setTitle('EbolaData')
  ->fromArray(array('ReleaseDate', 'COUNTRY', 'countryId', 'NEW CASES', 'NEW DEATHS', 'TOTAL CASES', 'TOTAL DEATHS', 'MORT RATIO'), null, 'A1');

$x = 2;

# first, insert data that pre-dates what is in HDX. This file should be in data directory and is treated as read-only
$src = PHPExcel_IOFactory::load($params['data_dir'] . '/cases-initial.xlsx');
$s = $src->getSheet();
$last = $s->getHighestRow();
$data = $s->rangeToArray("A2:H$last", null, false, false, false);

$dst->getSheet(0)->fromArray($data, null, "A2", true);
$x = 2 + count($data);

// mark the prepended data with a blue background
$dst->getSheet(0)->getStyle("A2:H".($x-1))->applyFromArray(array(
  'fill' => array(
    'type' => PHPExcel_STyle_Fill::FILL_SOLID,
	'color' => array('rgb' => 'DCE6F1'),
  ),
));

$src->disconnectWorksheets();
unset($src);

$recent = array('header' => array(null, null));
foreach($params['countries'] as $row) {
  $recent[$row] = array(
	'last21_prev' => null,
    'last21' => null,
	'cases' => 0,
	'mort' => 0,
  );
}

# iterate through the prepended data to get our cumulative totals to date
foreach($data as $row) {
  list($date,$country,$country_,$cases,$deaths) = $row;
  $recent[$country]['cases'] += $cases;
  $recent[$country]['mort']  += $deaths;
}

$path = get_file($url, 'hdx-ebola-cases');
if( ! $path ) {
  msg("Couldn't download file: $url");
  exit -1;
}

$reader = PHPExcel_IOFactory::createReaderForFile($path);
// for large workbooks, do some optimizations or we'll run out of memory
# $reader->setReadDataOnly(true);
# $reader->setLoadSheetsOnly(array($params['sheet_name']));
$src = $reader->load($path);
$s = $src->getSheet();
$last = $s->getHighestRow();

# For easier coding, we replace series names with codes
$series_codes = array(
  'Number of confirmed, probable and suspected Ebola cases in the last 21 days' => '21CASEALL',
  'Cumulative number of confirmed, probable and suspected Ebola deaths' => 'CMORTALL',
  'Cumulative number of confirmed, probable and suspected Ebola cases' => 'CCASEALL',
);

$data = array();
for($i=2;$i<=$last;$i++) {
  $row = $s->rangeToArray("A$i:D$i", null, true, false, false);
  list($series,$country,$date,$value) = $row[0];
  $series = trim($series);
  $country = trim($country);
  if( ! isset($series_codes[$series]) ) continue;  // don't care about this one
  if( ! in_array($country, $params['countries']) ) continue; // not tracking this country

  $series = $series_codes[$series];
  $data[] = array($series,$country,$date,$value);
}

# sort by row, country, series
usort($data, '_sort');

$result = array_fill(0, 4, null);
foreach($data as $row) {
  list($series,$country,$date,$value) = $row;
  $end_of_month = end_of_month($date);
  if( $country != $result[1] || $date != $result[0] ) {
    if( $result[2] || $result[3] ) {
	  $dst->getSheet(0)->fromArray(array(
	    $result[0],
		$result[1], preg_replace('/\s+/', '', strtolower($result[1])),
		$result[2] - $recent[$result[1]]['cases'],
		$result[3] - $recent[$result[1]]['mort'],
		$result[2],
		$result[3],
		"=G$x/F$x",
	  ), null, "A$x", true);
	  $x++;
	  $recent[$result[1]]['cases'] = $result[2];
	  $recent[$result[1]]['mort']  = $result[3];
	}

	$result = array($date, $country, null, null);
  }

  switch($series) {
    case 'CCASEALL':
	  $result[2] = $value;
	  break;
    case 'CMORTALL':
	  $result[3] = $value;
	  break;
    case '21CASEALL':
	  if( $date != $recent['header'][0] ) {
		$recent['header'][1] = $recent['header'][0];
		$recent['header'][0] = $date;
	    foreach($params['countries'] as $c) {
		  $recent[$c]['last21_prev'] = $recent[$c]['last21'];
		  $recent[$c]['last21'] = null;
		}
	  }
	  $recent[$country]['last21'] = $value;
	  break;
  }
}

if( $result[2] || $result[3] ) {
  $dst->getSheet(0)->fromArray(array(
	$result[0],
	$result[1], preg_replace('/\s+/', '', strtolower($result[1])),
	$result[2] - $recent[$result[1]]['cases'],
	$result[3] - $recent[$result[1]]['mort'],
	$result[2],
	$result[3],
	"=G$x/F$x",
  ), null, "A$x", true);
}

$dst_range = "A2:H$x";

$dst->getActiveSheet()->getStyle("A2:A$x")->getNumberFormat()->setFormatCode('m/d/yy');
$dst->getActiveSheet()->getStyle("D2:G$x")->getNumberFormat()->setFormatCode('0');
$dst->getActiveSheet()->getStyle("H2:H$x")->getNumberFormat()->setFormatCode('0.0%');

# Put last 21 days on a 2nd sheet
$dst->createSheet()->setTitle('Last21')
  ->fromArray(array('Country', 'Case Definition', $recent['header'][0], $recent['header'][1], 'Change', '% Change'), null, 'A1');
$dst->getSheet(1)->getStyle('C1:D1')->getNumberFormat()->setFormatCode('m/d/yy');
$dst->getSheet(1)->getStyle('F')->getNumberFormat()->setFormatCode('0.0%');
$x = 2;
foreach($recent as $key => $value) {
  if( $key == 'header' ) continue;
  $dst->getSheet(1)->fromArray(array($key, "All", $value['last21'], $value['last21_prev'], "=C$x-D$x", "=C$x/D$x-1"), null, "A$x");
  $x++;
}

// Roll up mortality rates on a third sheet
$rows = $dst->getSheet(0)->rangeToArray($dst_range, null, true, false, false);
$mortality = array();
foreach($rows as $row) {
  $end_of_month = end_of_month($row[0]);
  $country = $row[1];
  $mortality[$end_of_month][$country] = array($row[5], $row[6], $row[0]);
}

$dst->createSheet()->setTitle('Mortality')
  ->fromArray(array('Date', 'Month', 'Year', 'Country', 'Cases', 'Deaths', 'Mort Ratio'), null, 'A1');
$x = 2;
foreach($mortality as $key => $rows) {
  $sum = array(0, 0);
  foreach($rows as $country => $row) {
	$dst->getSheet(2)->fromArray(array($row[2], "=TEXT(A$x, \"mmm\")", "=YEAR(A$x)", $country, $row[0], $row[1], "=F$x/E$x"), null, "A$x", true);
    $sum[0] += $row[0];
	$sum[1] += $row[1];
	$x++;
  }

  $dst->getSheet(2)->fromArray(array($row[2], "=TEXT(A$x, \"mmm\")", "=YEAR(A$x)", 'Total', $sum[0], $sum[1], "=F$x/E$x"), null, "A$x", true);
  $x++;
}

$dst->getSheet(2)->getStyle("A2:A$x")->getNumberFormat()->setFormatCode('mmm-yy');
$dst->getSheet(2)->getStyle("G2:G$x")->getNumberFormat()->setFormatCode('0.0%');

// For cosmetic purposes, set some column widths
$dst->getSheet(0)->getColumnDimension('A')->setWidth(100/7);
$dst->getSheet(0)->getColumnDimension('B')->setWidth(180/7);
$dst->getSheet(0)->getColumnDimension('C')->setWidth(180/7);
$dst->getSheet(0)->getColumnDimension('D')->setWidth(100/7);
$dst->getSheet(0)->getColumnDimension('E')->setWidth(100/7);
$dst->getSheet(0)->getColumnDimension('F')->setWidth(100/7);
$dst->getSheet(0)->getColumnDimension('G')->setWidth(100/7);
$dst->getSheet(0)->getColumnDimension('H')->setWidth(100/7);

$dst->getSheet(1)->getColumnDimension('A')->setWidth(180/7);
$dst->getSheet(1)->getColumnDimension('B')->setWidth(180/7);
$dst->getSheet(1)->getColumnDimension('C')->setWidth(100/7);
$dst->getSheet(1)->getColumnDimension('D')->setWidth(100/7);

$dst->getSheet(2)->getColumnDimension('A')->setWidth(100/7);
$dst->getSheet(2)->getColumnDimension('D')->setWidth(180/7);

# write results
$dst->setActiveSheetIndex(0);
$output = PHPExcel_IOFactory::createWriter($dst, 'Excel2007');
$output->save("$params[target_dir]/{$params['cases']['target_name']}.xlsx");

# write status report
$most_recent = $date;
if( $params['status'] === 'date' ) {
  print ex_date($most_recent) . "\n";
}
elseif( $params['status'] ) {
  $output = array();
  $keys = array();
  $cache_msg = ($params['cache'] == 1) ? " (cached)" : "";
  print "Data source: $params[_src]$cache_msg\n";
  print "Data URL: $params[_url]\n";
  print "Most recent date: " . ex_date($most_recent) . "\n";
  print "Rows: " . count($data) . "\n";
  foreach($data as $row) {
	list($series,$country,$date,$value) = $row;
	if( $date == $most_recent ) {
	  $output[$country][$series] = $value;
	  $keys[$series] = 1;
	}
  }

  $sum = array();
  foreach($output as $row) {
    foreach($row as $key => $value) {
	  if( ! isset($sum[$key]) ) $sum[$key] = 0;
	  $sum[$key] += $value;
	}
  }

  $output['Total'] = $sum;
  printf("%-19s %8s %8s\n", 'COUNTRY', 'CASES', 'DEATHS');
  foreach($output as $key => $row) {
    printf("%-19s %8d %8d\n", $key, $row['CCASEALL'], $row['CMORTALL']);
  }
}

// DONE


function msg($msg) {

  fprintf(STDERR, "$msg\n");
}

function get_file($url, $dst) {
  global $params;

  $info = pathinfo($url);
  $path = $params['data_dir'] . '/' . $dst . '.' . $info['extension'];
  if( $params['cache'] == 1 ) return $path;

  if( ($file = file_get_contents($url)) === false ) {
    return null;
  }

  file_put_contents($path, $file);
  return $path;
}

function end_of_month($date) {
  static $tz=null;

  if( is_null($tz) ) $tz = date('Z');

  $date = getdate(PHPExcel_Shared_Date::ExcelToPHP($date) - $tz);
  return PHPExcel_Shared_Date::PHPToExcel(mktime(0, 0, 0, $date['mon']+1, 0, $date['year']));
}

function ex_date($date) {
  return gmdate('m/j/Y', PHPExcel_Shared_Date::ExcelToPHP($date));
}

function _sort($a, $b) {
  if( $a[2] !== $b[2] ) return $a[2] - $b[2];
  if( $a[1] !== $b[1] ) return strcmp($a[1], $b[1]);
  return strcmp($a[0], $b[0]);
}
