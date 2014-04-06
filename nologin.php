<? require_once("include/init.php");

// check if url variables are set, otherwise set reasonable defaults and reload
$week_options = gen_week_select(isset($_GET['week'])?$_GET['week']:NULL, 1, $week);

$http_get['week'] = "$week";
$http_get['q'] = $_GET['q'];

mysql_query_safe("SET SESSION group_concat_max_len = 65536");

// wat zoeken wij: leerling of lesgroep ?
// is het een leerling?
$ppl_id = sprint_singular(
	"SELECT ppl_id FROM ppl WHERE login = '%s' AND type = 'leerling'",
	mysql_escape_safe($_GET['q']));
$target = 'leerling';

if (!$ppl_id) {
	// nee, 't is geen leerling

	// is het een lesgroep?
	$grp_id = sprint_singular(
		"SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar'",
		mysql_escape_safe($_GET['q']));

	if (!$grp_id) {
		if (preg_match('/(.*)\.(.*)/', $_GET['q'], $matches)) {
			$grp_id = sprint_singular(
				"SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar'",
				mysql_escape_safe($matches[2]));
			$_GET['q'] = $matches[2];
		} else {
			regular_error($http_path.'/', NULL, 'Zoekterm '.htmlentities($_GET['q']).' niet gevonden. Klopt niet niet? Meld het aan de beheerder!');
		}
	} else {
     // log in database
	mysql_query_safe("INSERT INTO nologin ( type, id, week, phpsessid ) VALUES ( 'grp', $grp_id, $week, '%s' )", session_id());
}
	$target = 'lesgroep';
} else {
     // log in database
	mysql_query_safe("INSERT INTO nologin ( type, id, week, phpsessid ) VALUES ( 'ppl', $ppl_id, $week, '%s' )", session_id());
}

if ($target == 'leerling') {
	$inner_query = <<<EOQ
SELECT dag, lesuur,  CONCAT(IF(vak.afkorting IS NULL, grp.naam, vak.afkorting), ': ') target, text, notities.notitie_id, NULL action_name, NULL action_id, 1 dont, tags2notities.tag_id, 1 grp, 0 edit
FROM agenda
JOIN notities USING (notitie_id)
LEFT JOIN tags2notities USING (notitie_id)
JOIN grp2vak2agenda USING (agenda_id)
JOIN grp2vak USING (grp2vak_id)
JOIN grp USING (grp_id)
JOIN ppl2grp USING (grp_id)
LEFT JOIN vak USING (vak_id)
WHERE week = '$week' AND agenda.schooljaar = '$schooljaar'
AND ppl_id = $ppl_id
GROUP BY notitie_id
EOQ;
} else {

	$inner_query =<<<EOQ
SELECT dag, lesuur, CONCAT(IF(vak.afkorting IS NULL, grp.naam, vak.afkorting), ': ') target, text, notitie_id, NULL action_name, NULL action_id, 1 dont, tags2notities.tag_id, 1 grp, IF(doc2grp2vak.ppl_id IS NOT NULL, 1, 0)  edit
FROM agenda
JOIN notities USING (notitie_id)
JOIN grp2vak2agenda USING (agenda_id)
JOIN grp2vak USING (grp2vak_id)
LEFT JOIN tags2notities USING (notitie_id)
JOIN grp USING (grp_id)
LEFT JOIN vak USING (vak_id)
LEFT JOIN doc2grp2vak ON grp2vak.grp2vak_id = doc2grp2vak.grp2vak_id AND doc2grp2vak.ppl_id = '{$_SESSION['ppl_id']}'
WHERE week = '$week' AND agenda.schooljaar = '$schooljaar' AND grp_id = $grp_id
UNION
SELECT dag, lesuur, CONCAT(KB_LGRP(grp.naam, vak.afkorting), ': ') target, text, notities.notitie_id, NULL action_name, NULL action_id, 1 dont, tags2notities.tag_id, 1 grp, 0 edit
FROM agenda
JOIN notities USING (notitie_id)
LEFT JOIN tags2notities USING (notitie_id)
JOIN grp2vak2agenda USING (agenda_id)
JOIN grp2vak USING (grp2vak_id)
JOIN grp USING (grp_id)
LEFT JOIN vak USING (vak_id)
WHERE week = '$week' AND agenda.schooljaar = '$schooljaar'
AND grp_id = ANY (
	SELECT DISTINCT grp2.grp_id
	FROM grp
	JOIN ppl2grp USING (grp_id)
	JOIN ppl2grp AS ppl2equiv ON ppl2equiv.ppl_id = ppl2grp.ppl_id
	JOIN grp AS grp2 ON ppl2equiv.grp_id = grp2.grp_id AND grp.schooljaar = grp2.schooljaar
	WHERE grp.grp_id != grp2.grp_id AND grp.grp_id = $grp_id
	AND grp2.grp_type_id = ( SELECT grp_type_id FROM grp_types WHERE grp_type_naam = 'lesgroep' )
)
GROUP BY notitie_id
EOQ;
}

