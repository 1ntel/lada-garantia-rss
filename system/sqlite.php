<?php 

// открывает БД и инициализирует таблицы
function dbOpen() { 

	$db = new SQLite3('.\data\rss.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

	$db->exec("CREATE TABLE IF NOT EXISTS items (" . 
		"uid			TEXT, "	. 				// 	HHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHH UNIQUE
		"time			INTEGER NOT NULL, " . 	// 	DDDDDDDDDD
		"date			INTEGER, " . 			// 	DDDDDDDDDD
		"header			TEXT, " . 				//
		"description	TEXT, " . 				//
		"attachname		TEXT, " . 				//
		"attach			TEXT, " . 				//
		"url			TEXT);"); 				// 

	//$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_items_uid       ON items (uid);");	
	$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_items_time_desc ON items (time DESC);");	
		//INSERT INTO FOO VALUES(1,2,3); 
		//INSERT INTO FOO SELECT * FROM FOO;

	$db->exec("--
		PRAGMA page_size = 4096;		-- размер страницы БД; страница БД - это единица обмена между диском и кэшом, разумно сделать равным размеру кластера диска (у меня 4096)
		--PRAGMA cache_size = -kibibytes;	-- задать размер кэша соединения в килобайтах, по умолчанию он равен 2000 страниц БД
		PRAGMA encoding = 'UTF-8';		-- тип данных БД, всегда используйте UTF-8
		--PRAGMA foreign_keys = 1;		-- включить поддержку foreign keys, по умолчанию - ОТКЛЮЧЕНА
		PRAGMA journal_mode = TRUNCATE;	-- задать тип журнала, DELETE | TRUNCATE | PERSIST | MEMORY | WAL | OFF
		PRAGMA synchronous = NORMAL;	-- тип синхронизации транзакции, 0 | OFF | 1 | NORMAL | 2 | FULL");

	return $db; 
}

// выборка записей
function dbGetItems($db, $amount=10) {

	$statement = $db->prepare('SELECT * FROM items ORDER BY time DESC LIMIT :amount;');

	$amount = (int) $amount;
	$statement->bindValue(':amount', $amount, SQLITE3_INTEGER);

    $data = array();
	$result = $statement->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)){
        $data[] = $row;
    }
	return $data;
}

// выборка строки
function dbGetItem($db, $uid) {

	$statement = $db->prepare(
		'SELECT * FROM items WHERE uid = :uid;');

	$statement->bindValue(':uid', $uid, SQLITE3_TEXT);

    $data = array();
	$result = $statement->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)){
        $data[] = $row;
    }
	return $data;
}

// добавление строки
function dbAddItem($db, $row) {
	$statement = $db->prepare(
		"INSERT OR IGNORE INTO items " .
			"(uid,  time, date, header, description, attachname, attach, url) " .
		"VALUES " .
			"(?,    ?,    ?,    ?,      ?,           ?,          ?,      ?);");
			
	$row['time'] = (int) $row['time'];
	$row['date'] = (int) $row['date'];

	$statement->bindParam( 1, $row['uid'],			SQLITE3_TEXT);
	$statement->bindParam( 2, $row['time'],			SQLITE3_INTEGER);
	$statement->bindParam( 3, $row['date'], 		SQLITE3_INTEGER);
	$statement->bindParam( 4, $row['header'],		SQLITE3_TEXT);
	$statement->bindParam( 5, $row['description'],	SQLITE3_TEXT);
	$statement->bindParam( 6, $row['attachname'],	SQLITE3_TEXT);
	$statement->bindParam( 7, $row['attach'],		SQLITE3_TEXT);
	$statement->bindParam( 8, $row['url'],			SQLITE3_TEXT);
	
	$result = $statement->execute();
    return;
} 
