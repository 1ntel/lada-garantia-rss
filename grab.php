<?php

error_reporting( E_ALL ); // E_ERROR, 0
date_default_timezone_set( 'Europe/Moscow' );

require_once('.\system\sqlite.php');

if (!function_exists('curl_version')){
	die('Curl disabled. Quit.' . PHP_EOL);
}


    function toUTF8 ($string) {return iconv('windows-1251', 'utf-8', $string );}
  //function to1251 ($string) {return iconv('utf-8', 'windows-1251', $string );}
    function toCP866($string) {return iconv('utf-8', 'CP866//TRANSLIT', $string );}
	function saveCfg($path, $array){
		$content = '<?php' . PHP_EOL . 'return ' . var_export($array, true) . ';';
		return is_numeric(file_put_contents($path, $content));
	}
	function loadCfg($path){
		return require $path;
	}
	function curlSetOpt(&$ch, $url, $referer = false){
		//$cookie	= ".\data\cookie.txt";

		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; I; Windows NT 5.1; ru; rv:1.9.2.13) Gecko/20100101 Firefox/4.0"); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		if(isset($cookie)){
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
			curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie);
		} else {
			curl_setopt($ch, CURLOPT_COOKIEFILE, "");
		}
		if(isset($referer)){
			curl_setopt($ch, CURLOPT_REFERER, $referer); 
        } else {
			curl_setopt($ch, CURLOPT_REFERER, $url); 		
		}
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		//return $ch;
	}
	function getPagePost($ch, &$url, $postdata){
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_POST, 1);
		curlSetOpt($ch, $url);
		$result = curl_exec ($ch);

		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		
		if(curl_error($ch)){ 
			echo "\n\ncURL error #" . curl_errno($ch) . ": " . curl_error($ch);
			return false;
		}

		return $result;
	}
	function getPage($ch, &$url){
		curlSetOpt($ch, $url);
		$result = curl_exec ($ch);
		
		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		if(curl_error($ch)){ 
			echo "\n\ncURL error #" . curl_errno($ch) . ": " . curl_error($ch);
			return false;
		}

		return $result;
	}
	function clearTags($x){
		$x = preg_replace("/style=\"[^\"]*\"/si", "", $x);
		$x = preg_replace("/ {2,}/si", " ", $x);
		$x = preg_replace("/(.) +>/si", "$1>", $x);
		$x = preg_replace("/> /si", ">", $x);
		$x = preg_replace("/ </si", "<", $x);
		$x = preg_replace("/[\r\n]+/si", "\r\n", $x);
		return $x;
	}

	function printLog($str){
		echo date('mdHis') . " " . $str;
	}
	function printLogN($str){
		printLog($str);
		echo PHP_EOL;
	}
	function printLogE($str){
		echo $str . PHP_EOL;
	}

{//Получаем данные

	printLogN("START!");
	$config = loadCfg('.\data\config.inc.php');

	$ch = curl_init();
	
	printLog("AUTH... ");
	$postdata	= "id2_hf_0=&username=".$config['user']."&password=".base64_decode($config['pass']);
	$url = "http://portal.etc-auto.ru:7003/login?0-1.IFormSubmitListener-signIn-signInForm";
	getPagePost($ch, $url, $postdata);
	printLogE("\tOK!");

	printLog("GET MAIN PAGE... ");
	$url = "http://portal.etc-auto.ru:7003/main";
	$x = getPage($ch, $url);
	printLogE("\tOK!");
}

//$x = file_get_contents("input.html");

{//Причесываем вывод
	$x = strip_tags(clearTags($x), "<a><div><tr>");
	preg_match_all("/<tr[^>]*>.*?<\\/tr>/si", $x, $x, PREG_SET_ORDER);
	$newX = array();
	foreach ($x as $key => $value){
		$newX[] = $x[$key][0];
	}
	$x = $newX;
}

$db = dbOpen();

{//Парсим новости
	foreach ($x as $key => $value){
		$item = array();
		
		preg_match('@<div class=\"time\">(\d\d\.\d\d\.\d\d\d\d)<\\/div>@i', $value, $matches);
		$item['date'] = strtotime($matches[1]);
		$item['time'] = time();
		
		preg_match('@<a href=\"([^\"]+)\"[^>]*>([^<]*)<\\/a>@si', $value, $matches);
		if ($matches[1] === "#") {
			$item['attach'] = NULL;
		} else {
			$item['attach'] = "http://portal.etc-auto.ru:7003/" . $matches[1];
			$item['attach'] = str_replace("&amp;", "&", $item['attach']);
		}
		$item['header'] = str_replace("\r\n", "", $matches[2]);
		
		preg_match('@<div class=\"text\">([^<]*)<\\/div>@si', $value, $matches);
		$item['description'] = str_replace("\r\n", "", $matches[1]);

		preg_match('@wicketAjaxGet\(&\#039;([^\"><]*details)&\#039;@si', $value, $matches);
		$item['url'] = "http://portal.etc-auto.ru:7003/" . $matches[1];

		$item['uid'] = 
			strtoupper(hash("md5", $item['date'].$item['header'].$item['description'].$item['attach']));

		$dbItem = dbGetItem($db, $item['uid']);
		
		if (Isset($dbItem[0]['uid']) 
			AND $dbItem[0]['uid'] === $item['uid'])
		{
			printLogN("FOUND OLD ITEM " . $item['uid'] . ", SKIP.");
		} else {
			printLogN("FOUND NEW ITEM " . $item['uid'] . "!");

			{//Получаем подробности
				printLog("GET DETAILS PAGE " . $item['url'] . "... ");
				$detail = getPage($ch, $item['url']);
				$item['url'] = preg_replace('/\?\d+&/i', "?", $item['url']);

				$detail = strip_tags(clearTags($detail), "<a><div>");

				preg_match_all('@<div class=\"line clear\">([^<]*)<\\/div>@si', $detail, $matches, PREG_SET_ORDER);
				$hdr = str_replace("\r\n", "", $matches[0][1]);
				$desc = str_replace("\r\n", "", $matches[1][1]);

				preg_match('@<div class=\"text\">.*<a[^>]*>([^<]*)<\\/a>.*<\\/div>@si', $detail, $matches);
				$attachname = $matches[1];
				
				if ($hdr == false AND $desc == false AND $attachname == false){
					$item['attachname'] = NULL;	
					printLogE("\tERROR!");
				} else {
					$item['header'] = $hdr;
					$item['description'] = $desc;
					$item['attachname'] = $attachname;
					printLogE("\tOK!");
				}
			}
			dbAddItem($db, $item);
		}
	}
}

//Выходим
printLog("LOGOUT... ");
$url = "http://portal.etc-auto.ru:7003/j_spring_security_logout";
$detail = getPage($ch, $url);
printLogE("\tOK!");

printLogN("STOP!".PHP_EOL);
curl_close($ch);

