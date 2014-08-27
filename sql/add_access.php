#!/usr/bin/php5
<?
# databasefuncties en configuratie
include "../include/init.php";

if (count($argv) != 3) fatal_error("Usage: {$argv[0]} AFKR afkerdocentv");

$ppl_id = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '%s' AND active IS NOT NULL", mysql_escape_safe($argv[1]));

echo("ppl_id=$ppl_id\n");

mysql_query_safe("INSERT INTO ppl2altlogin ( ppl_id, altlogin ) VALUES ( $ppl_id, '%s' ) ON DUPLICATE KEY UPDATE altlogin = '%s'", mysql_escape_safe($argv[2]), mysql_escape_safe($argv[2]));

?>
