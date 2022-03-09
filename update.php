<?php

error_reporting( E_ALL ); // E_ERROR, 0
date_default_timezone_set( 'Europe/Moscow' );

class Cookies {
	//TODO make inherit from Storage
    public $path;
    public $store;
    private $oldCookies;

    function __construct($path) {
        $this->path = $path;
		
		if (file_exists($this->path)) {
			$this->store = $this->loadCfg($this->path);
		} else {
			$this->store = array();
		}

		$this->oldCookies = $this->store;
   }

    function get() {
        return $this->store;
    }
    
    function save() {
		if ($this->store !== $this->oldCookies) {
			return $this->saveCfg($this->path, $this->store);
		}
    }

    private function saveCfg($path, $array) {
		$content = '<?php' . PHP_EOL . 'return ' . var_export($array, true) . ';';
		return is_numeric(file_put_contents($path, $content));
	}

	private function loadCfg($path) {
		return require $path;
	}
} 

class Storage implements ArrayAccess {
    private $path;
    private $container;

    function __construct($path) {
        $this->path = $path;
		$this->load();
	}

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    public function save($path = false) {
		if (!$path) {$path = $this->path;}
		$content = '<?php' . PHP_EOL . 'return ' . var_export($this->container, true) . ';';
		return is_numeric(file_put_contents($path, $content));
	}

	public function load($path = false) {
		if (!$path) {$path = $this->path;}

		if (file_exists($path)) {
			$this->container = require $path;
		} else {
			$this->container = array();
		}
	}
} 

{#iconv
	setlocale(LC_CTYPE, 'ru_RU');
	function toUTF8 ($string) {return iconv('windows-1251',  'utf-8', $string );}
    function to1251 ($string) {return iconv('utf-8',  'windows-1251', $string );}
	function toCP866($string) {return iconv('utf-8', 'CP866//IGNORE', $string );}
}

class HTTP {
    private $curl;
    private $options;
    private $lastURL;

    function __construct($options = array()) {
		assert(function_exists('curl_version'), 'cURL disabled. Can\'t continue.');
		
		$this->curl = curl_init();
		

		$defaults = array(
			CURLOPT_TIMEOUT => 20,
			CURLOPT_SSL_VERIFYHOST => FALSE,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; I; Windows NT 5.1; ru; rv:1.9.2.13) Gecko/20100101 Firefox/4.0',
			CURLOPT_COOKIEFILE => '',
		);
		$this->options = (array)$options + $defaults;
		$this->lastURL = false;
		
		if (!curl_setopt_array($this->curl, $this->options)) {
			throw new Exception('Not all cURL options recognised.');
		}
	}

	function __destruct() {
		curl_close($this->curl);
	}

    public function get($url) {
		return $this->query($url);	
	}
	
    public function post($url, $postData) {
		return $this->query($url, $postData);
	}

	private function query(&$url, $postData = false) {
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_REFERER, ($this->lastURL?$this->lastURL:$url)); 

		if ($postData) {
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);
			curl_setopt($this->curl, CURLOPT_POST, TRUE);
		}

		$result = curl_exec($this->curl);
		$this->lastURL = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
		// curl_getinfo($this->curl, CURLINFO_HTTP_CODE), PHP_EOL;
		
		if (curl_error($this->curl)) { 
			echo "\n\ncURL error #" . curl_errno($this->curl) . ": " . curl_error($this->curl);
			return false;
		}

		return $result;
	}

    public function getLastURL() {
		return $this->lastURL;
	}

	public function reset() {
		$this->curl = curl_init(); // curl_reset($this->curl);
		return curl_setopt_array($this->curl, $this->options);	
	}
}

function countdown($seconds, $begin = '', $end = '') {
	echo $begin;

	$len = strlen((string)$seconds);
	do {
		$s = sprintf("%{$len}s", $seconds);
		echo $s;
		sleep(1);
		echo str_repeat(chr(8), strlen($s));
	} while ($seconds--);
	
	echo $end;
}

// $cookies = new Cookies('./data/cookies.php');
$config = new Storage('./data/config.php');
$http = new HTTP(array(
	//TODO rewrite cookie system to not save file if it isn't changed
	CURLOPT_USERAGENT  => $config['useragent'],
	CURLOPT_COOKIEFILE => $config['cookiefile'],
	CURLOPT_COOKIEJAR  => $config['cookiefile'],
	CURLOPT_HTTPHEADER => array(
		'Pragma: no-cache',
		'Accept-Language: ru',
	),
	// CURLOPT_PROXY => 'localhost:8888',
));

{#TRYING TO GET PAGE
	echo 'START', PHP_EOL;
	countdown(mt_rand(0, 300), 'TIMEOUT... ', "DONE!\n");
	$content = $http->get("{$config['domain']}/main");
	{#DEBUG
		// file_put_contents('./main.html', $content); //exit;
		// $content = file_get_contents('./main.html', false); // __halt_compiler();
	}
}

