<? require_once('include/init.php');
require_once('include/rooster_lib.php');
check_login();
if ($_SESSION['ppl_id'] != 3490) throw new Exception(2, 'test.php not for production use');

$ch = curl_rooster_init();

$docent = "SNEL";

$ppl_id = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '%s' AND active = 0", mysql_escape_safe($docent));

if (!$ppl_id) {
	echo "docent $docent niet bekend in database";
	exit;
}

$xpath = curl_rooster_req($ch, "http://intranet.ovc.nl/rooster/infoweb/index.php?ref=3&id=$docent");

$rooster = $xpath->query("/html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");

if (!$rooster) {
	echo("<table> met rooster van $docent niet gevonden op /html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");
	exit;
}	

$week_select = $xpath->query("/html/body/table/tr[3]/td[2]/form/table/tr/td[2]/div/select/option[@selected]");

if (!$week_select) {
	echo("<option> van weekselect is niet gevonden?!?!??!");
	exit;
}

$laatste_update = $xpath->query('//td[@id="tweedebalk"]');

if (!$laatste_update) {
	echo("Laatste update niet gevonden");
	exit;
}
$date_to_parse = substr(strrchr($laatste_update->item(0)->nodeValue, ','), 2);
$timestamp = strtotime($date_to_parse)."<br>\n";

$proposed_week = $week_select->item(0)->getAttribute('value')."<br>\n";

if (!in_array((int)$proposed_week, $lesweken)) echo('week op roosterbord is geen lesweek?');

//echo "week: $proposedweek<br>\n";

//foreach ($rooster as $que) {
//	echo $que->nodeName."\n";
//}

//echo $rooster->item(0)->nodeValue."\n";

//$cells = $xpath->query("//tr[2]/td[1]/table/tr/td", $rooster->item(0));
//foreach ($cells as $cell) {
//	echo $cell->nodeName."\n";
//}

//echo $laatste_update->item(0)->nodeValue."<br>\n";

for ($i = 2; $i <= 10; $i++) {
	for ($j = 1; $j <= 5; $j++) {
		mysql_query_safe("UPDATE IGNORE rooster SET obsoleted_by_rooster_id = 0 WHERE week = '%s' ".
				"AND schooljaar = 'schooljaar' AND dag = '%s' AND lesuur = '%s' AND ppl_id = '%s'",
				mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), mysql_escape_safe($ppl_id));
		$cells = $xpath->query("//tr[$i]/td[$j]/table/tr/td/span//text()", $rooster->item(0));
		$vak_id = sprint_singular('SELECT vak_id FROM vak WHERE afkorting = \'%s\'', mysql_escape_safe($cells->item(2)->nodeValue));
		if (!$vak_id) continue;
		$lokaal_id = sprint_singular('SELECT lokaal_id FROM lokalen WHERE lokaal_naam = \'%s\'', mysql_escape_safe($cells->item(4)->nodeValue));
		if (!$lokaal_id) continue;
		$lesgroep = explode('.', $cells->item(5)->nodeValue);
		if (count($lesgroep) != 2) continue;
		// bij stamklassen hoeven we alleen te kijken naar het deel achter de punt
		$grp_id = sprint_singular("SELECT grp_id FROM grp WHERE schooljaar = '$schooljaar' AND stamklas = 1 AND grp_type_id = 2 AND naam = '%s'", mysql_escape_safe($lesgroep[1]));
		if (!$grp_id) { // wellicht hebben we te maken met een cluster
			$grp_id = sprint_singular("SELECT grp_id FROM grp WHERE schooljaar = '$schooljaar' AND stamklas = 0 AND grp_type_id = 2 AND naam = '%s'", mysql_escape_safe($lesgroep[0].$lesgroep[1]));
			if (!$grp_id) continue;
		}
		$grp2vak_id = sprint_singular("SELECT grp2vak_id FROM grp2vak WHERE grp_id = '%s' AND vak_id = '%s'", mysql_escape_safe($grp_id), mysql_escape_safe($vak_id));
		if (!$grp2vak_id) continue;
		//foreach ($cells as $cell) {
	//		echo $cell->nodeValue."\n";
	//	}

		mysql_query_safe("INSERT INTO rooster ( schooljaar, week, dag, lesuur, grp2vak_id, ppl_id, lokaal_id, timestamp ) VALUES ( '$schooljaar', '%s', '%s', '%s', '%s', '%s', '%s', FROM_UNIXTIME('%s') )", mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), mysql_escape_safe($grp2vak_id), mysql_escape_safe($ppl_id), mysql_escape_safe($lokaal_id), mysql_escape_safe($timestamp));
		//echo $i.' '.$j.' '.$cells->item(2)->nodeValue.' '.$cells->item(4)->nodeValue.' '.$cells->item(5)->nodeValue."<br>\n";
		//echo "lokaal_id=$lokaal_id<br>\n";
		//echo "grp2vak_id=$grp2vak_id<br>\n";

	}
}
gen_html_header('Rooster');


gen_html_footer() ?>
