<?php 

function csvParse($fl,$rows)
{
	$fl = fopen('rvn.csv','r');
	$rows = 0;
	$ii = 0;
	echo '<table border="1" align="center" 
	style="text-align:center;font-size:15px;">';

	while(!feof($fl) && $i<=$rows){
		$buffer = fgetcsv($fl,256);
		@$len = sizeof($buffer);
		echo '<tr>';
		for($i=0;$i<$len;$i++){
			echo '<td>'.$buffer[$i].'</td>';
		}
		echo '</tr>';
		$ii++;
	}

	echo '</table>';
	fclose($fl);
}
