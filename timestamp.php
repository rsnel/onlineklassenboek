<? require_once('include/init.php');
require_once('include/rooster_lib.php');
check_login();
if ($_SESSION['ppl_id'] != 3490) throw new Exception(2, 'test.php not for production use');

$query = <<<EOQ
SELECT new.nieuwe_mentorgroep, new.nieuwe_mentor, new.leerlingnr, new.naam, mtoud.naam, GROUP_CONCAT(docoud.login ORDER BY docoud.login) oude_mentor FROM (
	SELECT vak.vak_id, mtnew.naam nieuwe_mentorgroep, GROUP_CONCAT(docnew.login ORDER BY docnew.login) nieuwe_mentor, lln.login leerlingnr, KB_NAAM(lln.naam0, lln.naam1, lln.naam2) naam, lln.ppl_id leerling
	FROM vak
	JOIN grp2vak AS mtnew2vak ON mtnew2vak.vak_id = vak.vak_id
	JOIN grp AS mtnew ON mtnew.grp_id = mtnew2vak.grp_id
	JOIN doc2grp2vak AS mtnew2grp2vak ON mtnew2grp2vak.grp2vak_id = mtnew2vak.grp2vak_id
	JOIN ppl AS docnew ON docnew.ppl_id = mtnew2grp2vak.ppl_id
	JOIN ppl2grp AS lln2mtnew ON lln2mtnew.grp_id = mtnew.grp_id
	JOIN ppl AS lln ON lln.ppl_id = lln2mtnew.ppl_id
	WHERE afkorting = 'mt' AND mtnew.schooljaar = '1112' AND mtnew.grp_type_id = 2 AND (
 mtnew.naam = '2H5C' OR
 mtnew.naam = '2H6C' OR
 mtnew.naam = '2H7C' OR
 mtnew.naam = '3H4C' OR
 mtnew.naam = '3H5C' OR
 mtnew.naam = '3H6C' OR
 mtnew.naam = '3H7C'
)
	GROUP BY mtnew.grp_id, lln.ppl_id
	ORDER BY lln.naam0, lln.naam1, lln.naam2
) AS new
LEFT JOIN ppl2grp AS lln2mtoud ON lln2mtoud.ppl_id = new.leerling
LEFT JOIN grp AS mtoud ON mtoud.grp_id = lln2mtoud.grp_id
LEFT JOIN grp2vak AS mtoud2vak ON mtoud2vak.vak_id = new.vak_id AND mtoud2vak.grp_id = mtoud.grp_id
LEFT JOIN doc2grp2vak AS mtoud2grp2vak ON mtoud2grp2vak.grp2vak_id = mtoud2vak.grp2vak_id
LEFT JOIN ppl AS docoud ON docoud.ppl_id = mtoud2grp2vak.ppl_id
WHERE mtoud.schooljaar = '1011' AND mtoud.grp_type_id = 2
GROUP BY mtoud.grp_id, new.leerling
ORDER BY nieuwe_mentor
EOQ;

//$result = mysql_query_safe($query);
//echo sprint_table($result);

$ch = curl_rooster_init();

$docent = "SNEL";

$ppl_id = sprint_singular("SELECT login FROM ppl WHERE login = '%s' AND active = 0", $ppl_id);

if ($ppl_id) {
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

//echo $date_to_parse."<br>\n";

if (preg_match('/(\d\d) (\w\w\w) (\d\d\d\d) (\d\d):(\d\d):(\d\d)/', $date_to_parse, $matches) < 1) {
	echo "unable to parse date";
	exit;
}
$day = $matches[1];
print_r($matches);
switch ($matches[2]) {
case 'Jan':
	$month = 1;
	break;
case 'Feb':
	$month = 2;
	break;
case 'Mar':
	$month = 3;
	break;
case 'Apr':
	$month = 4;
	break;
case 'May':
	$month = 5;
	break;
case 'Jun':
	$month = 6;
	break;
case 'Jul':
	$month = 7;
	break;
case 'Aug':
	$month = 8;
	break;
case 'Sep':
	$month = 9;
	break;
case 'Oct':
	$month = 10;
	break;
case 'Nov':
	$month = 11;
	break;
case 'Dec':
	$month = 12;
	break;
}
$year = $matches[3];
$hour = $matches[4];
$minute = $matches[5];
$second = $matches[6];
echo "year $year<br>\n";
echo "month $month<br>\n";
echo "day $day<br>\n";
if (!checkdate($month, $day, $year)) {
	echo "rooster server geeft ongeldige datum";
	exit;
}
echo "hour $hour<br>\n";
echo "minute $minute<br>\n";
echo "second $second<br>\n";
$timestamp = mktime($hour, $minute, $second, $month, $day, $year);

$timestamp2 = strtotime($date_to_parse)."<br>\n";

echo "strftime ".strftime("%c", $timestamp)."<br>\n";
echo $date_to_parse.' '.$timestamp.' '.$timestamp2."<br>\n";
$proposed_week = $week_select->item(0)->getAttribute('value')."<br>\n";

if (!in_array((int)$proposed_week, $lesweken)) echo('week op roosterbord is geen lesweek?');

//echo "week: $proposedweek<br>\n";

gen_html_header('Rooster');

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
				mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), $ppl_id);
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
		echo $i.' '.$j.' '.$cells->item(2)->nodeValue.' '.$cells->item(4)->nodeValue.' '.$cells->item(5)->nodeValue."<br>\n";
		echo "lokaal_id=$lokaal_id<br>\n";
		echo "grp2vak_id=$grp2vak_id<br>\n";
		//foreach ($cells as $cell) {
	//		echo $cell->nodeValue."\n";
	//	}

		//mysql_query_safe("INSERT 

	}
}

gen_html_footer() ?>
