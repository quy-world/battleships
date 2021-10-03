<?php
	include('./ships.php');

/*** Ships ***/
function sqlintcols($results, $cols){
	// [{str:str}] -> [str] -> [{str:str|int}]
	foreach($results as $k => $r){
		foreach($cols as $c){
			$results[$k][$c] = intval($r[$c]);
		}
	}
	return $results;
}
function getShipsPure(){
	// null -> [{(str):(str|int|[int])}]
	$handle = getConnection();
	$smt = $handle->prepare('SELECT name, length, cost FROM shiptype ORDER BY shiptype_id ASC');
	checkHandle($smt);
	$smt->execute();
	$obj = $smt->fetchAll(PDO::FETCH_ASSOC);
	foreach($obj as $x){ $x['offsets'] = []; }
	$obj = sqlintcols($obj, ["length", "cost"]);
	$typeres2 = bqry('SELECT * FROM turretlocations', []);
	foreach($typeres2 as $t){
		$obj[$t['shiptype_id']]['offsets'][] = intval($t['offset']);
	}
	return $obj;
}

function get_ships_from_db($gameid, $playerid, $moreInfo=false) {
	// int -> int -> bool -> [strs]|[strs|bool]
	$result = bqry("SELECT * FROM ships WHERE game_id=? AND player_id=? ORDER BY id", [$gameid, $playerid]);
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

function shipsarray($get_ships_from_db) {
	// [strs]|[strs|bool] -> [ [[int, int]] ]
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

function xyForm($ship, $diff=false){
	// "int"  -> bool -> [ [int, int] ]
	if(count($ship)===0){ return []; }
	// make x, y, len ints because db returns strs
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

function nameships($shiphitbool) {
	// [bool|str] -> [bool|str] 	
	$alphabet=['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
	$i=0;
	$namedships=[];
	foreach($shiphitbool as $ship){$namedships[]=array_merge([$alphabet[$i]],$ship);$i++;}
	return $namedships;
}

function managefleet($game_id, $moisession, $fleethealth) {
	// int -> str -> [bool|str] -> HTMLString
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
	/* $handle = getConnection();
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
		<input type="hidden" name="moisession" value='.$moisession.'>
		<button>Add</button>
	</form>
	'); 
	$ret .= ('</div>');*/

	return $ret;
}


/*** Hits ***/
function getHitsFromDb($gameid, $playerid) { 
	// int -> int -> [[int, int]]
	$lasthit = lastHitId($gameid, $playerid);
	$handle = getConnection();
	$results = bqry("SELECT x, y FROM hits WHERE game_id=? AND player_id=? AND id>? ORDER BY id DESC", [$gameid, $playerid, $lasthit]);
	$hits = array();
	foreach($results as $array){
		$hits[] = array((int)$array['x'], (int)$array['y']);
	}
	return $hits;
}

function lastHitId($game_id, $player_id){
	// int -> int -> int
	$results = bqry("SELECT last_hit_id FROM ships WHERE game_id=? AND player_id=? AND sunk_bool=0 ORDER BY id", [$game_id, $player_id]);
	if(count($results)===0){
		return 0;
	}
	return $results[0]['last_hit_id'];
}

function last_three($game_id, $player_id) {
	// int -> int -> [{key:"int"}]
	return bqry("SELECT * FROM hits WHERE game_id=? AND player_id=? ORDER BY id DESC LIMIT 3", [$game_id, $player_id]);
}

function hits_left($game_id) {
	// int -> int 
	$results = bqry("SELECT * FROM turn WHERE game_id=?", [$game_id]);
	return $results[0]['hits_left'];
}

/*** Ships + Hits ***/
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

function possible_hits($shipsarray, $hitsarray) {
	$can_attack = 0;
	foreach($shipsarray as $ship){
		$turretlocations = getTurrets(count($ship));
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
	$qryshipresults = bqry("SELECT shiptype_id from shiptype WHERE length=?", [$shiplength]);
	$shiptype_id = $qryshipresults[0]['shiptype_id'];
	$turretresults = bqry("SELECT offset from turretlocations WHERE shiptype_id=?", [$shiptype_id]);
	return $turretresults;
}

function turretCoords($game_id, $player_id){
	// int -> int -> [ [int, int] ]
	$results = bqry("SELECT x,y,size,orientation FROM ships WHERE game_id=? AND player_id=? AND sunk_bool=0", [$game_id, $player_id]);
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

/*** Turns and Money ***/
function get_money_from_db($gameid, $playerid) {
	// int -> int -> int
	$result = bqry("SELECT game_id, player_id, money FROM money WHERE game_id=? AND player_id=?", [$gameid, $playerid]);
	$money_amt=$result[0]['money'];
	return $money_amt;
}

function whos_turn($game_id) {
	// int -> [int, int]
	$info = bqry("SELECT * FROM turn WHERE game_id=?", [$game_id]);
	$results = $info[0];
	return [$results['player_id'], $results['hits_left']];
}

function update_turn($game_id, $player_id, $qry_hits_left) {
	// int -> int -> int -> null
	bexec("UPDATE turn SET player_id=".$player_id.", hits_left=".$qry_hits_left." WHERE game_id=?", [$game_id]);
}

function log_action($game_id, $action_type, $action_id) {
	// int -> int -> int -> null
	bexec("INSERT INTO history (game_id, action_type, action_id) VALUES (?,?,?)", [$game_id, $action_type, $action_id]);
}

?>