$testresult = mysql_query_safe(<<<EOT
SELECT dag, lesuur, GROUP_CONCAT(text ORDER BY grp DESC, notitie_id SEPARATOR '\n') text
FROM (
        SELECT dag, lesuur, CONCAT(
                '<div class="',
                IF(grp, 'grp', 'pers'), '">',
                IFNULL(target, ''),
                IFNULL(bla3.text, ''),
                IFNULL(GROUP_CONCAT(tags SEPARATOR ''), ''),
                '</div>') text, notitie_id, 1 edit, grp
        FROM (
                SELECT dag, lesuur, target, text, edit, IFNULL(CONCAT(
                        '\n<span class="tag">[',
                        tag,
                        ']</span>'), '') tags, notitie_id, grp
                FROM ( $inner_query ) bla2
                LEFT JOIN tags USING (tag_id)
                GROUP BY notitie_id, tag_id
        ) AS bla3
        GROUP BY notitie_id
) AS bla4
GROUP BY lesuur, dag
EOT
);

$year_lasthalf = substr($schooljaar_long, 5);
$year_firsthalf = substr($schooljaar_long, 0, 4);

function print_dag($dag) {
	switch ($dag) {
		case 0: return 'zo';
		case 1: return 'ma';
		case 2: return 'di';
		case 3: return 'wo';
		case 4: return 'do';
		case 5: return 'vr';
		case 6: return 'za';
        }
}


function print_rev($time, $rev = 0) {
        return 'r'.$rev.' '.date('W', $time).print_dag(date('w', $time)).date('G:i', $time);
}

function print_diff($row) {
	$bla = array();
	if ($row['f_dag'] != $row['s_dag'] || $row['f_uur'] != $row['s_uur']) 
		$bla[] = print_dag($row['s_dag']).$row['s_uur'];
	if ($row['f_lesgroepen'] != $row['s_lesgroepen'])
		$bla[] = $row['s_lesgroepen'];
	if ($row['f_vakken'] != $row['s_vakken']) 
		$bla[] = $row['s_vakken'];
	if ($row['f_docenten'] != $row['s_docenten'])
		$bla[] = $row['s_docenten'];
	if ($row['f_lokalen'] != $row['s_lokalen'])
		$bla[] = $row['s_lokalen'];

	return implode('/', $bla);
}

$wijz_id = sprint_singular("SELECT MAX(rooster_id) FROM roostertest.weken2roosters JOIN roostertest.weken USING (week_id) WHERE week = $week");
if ($wijz_id) {
	$basis_id = sprint_singular("SELECT MAX(rooster_id) FROM roostertest.weken2roosters JOIN roostertest.weken USING (week_id) WHERE wijz_id = 0 AND week = $week");
} else {
	$wijz_id = $basis_id = sprint_singular("SELECT MAX(rooster_id) FROM roostertest.weken2roosters WHERE week_id < ( SELECT week_id FROM roostertest.weken WHERE week = $week ) AND wijz_id = 0");
}

if (true || !$basis_id) {
	$roosterstatus = 'GEEN';
	goto out; // geen rooster
}

$result4 = mysql_query_safe("SELECT basis_id, wijz_id, timestamp FROM roostertest.weken2roosters JOIN roostertest.weken USING (week_id) WHERE rooster_id = $wijz_id");
$test = mysql_fetch_row($result4);
$roosterstatus = print_rev($test[2], $test[0].','.$test[1]);

if ($basis_id == $wijz_id) $wijz_id = 0;

