<?php

/*
 * Supported BEPs (http://www.bittorrent.org/beps/bep_0000.html):
 * 
 *  BEP003: The BitTorrent Protocol Specification (http://www.bittorrent.org/beps/bep_0003.html)
 *  BEP023: Tracker Returns Compact Peer Lists (http://www.bittorrent.org/beps/bep_0023.html)
 */

/*
 *  !!!!!! REQUIREMENTS !!!!!!
 *  - PHP > 5.3.0 (due to Predis)
 *  - Redis >2.0.0 (due to SETEX command)
 *  - Predis >0.6.0 (due to SETEX command)
 */

/*
 *                  Redis Structure
 * --------------------------------------------------------------------------------------------------
 * |  peers:info_hash - Hash {peerId:ip:info_hash = 1}
 * |  torrent:peerId:ip:info_hash - Expired string json([peer_id, ip, port])
 * |  
 */

require 'Predis/Autoloader.php';
include_once("BEncode.php");

// in seconds
define('MIN_ANNOUNCE_INTERVAL_SEC', 60);
define('RECOMMEND_ANNOUNCE_INTERVAL_SEC', 90);
define('EXPIRE_TIME_SEC', 300);

// Check input variables
// TODO: log unknown field

$inputVarNames_EscapedStrings = array (
    'info_hash',
    'peer_id',
);

$inputVarNames_Strings = array(
        'event',
);
// ignore 'ip' field
$inputVarNames_Numbers = array(
        'compact',
        'downloaded',
        'left',
        'port',
        'uploaded',
);

InitInputVariables();
VerifyInputVariables();

try {
    Predis\Autoloader::register();
    $redis = new Predis\Client();
} catch (Exception $e) {
    SendError('1. Couldn\'t connect to DB');
}

// update info
$peer = array(
        $inputVars['peer_id'],
        $userIp,
        $inputVars['port'],
    );
try{
    $key = implode(':', array($inputVars['peer_id'], $userIp, $inputVars['info_hash']));
    $redis->setex('torrent:' . $key, EXPIRE_TIME_SEC, json_encode($peer));
    $redis->hset('peers:' . $inputVars['info_hash'], $key, 1);
}catch (Exception $e) {
    error_log($e . "\n For ip: " . $userIp . ' and query: ' . $_SERVER['QUERY_STRING']);
    SendError('2. DB error');
}

// TODO: Switch to SET insted of SETEX in newer Redis version

try {
    $peersKeys = $redis->hkeys('peers:' . $inputVars['info_hash']);
    $peers = $redis->mget(array_map(function ($value) {return 'torrent:' . $value;}, $peersKeys));
}catch (Exception $e) {
    error_log($e . "\n For ip: " . $userIp . ' and query: ' . $_SERVER['QUERY_STRING']);
    SendError('3. DB error');
}

$peersOutput = array();
$keysToDeleteByTimeout = array();
for ($i = 0; $i < count($peers); $i = $i+1) {
    if (empty($peers[$i])) {
        $keysToDeleteByTimeout[] = $peersKeys[$i];
        continue;
    }
    $peer = json_decode($peers[$i]);
    if ($peer[0] === $inputVars['peer_id'])
        continue;
    if ($inputVars['compact']) {
        $peersOutput[] = StrIpToBinary($peer[1]) . pack('n', $peer[2]);
    } else {
        $peersOutput[] = array(
                'peer id'       => $peer[0],
                'ip'              => $peer[1],
                'port'           => intval($peer[2]),
        );
    }
}
if ($inputVars['compact']) {
    $peersOutput = implode($peersOutput);
}
if (count($keysToDeleteByTimeout)) {
    $redis->hdel('peers:' . $inputVars['info_hash'], $keysToDeleteByTimeout);
}
    
// Generate output
$output = array(
	'interval'     => RECOMMEND_ANNOUNCE_INTERVAL_SEC,
	'min interval' => MIN_ANNOUNCE_INTERVAL_SEC, // ?
	'peers'        => $peersOutput,
);

// Return data to client
echo BEncode($output);
exit;

// ----------------------------------------------------------------------------
// Functions
//

function BinIpToStr ($ip) {
    return sprintf('%3u.%3u.%3u.%3u', $ip[0], $ip[1], $ip[2], $ip[3]);
}

function InitInputVariables() {
    global $inputVarNames_EscapedStrings,
               $inputVarNames_Strings,
               $inputVarNames_Numbers,
               $inputVars;
               
    foreach ($inputVarNames_EscapedStrings as $var_name) {
        $inputVars[$var_name] = isset($_GET[$var_name]) ?  urldecode($_GET[$var_name]) : null;
    }
    foreach ($inputVarNames_Strings as $var_name) {
        $inputVars[$var_name] = isset($_GET[$var_name]) ? (string) $_GET[$var_name] : null;
    }
    foreach ($inputVarNames_Numbers as $var_name) {
        $inputVars[$var_name] = isset($_GET[$var_name]) ? (float) $_GET[$var_name] : null;
    }
    
    // FIXME: verify to not SQL injection for NoSQL
    
    global $userIp;
    $userIp = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($inputVars['compact'])) {
        // in according to BEP023
        $inputVars['compact'] = 1;
    }        
}

function SendError ($msg) {
        $output = bencode(array(
                'min interval'    => (int) MIN_ANNOUNCE_INTERVAL_SEC,
                'failure reason'  => (string) $msg,
        ));
        
        error_log($msg);

        exit($output);
}

function StrIpToBinary ($ip) {
        $d = explode('.', $ip);
        return sprintf('%c%c%c%c', $d[0], $d[1], $d[2], $d[3]);
}

function VerifyInputVariables () {
    global $inputVars;
    if (!isset($inputVars['info_hash']) || strlen($inputVars['info_hash']) != 20) {
            // Похоже, к нам зашли через браузер.
            // Вежливо отправим человека на инструкцию по псевдотрекеру.
            echo "<meta http-equiv=refresh content=0;url=/pseudotracker/>";
            exit();
    //      SendError("Invalid info_hash: '$info_hash' length ".strlen($info_hash));
    }
    if (!isset($inputVars['peer_id']) || strlen($inputVars['peer_id']) != 20) {
            SendError('Invalid peer_id');
    }
    if (!isset($inputVars['port']) || $inputVars['port'] < 0 || $inputVars['port'] > 0xFFFF) {
            SendError('Invalid port');
    }
    if (!isset($inputVars['uploaded']) || $inputVars['uploaded'] < 0) {
            SendError('Invalid uploaded value');
    }
    if (!isset($inputVars['downloaded']) || $inputVars['downloaded'] < 0) {
            SendError('Invalid downloaded value');
    }
    if (!isset($inputVars['left']) || $inputVars['left'] < 0) {
            SendError('Invalid left value');
    }
}