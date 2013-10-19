<?php
ini_set('display_errors', 1);
require_once('TwitterAPIExchange.php');

/** Set access tokens here - see: https://dev.twitter.com/apps/ **/
$settings = array(
    'oauth_access_token' => "YOUR TOKEN HERE",
    'oauth_access_token_secret' => "YOUR TOKEN HERE",
    'consumer_key' => "YOUR KEY HERE",
    'consumer_secret' => "YOUR KEY HERE"
);

/** URL for REST request, see: https://dev.twitter.com/docs/api/1.1/ **/
$url = 'https://api.twitter.com/1.1/search/tweets.json';


$conexion = mysqli_connect("localhost", "root", "", "twitter_geo") OR die("Error: ". mysqli_error());
$result_ids = mysqli_query($conexion, "SELECT id_tuit FROM Tuit ORDER BY Fecha DESC LIMIT 1");
$results_tuit = mysqli_fetch_assoc($result_ids);

/** Perform a GET request and echo the response **/
/** Note: Set the GET field BEFORE calling buildOauth(); **/
$getfield = '?q=4sq.com&geocode=6.235925,-75.575137,20km&count=3500&since_id=' . $results_tuit['id_tuit'];
$requestMethod = 'GET';

 mysqli_free_result($result_ids);
$twitter = new TwitterAPIExchange($settings);
echo "<table border='1'><tr><th>#</th><th>coordenadas</th><th>tuit</th><th>user</th><th>Location</th><th>Fecha</th><th>imagen</th></tr>";
$flag = false;
$contador = 1;
$a = json_decode($twitter->setGetfield($getfield)
             ->buildOauth($url, $requestMethod)
             ->performRequest());

$tuit_array = array();

do{

	if($flag == false){
		$flag = true;
	} else {
		$a = json_decode($twitter->setGetfield($a->search_metadata->next_results)
             ->buildOauth($url, $requestMethod)
             ->performRequest());
	}

	foreach($a->statuses as $p){

		echo "<tr><td>" . $contador . "</td>";
		if(!is_null($p->geo)){
			echo "<td>(" . $p->geo->coordinates[0] . ", " . $p->geo->coordinates[1] . ")</td>";
			$tuit_array[]["punto"] = "POINT(" . $p->geo->coordinates[0] . " " . $p->geo->coordinates[1] . ")";
		}else{
			echo "<td>( Sin datos )</td>";
			$tuit_array[]["punto"] = "NULL";
		}
		$tuit_array[($contador-1)]["tuit"] = $p->text;
		$tuit_array[($contador-1)]["user"] = $p->user->screen_name;
		$tuit_array[($contador-1)]["fecha"] = date('Y-m-d H:i:s', strtotime($p->created_at));
		$tuit_array[($contador-1)]["id_str"] = $p->id_str;
		echo "<td>" . $p->text . "</td>";
		echo "<td> @" . $p->user->screen_name . "</td>";
		echo "<td>" . $p->user->location . "</td>";
		echo "<td>" . $p->created_at . "</td>";
		echo "<td><img src='" .$p->user->profile_image_url . "' /></td></tr>";
		$contador++;
	}
}while(isset($a->search_metadata->next_results));
//}while(is_object($a));
echo "</table>";

//Store in database
$query = "INSERT INTO Tuit(Punto, Tuit, User, Fecha, id_tuit) VALUES(";
mysqli_autocommit($conexion, false); // set autocommit to false
foreach($tuit_array as $tuit){
	
	$query_exe = $query . "GEOMFROMTEXT( '" . $tuit["punto"] . "' ), '" . mysqli_real_escape_string($conexion,$tuit["tuit"]) . "', '" . mysqli_real_escape_string($conexion,$tuit["user"]) . "', '" . $tuit["fecha"] . "', '". $tuit["id_str"] ."')";
	$ax = mysqli_query($conexion, $query_exe);
	if(!$ax){
		echo $query_exe;
		mysqli_rollback($conexion);
		die("Error al insertar: ". mysqli_error($conexion));
	}
}
mysqli_commit($conexion);
mysqli_close($conexion);
echo "Finalizado insercion";