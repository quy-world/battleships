<?php

	include('./battleshipgame.php');
	include('./outputhelpers.php');
	
	if(!isset($player_id)){
		$player_id = intval($_COOKIE["playerid"]);
		$enemy_id = intval($_COOKIE["enemyid"]);
		$game_id = intval($_COOKIE["gameid"]);
	}
	
	$results = bqry("SELECT * FROM users WHERE userid=?", [$player_id]);
	$name = $results[0]['username'];
	echo '<h2> Signed in as : '.$name.'</h2>';
	
	echo('pPPP'. $player_id.$enemy_id);
	$pureships = getShipsPure();
	function getshipdetails($shiptypeid=false){
		global $pureships;
		if($shiptypeid === false){ return $pureships; }
		return $pureships[$shiptypeid];
	}
	/*** Generate turrets and ship ***/
	function createTurr($offset){
		// int -> HTMLString
		$turrstyle = cssstyles(
		  [ ['margin-left', ($offset*40+4).'px'
		] , ['margin-top' , 2 . 'px'
		] , ['dispay'     , 'initial'
		] , ['background' , 'transparent'
		] ], true);
		
		return '<div class="container" '.$turrstyle.'">
			<div class="modelThreeBase"></div>
			<div class="modelThreeGun"></div>
		   </div>';	
	}
	function createShip($typeid, $x=0, $y=0, $orientation){
		// int -> int -> int -> int -> HTMLString
		$ship = getshipdetails($typeid);
		$orientation = $orientation===0?'':'vertical';
		
		$styles = cssstyles(
			  [ ['margin-left', (40*$x).'px'
			] , ['margin-top' , (40*$y).'px'
			] , ['width'      , (40*$ship['length']).'px'
			] , ['background' , 'black'
			] ], true);
		
		$str = '<div class="ship '.$orientation.'"'.$styles.'">';
		
		foreach($ship['offsets'] as $o){
			$str .= createTurr($o);	
		} 
		$str .= '</div>';
		return $str;
	}
	
	function createBox(){
		// null -> HTMLString
		$box = '<div id="shipbox">';
		$heading = '
			<div id="shipsheader">
				<div id="headerinner">
					<div id="innername"> </div>
				</div>
			</div>
		';
		$box .= $heading;
		$box .= '<div id="shipcontainer">';
		$getShipDetails = getshipdetails();
		for($i=0; $i<count(getshipdetails()); $i++){
			$box .= createShip($i, $i, $i, 0);
		}
		$box .= '</div>';	
		$box .= '<button id="rotateships">
					<div id="rotateshipsinner">
						<div class="container rotater">
							<div class="base"></div>
							<div class="modelThreeBase"></div>
							<div class="modelThreeGun"></div>
					   </div>
					</div>
				</button>';
		
		$box .= '</div>';
		return $box;
	}
	/*** Adding hits to DB ***/
	if(isset($_POST['hitx'])&&isset($_POST['hity'])&&isset($_POST['moisession'])) { 
		// qPOST
		$x = $_POST['hitx'];
		$y = $_POST['hity'];
		if(whos_turn($_POST['game_id'])[0]!=$player_id){ die("It's not your turn."); }
		if(intval(hits_left($_POST['game_id'])[0])===0){ die("You have no hits available. Buy ships to get hits."); }
		bexec('INSERT INTO hits (game_id,player_id,x,y) VALUES (?,?,?,?)', [$_POST['game_id'], $enemy_id, $x, $y]);
		
		// ADD HISTORY:
		$hit_id = getConnection()->lastInsertId();
		log_action($_POST['game_id'], 2, $hit_id);
		$results = bqry("SELECT * FROM turn WHERE game_id=?", [$_POST['game_id']]);
		$result=$results[0];
		if($result['hits_left'] <= 1){
			// set turn to other player
			update_turn(
				$_POST['game_id'], 
				$enemy_id, 
				possible_hits(
					shipsarray(get_ships_from_db($game_id, $enemy_id)),
					getHitsFromDb($game_id, $enemy_id)
				)
			);
		}else{
			$decrement=$result['hits_left']-1;
			bexec("UPDATE turn SET hits_left=".$decrement." WHERE game_id=? AND player_id=?", [$_POST['game_id'], $player_id]);
		}
	}
	
	
	/*** Handling new ships ***/
	if(isset($_POST['x'])){
		// x and y are numbers for now
		$x = $_POST['x']; $y = $_POST['y'];
		$len = $_POST['length']; 
		$horiz = (int)$_POST['orientation']===1?1:0;

		$game_id = $_POST['game_id']; //$player_id = $_POST['player_id'];
		$new_ship = [$x, $y, $len, $horiz];

		$handle = getConnection();
		$results = bqry("SELECT * FROM shiptype WHERE length=?", [$len]);
		$ship = $results[0];
		if((int)$x<0||(int)$x>8||(int)$y<0||(int)$y>8
			||(($horiz===1) && (((int)$y+(int)$len)>9) )
			||(!($horiz===1) && (((int)$x+(int)$len)>9) )){
			die('Invalid inputs sent for ship creation.');
		}

		if(!valid_placement(last_three($game_id, $player_id), $new_ship)){die('Cannot place ship in area of last 3 hits');}
		
		$shipcoords = xyForm($_POST, true);
		// CHECK USER HAS ENOUGH MONEY:
		$handle = getConnection();
		$results = bqry("SELECT money FROM money WHERE game_id=? AND player_id=?", [$game_id, $player_id]);
		$money= $results[0]['money'];
		if((int)$money < $ship['cost']){die('You do not have enough money to do that!');}
		// CHECK FOR COLLISIONS:
		$list_of_ships = append(get_ships_from_db($game_id, $player_id), $new_ship);
		if(colliding_ships($list_of_ships)===true){
			die('Ships Collide. Try again.');
		}else{ 
			// APPEND TO DATABASE:
			$delqstr_ = '';
			// DELETE OLD HITS
			foreach($shipcoords as $sc){
				$ex = (int)$sc[0];
				$wy = (int)$sc[1];
				$delqstr_ .= "DELETE FROM hits WHERE game_id=".$game_id." AND player_id=".$player_id." AND x=".$ex." AND y=".$wy.";";
			}
			$delqstr = "START TRANSACTION;".$delqstr_."COMMIT;";
			$delhit = $handle->prepare($delqstr);
			checkHandle($delhit);
			$delhit->execute();
			$delhit->closeCursor();
			$hitresults = bqry("SELECT * FROM hits WHERE game_id=? AND player_id=? ORDER BY id DESC", [$game_id, $player_id]);
			$last_hit_id = isset($hitresults[0]['id'])?$hitresults[0]['id']:0;
			
			$turrets = turretCoords($game_id, $player_id);
			$tnum = [];
			$hitnorm = array_map(function($x){ return [(int)$x['x'], (int)$x['y']];}, $hitresults);
			$hitsavailable = count($turrets) + count(getTurrets((int)$_POST['length']));

			foreach($turrets as $t){
				if($hitsavailable===0){ break; }
				if(is_int(array_search($t, $hitnorm))){ $hitsavailable--; }
			}
			//update_turn((int)$game_id, $player_id, $hitsavailable);
			// ADD THE NEW SHIP:
			$inputs = [$game_id, $player_id, $x, $y, $len, $horiz, $last_hit_id, 0];
			bexec('INSERT INTO ships (game_id,player_id,x,y,size,orientation,last_hit_id, sunk_bool) VALUES (?,?,?,?,?,?,?,?)', $inputs);

			//ADD HISTORY:
			$ship_id = $handle->lastInsertId();
			log_action($game_id, 1, $ship_id);
			// UPDATE PLAYER'S MONEY:
			$nowmoney=$money-$ship['cost'];
			bexec("UPDATE money SET money=".$nowmoney." WHERE game_id=? AND player_id=?", [$game_id, $player_id]);
		}

	}