function rquery_inner($where, $id1, $id2, $wijz) {
	return <<<EOQ
SELECT f.zermelo_id AS f_zermelo_id, f.dag AS f_dag, f.uur AS f_uur, f.vakken AS f_vakken,
	f.docenten AS f_docenten, f.lokalen AS f_lokalen, f.lesgroepen AS f_lesgroepen, f2g.grp_naam AS f_grp_naam, f2g.grp2vak_id AS f_grp2vak_id, f2g.grp_id AS f_grp_id,
	s.zermelo_id AS s_zermelo_id, s.dag AS s_dag, s.uur AS s_uur, s.vakken AS s_vakken,
	s.docenten AS s_docenten, s.lokalen AS s_lokalen, s.lesgroepen AS s_lesgroepen, s2g.grp_naam AS s_grp_naam, s2g.grp2vak_id AS s_grp2vak_id, s2g.grp_id AS s_grp_id, e2s.entity_id AS vis, $wijz AS wijz
FROM roostertest.entities2lessen AS e2f
JOIN roostertest.rooster2lessen AS r2f ON r2f.les_id = e2f.les_id AND r2f.rooster_id = $id1
JOIN roostertest.lessen AS f ON f.les_id = e2f.les_id
LEFT JOIN roostertest.lessen2grp2vak AS f2g ON f2g.les_id = r2f.les_id
LEFT JOIN (
	SELECT s.zermelo_id, s.dag, s.uur, s.vakken, s.docenten, s.lokalen, s.lesgroepen, s.les_id
	FROM roostertest.lessen AS s
	JOIN roostertest.rooster2lessen AS r2s ON r2s.les_id = s.les_id AND r2s.rooster_id = $id2
) AS s ON s.zermelo_id = f.zermelo_id
LEFT JOIN roostertest.entities2lessen AS e2s ON e2s.les_id = s.les_id AND e2s.entity_id = e2f.entity_id
LEFT JOIN roostertest.lessen2grp2vak AS s2g ON s2g.les_id = s.les_id
WHERE $where
EOQ;
}

function rquery($where, $id1, $id2) {
	return rquery_inner($where, $id1, $id2, 1).' UNION ALL '.rquery_inner($where, $id2, $id1, 0);
}

function rquery_new($entity_ids, $id1, $id2) {
	 return mysql_query_safe('SELECT * FROM ( '.rquery("e2f.entity_id IN ( $entity_ids ) ", $id1, $id2).' ) AS r ORDER BY f_uur, f_dag, wijz DESC, s_zermelo_id');
}

$rostertype = 4;
$entity_ids = <<<EOQ
SELECT DISTINCT entities.entity_id
FROM grp2vak
JOIN ppl2grp USING (grp_id)
JOIN ppl2grp AS grp2ppl USING (ppl_id)
JOIN roostertest.ovckb2entities ON grp2ppl.grp_id = ovckb2entities.ovckb_id
JOIN roostertest.entities ON entities.entity_id = ovckb2entities.entity_id AND entity_type = 4
WHERE grp2vak.grp_id = '$grp_id'
EOQ;
$result4 = rquery_new($entity_ids, $basis_id, $wijz_id);

out:

gen_html_header('Agenda', NULL, $_GET['q']); 
status();

//if ($_SESSION['ppl_id'] == 3490) echo(sprint_table($result4));

?>
	<form name="select" action="nologin.php" method="GET" accept-charset="UTF-8">
	<input type="hidden" name="q" value="<? echo($_GET['q']); ?>">
<p>week: <? prevweek($week, 'nologin.php'); echo($week_options); nextweek($week, 'nologin.php'); ?>
<? echo(get_rooster_link()); ?>
<p style="text-align: center">
<? if ($week < 30) {
	$year = substr($schooljaar_long, 5);
} else {
	$year = substr($schooljaar_long, 0, 4);
}
$day_in_week = strtotime(sprintf("$year-01-04 + %d weeks", $week - 1));
$thismonday = $day_in_week - ((date('w', $day_in_week) + 6)%7)*24*60*60;
?>
<table style="table-layout: auto" border="1" width="100%">
<colgroup width="2.5%"></colgroup>
<colgroup width="19.5%" span="5"></colgroup>
<tr>
<th>
<th>maandag <?   echo date("j-n", $thismonday)          ?>
<th>dinsdag <?   echo date("j-n", $thismonday + 86400)  ?>
<th>woensdag <?  echo date("j-n", $thismonday + 172800) ?>
<th>donderdag <? echo date("j-n", $thismonday + 259200) ?>
<th>vrijdag <?   echo date("j-n", $thismonday + 345600) ?>

