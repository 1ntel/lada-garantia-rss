<?php

	date_default_timezone_set( 'Europe/Moscow' );
	error_reporting( E_ALL ); //E_ERROR
	mb_internal_encoding("UTF-8");

	function loadCfg($path){return require $path;}
	$config = loadCfg('.\data\config.php');

	require_once('.\system\sqlite.php');
	$db = dbOpen();
	$items = dbGetItems($db, 10);

	header( 'Cache-Control: no-cache, must-revalidate' ); // HTTP/1.1
	header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' ); // Date in the past
	header( 'content-type: application/rss+xml; charset=utf-8' );
	echo( '<?xml version="1.0" encoding="UTF-8"?>' );
	echo( '<rss version="2.0">' );
	
	function trim_text($input, $length) {
		if (mb_strlen($input) <= $length) {
			return $input;
		}

		// Find the last space (between words we're assuming) after the max length.
		$last_space = mb_strrpos(mb_substr($input, 0, $length), ' ');
		// Trim
		$trimmed_text = mb_substr($input, 0, $last_space);
		// Add ellipsis.
		$trimmed_text .= '…';

		return $trimmed_text;
	}

?><channel>
	<title><?=$config['title'];?></title>
	<link><?=$config['link'];?></link>
	<description><![CDATA[<?=$config['description'];?>]]></description>
	<language>ru-ru</language>
	<managingEditor><?=$config['managingEditor'];?></managingEditor>
	<webMaster><?=$config['webMaster'];?></webMaster>
	<generator><?=$config['generator'];?></generator>
	<pubDate><?=date( DATE_RSS, $items[1]['date'] );?></pubDate>
	<lastBuildDate><?=date( DATE_RSS, $items[1]['date'] );?></lastBuildDate>
	<!--<image>
		<link><?=$config['link'];?></link>
		<url><?=$config['imageurl'];?></url>
		<title><?=$config['title'];?></title>
	</image>-->
<?php

	foreach($items as $item){
	
?>
	<item>		
		<title><![CDATA[<?=trim_text(
			str_replace(
				array('Предписание', 'ПРЕДПИСАНИЕ', 'Информационное письмо', 'ИНФОРМАЦИОННОЕ ПИСЬМО'), 
				array('ПР', 'ПР', 'ИП', 'ИП'), 
				htmlspecialchars_decode($item['header'], ENT_QUOTES))
			.'. '.strip_tags($item['description']), 60);
		?>]]></title>
		<guid isPermaLink="false"><?=$item['uid'];?></guid>
		<link><?php if ($item['attach']) {echo htmlspecialchars($item['attach']);} else {echo htmlspecialchars($item['url']);}?></link>
		<description><![CDATA[<p><?=$item['header'];?></p><p><?=$item['description'];?></p>
			<?php		
				if ($item['attach']) {
					echo "<p>Прикрепленный документ: <a href=\"" . $item['attach'] . "\">" .
						str_replace("_", " ", $item['attachname']) .
						"</a></p>";
				} ?></a>]]></description>
		<pubDate><?=date( DATE_RSS /*'D, d M Y g:i:s +0000'*/, $item['time'] );?></pubDate>
	</item>
<?php	
	} 
?>

</channel>
</rss>
