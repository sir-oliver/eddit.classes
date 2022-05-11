<?php
function ce_microtime()
{
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}
function ce_diff_microtime($ts = 0)
{
	$now = ce_microtime();
	if ( $ts > 0 )
	{
		return (float)$now - (float)$ts;
	}
	elseif ( defined('CE_START') )
	{
		return (float)$now - (float)CE_START;
	}
	else return (float)0;
}
define ("CE_START", ce_microtime());
define ("CE_MEM_START", memory_get_usage());

$hostName = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : $_SERVER['SCRIPT_NAME'];
if (!empty($_SERVER["REQUEST_URI"])) $hostName .= $_SERVER["REQUEST_URI"];
$hostName = ''.(int)posix_getpid().' '.$hostName;
define('CE_HOSTNAME',$hostName);
openlog($hostName, LOG_ODELAY, LOG_LOCAL7);

$remoteAddr = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
define ('IS_SMESH_IP',(bool)preg_match('/^90\.146\.194\.80$|^81\.10\.170\.10$/',$remoteAddr));
define ('CE_DEBUG', ( IS_SMESH_IP && ( defined('CE_FORCE_DEBUG') || strpos($_SERVER['REQUEST_URI'],'debug') )));
if (CE_DEBUG) error_reporting(E_ALL);

if (isset($_SERVER['REQUEST_URI']) && preg_match('/assetmanager\.php|autodiscover\.xml|FCKeditor|wp-login\.php|myluph\.php|jsp$|wget|curl|cfm$|asp$|aspx$|_vti_pvt|~guest|~log/i',$_SERVER['REQUEST_URI']))
{
	syslog(LOG_INFO,'Read access forbidden ----> '.$_SERVER['REMOTE_ADDR']);
	header("HTTP/1.1 403 Forbidden");
	exit('403 Forbidden');
}
#
# check for robots and badly behaving IPs
#
$robots = '/^ApacheBench|^Java|WebDAV|X11|check|Qwantify|PECL|Gigablast|Jakarta|Java|Ruby|bot|spider|crawl|index|PycURL|curl|voyager|HTMLDOC|slurp|unknown|Ocelli|BorderManager|find|dig|Francis|psycheclone|larbin|search|seek|wwwtype|siteexplorer|^WebC-I/i';
define ('IS_ROBOT', isset($_SERVER['HTTP_USER_AGENT']) && preg_match($robots , $_SERVER['HTTP_USER_AGENT']));

putenv("TZ=Europe/Vienna");
?>