?><html>
<head>
<link href="/battleships/style.css" rel="stylesheet" type="text/css">
<script src="/battleships/script.js" defer></script>
<link href="./css/all.min.css" rel="stylesheet">
<script>
	var game_id = <?php echo(json_encode($game_id));?>;
	var moisession = <?php echo(json_encode($moisession));?>; // qPost
</script>

</head>
<body>


<?php

/*** Displaying Functions ***/
function label_graphs($label, $ocean, $class='') {
	// int -> HTMLString -> str -> HTMLString
	return ('<div class="wrapmusic '.$class.'">'.'<div class="label">'.$label.'</div>'.$ocean.'</div>');
}

function cssstyles($ps, $tag=false){
	// [[CSSProperty, CSSValue]] -> bool ->  HTMLString
	$styles = implode('', array_map(function($p){return ($p[0].": ".$p[1].";");}, $ps));
	if($tag){ return ' style="'.$styles.'"'; }
	return $styles;
}

function outputShips($shiplist, $hitscoords, $hits, $mine=true){
	// [["int"|bool]] -> [int] -> [[int, int]] -> bool -> HTMLString
	$string = '';
	foreach($shiplist as $ship){ 
		$xy = xyForm($ship);
		$shipcoords = array_map(function($x){ return $x[0]+$x[1]*9; }, $xy);
		$issunk = true;
		foreach($shipcoords as $sc){
			if(!is_int(array_search($sc, $hitscoords))){
				$issunk = false; break;
			}
		}
		foreach($hits as $hit){
			if(!is_int(array_search($hit[0]+$hit[1]*9, $shipcoords))){ continue; }
			
			$styles = cssstyles(
				 [ ['margin-left', (40*$hit[0]).'px'
				], ['margin-top' , (40*$hit[1]).'px'		
				]], true);
			$string .= '<div class="hitbox yellow" '.$styles.'"><div class="exes fas fa-times"></div></div>';
		}
		if(($issunk&&!$mine)||$mine){
			$styles = cssstyles(
				  [ ['margin-left', (40*$ship[0]).'px'
				] , ['margin-top' , (40*$ship[1]).'px'
				] , ['width'      , (40*$ship[2]).'px'
				] , ['background' , 'black'
				] ], true);
			$vert = $ship[3]==="0"||$ship[3]==="2"?'':'vertical';
			$string .= '<div class="ship '.$vert.'"'.$styles.'></div>';
		}
	 }
	return $string;
}

