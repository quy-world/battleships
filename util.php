<?php
	function arraymerge(...$arrays) {
		$merged = [];
		foreach($arrays as $array){
			foreach($array as $elem){
				$merged[] = $elem;
			}
		}
		return $merged;
	}
	
	
	function set($listoflists){
	// returns unique elements from a combined list-of-lists
	$accum = accumulator($listoflists);
	$set = [];
	foreach($accum as $elem => $num){if($num == 1){$set[]=$elem;}}
	return $set;
}

	function atLeastTwo(...$listoflists){
		// [[]] -> []
		$accum = accumulator($listoflists);
		$atLeastTwo = [];
		foreach($accum as $elem => $num){
			if($num>1){$atLeastTwo[]=$elem;}
		}
		return $atLeastTwo;
	}

	function accumulator($listoflists) {
		$accum = [];
		foreach($listoflists as $list) {
			foreach($list as $elem){$accum[$elem] = isset($accum[$elem])?$accum[$elem]+1:1;}
		}
		return $accum;
	}
	
	function flat($list){
		return array_reduce($list, function($a, $x){ return array_merge($a, $x); }, []);
	}
	function set2($list){
		$ret = [];
		foreach($list as $x){
			if(isset($ret[$x])){
				$ret[$x] += 1;
			}else{
				$ret[$x] = 1;
			}
		}
		return array_keys($ret);
	}
	
	function prepend($x, $xs){return arraymerge([$x], $xs);}
	function append($xs, $x){return arraymerge($xs, [$x]);}
	function varjson($x){echo(json_encode($x));}
	
	function numberedForm($form){
		for($i=0; $i<count($form); $i++){
			$form[$i] = (int)$form[$i];
		}
		return $form;
	}
	
	function br(){ echo '<br>'; }
	
	function shipconverter($listofships) {
		// max is 80
		$numberedformlist = [];
		foreach($listofships as $normalform) {
			/* echo '()()()';
			echo(json_encode($normalform)); */
			$numberedformlist[] = numberedform($normalform);
		}
		return $numberedformlist;
	}
?>