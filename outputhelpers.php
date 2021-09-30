<?php
	include('./ships.php');

$PDO_ARGS = ["mysql:host=localhost:3306;dbname=battleships", "root", ""];
// put PDO handle into a functiuon into a difeertnt fuileabove htdocs and include
function connectDB(){
	return (new PDO("mysql:host=localhost:3306;dbname=battleships", "root", "");)
}
function getHitsFromDb($gameid, $playerid) { 
	// int -> int -> [[int, int]]
	global $PDO_ARGS;
	$lasthit = lastHitId($gameid, $playerid);
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare("SELECT x, y FROM hits WHERE game_id=? AND player_id=? AND id>? ORDER BY id DESC");
	$smt->execute(array($gameid, $playerid, $lasthit));
	$results = $smt->fetchAll();
	$hits = array();
	foreach($results as $array){
		$hits[] = array((int)$array['x'], (int)$array['y']);
	}
	return $hits;
}

function lastHitId($game_id, $player_id){
	// int -> int -> int
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare("SELECT last_hit_id FROM ships WHERE game_id=? AND player_id=? AND sunk_bool=0 ORDER BY id");
	$smt->execute(array($game_id, $player_id));
	$results = $smt->fetchAll();
	return $results[0]['last_hit_id'];
}

function sqlintcols($results, $cols){
	// [{str:str}] -> [str] -> [{str:str|int}]
	global $PDO_ARGS;
	foreach($results as $k => $r){
		foreach($cols as $c){
			$results[$k][$c] = intval($r[$c]);
		}
	}
	return $results;
}
function getShipsPure(){
	// null -> [{(str):(str|int|[int])}]
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare('SELECT name, length, cost FROM shiptype ORDER BY shiptype_id ASC');
	$smt->execute();
	$obj = $smt->fetchAll(PDO::FETCH_ASSOC);
	foreach($obj as $x){ $x['offsets'] = []; }
	$obj = sqlintcols($obj, ["length", "cost"]);
	$smt2 = $handle->prepare('SELECT * FROM turretlocations');
	$smt2->execute();
	$typeres2 = $smt2->fetchAll();
	foreach($typeres2 as $t){
		$obj[$t['shiptype_id']]['offsets'][] = intval($t['offset']);
	}
	return $obj;
}

function get_ships_from_db($gameid, $playerid, $moreInfo=false) {
	// int -> int -> bool -> [strs]|[strs|bool]
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare("SELECT * FROM ships WHERE game_id=? AND player_id=? ORDER BY id");
	$smt->execute(array($gameid, $playerid));
	$result = $smt->fetchAll();
	$shipdetails = array();
	foreach($result as $array) {
		$orientation = $array['orientation'];
		$last_hit_id = $array['last_hit_id']==="1";
		$sunk_bool = $array['sunk_bool']==="1";
		if($moreInfo){
			$shipdetails[] = array($array['x'], $array['y'], $array['size'], $orientation, $last_hit_id, $sunk_bool);
		}else{
			$shipdetails[] = array($array['x'], $array['y'], $array['size'], $orientation);
		}
	}
	return $shipdetails;
}

function get_money_from_db($gameid, $playerid) {
	// int -> int -> int
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare("SELECT game_id, player_id, money FROM money WHERE game_id=? AND player_id=?");
	$smt->execute(array($gameid, $playerid));
	$result = $smt->fetchAll();
	$money_amt=$result[0]['money'];
	return $money_amt;
}

function shipsarray($get_ships_from_db) {
	// [strs]|[strs|bool] -> [ [[int, int]] ]
	global $PDO_ARGS;
	$ships = $get_ships_from_db;
	$coord_array = [];
	foreach($ships as $ship) {
		$x=$ship[0]; $y=$ship[1]; $size=$ship[2]; $orientation=$ship[3];
		$coords = [];
		for($i=0;$i<$size;$i++){
			$coords[] = $orientation ? [$x+$i,(int)$y] : [(int)$x,$y+$i];
		}
		$coord_array[]=$coords;
	}
	return $coord_array;
}

