<?php

//include('./util.php');

 function loadfleet($shiplist) {
	 $ocean = new_ocean();
	 foreach($shiplist as $ship){
		 $ocean = addship($ocean, $ship);
	 }
	 return $ocean;
 }
 
 function new_ocean() {
	 return array_fill(0, 81, false);
 }
 function addship($ocean, $ship) {
	 $xoffset = $ship[0];
	 $yoffset = $ship[1];
	 $shiplength = $ship[2];
	 $horizontal = $ship[3];
	 
	 if($horizontal){
		 if ($shiplength>9-$xoffset){
			 die('Error: the ship is too long for this grid');
		 }
		 array_splice($ocean, $xoffset+$yoffset*9, $shiplength, array_fill(0, $shiplength, true));
	 } else { 
		 if ($shiplength>9-$yoffset){
			 die('Error: the ship is too long for this grid');
		 }
		 $rotated = undimension(rotate(dimension($ocean, 9, 9)));
		 array_splice($rotated, $xoffset*9+$yoffset, $shiplength, array_fill(0, $shiplength, true));
		 $ocean = undimension(rotate(dimension($rotated, 9, 9)));
	 }
	 return $ocean;
 }

function colliding_ships($shiplist) {
	$f = function ($x){ return xyForm($x); };
	$g = function ($y) { return array_map(function($x){ return $x[0]+$x[1]*9; }, $y); };
	$xyformat = array_map($g, array_map($f, $shiplist));
	if(count(set2(flat($xyformat)))===count(flat($xyformat))){
		return false;
	}
	return true;
}

?>