{#LOGIN
	if (strpos($content, 'action="login?') !== false) {
		echo 'UNAUTHORIZED.', PHP_EOL;

		$dom = new domDocument;
		@$dom->loadHTML($content);
		$dom->preserveWhiteSpace = false;
		$finder = new DomXPath($dom);

		$auth = array();
		foreach ($finder->query('//form') as $form) {
			$auth['action'] = $form->getAttribute('action');
			foreach ($finder->query('.//input', $form) as $input) {
				$auth['post'][$input->getAttribute('name')] = $input->getAttribute('value');	
			}
			break;
		};
		
		$auth['post']['username'] = $config['user'];
		$auth['post']['password'] = base64_decode($config['pass']);
		// var_export($auth); exit;
		
		{#AUTH
			countdown(mt_rand(1, 10), 'TIMEOUT... ', "DONE!\n");
			echo 'AUTHENTICATION... ';
			$content = $http->post("{$config['domain']}/{$auth['action']}", http_build_query($auth['post']));
			// file_put_contents('./login.html', $content); //exit;

			if (strpos($content, 'action="login?') !== false) {
				echo 'FAILED!', PHP_EOL;

				// foreach ($finder->query("//li[@class='feedbackPanelERROR']") as $error)
				// {echo $error->textContent, PHP_EOL;}
				// $cookies->save();
				exit(1);
			}
			else
			{
				$http->reset();
				echo "SUCCESS!", PHP_EOL; 
			}
		}
	} else {
		echo "ALREADY LOGGED IN.", PHP_EOL;
	}
}

require_once('.\system\sqlite.php');
$db = dbOpen();

$dom = new domDocument;
@$dom->loadHTML($content);
$dom->preserveWhiteSpace = false;
$finder = new DomXPath($dom);

foreach ($finder->query("//div[@class='news']/div/div/table/tr") as $element) {
	$item = array();
	
	$item['date'] = strtotime($finder->query(".//div[@class='time']", $element)->item(0)->nodeValue);
	$item['time'] = time();
	
	$a = $finder->query('.//a', $element)->item(0)->getAttribute('href');
	if ($a === "#") {
		$item['attach'] = null;
	} else {
		$item['attach'] = "{$config['domain']}/{$a}";
		$item['attach'] = str_replace('&amp;', '&', $item['attach']);
	}
	
	$item['header'] = trim($finder->query('.//a', $element)->item(0)->nodeValue);

	$item['description'] = trim($finder->query(".//div[@class='text']", $element)->item(0)->nodeValue);
	
	if ($item['date'] < 1497463774) { #backward compatibility
		$item['header'] = preg_replace('/ {2,}/si', ' ', $item['header']);
		$item['description'] = preg_replace('/ {2,}/si', ' ', $item['description']);
		$item['description'] = str_replace("\r\n", '', $item['description']);
	} else {
		$item['description'] = str_replace("\r\n", "<br>\n", $item['description']);	
	}

	$url = trim($finder->query('.//a', $element)->item(1)->getAttribute('onclick'));
	preg_match('@wicketAjaxGet\(\'([^\"><]*details)\'@si', $url, $matches);
	$item['url'] = "{$config['domain']}/{$matches[1]}";

	$item['uid'] = 
		strtoupper(hash('md5', $item['date'].$item['header'].$item['description'].$item['attach']));


	$dbItem = dbGetItem($db, $item['uid']);
	if (isset($dbItem[0]['uid']) 
		AND $dbItem[0]['uid'] === $item['uid'])
	{
		echo "FOUND OLD ITEM {$item['uid']}, SKIP.", PHP_EOL;
	} else {
		echo "FOUND NEW ITEM {$item['uid']}!", PHP_EOL;
		// echo toCP866(var_export($item, true));
		
		{#GETTING DETAILS
			countdown(mt_rand(1, 15), 'TIMEOUT... ', "DONE!\n");
			echo "GET DETAILS PAGE {$item['url']}... ";
			$detail = $http->get($item['url']);
			$item['url'] = $http->getLastURL();

			// file_put_contents('./form.html', $detail); //exit;
			// $detail = file_get_contents('./form.html', false); // __halt_compiler();	

			$dom1 = new domDocument;
			@$dom1->loadHTML($detail);
			$dom1->preserveWhiteSpace = false;
			$finder1 = new DomXPath($dom1);
			
			$divs = $finder1->query("//div[@class='line clear']");
			$hdr  = trim($divs->item(0)->textContent);
			$desc = trim($divs->item(1)->textContent);
			$attachname = trim($finder1->query(".//a", $divs->item(2))->item(0)->textContent);

			$item['url'] = preg_replace('/\?\d+&/i', '?', $item['url']);
			if($item['date'] < 1497463774){ #backward compatibility
				$hdr  = str_replace("\r\n", '', $hdr);
				$desc = str_replace("\r\n\r\n", "\r\n", $desc);
			} else {
				$desc = str_replace("\r\n", "<br>\n", $desc);
			}

			if (!$hdr AND !$desc AND !$attachname){
				$item['attachname'] = null;	
				echo 'ERROR', PHP_EOL;
			} else {
				$item['header']      = $hdr;
				$item['description'] = $desc;
				$item['attachname']  = $attachname;
				echo 'OK!', PHP_EOL;
				// echo toCP866(var_export($item, true));
			}

			$finder1 = null;
			$dom1 = null;
		}
		dbAddItem($db, $item);
	}
}

echo 'ALL DONE', PHP_EOL;
