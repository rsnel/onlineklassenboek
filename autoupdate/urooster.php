#!/usr/bin/php
<?
require '../include/init.php';
require '../include/rooster_lib.php';

function print_header($ch, $line) {
	echo $line;
	return strlen($line);
}

//$result = mysql_query_safe(<<<EOT
//SELECT DISTINCT ppl_id, login FROM ppl
//JOIN doc2grp2vak USING (ppl_id)
//JOIN grp2vak USING (grp2vak_id)
//JOIN grp USING (grp_id)
//WHERE schooljaar = '$schooljaar' AND grp_type_id = 2;
//EOT
//);

//while ($row = mysql_fetch_array($result)) {
//	echo "{$row[1]}\n";
//}

$cookiefile = tempnam("/tmp", "COOK");

$ch = curl_rooster_init();
//curl_setopt($ch, CURLOPT_HEADERFUNCTION, print_header);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);

restart: 

$xpath = curl_rooster_req($ch, "http://intranet.ovc.nl/rooster/infoweb/index.php?ref=3");

$laatste_update = $xpath->query('//td[@id="tweedebalk"]');

if (!$laatste_update)
        regular_error($http_path.'/', $_GET, "laatste update niet gevonden");

$date_to_parse = substr(strrchr($laatste_update->item(0)->nodeValue, ','), 2);
$timestamp = strtotime($date_to_parse);

echo "laatste update $timestamp\n";

$week_select = $xpath->query("/html/body/table/tr[3]/td[2]/form/table/tr/td[2]/div/select/option[@selected=\"selected\"]");

if (!$week_select) regular_error($http_path.'/', $_GET, "&lt;option&gt; van weekselect is niet gevonden?!?!??!");

echo 'length='.$week_select->length."\n";
$proposed_week = $week_select->item(0)->getAttribute('value');

if (!in_array((int)$proposed_week, $lesweken))
	        regular_error($http_path.'/', $_GET, "huidige week op het roosterbord is geen lesweek");

echo "default week $proposed_week\n";

$random = (double)mt_rand()/(double)mt_getrandmax();
//echo $random."\n";

$xpath = curl_rooster_req($ch, "http://intranet.ovc.nl/rooster/infoweb/selectie.inc.php?wat=groep&weeknummer=$proposed_week&groep=*allen&type=1&sid=$random");

$bla = $xpath->query("//option");

if (!$bla) {
	echo "nicht gefunden\n";
	exit;
}

