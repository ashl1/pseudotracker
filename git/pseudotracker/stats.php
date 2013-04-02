<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" /> 
<title>Статистика</title>
<style type="text/css">
body { font-family: Verdana, Arial; font-size: 10pt; }
td { font-family: Verdana, Arial; font-size: 9pt; }
td.mini { font-family: Verdana, Arial; font-size: 8pt; }

tr.rowType1 { background-color: #bbffbb; }
tr.rowType2 { background-color: #bbbbff; }
</style>
</head>
<body>
<?php

# Данные для доступа к БД MySQL

$DBHost='localhost';
$DBUser='retracker_user';
$DBPass='';
$DBName='retracker';

# -------------- Дальше ничего менять не надо! --------------------------------

mysql_connect("$DBHost","$DBUser","$DBPass") or msg_die("Could not connect: " . mysql_error());
mysql_select_db("$DBName");

$result = mysql_query("REPAIR TABLE tracker")
 or msg_die("MySQL error: " . mysql_error());

echo "<h3>Статистика локального псевдотрекера</h3>";

$result = mysql_query("SELECT count(DISTINCT ip) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$ip_num = $row[0];

$result = mysql_query("SELECT count(DISTINCT info_hash) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$hash_num = $row[0];

$result = mysql_query("SELECT count(info_hash) - count(DISTINCT info_hash) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$delta = $row[0];

# патченный azureus не прописывает поле tracker
$result = mysql_query("SELECT count(DISTINCT ip) FROM tracker WHERE tracker = '' AND descr != ''")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$azureus = $row[0];

//$action = $_GET['action']? $_GET['action']: 'show_all';
$action = '';
$order = $_GET['order']? $_GET['order']: '';

echo "Всего клиентов: $ip_num, всего торрентов: $hash_num, общих торрентов: $delta<br>\n";
/*if ($action == 'show_all') {
echo "Отобразить только торренты <a href=?order=$order>с описанием</a><br>\n";
} else {
echo "Отобразить торренты <a href=?action=show_all&order=$order>без описания</a><br>\n";
}
echo "Если у вашего торрента не отображается описание, вам нужно аккуратно его <a href=patcher/>пропатчить</a>\n";*/

if ($order == '') { $order = 'ip'; }
$order = mysql_real_escape_string($order);

//$where = "WHERE descr != ''";
//if ($action == 'show_all') { $where = ''; }
$where = '';

$result = mysql_query("SELECT ip_real, port, descr, tracker, publisherurl, pleft, downloaded, length FROM tracker $where ORDER BY $order")
 or die("MySQL error: " . mysql_error());

$rowset = array();

echo "<table border=1><tr><td><a href=?order=ip&action=$action>Общага</a></td>";
echo "<td><a href=?order=descr&action=$action>Описание</a></td><td><a href=?order=tracker&action=$action>Трекер</a></td>";
echo "<td><b>Размер (примерно)</b></td>";
echo "<td><b>%</b></td><td><a href=?order=publisherurl&action=$action>Инфо</a></td></tr>";

$row_style = 'rowType2';
$hostel_old = '';
while ($row = mysql_fetch_row($result))
{
  if ($row[7] > 0) {
    # если торрент пропатчен правильно, и есть поле length
    $pleft = round(round($row[5])/(1024*1024));
    $size = $row[7];
    # hackfix a "round" bug
    if ($pleft > $size) { $pleft = $size; }
    $dwnl = $size - $pleft;
    $percent = round(100*$dwnl/$size);
  } else { 
    # если нет, попробуем прикинуть по полям downloaded и left
    $pleft = round(round($row[5])/(1024*1024));
    $dwnl = round(round($row[6])/(1024*1024));
    $size = $pleft + $dwnl;
    $percent = round(100*$row[6]/($row[5]+$row[6]+1));
  }

  // determine the hostel
  $hostel = '';
  if (preg_match("#172\.24\.#", $row[0])) { $hostel='4 общ'; }
  else if (preg_match("#172\.25\.#", $row[0])) { $hostel='5 общ'; }
  else if (preg_match("#172\.26\.#", $row[0])) { $hostel='6 общ'; }
  else if (preg_match("#172\.28\.#", $row[0])) { $hostel='8 общ'; }
  else if (preg_match("#172\.29\.#", $row[0])) { $hostel='9 общ'; }
  else if (preg_match("#172\.16\.(([0-9])|(1[0-5]))\.#", $row[0])) { $hostel='11 общ'; }
  else if (preg_match("#172\.16\.((1[6-9])|(2[0-9])|(3[01]))\.#", $row[0])) { $hostel='10 общ'; }
  else { $hostel = 'Неизв.'; }

  if ($hostel != $hostel_old) {
    $row_style = $row_style == 'rowType1'? 'rowType2': 'rowType1';
    $hostel_old = $hostel;
  }
  
  if ($row[5] == 0) { $percent = 100; }

  $size .= " МБ";
  $percent .= "%";

  if ($size == 0) { $size = '<font color=#999999>неизвестно</font>'; }

  if ($row[4] != '') { $url ="<a target=_blank href=\"$row[4]\">есть</a>"; }
  if ($row[4] == '') { $url = "<font color=#999999>нет</font>"; }
  echo "<tr class=\"$row_style\"><td>$hostel</td><td>$row[2]&nbsp;</td>";

  # hackfix: принудительно убираем passkey из торрентов,
  # если торренты патчены кривым патчером ***
  $row[3] = preg_replace('/([A-Za-z0-9\.\-:]+)\/.*/','$1',$row[3]);

  echo "<td>$row[3]&nbsp;</td>";
  echo "<td>$size &nbsp;</td><td>$percent &nbsp;</td><td>$url&nbsp;</td></tr>\n";
}

echo "</table>";

exit;

function msg_die ($msg)
{
	die("<br><font color=red>$msg</font><br>\n");
}
?>
</body>
</html>