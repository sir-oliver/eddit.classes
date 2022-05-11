<?php
if ( defined('NO_AUTO_APPEND') ) exit();

$sid = session_id();
if ( !empty( $sid ) ) session_write_close();

if ( defined('CE_START') && function_exists('ce_diff_microtime') ) define ('CE_TIME', ce_diff_microtime() );
else define ('CE_TIME', 0);

if ( defined('CE_MEM_START') )
{
	define ('CE_MEM_END', memory_get_usage());
	define ('CE_MEM_USAGE', CE_MEM_END - CE_MEM_START );
}

if (CE_DEBUG || defined('CE_SHOW_DEBUGGER'))
{
    // echo '<hr>';
    // echo '<a class="btn btn-default pull-left" role="button" data-toggle="collapse" href="#debugger" aria-expanded="true" aria-controls="collapseExample">';
    // echo '<span class="glyphicon glyphicon-wrench"></span> &nbsp; DEBUG';
    // echo '</a>';

    echo '<div id="debugger" style="display:none">';
    echo '<div class="container-fluid" style="font-size: 0.7em">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
/*
		echo '<div class="panel panel-default">';
		echo '<div class="panel-heading">Statistics</div>';
		echo '<div class="panel-body">';
		echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em">';
		echo '<b>PHP Execution Time</b> = ';
		printf('%8.4f sec.',CE_TIME);
		if ( defined('CE_MEM_USAGE') )
		{
			echo PHP_EOL.'<b>Memory Usage</b>       =   ';
			printf('%s', str_pad( number_format( CE_MEM_USAGE/1024, 2, ',', '.').' kB', 23));
		}
		echo '</pre>';
		echo '</div>';
		echo '</div>';
*/
        if ( sizeof($_POST) > 0 )
        {
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">$_POST</div>';
            echo '<div class="panel-body">';
        	echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em; background: transparent">';
            echo htmlentities(print_r($_POST,1));
            echo '</pre>';
            echo '</div>';
            echo '</div>';
        }
        if ( sizeof($_GET) > 0 )
        {
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">$_GET</div>';
            echo '<div class="panel-body">';
        	echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em; background: transparent">';
            echo htmlentities(print_r($_GET,1));
            echo '</pre>';
            echo '</div>';
            echo '</div>';
        }
        if ( sizeof($_COOKIE) > 0 )
        {
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">$_COOKIE</div>';
            echo '<div class="panel-body">';
        	echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em; background: transparent">';
            echo htmlentities(print_r($_COOKIE,1));
            echo '</pre>';
            echo '</div>';
            echo '</div>';
        }
        if ( !empty( $sid ) )
    	{
            echo '<div class="panel panel-default">';
            echo '<div class="panel-heading">$_SESSION</div>';
            echo '<div class="panel-body">';
        	echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em; background: transparent">';
            echo htmlentities(print_r($_SESSION,1));
            echo '</pre>';
            echo '</div>';
            echo '</div>';
    	}


    echo '</div>';
    echo '<div class="col-md-6">';

        if ( class_exists('EDDIT') )
    	{
    		EDDIT::dumpLog();
    	}

	echo '</div>'; // col
	echo '</div>'; // row
	echo '</div>'; // container
	echo '</div>'; // #debugger
	echo '<script>';
	echo 'clickedit_console = window.open("", "clickedit_console", "width=1024,height=600,left=50,top=50,resizable,scrollbars=yes");';
	// echo '$(clickedit_console.document.head).html( \'<title>EDDIT::Debugger '.$_SERVER['REQUEST_URI'].'</title><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">\' );';
	// echo '$(clickedit_console.document.body).html( $("#debugger").html() );';
	// echo 'clickedit_console.document.writeln( \'<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootswatch/3.3.6/united/bootstrap.min.css">\' );';
	echo 'clickedit_console.document.body.innerHTML = "";';
	echo 'clickedit_console.document.writeln( \'<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">\' );';
	echo 'clickedit_console.document.writeln( document.getElementById("debugger").innerHTML );';
	echo '</script>';
}

if ( CE_MEM_USAGE > 48*1024*1024 )
{
    $msg = sprintf(
    	'MEM: (%d) %s %s%s FROM IP %s --> %s kB',
    	posix_getpid(), $_SERVER["REQUEST_METHOD"], (defined('CE_NAME')) ? CE_NAME : $_SERVER["SERVER_NAME"], substr($_SERVER["REQUEST_URI"],0,200) , $_SERVER['REMOTE_ADDR'], number_format( CE_MEM_USAGE/1024, 2, ',', '.')
    );
    syslog(LOG_INFO,  $msg);
}

if ( CE_TIME > 2 )
{
        $msg = sprintf(
        	'SLOW: (%d) %s %s%s FROM IP %s --> %7.4f sec.',
        	posix_getpid(), $_SERVER["REQUEST_METHOD"], (defined('CE_NAME')) ? CE_NAME : $_SERVER["SERVER_NAME"], substr($_SERVER["REQUEST_URI"],0,200) , $_SERVER['REMOTE_ADDR'], CE_TIME
        );
		if ( defined('CE_MEM_USAGE') )
		{
			$msg .= sprintf(' / %s kB', number_format( CE_MEM_USAGE/1024, 2, ',', '.') );
		}
        if (CE_TIME > 5)	syslog(LOG_WARNING,     $msg);
        elseif (CE_TIME > 3)	syslog(LOG_NOTICE, $msg);
        elseif (CE_TIME > 1)		syslog(LOG_INFO,  $msg);
}
closelog();

?>
