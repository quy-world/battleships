<?php
	include('./outputhelpers.php');
	include('./util.php');
	
	function getfresh(){ header('Location: http://localhost/battleships/index.php'); }
	
	/*** CHECK FOR COOKIES ***/
	if(isset($_COOKIE["battleshipcookie"])){
		$usercookie = $_COOKIE["battleshipcookie"];
		$sessionid  = substr($usercookie, 0, 6);
		$usercookie2 = substr($usercookie, 6, strlen($usercookie));
		$results = bqry("SELECT * from sessions WHERE sessionid=?", [$sessionid]);
		$result  = $results[0];
		if($result['token']!==hash("sha512", $result['tokensalt'].$usercookie2)){
			echo('you still hold a cookie');
		}else{
			$signedin = intval($result['userid']);
		}		
	}
	/* if(isset($_COOKIE["gameid"])){
		setcookie("gameid", $_COOKIE["gameid"], 1);
		setcookie("playerid", $_COOKIE["playerid"], 1);
		setcookie("enemyid", $_COOKIE["enemyid"], 1);
	} */
	/*** NEW USER ***/
	if(isset($_POST['newuser'])){
		$username = $_POST['newuser'];
		$pw = $_POST['pw'];
		$pw2 = $_POST['pw2'];
		if($pw === '' || $pw !== $pw2){
			die('Your passwords do not match. Please try again.');
		}
		$results = bqry("SELECT * FROM users WHERE username=?", [$username]);
		if(count($results)>0){
			die('Username "'.$username.'" has already been taken.');
		}

		$salt = randstr(6);
		$hashedpw =  hash("sha512", $salt.$pw);
		bexec("INSERT INTO users (username,password,salt) VALUES (?,?,?)", [$username, $hashedpw, $salt]);
	}

	/*** SIGN IN ***/
	if(isset($_POST['username'])){
		$results = bqry("SELECT * FROM users WHERE username=?", [$_POST['username']]);
		$result = $results[0]; // expect only one result
		$hashedreceived = hash("sha512", $result['salt'].$_POST['pw']);
		if($result['password']===$hashedreceived){
			echo('WELCOME!!');
		}else{
			echo('!!WRONG PASSWORD');
		}

		/*** SEND TOKEN AND GET USER AGENT ***/
		$tsalt = randstr(6);
		$usalt = randstr(6);
		$randomtoken = randstr(20);
		$randtokensalted = hash("sha512", $tsalt.$randomtoken);
		$useragentsalted = hash("sha512", $usalt.$_SERVER["HTTP_USER_AGENT"]);
		$expiry = time() + 3600; // 1 hour sessions
		echo('user agent is: '. $_SERVER["HTTP_USER_AGENT"]);
		echo('<br> rnadom token is: '.$randomtoken);
		$sessionid = randstr(6);
		bexec('INSERT INTO sessions (sessionid, userid, token, tokensalt, useragent, useragentsalt, expiry) VALUES (?,?,?,?,?,?,?)', [$sessionid, $result['userid'], $randtokensalted, $tsalt, $useragentsalted, $usalt, $expiry]);
		 
		/*** SET COOKIE ***/
		setcookie("battleshipcookie", $sessionid.$randomtoken, $expiry);
		getfresh();
	}
	
	/*** NEW GAME ***/
	if(isset($signedin)){
		//$pendingGames = bqry("SELECT * FROM pendinggames JOIN users ON users.userid =  pendinggames.userid WHERE pendinggames.userid!=?", [$signedin]);
		$results = bqry("SELECT * FROM users WHERE userid=?", [$signedin]);
		$name = $results[0]['username'];
		echo '<h2> Signed in as : '.$name.'</h2>';
		if(isset($_POST['signout'])){
			setcookie("battleshipcookie", $_COOKIE["battleshipcookie"], 1); 
			getfresh();
		}
		if(isset($_POST['gamename'])) {
			$gamename = $_POST['gamename'];
			$newgameid = bexec("INSERT INTO games (gamename) VALUES (?)", [$gamename]);
			bexec("INSERT INTO gameusers (gameid, userid, timestamp) VALUES (?,?,now())", [$newgameid, $signedin]);
			
		}
		
		/** Get Available Games **/
		$pendingGames = bqry("
			SELECT * FROM (
				SELECT users.username AS username, gameusers.gameid AS gameid, games.gamename AS gamename, users.userid AS userid, gameusers.userid AS guid, count(gameusers.gameid) AS usernum from users 
					JOIN gameusers ON users.userid=gameusers.userid 
					JOIN games     ON gameusers.gameid=games.gameid 
					GROUP BY gameusers.gameid having usernum=1
			) AS table1 WHERE userid!=?;", [$signedin]);
		$str = '<h2>List of Pending Games: </h2> 
			<form method="post" action="/battleships/index.php">';
		forEach($pendingGames as $p){
			$arr = implode(',', [$signedin, $p['userid'], $p['gameid'], $p['gamename']]);
			$str .= '<input type="radio" name="joingame" value="'.
				$arr
			.'" id="'.$p['gameid'].'"gameid="'.$p['gameid'].'" '
			.' />'.'<label for="'.$p['gameid'].'">'.$p['gamename'].' feat. "'.$p['username'].'"</label>';
		}
		$str .= '<input type="submit" value="Join game">';
		$str .= '</form>';
		echo($str);
		
		if(isset($_POST['joingame'])){
			$arr = explode(',', $_POST['joingame']);
			
			$signedin = intval($arr[0]);
			$gameowner = intval($arr[1]);
			$gameid = intval($arr[2]);
			$gamename = $arr[3];
			if(count(bqry("SELECT gameid FROM gameusers WHERE gameid=?", [$gameid]))<2){
				bexec("INSERT INTO gameusers (gameid, userid, timestamp) VALUES (?,?,UNIX_TIMESTAMP())", [$gameid, $signedin]);
				//insert money and history into db:

				bexec("INSERT INTO money (gameid, playerid, money) VALUES (?,?,?), (?,?,?)", [$gameid, $gameowner, 500, $gameid, $signedin, 500]);

				//assign random ship 
				function assign_random($gameid, $player_id) { //  procedure
					$invalid_ship=true;
					$randx;
					$randy;
					$randlen;
					$randhoriz;
					global $gameowner, $signedin;
					while($invalid_ship){
						$randx = rand(0,8);
						$randy = rand(0,8);
						$randlen = rand(2,6);
						$randhoriz = rand(0,1)==0?true:false;
						if($randhoriz){
							if(!($randlen>9-$randx)){$invalid_ship=false;}
						}else{ // vertical
							if(!($randlen>9-$randy)){$invalid_ship=false;}
						}
					}
					$lastHIT=0;
					$sunkBool = 0;
					//insert ship
					$inputs = [$gameid, $player_id, $randx, $randy, $randlen, $randhoriz, $lastHIT, $sunkBool]; 
					bexec("INSERT INTO ships (gameid,playerid,x,y,size,orientation, lasthitid, sunkbool) VALUES (?,?,?,?,?,?,?,?)", $inputs);

					$skelresults = bqry("SELECT shiptype_id FROM shiptype WHERE length=?", [$randlen]);
					$shiptype_id = $skelresults[0]['shiptype_id'];

					//get turrets update turn
					$turrets = bqry("SELECT * FROM turretlocations WHERE shiptype_id=?", [$shiptype_id]);

					if($player_id == $gameowner) { // give main player the first turn
						bexec("INSERT INTO turn (gameid, playerid, hitsleft) VALUES (?,?,?)", [$gameid, $gameowner, count($turrets)]);
					}else{
						bexec("INSERT INTO turn (gameid, playerid, hitsleft) VALUES (?,?,?)", [$gameid, $signedin, count($turrets)]);
					}
				}
				assign_random($gameid, $gameowner);
				assign_random($gameid, $signedin);		
			}
			getfresh();
		}

		/** Show Active Games **/
		$str2 = '<h2>My Active Games: </h2>';
		$activegames = bqry("SELECT * FROM 
			(SELECT gameusers.gameid AS gameid, gameusers.userid AS userid, gamename 
				FROM gameusers 
				JOIN games ON gameusers.gameid=games.gameid 
				WHERE gameusers.gameid 
					IN (SELECT gameusers.gameid 
							FROM gameusers 
							JOIN games ON gameusers.gameid=games.gameid 
							GROUP BY gameusers.gameid HAVING count(gameusers.gameid)=2
						)
			) AS temp WHERE userid=?;
		", [$signedin]);
		$str2 .= '<form method="post" action="/battleships/output.php">';
		foreach($activegames as $a){
			$arr = implode(',', [$signedin, $a['gameid']]);
			$str2 .= '<input type="radio" name="playgame" value="'.
				$arr
			.'" id="'.$a['gameid'].'" />'.'<label for="'.$a['gameid'].'">"'.$a['gamename'].'"</label>';
		}
		$str2 .= '<input type="submit" value="Enter game">';
		$str2 .= '</form>';
		echo($str2);

		/*** Create New Game ***/
		$str3  = '<h2>Create a new game:</h2><form method="post" action="/battleships/index.php">';
		$str3 .= '<input name="gamename" type="text" placeholder="Enter the game name:">';
		$str3 .= '<input hidden type="text" name="userid" value="'.$signedin.'">';
		$str3 .= '<input type="submit" value="Create Game">';
		$str3 .= '</form>';
		echo($str3);

		/*** Sign out button ***/
		$str4  = '<form method="post" action="/battleships/index.php">';
		$str4 .= '<input name="signout" type="submit" value="Sign out">';
		$str4 .= '</form>';
		echo($str4);
	}else{
		// not signed in page here
		$str =  '<div style="float: left">
					<h2>New User: </h2>
						<form method="post" action="/battleships/index.php">
							Username: <input type="text" name="newuser"/> <br>
							Password:<input  type="password" name="pw"/><br>
							Confirm Password:<input  type="password" name="pw2"/><br>
							<input type="submit" value="Sign Up">
						</form>
					</div>

					<div style="float: left">
					<h2>Sign in: </h2>
						<form method="post" action="/battleships/index.php">
							Username: <input type="text" name="username"/> <br>
							Password:<input  type="password" name="pw"/><br>
							<input type="submit" value="Sign In">
						</form>
					</div>';
		echo($str);
	}
?><html>
	<head></head>
	
	<body>
		
	</body>
</html>