<? $row = mysql_fetch_row($testresult);
if (isset($result4) && $result4) {
	$rosterrow = mysql_fetch_array($result4);
} else $rosterrow = NULL;
for ($i = 1; $i <= 9; $i++) { ?>
<tr align="left" valign="top"><td><? echo '<span title="'.$lestijden[$i].'">'.$i.'</span>' ?></td>
<?	for ($j = 1; $j <= 5; $j++) {
		echo('<td>');
		if (is_array($row) && $row[0] == $j && $row[1] == $i) {
			echo($row[2]);
			$row = mysql_fetch_row($testresult);
		} 
		echo('&nbsp;'); // IE should display cells also
		$dayanduur = $j.'&lesuur='.$i;
		while (is_array($rosterrow) && $rosterrow['f_dag'] == $j && $rosterrow['f_uur'] == $i) {
			$extra = '';
			$text = '';
			if ($rosterrow['s_zermelo_id'] && !$rosterrow['s_dag'] && $rosterrow['wijz']) $extra = ' uitval';
			else if (!$rosterrow['s_zermelo_id'] && !$rosterrow['wijz']) $extra = ' extra';
			else if ($rosterrow['f_dag'] == $rosterrow['s_dag'] && $rosterrow['f_uur'] == $rosterrow['s_uur'] && $rosterrow['vis']) {
				// les is niet in tijd verplaatst, maar wel gewijzigd, beide zijn zichtbaar
				if ($rosterrow['wijz']) { // dit is de oude les, skip
					$rosterrow = mysql_fetch_array($result4);
					continue;
				} else if ( $rosterrow['f_vakken'] != $rosterrow['s_vakken'] ||
					$rosterrow['f_docenten'] != $rosterrow['s_docenten'] ||
					$rosterrow['f_lokalen'] != $rosterrow['s_lokalen'] ||
					$rosterrow['f_lesgroepen'] != $rosterrow['s_lesgroepen']) {
						$extra = ' gewijzigd';
						$text = ' <- '.print_diff($rosterrow);
				}
			} else if ($rosterrow['wijz'] && $rosterrow['s_zermelo_id']) {
				$text = ' -> '.print_diff($rosterrow);
				$extra = ' verplaatstnaar';
			} else if (!$rosterrow['wijz'] && $rosterrow['s_zermelo_id']) {
				$text = ' <- '.print_diff($rosterrow);
				$extra = ' verplaatstvan';
			}
			$info = array();
			if ($rostertype != 1) {
				$grp_naam = NULL;
				if ($rosterrow['f_grp_naam']) {
					if ($grp_id != $rosterrow['f_grp_id']) 
						$grp_naam = $rosterrow['f_grp_naam'];
						//$info[] = $rosterrow['f_grp_naam'].'('.$rosterrow['f_grp_id'].')';
				} else if ($rosterrow['f_lesgroepen']) $grp_naam = $rosterrow['f_lesgroepen'];
				if ($grp_naam) $info[] = $grp_naam;
				// check of het vak al in de naam van de lesgroep zit
				if (!$grp_naam || !preg_match("/{$rosterrow['f_vakken']}[0-9]?/i", $grp_naam)) $info[] = $rosterrow['f_vakken'];
			} else if ($rosterrow['f_vakken']) $info[] = $rosterrow['f_vakken'];
			if ($rostertype != 2) if ($rosterrow['f_docenten']) $info[] = $rosterrow['f_docenten'];
			if ($rosterrow['f_lokalen']) $info[] = $rosterrow['f_lokalen'];
			echo(' <span class="roster'.$extra.'">'.implode('/', $info).$text.'</span>');
			//print_r($rosterrow);
			$rosterrow = mysql_fetch_array($result4);
		}
	}
}
?>
</table>
</p>
<p> 
 Roosterupdate: <? echo $roosterstatus ?>.
<? gen_html_footer(); ?>
