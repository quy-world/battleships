<?php	
	$PDO_ARGS = ["mysql:host=localhost:3306;dbname=battleships", "root", ""];

	if(isset($_REQUEST['game_id'])) {
		$player_id;
		$game_id = $_REQUEST['game_id'];
		$password = $_REQUEST['password'];
		
		$handle = new PDO($PDO_ARGS[0], $PDO_ARGS[1], $PDO_ARGS[2]);
		$smt = $handle->prepare("SELECT * FROM games WHERE game_id=?");
		$smt->execute([$game_id]);
		$result = $smt->fetchAll();
		$info = $result[0]; // there should only be one unique result
		
		if($password==$info['pw_A']) {
			echo('Welcome Player A');
			$player_id = 1;
		} else {
			if($password==$info['pw_B']) {
				echo('Welcome Player B');
				$player_id = 2;
			} else {
				echo('Wrong Credentials');
			}
		}
		if(isset($player_id)) $enemy_id = $player_id==1 ? 2:1;
	}

?><html>
	<head></head>
	
	<body>
		<div><a href="http://localhost/battleships/index.php">Return to Dashboard</a></div>
		<?php if(!isset($player_id)) die(); ?>
	</body>
</html>