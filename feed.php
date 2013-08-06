<?php

	date_default_timezone_set( 'Europe/Moscow' );
	error_reporting( E_ALL ); //E_ERROR

	function loadCfg($path){
		return require $path;
	}
	$config = loadCfg('.\data\config.inc.php');

	$page = array();
	$page['title'] = 'Новости - Лада Гарантия';
	$page['description'] = 'Новости ИС Лада Гарантия';
	$page['link'] = 'http://portal.etc-auto.ru:7003/main';
	$page['generator'] = 'portal.etc-auto.ru';
	$page['imageurl'] = 'http://portal.etc-auto.ru:7003/images/logo.jpg';
	$page['managingEditor'] = 'support@etc-auto.ru (Техподдержка ИТЦ АВТО)';
	$page['items'] = array();

	require_once('.\system\sqlite.php');
	$db = dbOpen();
	$page['items'] = dbGetItems($db, 10);

	header( 'Cache-Control: no-cache, must-revalidate' ); // HTTP/1.1
	header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' ); // Date in the past
	header( 'content-type: application/rss+xml' );
	echo( '<?xml version="1.0" encoding="UTF-8"?>' );
	echo( '<rss version="2.0">' );

?><channel>
	<title><?=$page['title'];?></title>
	<link><?=$page['link'];?></link>
	<description><![CDATA[<?=$page['description'];?>]]></description>
	<language>ru-ru</language>
	<managingEditor><?=$page['managingEditor'];?></managingEditor>
	<webMaster><?=$config['webMaster'];?></webMaster>
	<generator><?=$page['generator'];?></generator>
	<pubDate><?=date( DATE_RSS, $page['items'][1]['date'] );?></pubDate>
	<lastBuildDate><?=date( DATE_RSS, $page['items'][1]['date'] );?></lastBuildDate>
	<image>
		<link><?=$page['link'];?></link>
		<url><?=$page['imageurl'];?></url>
		<title><?=$page['title'];?></title>
	</image>

<?php

	foreach($page['items'] as $item){

?>

	<item>		
		<title><![CDATA[<?=htmlspecialchars_decode($item['header'], ENT_QUOTES);?>]]></title>
		<guid isPermaLink="false"><?=$item['uid'];?></guid>
		<link><?=htmlspecialchars($item['url']);?></link>
		<description><![CDATA[<p><?=$item['description'];?></p>
			<?php		
				if ($item['attach']) {
					echo "<p>Прикрепленный файл: <a href=\"" . $item['attach'] . "\">" .
						str_replace("_", " ", $item['attachname']) .
						"</a></p>";
				} ?></a>]]></description>
		<pubDate><?=date( DATE_RSS /*'D, d M Y g:i:s +0000'*/, $item['time'] );?></pubDate>
	</item>

<?php	} ?>

</channel>
</rss>