function nameships($shiphitbool) {
	// [bool|str] -> [bool|str] 	
	global $PDO_ARGS;
	$alphabet=['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
	$i=0;
	$namedships=[];
	foreach($shiphitbool as $ship){$namedships[]=array_merge([$alphabet[$i]],$ship);$i++;}
	return $namedships;
}

function managefleet($game_id, $password, $fleethealth) {
	// int -> str -> [bool|str] -> HTMLString
	global $PDO_ARGS;
	$ret = '<div id=cmd>';
	$ret .= '<ul>';
	foreach($fleethealth as $ship){
		$name=$ship[0]; $sunk=$ship[1]; $hits=array_slice($ship,2); $length=count($hits);
		switch($length){case 2: $shiptype='Destroyer';break;
						case 3: $shiptype='Submarine';break;
						case 4: $shiptype='Cruiser';break;
						case 5: $shiptype='Battleship';break;
						case 6: $shiptype='Carrier';break;}
		$string =(
			!$sunk
			?'Ship '.$name.' '.'('.$shiptype.')'.': '.implode("",$hits)
			:'<div style="color: grey">'.'Ship '.$name.' '.'('.$shiptype.')'.': '.implode("",$hits).' SUNK'.'</div>'
		);
		$string_ = 'Ship '.$name.' '.'('.$shiptype.')'.': '.implode("",$hits);
		$string = !$sunk ? $string_ : '<div style="color: grey">'.$string_.' SUNK'.'</div>';
		$ret .= ('<li> '.$string.'</li>');
	}
	$ret .= ('</ul></div>');
	
	// options to add new ships at what coordinates
	/* $handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$qry = $handle->prepare("SELECT name, length, cost FROM shiptype ORDER BY length");
	$qry->execute();
	$results = $qry->fetchAll();

	$optionsStr = '';
	foreach($results as $ship) {
		$optionsStr.= '<option value="';
			$optionsStr.= $ship['length'].'">';
			$optionsStr.= $ship['name'].' '.'('.$ship['length'].') ';
			$optionsStr.= 'Cost: $'.$ship['cost'];
		$optionsStr.='</option>';
	}
	$ret .= ('
	<form method="post" action="/battleships/output.php" style="padding-left: 10px;">
		<input name="x" placeholder="Enter starting x"/>
		<input name="y" placeholder="Enter starting y"/>
		<select name="length">'.
			$optionsStr
		.'</select>
		<select name="orientation">
			<option value="1">orientation</option>
			<option value="0">Vertical</option>
		</select>
		<input type="hidden" name="game_id" value='.$game_id.'>
		<input type="hidden" name="password" value='.$password.'>
		<button>Add</button>
	</form>
	'); 
	$ret .= ('</div>');*/

	return $ret;
}

function shiphitbool($shipsarray, $hitsarray) {
	// [ [[int, int]] ] -> [ [int, int] ] -> [ [bool|str] ]
	$hitships = [];
	foreach($shipsarray as $ship){
		$hitbool=[]; $asterix=0;
		foreach($ship as $coord) {
			if(in_array($coord, $hitsarray)){$hitbool[]='*';$asterix++;}else{$hitbool[]='o';}
		}
		$hitships[]=prepend(!!($asterix==count($ship)), $hitbool);
	}
	return $hitships;
}

function possible_hits($shipsarray, $hitsarray) {
	
	$can_attack = 0;
	foreach($shipsarray as $ship){
		$turretlocations = getTurrets(count($ship)); // NOTE: based on length
		$turretarray = [];
		foreach($turretlocations as $turret){
			$turretarray[] = $ship[(int)$turret['offset']];
		}
		foreach($turretarray as $turret) {
			if(!in_array($turret, $hitsarray)){$can_attack++;}
		}
	}
	return $can_attack;
}
/*** Turrets ***/
function getTurrets($shiplength) {
	// int -> [{str:str}]
	// returns a list of turret positions for a particular ship length
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$qryship = $handle->prepare("SELECT shiptype_id from shiptype WHERE length=?");
	$qryship->execute([$shiplength]);
	$qryshipresults = $qryship->fetchAll();
	$shiptype_id = $qryshipresults[0]['shiptype_id'];
	$qryturrets = $handle->prepare("SELECT offset from turretlocations WHERE shiptype_id=?");
	$qryturrets->execute([$shiptype_id]);
	$turretresults = $qryturrets->fetchAll();
	return $turretresults;
}

function turretCoords($game_id, $player_id){
	// int -> int -> [ [int, int] ]
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$smt = $handle->prepare("SELECT x,y,size,orientation FROM ships WHERE game_id=? AND player_id=? AND sunk_bool=0");
	$smt->execute(array($game_id, $player_id));
	$results = $smt->fetchAll(); // array of ships
	$coords = [];
	foreach($results as $ship){
		$x=$ship['x']; $y=$ship['y'];
		$len=$ship['size']; $horiz=$ship['orientation'];
		$turretOffsets= getTurrets($len);
		$xyForm = xyForm([$x, $y, $len, $horiz]);
		foreach($turretOffsets as $offset){
			$coords[] = $xyForm[$offset['offset']];
		}
	}
	return $coords;
}
/*** Ships hits ***/
function xyForm($ship, $diff=false){
	// "int"  -> bool -> [ [int, int] ]
	if(count($ship)===0){ return []; }
	// make x, y, len ints becuase db returns as strs
	if($diff){
		$x=intval($ship['x']); $y=intval($ship['y']);
		$len=intval($ship['length']); 
		$horiz= $ship['orientation']==="0"||$ship['orientation']==="2";
	}else{
		$x=intval($ship[0]); $y=intval($ship[1]);
		$len=$ship[2]; 
		$horiz= $ship[3]==="0"||$ship[3]==="2";

	}
	$xys=[];
	for($i=0;$i<$len;$i++){
		$modx =  $horiz ? $x+$i : $x;
		$mody = !$horiz ? $y+$i : $y;
		$xys[]= [(int)$modx, (int)$mody];
	}
	return $xys;
}

function hits_left($game_id) {
	// int -> int 
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$hits = $handle->prepare("SELECT * FROM turn WHERE game_id=?");
	$hits->execute([$game_id]);
	$results = $hits->fetchAll();
	return $results[0]['hits_left'];
}

function whos_turn($game_id) {
	// int -> [int, int]
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$turn = $handle->prepare("SELECT * FROM turn WHERE game_id=?");
	$turn->execute([$game_id]);
	$info = $turn->fetchAll();
	$results = $info[0];
	return [$results['player_id'], $results['hits_left']];
}

function last_three($game_id, $player_id) {
	// int -> int -> [{key:"int"}]
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$lastthreeqry = $handle->prepare("SELECT * FROM hits WHERE game_id=? AND player_id=? ORDER BY id DESC LIMIT 3");
	$lastthreeqry->execute([$game_id, $player_id]);
	$results = $lastthreeqry->fetchAll();
	varjson($results);
	return $results;
}

function valid_placement($last_three, $ship){
	// [{key:"int"}] -> bool
	foreach($last_three as $hit){
		$hitship = [$hit['x'], $hit['y'], 1, true];
		if(colliding_ships([$hitship, $ship])===true){
			return false;
		}
	}
	return true;
}

function update_turn($game_id, $current_id, $qry_hits_left) { // procedure
	// int -> int -> int -> null
	global $PDO_ARGS;
	$next_id = $current_id==2?1:2;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$turn = $handle->prepare("UPDATE turn SET player_id=".$next_id.", hits_left=".$qry_hits_left." WHERE game_id=?");
	$turn->execute([$game_id]);
}

function log_action($game_id, $action_type, $action_id) { // procedure
	// int -> int -> int
	global $PDO_ARGS;
	$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
	$logdata = $handle->prepare("INSERT INTO history (game_id, action_type, action_id) VALUES (?,?,?)");
	$logdata->execute([$game_id, $action_type, $action_id]);
}

?>