function output($shiplist, $hits, $game_id, $player_id, $myships=true){
	// [["int"]{4}] -> [[int, int]]-> int -> int -> bool -> HTMLString
	$enemy_ship = !$myships?' enemy':'';
	$string = '<div class="ocean'.$enemy_ship.'" style="margin-top: 70px;">';
	$hitscoords = array_map(function($x){ return $x[0]+$x[1]*9; }, $hits);
	if($myships){
		$string .= outputShips($shiplist, $hitscoords, $hits);

		$lastthree = array_slice($hits, 0, 3);
		$shipcoords = array_map(function($x){ return xyForm($x);}, $shiplist);
		$shipcoords = array_reduce($shipcoords, function($a, $x){ return array_merge($a, $x); }, []);
		$shipcoords = array_map(function($x){ return $x[0]+$x[1]*9; }, $shipcoords);
		foreach($lastthree as $hit){ // x y 
			$numhit = $hit[0]+$hit[1]*9;
			if(is_int(array_search($numhit, $shipcoords))) { continue; }
			$styles = cssstyles(
				  [ [ 'left', (40*$hit[0]).'px'
				] , [ 'top' , (40*$hit[0]).'px'
				] ], true);
			$string .= '<div class="hitbox lastthree"'.$styles.'><div></div></div>';;
		}
		$hitsnums = array_map(function ($x){ return $x[0]+$x[1]*9; }, $hits);
		$turretCoords = turretCoords($game_id, $player_id);
		foreach($turretCoords as $Coord){ // x y 
			$ishit = is_int(array_search($Coord[0]+$Coord[1]*9, $hitsnums));
			$styles = cssstyles(
				  [ [ 'margin-left', (40*$Coord[0]+4).'px'
				] , [ 'margin-top', (40*$Coord[1]+2).'px'
				] , [ 'background',  'transparent'
				] , [ 'display', ($ishit?'none':'initial')
				] ]
			, true);
			$string .=
				'<div class="container" '.$styles.'">
					<div class="base"></div>
					<div class="modelThreeBase"></div>
					<div class="modelThreeGun"></div>
				   </div>';
		}
	}else{
		$shiplist = get_ships_from_db($game_id, $player_id, true);
		$xyformats = array_map(function($x){ return [(bool)$x[5], xyForm($x)]; }, shipconverter($shiplist)); // numeralised Form
		$normalform = array_map(function($x) { return [$x[0], array_map(function($y){ return $y[0]+$y[1]*9; }, $x[1])]; }, $xyformats);

		$hitcoords = [];
		$sunkcoords = [];
		foreach($normalform as $n){
			if($n[0]){
				$sunkcoords = array_merge($sunkcoords, $n[1]);
			}else{
				$hitcoords = array_merge($hitcoords, $n[1]);
			}
		}

		$string .= outputShips($shiplist, $hitscoords, $hits, false);
		$lastThree = array_slice($hits, 0, 3);
		foreach($hits as $hit){ 
			$numhit = $hit[0]+$hit[1]*9;
			$hidehitbox = !in_array($numhit, $hitcoords );
			$imrecent     =  in_array($hit, $lastThree);
			$sunkcoords2 = (in_array($numhit, $hitcoords)
				?'<div class="exes fas fa-times"></div>':'<div></div>');

			if($hidehitbox&&!$imrecent){ continue; }

			$last3 = $imrecent&&(strlen($sunkcoords2)===11)?' lastthree':'';
			$styles = cssstyles(
				  [ [ 'margin-left',(40*$hit[0]).'px'
				  ]	, [ 'margin-top',(40*$hit[1]).'px'
				  ]]
			, true);
			
			$string .= '<div class="hitbox'.$last3.'" '.$styles.'">'.$sunkcoords2.'</div>';
		}
	}
	$string .='</div>';
	return $string;
};

