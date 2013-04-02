<?php

include_once("BEncode.php");

# Данные для доступа к БД MySQL

$DBHost='localhost';
$DBUser='retracker_user';
$DBPass='';
$DBName='retracker';

# -------------- Дальше ничего менять не надо! --------------------------------

define('TIMESTART', utime());
define('TIMENOW',   time());

$announce_interval = 1800;
$expire_factor     = 4;
$peer_expire_time  = TIMENOW - floor($announce_interval * $expire_factor);

mysql_connect("$DBHost","$DBUser","$DBPass") or msg_die("Could not connect: " . mysql_error());
mysql_select_db("$DBName");

mysql_query("DELETE FROM tracker WHERE update_time < $peer_expire_time")
 or msg_die("1. MySQL error: " . mysql_error());

// Input var names
// String
//	'info_hash',
//	'peer_id',

$input_vars_str = array(
	'event',
	'descr',
	'mt',
	'pu',
);
// Numeric
$input_vars_num = array(
	'port',
	'uploaded',
	'downloaded',
	'left',
	'numwant',
	'compact',
	'l',
);

// Init received data
$info_hash = urldecode($_GET['info_hash']);
$peer_id = urldecode($_GET['peer_id']);

// String
foreach ($input_vars_str as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (string) $_GET[$var_name] : null;
}
// Numeric
foreach ($input_vars_num as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (float) $_GET[$var_name] : null;
}

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Похоже, к нам зашли через браузер.
	// Вежливо отправим человека на инструкцию по псевдотрекеру.
	echo "<meta http-equiv=refresh content=0;url=/pseudotracker/>";
	die;
//	msg_die("Invalid info_hash: '$info_hash' length ".strlen($info_hash));
}
if (!isset($peer_id) || strlen($peer_id) != 20)
{
	msg_die('Invalid peer_id');
}
if (!isset($port) || $port < 0 || $port > 0xFFFF)
{
	msg_die('Invalid port');
}
if (!isset($uploaded) || $uploaded < 0)
{
	msg_die('Invalid uploaded value');
}
if (!isset($downloaded) || $downloaded < 0)
{
	msg_die('Invalid downloaded value');
}
if (!isset($left) || $left < 0)
{
	msg_die('Invalid left value');
}

// IP
$ip = $_SERVER['REMOTE_ADDR'];


// unknown code
/*if (!$tr_cfg['ignore_reported_ip'] && isset($_GET['ip']) && $ip !== $_GET['ip'])
{
	if (!$tr_cfg['verify_reported_ip'])
	{
		$ip = $_GET['ip'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
	{
		foreach ($matches[0] as $x_ip)
		{
			if ($x_ip === $_GET['ip'])
			{
				if (!$tr_cfg['allow_internal_ip'] && preg_match("#^(10|172\.16|192\.168)\.#", $x_ip))
				{
					break;
				}
				$ip = $x_ip;
				break;
			}
		}
	}
}*/

// Check that IP format is valid
if (!verify_ip($ip))
{
	msg_die("Invalid IP: $ip");
}
// Convert IP to HEX format
$ip_sql = encode_ip($ip);

// ----------------------------------------------------------------------------
// Start announcer
//

$main_tracker = "$mt";

// Escape strings
$info_hash = mysql_real_escape_string($info_hash);
$port = mysql_real_escape_string($port);
$peer_id = mysql_real_escape_string($peer_id);
$descr = mysql_real_escape_string($descr);
$main_tracker = mysql_real_escape_string($main_tracker);
$pu = mysql_real_escape_string($pu);
$left = mysql_real_escape_string($left);
$downloaded = mysql_real_escape_string($downloaded);
$l = mysql_real_escape_string($l);

// Stopped event
if ($event === 'stopped')
{
	mysql_query("DELETE FROM tracker WHERE info_hash = '$info_hash' AND ip = '$ip_sql' AND port = $port")
	 or msg_die("2. MySQL error: " . mysql_error());

	die();
}


mysql_query("REPLACE INTO tracker SET info_hash = '$info_hash', ip = '$ip_sql', port = $port, peer_id='$peer_id',
 update_time = ". time() .", descr = '$descr', tracker = '$main_tracker',
 ip_real = '$ip', publisherurl = '$pu', pleft = '$left', downloaded = '$downloaded', length = '$l'")
 or msg_die("3. MySQL error: " . mysql_error());

// Select peers

$result = mysql_query("SELECT peer_id, ip, port FROM tracker WHERE info_hash = '$info_hash'")
 or msg_die("4. MySQL error: " . mysql_error());

$peers_list = array();

while ($row = mysql_fetch_array($result, MYSQL_NUM))
{
	$peers_list[] = array(
		'peer id' => $row[0],
		'ip'      => decode_ip($row[1]),
		'port'    => intval($row[2]),
	);;
}

/*if ($compact_mode)
{
	// CANNOT DO THIS
	$peers = '';

	foreach ($rowset as $peer)
	{
		$peers .= pack('Nn', ip2long(decode_ip($peer['ip'])), $peer['port']);
	}
}
else
{ */
//}

// Generate output

$output = array(
	'interval'     => 300, // tracker config: announce interval (sec?)
	'min interval' => 60, // tracker config: min interval (sec?)
	'peers'        => $peers_list,
);

// Return data to client
echo BEncode($output);

exit;

// ----------------------------------------------------------------------------
// Functions
//
function utime ()
{
	return array_sum(explode(' ', microtime()));
}

function msg_die ($msg)
{
	$output = bencode(array(
		'min interval'    => (int) 60,
		'failure reason'  => (string) $msg,
	));
	
	error_log($msg);

	die($output);
}

function dummy_exit ($interval = 60)
{
	$output = bencode(array(
		'interval'     => (int)    $interval,
		'min interval' => (int)    $interval,
		'peers'        => (string) DUMMY_PEER,
	));

	die($output);
}

function encode_ip ($ip)
{
	$d = explode('.', $ip);

	return sprintf('%02x%02x%02x%02x', $d[0], $d[1], $d[2], $d[3]);
}

function decode_ip ($ip)
{
	return long2ip("0x{$ip}");
}

function verify_ip ($ip)
{
	return preg_match('#^(\d{1,3}\.){3}\d{1,3}$#', $ip);
}

function str_compact ($str)
{
	return preg_replace('#\s+#', ' ', trim($str));
}