foreach ($bla as $ding) {
	$login = $ding->nodeValue;
	//if ($login != 'LOCH') continue;
	$ppl_id = sprint_singular("SELECT ppl_id, login FROM ppl WHERE login = '%s' AND active = 0", mysql_escape_safe($login));
	if (!$ppl_id) continue;
	echo "do ppl_id=$ppl_id, login=$login, timestamp=$timestamp, week=$proposed_week\n";

	$xpath = curl_rooster_req($ch, "http://intranet.ovc.nl/rooster/infoweb/index.php?ref=3&id=$login");

	$rooster = $xpath->query("/html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");

	if (!$rooster) regular_error($http_path.'/', $_GET, "&lt;table&gt; met rooster van $docent niet gevonden op /html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");

	$week_select = $xpath->query("/html/body/table/tr[3]/td[2]/form/table/tr/td[2]/div/select/option[@selected]");

	if (!$week_select)
		        regular_error($http_path.'/', $_GET, "&lt;option&gt; van weekselect is niet gevonden?!?!??!");

	$proposed_week2 = $week_select->item(0)->getAttribute('value');

	$laatste_update = $xpath->query('//td[@id="tweedebalk"]');

	if (!$laatste_update)
		        regular_error($http_path.'/', $_GET, "laatste update niet gevonden");

	$date_to_parse = substr(strrchr($laatste_update->item(0)->nodeValue, ','), 2);
	$timestamp2 = strtotime($date_to_parse);

	if ($proposed_week != $proposed_week2 || $timestamp != $timestamp2) goto restart;

	$current_rooster_timestamp = sprint_singular("SELECT UNIX_TIMESTAMP(MAX(timestamp)) FROM rooster WHERE week = '%s' AND ppl_id = '%s'", mysql_escape_safe($proposed_week), mysql_escape_safe($ppl_id));

	if ((int)$current_rooster_timestamp == $timestamp) continue; // rooster van docent is al ok
	
for ($i = 2; $i <= 10; $i++) {
        for ($j = 1; $j <= 5; $j++) {
                mysql_query_safe("UPDATE IGNORE rooster SET obsoleted_by_rooster_id = 0 WHERE week = '%s' ".
                                "AND schooljaar = '$schooljaar' AND dag = '%s' AND lesuur = '%s' AND ppl_id = '%s'",
                                mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), mysql_escape_safe($ppl_id));
                $cells = $xpath->query("//tr[$i]/td[$j]/table/tr/td/span//text()", $rooster->item(0));
		if (!$cells || !$cells->length) continue;
		//foreach ($cells as $cell) {
		//	echo $cell->nodeName, ' ', $cell->nodeValue, "\n";
		//}
		//for ($i = 0; $i < 3; $i++) {
		//	echo $i, ' ', $cells->item($i)->nodeName, ' ', $cells->item($i)->nodeValue, "\n";
		//}
		$length = $cells->length;
	//	echo "length=".$length."\n";
		if ($length == 5) {
			$lesgroepklas = $cells->item(0)->nodeValue;
			$vak = $cells->item(2)->nodeValue;
			$lokaal = $cells->item(3)->nodeValue;
		} else if ($length == 4) {
			$lesgroepklas = $cells->item(0)->nodeValue;
			$vak = $cells->item(1)->nodeValue;
			$lokaal = $cells->item(2)->nodeValue;
		} else if ($length == 1) {
			$lesgroepklas = NULL;
			$vak = $cells->item(0)->nodeValue;
			$lokaal = '???';
		} else if ($length == 3) {
			$lesgroepklas = NULL;
			$vak = $cells->item(1)->nodeValue;
			$lokaal = '???';
		}
		echo "$i $j lesgroep=$lesgroepklas vak=$vak lokaal=$lokaal\n";

                $vak_id = sprint_singular('SELECT vak_id FROM vak WHERE afkorting = \'%s\'', mysql_escape_safe($vak));
		if (!$vak_id) {
			echo 'vak '.$vak.' niet gevonden voor docent '.$login."\n";
			continue;
		}
		if ($lokaal == '???') $lokaal_id = NULL;
		else {
			$lokaal_id = sprint_singular('SELECT lokaal_id FROM lokalen WHERE lokaal_naam = \'%s\'', mysql_escape_safe($lokaal));
			if (!$lokaal_id) {
				echo 'lokaal '.$lokaal.' niet gevonden voor docent '.$login."\n";
				continue;
			}
		}

		if ($vak == 'stip ') {
			$grp_id = 0;
		} else {

                $lesgroep = explode('.', $lesgroepklas);
		if (count($lesgroep) == 1) {
			$grp_id = sprint_singular("SELECT grp_id FROM grp WHERE schooljaar = '$schooljaar' AND stamklas = 1 AND grp_type_id = 2 AND naam = '%s'", mysql_escape_safe($lesgroep[0]));
			if (!$grp_id) {
				echo 'klas of lesgroep '.$lesgroep[0].' niet gevonden voor docent '.$login."\n";
				continue;
			}

		} else if (count($lesgroep) != 2) {
			echo 'lesgroep '.$cells->item(0)->nodeValue.' bevat niet maximaal 1 punt voor docent '.$login."\n";
			continue;
		} else {

                // bij stamklassen hoeven we alleen te kijken naar het deel achter de punt
                $grp_id = sprint_singular("SELECT grp_id FROM grp WHERE schooljaar = '$schooljaar' AND stamklas = 1 AND grp_type_id = 2 AND naam = '%s'", mysql_escape_safe($lesgroep[1]));
                if (!$grp_id) { // wellicht hebben we te maken met een cluster
                        $grp_id = sprint_singular("SELECT grp_id FROM grp WHERE schooljaar = '$schooljaar' AND stamklas = 0 AND grp_type_id = 2 AND naam = '%s'", mysql_escape_safe($lesgroep[0].$lesgroep[1]));
			if (!$grp_id) {
				echo 'klas of lesgroep '.$lesgroep[0].'.'.$lesgroep[1].' niet gevonden voor docent '.$login."\n";
				continue;
			}
                }

		}

		}

                $grp2vak_id = sprint_singular("SELECT grp2vak_id FROM grp2vak WHERE grp_id = '%s' AND vak_id = '%s'", mysql_escape_safe($grp_id), mysql_escape_safe($vak_id));
                if (!$grp2vak_id) {
			echo 'klas of lesgroep '.$lesgroep[0].'.'.$lesgroep[1].' niet gevonden met vak '.$cells->item(1)->nodeValue.' voor docent '.$login."\n";
			continue;
		}

                mysql_query_safe("INSERT INTO rooster ( schooljaar, week, dag, lesuur, grp2vak_id, ppl_id, lokaal_id, timestamp ) VALUES ( '$schooljaar', '%s', '%s', '%s', '%s', '%s', '%s', FROM_UNIXTIME('%s') )", mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), mysql_escape_safe($grp2vak_id), mysql_escape_safe($ppl_id), mysql_escape_safe($lokaal_id), mysql_escape_safe($timestamp));

        }
}


	echo "$login ok\n";
	sleep(mt_rand(2, 10));
}

unlink($cookiefile);

?>
