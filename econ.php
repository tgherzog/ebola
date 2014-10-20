#!/usr/bin/php
<?php

$root = dirname(__FILE__) . '/';
require_once('PHPExcel/Classes/PHPExcel.php');
require_once("settings.php");

# print_r($params); exit;

// create destination spreadsheet
$dst = new PHPExcel();

$dst->getActiveSheet()
  ->setTitle('Imported Rice')
  ->fromArray(array('Date', 'Price/Kg', 'Country', 'Year', 'Month', '% Change'), null, 'A1');

$dst->createSheet()->setTitle('Diesel')
  ->fromArray(array('Date', 'Gallons (mill.)', 'Country', 'Year', 'Month', '% Change'), null, 'A1');

for($i=0;$i<$dst->getSheetCount();$i++) {
  $s = $dst->getSheet($i);
  $s->getStyle("A")->getNumberFormat()->setFormatCode('mmm-yy');
  $s->getStyle("B")->getNumberFormat()->setFormatCode('0.0');
  $s->getStyle("F")->getNumberFormat()->setFormatCode('0.0%');
}

$summary = array();
$recent_date = 0;
foreach($params['econ']['sources'] as $key => $info) {
  $x = 2;
  foreach($info as $country => $elem) {
	$src = PHPExcel_IOFactory::load("$params[data_dir]/econ/$elem[name]");
	if( ! $elem['factor'] ) $elem['factor'] = 1;
	$s = $src->getSheet();
	$last = $s->getHighestRow();
	$data = $s->rangeToArray("A$elem[first_row]:B$last", null, true, false, false);
	$i = $perc = 0;
	foreach($data as $row) {
	  list($date,$price) = $row;
	  if( ! $date ) break;			// A blank/0 date indicates end of data. There might be footnotes, source, or incomplete time period after that

	  $price *= $elem['factor'];
	  $rec = array($date, $price, $country, "=YEAR(A$x)", "=TEXT(A$x,\"mmm\")", null);
	  if( $i >= 12 ) {
	    $rec[5] = "=B$x/B" . ($x-12) . "-1";
		$perc = $price/ ($data[$i-12][1] * $elem['factor']) - 1;
		$perc *= 100;
	  }
	  $dst->getSheetByName($key)->fromArray($rec, null, "A$x", true);
	  $summary[$key][$country] = array(ex_date($rec[0], 'M-Y'), $perc);
	  $recent_date = max($recent_date,$date);
	  $x++;
	  $i++;
	}
  }
}

// For costmetic purposes, set some column widths
$dst->getSheet(0)->getColumnDimension('C')->setWidth(180/7);
$dst->getSheet(1)->getColumnDimension('C')->setWidth(180/7);

# write results
$dst->setActiveSheetIndex(0);
$output = PHPExcel_IOFactory::createWriter($dst, 'Excel2007');
$output->save("$params[target_dir]/{$params['econ']['target_name']}.xlsx");

if( $params['status'] === 'date' ) {
  print ex_date($recent_date) . "\n";
}
elseif( $params['status'] == 1 ) {
  print "Most recent date: " . ex_date($recent_date) . "\n";
  printf("%-20s %-20s %10s %8s\n", 'COUNTRY', 'COMMODITY', 'DATE', '% CHG');
  foreach($summary as $key => $info)
    foreach($info as $name => $row) {
	  printf("%-20s %-20s %10s %8.2f\n", $name, $key, $row[0], $row[1]);
	}
}

function ex_date($date, $fmt='m-Y') {
  return gmdate($fmt, PHPExcel_Shared_Date::ExcelToPHP($date));
}