/*** End game functions ***/

$hitsleft = hits_left($game_id);

function turn_output($game_id, $player_id){
	// int -> int -> null
	$whos_turn = whos_turn($game_id);
	$turn_id = $whos_turn[0];
	global $enemy_id;
	global $hitsleft;
	if($turn_id == $player_id) {
		varjson("Your hits left are: ".$hitsleft);
	}else{
		varjson("Enemy's hits left are: ".$hitsleft);
	}
}

function gameover($go, $pid, $fh, $mon){
	// bool -> int -> [[str|bool]]-> "int" -> bool
	if($go===false){ return false; }
	if($go!==true){ die('WHAT?'); }
	$mepl = 0;
	global $hitsleft;
	global $hideenemy;
	foreach($fh  as $f){ if($f[1]===true){ $mepl++; } }
	if((count($fh)===$mepl||(int)$hitsleft===0) && (int)$mon<20){ 
		echo('<h2>Player '.(((int)$pid)===1?'B':'A').' has won!</h2>');
		$hideenemy = false;
		return false;
	}
	return true;
	
}


/*** Set and display state of game ***/

$ocean = new_ocean();
$shiplist = get_ships_from_db($game_id, $player_id);
$fleethealth  = nameships(shiphitbool(shipsarray(get_ships_from_db($game_id, $player_id)),getHitsFromDb($game_id, $player_id)));
$fleethealth2 = nameships(shiphitbool(shipsarray(get_ships_from_db($game_id, $enemy_id)),getHitsFromDb($game_id, $enemy_id)));
$giveoptions = true;
$hideenemy = true;
$dash  = '<div id="dash">';
$map   = '<div id="maps">';
$map .= label_graphs(
	  "my ships"
	, output($shiplist, getHitsFromDb($game_id, $player_id), $game_id, $player_id, true)
	, 'friendly');
$shiplist2 = get_ships_from_db($game_id, $enemy_id);

$moneyme  = get_money_from_db($game_id, $player_id);
$monenemy = get_money_from_db($game_id, $enemy_id);

$giveoptions = gameover($giveoptions, $player_id, $fleethealth, $moneyme);
$giveoptions = gameover($giveoptions, $enemy_id, $fleethealth2, $monenemy);

$map .= label_graphs("enemy ships", output($shiplist2, getHitsFromDb($game_id, $enemy_id), $game_id, $enemy_id, false), $hideenemy?'enemy':'friendly');

turn_output($game_id, $player_id);
if($giveoptions){ 
	$map .= managefleet($game_id, $moisession, $fleethealth);
}

$dash .= $map.'</div>';
echo $dash;

$signout  = '<form method="post" action="/battleships/index.php">';
$signout .= '<input name="signout" type="submit" value="Sign out">';
$signout .= '</form>';
echo($signout);
?>

	<script defer> 
		var money  = <?php echo($moneyme);?>;
		var newdiv = () => document.createElement('div')
		
		var cmd    = document.getElementById('cmd')
		
		var bigbox = newdiv()
		bigbox.id = "coincontainer"

		var montitle = newdiv()
		montitle.id = 'montitle'
		montitle.innerText = "Money"

		var coinjar = newdiv()
		coinjar.id = 'coinjar'
		
		bigbox.appendChild(montitle)
		bigbox.appendChild(coinjar)

		var coindiv = newdiv()
		coindiv.classList.add('container')
		coindiv.classList.add('coin')
		var thecoin = newdiv()
		thecoin.classList.add('coins')
		coindiv.appendChild(thecoin)
		var gencoin = () => coindiv.cloneNode(true)
		
		//console.log(typeof money);
		for(var i=0; i<money/10; i++){
			coinjar.appendChild(gencoin())
		}
		//coinjar.appendChild(gencoin())
		
		cmd.appendChild(bigbox)
		
		cmd.innerHTML += <?php echo json_encode(createBox()); ?>;
	</script>
	

</body>
</html>