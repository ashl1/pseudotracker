Установка:

1. Создать базу данных MySql.

2. Создать в ней таблицу tracker:

CREATE TABLE `tracker` (
  `info_hash` char(20) collate utf8_bin NOT NULL,
  `ip` char(8) collate utf8_bin NOT NULL,
  `port` int(11) NOT NULL,
  `peer_id` varbinary(21) DEFAULT NULL,
  `update_time` int(11) NOT NULL,
  `descr` varchar(255) collate utf8_bin default NULL,
  `tracker` varchar(255) collate utf8_bin default NULL,
  `ip_real` varchar(32) collate utf8_bin default NULL,
  `publisherurl` varchar(255) collate utf8_bin default NULL,
  `pleft` bigint(16) default NULL,
  `downloaded` bigint(16) NOT NULL,
  `length` int(11) NOT NULL,
  PRIMARY KEY  (`info_hash`,`ip`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

Предлагается также использовать тип MEMORY для ускорения обработки
Attention! Before each table creation should set the variable while use MEMORY type tables. Use variable with default value 100 MiB.
SET max_heap_table_size = 100*1024*1024;

3. Убедится, что в php.ini "magic_quotes_gpc = Off".
В противном случае некоторые торренты будут обрабатываться некорректно.

4. В pseudotracker\patcher\index.pl есть следующие строки:

#!"H:\Program Files\xampp\perl\bin\perl.exe"

require "H:\\Program Files\\xampp\\htdocs\\pseudotracker\\patcher\\tf.pl";

$path = "http://10.116.10.16";

В первой из них нужно указать полный путь к perl,
во второй - полный путь к файлу tf.pl (pseudotracker\patcher\tf.pl, не забывайте экранировать "\", как в примере),
в третьей - хост/путь, по которым будет размещен announce.php (без последнего "/")

5. Во всех .php-файлах (в самом начале) указать реальные данные для доступа к MySQL

$DBHost="localhost";
$DBUser="tracker";
$DBPass="12345";
$DBName="tracker";

6. Важно! Если в вашей сети используется двойная система адресации (отдельно внутренние и отдельно внешние IP,
такая ситуация возникает при использовании PPPoE/PPPTP), веб-сервер, под управлением которого работает псевдотрекер,
должен слушать именно тот IP, по которому идет дешевый/нелимитированный трафик (т.е. локальный).

Для Apache задача решается прописыванием в apache.conf следующей строки (10.116.10.16 - пример локального IP):

Listen 10.116.10.16:80


---

Вопросы: unxed@mail.ru или ICQ# 212719

