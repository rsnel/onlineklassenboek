<?
date_default_timezone_set('Europe/Amsterdam');
//
// deze data wijzigt elk schooljaar
$schooljaar_long='2013/2014'; // format YYYY/YYYY
$vorig_schooljaar_long='2012/2013'; // format YYYY/YYYY
$lesweken = explode(' ', '35 36 37 38 39 40 41 42 44 45 46 47 48 49 50 51 '.
	'2 3 4 5 6 7 8 10 11 12 13 14 15 16 19 20 21 22 23 24 25 26 27');

$http_server='klassenboek.ovc.nl';
$http_path=''; // without trailing slash
$cookie_path =$http_path.'/';
$session_subdir = 'ovckb_sessions';

$teletop_server = 'http://ovc1.teletop.nl';
$teletop_vaksite_prefix = '/tt/abvo/courses/';

$schoolnaam = " OVC";
$favicon = "favicon_ovc.ico";

// hier staan wachtwoorden en secret MAC keys
require_once('config_secret.php');

//lestijden
$lestijden[1] = "8:30-9:20";
$lestijden[2] = "9:20-10:10";
$lestijden[3] = "10:10-11:20 (incl. 20 min pauze)";
$lestijden[4] = "11:20-12:10";
$lestijden[5] = "12:10-13:30 (incl. 30 min pauze)";
$lestijden[6] = "13:30-14:20";
$lestijden[7] = "14:20-15:10";
$lestijden[8] = "15:10-16:00";
$lestijden[9] = "16:00-16:50";

$svgweb = '/svgweb/svg.js';
$cachedir = '/var/www-ssl/onlineklassenboek.nl/cache/ovc';

$beheerder = 'R. Snel <rik@snel.it>';

?>
