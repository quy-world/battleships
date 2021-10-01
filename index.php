<?php
	include('./outputhelpers.php');
	include('./util.php');
	// fetch all games
	$handle = getConnection();
	$smt = $handle->prepare("SELECT * FROM games");
	$smt->execute();
	$games = $smt->fetchAll();
	// create new game and refresh to add this game
	
	/*** CHECK FOR COOKIES ***/
	if(isset($_COOKIE["battleshipcookie"])){
		$usercookie = $_COOKIE["battleshipcookie"];
		$sessionid  = substr($usercookie, 0, 6);
		$usercookie2 = substr($usercookie, 6, strlen($usercookie));
		/* $smt = $handle->prepare("SELECT * from sessions WHERE sessionid=?");
		$smt->execute([$sessionid]);
		$results = $smt->fetchAll(); */
		$results = bqry("SELECT * from sessions WHERE sessionid=?", [$sessionid]);
		//if(isset($result)){ // problem
		$result  = $results[0];
		if($result['token']!==hash("sha512", $result['tokensalt'].$usercookie2)){
			echo('you still hold a cookie');
		}else{
			$signedin = intval($result['userid']);
		}
		//}
		
		
	}

	/*** NEW USER ***/
	if(isset($_POST['newuser'])){
		$username = $_POST['newuser'];
		$pw = $_POST['pw'];
		$pw2 = $_POST['pw2'];
		if($pw === '' || $pw !== $pw2){
			die('Your passwords do not match. Please try again.');
		}
			
		/* $smt = $handle->prepare("SELECT * FROM users WHERE username=?");
		$smt->execute([$username]);
		$results = $smt->fetchAll(); */
		$results = bqry("SELECT * FROM users WHERE username=?", [$username]);
		if(count($results)>0){
			die('Username "'.$username.'" has already been taken.');
		}

		$salt = randstr(6);
		$hashedpw =  hash("sha512", $salt.$pw);

		/* $smt2 = $handle->prepare("INSERT INTO users (username,password,salt) VALUES (?,?,?)");
		$smt2->execute([$username, $hashedpw, $salt]); */
		bexec("INSERT INTO users (username,password,salt) VALUES (?,?,?)", [$username, $hashedpw, $salt]);
	}

	/*** SIGN IN ***/
	if(isset($_POST['username'])){
		$handle = getConnection();
		// $smt = $handle->prepare("SELECT * FROM users WHERE username=?");
		// $smt->execute([$_POST['username']]);
		// $results = $smt->fetchAll();
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
		/* $smt = $handle->prepare('INSERT INTO sessions (sessionid, userid, token, tokensalt, useragent, useragentsalt, expiry) VALUES (?,?,?,?,?,?,?)');
		$smt->execute([$sessionid, $result['userid'], $randtokensalted, $tsalt, $useragentsalted, $usalt, $expiry]); */
		bexec('INSERT INTO sessions (sessionid, userid, token, tokensalt, useragent, useragentsalt, expiry) VALUES (?,?,?,?,?,?,?)', [$sessionid, $result['userid'], $randtokensalted, $tsalt, $useragentsalted, $usalt, $expiry]);
		 
		/*** SET COOKIE ***/
		//$_COOKIE["playercookie"] = $randomtoken;
		setcookie("battleshipcookie", $sessionid.$randomtoken, $expiry);
		header('Location: http://localhost/battleships/index.php');
	}
	
	/*** NEW GAME ***/
	//if(isset($_POST['game_name'])&&isset($_POST['pw_A'])&&isset($_POST['pw_B'])) {
	if(isset($signedin)){
		
		/* $smt = $handle->prepare("SELECT * FROM users WHERE userid=?");
		$smt->execute([$signedin]);
		$results = $smt->fetchAll(); */
		$results = bqry("SELECT * FROM users WHERE userid=?", [$signedin]);
		$name = $results[0]['username'];
		echo '<h2> Signed in as : '.$name.'</h2>';
		if(isset($_POST['signout'])){
			setcookie("battleshipcookie", $_COOKIE["battleshipcookie"], 1); 
			header('Location: http://localhost/battleships/index.php');
		}
		if(isset($_POST['gamename'])) {
			$gamename = $_POST['gamename'];
			/*$smt = $handle->prepare("INSERT INTO pendinggames (gamename, userid) VALUES (?,?)");
			$smt->execute([$gamename, $signedin]); */
			bexec("INSERT INTO pendinggames (gamename, userid) VALUES (?,?)", [$gamename, $signedin]);
			
		}
		
		/** Get Available Games **/
		/* $smt = $handle->prepare("SELECT * FROM pendinggames JOIN users ON users.userid =  pendinggames.userid WHERE pendinggames.userid!=?");
		$smt->execute([$signedin]);
		$pendingGames = $smt->fetchAll(); */
		$pendingGames = bqry("SELECT * FROM pendinggames JOIN users ON users.userid =  pendinggames.userid WHERE pendinggames.userid!=?", [$signedin]);
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
			$game_id = intval($arr[2]);
			$gamename = $arr[3];
			
			/* $smt = $handle->prepare("INSERT INTO games (game_id, game_name, userid_A, userid_B) VALUES (?, ?, ?, ?)");
			$smt->execute([$game_id, $gamename, $gameowner, $signedin]); */
			bexec("INSERT INTO games (game_id, game_name, userid_A, userid_B) VALUES (?, ?, ?, ?)", [$game_id, $gamename, $gameowner, $signedin]);
			
			/* $smt2 = $handle->prepare("DELETE FROM pendinggames WHERE gameid=?");
			$smt2->execute([$game_id]); */
			bexec("DELETE FROM pendinggames WHERE gameid=?", [$game_id]);
			//insert money and history into db:

			/* $smt4 = $handle->prepare("INSERT INTO money (game_id, player_id, money) VALUES (?,?,?)");
			$smt4->execute([$game_id, $gameowner, 500]);
			$smt4->execute([$game_id, $signedin, 500]); */	
			if($gameowner===$signedin){ die('gameowner and signed in are same ids'); } // qqq
			bexec("INSERT INTO money (game_id, player_id, money) VALUES (?,?,?)", [$game_id, $gameowner, 500]);
			bexec("INSERT INTO money (game_id, player_id, money) VALUES (?,?,?)", [$game_id, $signedin, 500]);

			//assign random ship 
			function assign_random($game_id, $handle, $player_id) { //  procedure
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
				$inputs = [$game_id, $player_id, $randx, $randy, $randlen, $randhoriz, $lastHIT, $sunkBool]; 
				/* $shipqry = $handle->prepare("INSERT INTO ships (game_id,player_id,x,y,size,orientation, last_hit_id, sunk_bool) VALUES (?,?,?,?,?,?,?,?)");
				$shipqry->execute($inputs); */
				bexec("INSERT INTO ships (game_id,player_id,x,y,size,orientation, last_hit_id, sunk_bool) VALUES (?,?,?,?,?,?,?,?)", $inputs);
				if($player_id == $gameowner){
					$ship_id = $handle->lastInsertId(); 
					log_action($game_id, 0, $ship_id);
				}
				if($player_id == $signedin){
					$ship_id = $handle->lastInsertId(); 
					log_action($game_id, 1, $ship_id);
				}

				/* $skeletonqry = $handle->prepare("SELECT shiptype_id FROM shiptype WHERE length=?");
				$skeletonqry->execute([$randlen]);
				$skelresults = $skeletonqry->fetchAll(); */
				$skelresults = bqry("SELECT shiptype_id FROM shiptype WHERE length=?", [$randlen]);
				$shiptype_id = $skelresults[0]['shiptype_id'];

				//get turrets update turn
				/* $turretqry = $handle->prepare("SELECT * FROM turretlocations WHERE shiptype_id=?");
				$turretqry->execute([$shiptype_id]);
				$turrets = $turretqry->fetchAll(); */
				$turrets = bqry("SELECT * FROM turretlocations WHERE shiptype_id=?", [$shiptype_id]);

				if($player_id == $gameowner) { // give main player the first turn
					/* $turnone = $handle->prepare("INSERT INTO turn (game_id, player_id, hits_left) VALUES (?,?,?)");
					$turnone->execute([$game_id, $gameowner, count($turrets)]); */
					bexec("INSERT INTO turn (game_id, player_id, hits_left) VALUES (?,?,?)", [$game_id, $gameowner, count($turrets)]);
				}else{
					bexec("INSERT INTO turn (game_id, player_id, hits_left) VALUES (?,?,?)", [$game_id, $signedin, count($turrets)]);
				}
			}
			assign_random($game_id, $handle, $gameowner);
			assign_random($game_id, $handle, $signedin);		
		}

		/** Show Active Games **/
		$str2 = '<h2>My Active Games: </h2>';
		/* $smt = $handle->prepare("SELECT * FROM games WHERE userid_A=? OR userid_B=?");
		$smt->execute([$signedin, $signedin]);
		$activegames = $smt->fetchAll(); */
		$activegames = bqry("SELECT * FROM games WHERE userid_A=? OR userid_B=?", [$signedin, $signedin]);
		$str2 .= '<form method="post" action="/battleships/output.php">';
		foreach($activegames as $a){
			$arr = implode(',', [$signedin, $a['game_id']]);
			$str2 .= '<input type="radio" name="playgame" value="'.
				$arr
			.'" id="'.$a['game_id'].'" />'.'<label for="'.$a['game_id'].'">"'.$a['game_name'].'"</label>';
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
