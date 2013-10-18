<? require_once('include/init.php');
require_once('include/rooster_lib.php');
check_login();

if (!check_week($_GET['week'])) regular_error($http_path.'/', $_GET, "ongeldige waarde van week gegeven :(");

$ppl_id = $_SESSION['ppl_id'];

$docent = sprint_singular("SELECT login FROM ppl WHERE ppl_id = '{$_SESSION['ppl_id']}' AND type = 'personeel' AND active = 0");

if (!$docent) regular_error($http_path.'/', $_GET, "kan geen afkorting vinden van ppl_id={$_SESSION['ppl_id']}");

$ch = curl_rooster_init();
$xpath = curl_rooster_req($ch, "http://intranet.ovc.nl/rooster/infoweb/index.php?ref=3&id=$docent");

$rooster = $xpath->query("/html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");

if (!$rooster)
	regular_error($http_path.'/', $_GET, "&lt;table&gt; met rooster van $docent niet gevonden op /html/body/table/tr[3]/td[2]/div/table/tr[1]/td[1]/table");

$week_select = $xpath->query("/html/body/table/tr[3]/td[2]/form/table/tr/td[2]/div/select/option[@selected]");

if (!$week_select) 
	regular_error($http_path.'/', $_GET, "&lt;option&gt; van weekselect is niet gevonden?!?!??!");

$proposed_week = $week_select->item(0)->getAttribute('value');

$laatste_update = $xpath->query('//td[@id="tweedebalk"]');

if (!$laatste_update)
	regular_error($http_path.'/', $_GET, "laatste update niet gevonden");

$date_to_parse = substr(strrchr($laatste_update->item(0)->nodeValue, ','), 2);
$timestamp = strtotime($date_to_parse);

if (!in_array((int)$proposed_week, $lesweken))
	regular_error($http_path.'/', $_GET, "huidige week op het roosterbord is geen lesweek");

$current_rooster_timestamp = sprint_singular("SELECT UNIX_TIMESTAMP(MAX(timestamp)) FROM rooster WHERE week = '%s' AND ppl_id = '%s'", mysql_escape_safe($proposed_week), mysql_escape_safe($ppl_id));

if ((int)$current_rooster_timestamp == $timestamp) {
	// rooster is up to date
	$_SESSION['successmsg'] = 'Rooster is al up to date, versie '.strftime("%c", $current_rooster_timestamp);
	header('Location: '.$http_path.'/'.sprint_url_parms($_GET));
	exit;
}

//regular_error($http_path.'/', $_GET, "$current_rooster_timestamp $timestamp spanning");

for ($i = 2; $i <= 10; $i++) {
	for ($j = 1; $j <= 5; $j++) {
		mysql_query_safe("UPDATE IGNORE rooster SET obsoleted_by_rooster_id = 0 WHERE week = '%s' ".
				"AND schooljaar = '$schooljaar' AND dag = '%s' AND lesuur = '%s' AND ppl_id = '%s'",
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

		mysql_query_safe("INSERT INTO rooster ( schooljaar, week, dag, lesuur, grp2vak_id, ppl_id, lokaal_id, timestamp ) VALUES ( '$schooljaar', '%s', '%s', '%s', '%s', '%s', '%s', FROM_UNIXTIME('%s') )", mysql_escape_safe($proposed_week), mysql_escape_safe($j), mysql_escape_safe($i - 1), mysql_escape_safe($grp2vak_id), mysql_escape_safe($ppl_id), mysql_escape_safe($lokaal_id), mysql_escape_safe($timestamp));

	}
}

$_SESSION['successmsg'] = 'Rooster geupdated, huidige versie '.strftime("%c", $timestamp);
header('Location: '.$http_path.'/'.sprint_url_parms($_GET));


