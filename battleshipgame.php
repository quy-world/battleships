<?php	
	include("./util.php");
		
	if(isset($_POST['playgame'])) {
		$arr = explode(',', $_POST['playgame']);
		$player_id = intval($arr[0]);
		$game_id = intval($arr[1]);
		$handle = getConnection();
		$results = bqry("SELECT * FROM games WHERE game_id=?", [$game_id]);// $smt->fetchAll();
		$result = $results[0];
		$enemy_id = intval($result['userid_A'])===$player_id?intval($result['userid_B']):intval($result['userid_A']);
	}
	if(isset($_COOKIE['battleshipcookie'])){
		$moisession = $_COOKIE['battleshipcookie'];
	}else{
		die('session no longer');
	}
?><html>
	<head></head>
	
	<body>
		<div><a href="http://localhost/battleships/index.php">Return to Dashboard</a></div>
	</body>
</html>