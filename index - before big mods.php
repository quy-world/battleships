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
		$smt = $handle->prepare("SELECT * from sessions WHERE sessionid=?");
		$smt->execute([$sessionid]);
		$results = $smt->fetchAll();
		$result  = $results[0];
		if($result['token']!==hash("sha512", $result['tokensalt'].$usercookie2)){
			die('THIS IS NOT YOUR SESSION');
		}else{
			$signedin = intval($result['userid']);
		}
	}

	/*** NEW USER ***/
	if(isset($_POST['newuser'])){
		$username = $_POST['newuser'];
		$pw = $_POST['pw'];
		$pw2 = $_POST['pw2'];
		if($pw === '' || $pw !== $pw2){
			die('Your passwords do not match. Please try again.');
		}
			
		$smt = $handle->prepare("SELECT * FROM users WHERE username=?");
		$smt->execute([$username]);
		$results = $smt->fetchAll();
		if(count($results)>0){
			die('Username "'.$username.'" has already been taken.');
		}

		$salt = randstr(6);
		$hashedpw =  hash("sha512", $salt.$pw);

		$smt2 = $handle->prepare("INSERT INTO users (username,password,salt) VALUES (?,?,?)");
		$smt2->execute([$username, $hashedpw, $salt]);
	}

	/*** SIGN IN ***/
	if(isset($_POST['username'])){
		$handle = getConnection();
		$smt = $handle->prepare("SELECT * FROM users WHERE username=?");
		$smt->execute([$_POST['username']]);
		
		$results = $smt->fetchAll();
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
		$smt = $handle->prepare('INSERT INTO sessions (sessionid, userid, token, tokensalt, useragent, useragentsalt, expiry) VALUES (?,?,?,?,?,?,?)');
		$smt->execute([$sessionid, $result['userid'], $randtokensalted, $tsalt, $useragentsalted, $usalt, $expiry]);
		
		 
		/*** SET COOKIE ***/
		//$_COOKIE["playercookie"] = $randomtoken;
		setcookie("battleshipcookie", $sessionid.$randomtoken, $expiry);
	}
	
	/*** NEW GAME ***/
	if(isset($_POST['game_name'])&&isset($_POST['pw_A'])&&isset($_POST['pw_B'])) {
		// ASSUME passwords are non-empty and both players have different passwords
		$name = $_POST['game_name'];
		$pwA = $_POST['pw_A'];
		$pwB = $_POST['pw_B'];
		if($pwA == $pwB || $pwA == '' || $pwB == '') {die('Passwords invalid, please try again.');}
		

		/* $randstrA = randstr(10);
		$randstrB = randstr(10);
		$hashedA = hash("sha512", $randstrA.$pw_A);
		$hashedB = hash("sha512", $randstrB.$pw_B);

		//$smt2 = $handle->prepare("INSERT INTO games (game_name,pw_A,pw_B) VALUES (?,?,?)");

		//$smt2 = $handle->prepare("INSERT INTO users ("
		$smt2 = $handle->prepare("INSERT INTO users (game_name,pw_A,pw_B) VALUES (?,?,?)");
		$smt2->execute([$name, $hashedA, $hashedB]); */

		// insert monies and history into db:
		$game_id = $handle->lastInsertId();
		// log_action($game_id, 0, $game_id);

		$smt4 = $handle->prepare("INSERT INTO money (game_id, player_id, money) VALUES (?,?,?)");
		$smt4->execute([$game_id, 1, 500]);
		$smt4->execute([$game_id, 2, 500]);

		// assign random ship 
		function assign_random($game_id, $handle, $player_id) { //  procedure
			$invalid_ship=true;
			$randx;
			$randy;
			$randlen;
			$randhoriz;
			while($invalid_ship){
				$randx = rand(0,8);
				$randy = rand(0,8);
				$randlen = rand(2,6);
				$randhoriz = rand(0,1)==0?true:false; // qqq
				if($randhoriz){
					if(!($randlen>9-$randx)){$invalid_ship=false;}
				}else{ // vertical
					if(!($randlen>9-$randy)){$invalid_ship=false;}
				}
			}
			$lastHIT=0; // IS THIS A GOOD???
			$sunkBool = 0;
			// insert ship
			$inputs = [$game_id, $player_id, $randx, $randy, $randlen, $randhoriz, $lastHIT, $sunkBool]; // qqq
			$shipqry = $handle->prepare("INSERT INTO ships (game_id,player_id,x,y,size,horizontal, last_hit_id, sunk_bool) VALUES (?,?,?,?,?,?,?,?)");
			$shipqry->execute($inputs);

			if($player_id == 1){
				$ship_id = $handle->lastInsertId(); /// POTATO
				log_action($game_id, 0, $ship_id);
			}
			if($player_id == 2){
				$ship_id = $handle->lastInsertId(); /// POTATO
				log_action($game_id, 1, $ship_id);
			}

			$skeletonqry = $handle->prepare("SELECT shiptype_id FROM shiptype WHERE length=?");
			$skeletonqry->execute([$randlen]);
			$skelresults = $skeletonqry->fetchAll();
			$shiptype_id = $skelresults[0]['shiptype_id'];

			// get turrets update turn
			$turretqry = $handle->prepare("SELECT * FROM turretlocations WHERE shiptype_id=?");
			$turretqry->execute([$shiptype_id]);
			$turrets = $turretqry->fetchAll();

			if($player_id == 1) { // give player 1 the first turn
				$turnone = $handle->prepare("INSERT INTO turn (game_id, player_id, hits_left) VALUES (?,?,?)");
				$turnone->execute([$game_id, 1, count($turrets)]);
			}
		}
		// player id must be in order like this:
		assign_random($game_id, $handle, 1);
		assign_random($game_id, $handle, 2);
		
		header('Location: http://localhost/battleships/index.php');
		
	}
?><html>
	<head></head>
	
	<body>
		<div style="float: left; width: 50%">
			<h2>List of Games: </h2>
			<form method="post" action="/battleships/output.php">
				<!-- <input type="radio" value="1" name="game_id" checked="checked"/> -->
				<?php foreach($games as $record => $game) {?>
				<input type="radio" id=<?php echo($game['game_name']);?> name="game_id" value=<?php echo($game['game_id']);?>>
					<label for=<?php echo($game['game_name']);?>><?php echo($game['game_name']. ' game');?></label> </br>
				<?php } ?>
				Player: <select name="player">
							<option value="1">Player A</option>
							<option value="2">Player B</option>
						</select><br>
				Username: <input name="username" placeholder="Enter your username: "/><br>
				Player's password:<input type="password" name="password" placeholder="Enter your password:">
				<input type="submit" value="Submit">
			</form>
		</div>
		
		
		<!-- <div style="float: left">
		<h2>New Game: </h2>
			<form method="post" action="/battleships/index.php">
				Game Name:<input type="text" name="game_name"> </br>
				Player A password:<input type="password" name="pw_A" > </br>
				Player B password:<input type="password" name="pw_B"> </br>
				<input type="submit" value="Create Game">
			</form>
		</div> -->

		<h2>New Game: </h2>
			<form method="post" action="/battleships/index.php">
				Game Name:<input type="text" name="game_name"> </br>
				<input type="submit" value="Create Game">
			</form>
		</div>

		<div style="float: left">
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
		</div>
	</body>
</html>
