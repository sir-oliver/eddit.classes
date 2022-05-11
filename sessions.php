<?PHP
define('SESSION_TABLE', 'cms.sessions_new');
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME',20);

function session_db_open ($save_path, $session_name)
{
	return true;
}
function session_db_close ()
{
	return true;
}
function session_db_read ($sessionID)
{
	$data = DB::queryFirstField
	(
		'SELECT value FROM %b WHERE sid=%s',
		SESSION_TABLE,
		$sessionID
	);
	if ( $data == NULL ) $data = '';
	return $data;
}
function session_db_write ($sessionID, $value)
{
	global $CE;
	return DB::insertUpdate
	(
		SESSION_TABLE,
		array
		(
			'sid' => $sessionID,
			// 'id' => EDDIT::config('dbName'),
			'id' => EDDIT::arrayKey("SERVER_NAME",$_SERVER,"-"),
			'modified' => DB::sqleval("NOW()"),
			'value' => $value,
			'ip' => EDDIT::arrayKey("REMOTE_ADDR",$_SERVER,"-"),
			'browser' => EDDIT::arrayKey("HTTP_USER_AGENT",$_SERVER,"-"),
		),
		array
		(
			'modified' => DB::sqleval("NOW()"),
			'value' => $value,
		)
	);
}
function session_db_destroy ($sessionID)
{
	session_db_gc();
	return DB::delete
	(
		SESSION_TABLE,
		'sid=%s', $sessionID
	);
}
function session_db_gc ()
{
	return DB::delete
	(
		SESSION_TABLE,
		'modified < (NOW() - INTERVAL %i MINUTE)', SESSION_LIFETIME
	);
}
if ( class_exists('DB') )
{
	session_set_save_handler
	(
		"session_db_open",
		"session_db_close",
		"session_db_read",
		"session_db_write",
		"session_db_destroy",
		"session_db_gc"
	);
}
else
{
	syslog(LOG_ERR,__FILE__.' DB class not defined.');
}
?>
