#!/usr/bin/php5
<?
# databasefuncties en configuratie
include "../include/init.php";

$input_encoding = 'ISO-8859-1';

//echo("schooljaar $schooljaar\n");

if (!isset($argv[1])) fatal("je moet de naam van de juiste udmz meegeven");

$lines = gzfile($argv[1]);

if (!$lines) fatal("niet gelukt om {$argv[1]} in te lezen");

for ($i = 0; $i < count($lines); $i++) {
	$lines[$i] = trim($lines[$i]);
	if ($lines[$i] == '########') {
		$index[trim($lines[$i+1])] = $i + 2;
	}
}

function fatal($arg) {
	echo($arg."\n");
	exit(1);
}

function find_idx($key, $legenda, $section) {
	if (($idx = array_search($key, $legenda)) === FALSE)
		fatal("key $key niet gevonden in legenda van $section");
	return $idx;
}

function find_idxs(&$keys, $legenda_row, $section) {
	$legenda = explode("\t", $legenda_row);

	foreach ($keys as $key => &$value) {
		$value = find_idx($key, $legenda, $section);
	}
}

function htmlenc_iconv_trim($string) {
	global $input_encoding;
	return htmlenc(iconv($input_encoding, "UTF-8", trim($string)));
}

$stamz = array();
$oudennieuw = array();

foreach ($index as $section => $idx) {
	if (!preg_match('/^Leerling\./', $section)) continue;

	$keys = array(
		'ID' => NULL,
		'LASTNAME' => NULL,
		'FIRSTNAME' => NULL, 
		'BETWEENNAME' => NULL,
		'BASICCLASS' => NULL);

	find_idxs($keys, $lines[$idx++], $section);

	while (($line = $lines[$idx++]) != '########') {
		$fields = explode("\t", $line);

		// zit de leerling in #uit?, dan hoeft de leerling niet in het klassenboek

		$stamklas = htmlenc_iconv_trim($fields[$keys['BASICCLASS']]);

		if ($stamklas == '#uit') continue;

		$login = htmlenc_iconv_trim($fields[$keys['ID']]);
		$naam0 = htmlenc_iconv_trim($fields[$keys['LASTNAME']]);
		$naam1 = htmlenc_iconv_trim($fields[$keys['FIRSTNAME']]);
		$naam2 = htmlenc_iconv_trim($fields[$keys['BETWEENNAME']]);

		$ppl_id = sprint_singular(
			"SELECT ppl_id FROM ppl WHERE login = '%s' AND active = 0",
			mysql_escape_safe($login));

		if (!$ppl_id) {
			// deze leerling hebben we nog niet
			mysql_query_safe(
				"INSERT INTO ppl ( login, naam0, naam1, naam2, type ) ".
				"VALUES ( '%s', '%s', '%s', '%s', 'leerling' )",
					mysql_escape_safe($login),
					mysql_escape_safe($naam0),
					mysql_escape_safe($naam1),
					mysql_escape_safe($naam2));
			$ppl_id = mysql_insert_id();
		}

		$tmp = array("login" => $login, "groepen" => array());

		// we willen geen BHC stamklassen importeren vanuit het
		// rooster van het OVC
		if (preg_match('/BHC_(.*)/', $stamklas)) continue;

		$stamz[$fields[$keys['BASICCLASS']]] = 1;

		// hebben we de stamklas al?
		$grp_id = sprint_singular(
			"SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar'",
			mysql_escape_safe($stamklas));

		if (!$grp_id) {
			mysql_query_safe("INSERT INTO grp ( naam, schooljaar, stamklas, grp_type_id ) ".
				"VALUES ( '%s', '$schooljaar', 1, 2 )",
					mysql_escape_safe($stamklas));
			$grp_id = mysql_insert_id();
		}

		$tmp['groepen'][$grp_id] = array('naam' => $stamklas, 'new' => true);

		$oudennieuw[$ppl_id] = $tmp;
	}

}

foreach ($index as $section => $idx) {
	if (!preg_match('/^Groep\.(.*)/', $section, $matches)) continue;
	if ($matches[1] == '#uit') continue;
	if ($matches[1] == 'OVERIG') continue; // stamklassen BHC, WTF?!?!

	$keys = array('ID' => NULL, 'SET' => NULL);

	find_idxs($keys, $lines[$idx++], $section);

	while (($line = $lines[$idx++]) != '########') {
		$fields = explode("\t", $line);

		$naam = htmlenc_iconv_trim($fields[$keys['ID']]);
		if ($naam == '#allen') continue;

		if (isset($stamz[$naam])) continue; // stamklas, hebben we al

		$total_naam = $matches[1].'.'.$fields[$keys['ID']];

		// hebben we deze lesgroep al?
		$grp_id = sprint_singular(
			"SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar'",
			mysql_escape_safe($total_naam));

		if (!$grp_id) {
			mysql_query_safe("INSERT INTO grp ( naam, schooljaar, stamklas, grp_type_id ) ".
				"VALUES ( '%s', '$schooljaar', 0, 2 )",
					mysql_escape_safe($total_naam));
			$grp_id = mysql_insert_id();

		}

		$lln = explode(',', $fields[$keys['SET']]);
		if (count($lln) == 1 && $lln[0] === '') continue; // geen leerlingen

		foreach ($lln as $ll) {
			$ppl_id = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '%s' AND active IS NOT NULL", mysql_escape_safe($ll));
			if (!$ppl_id) fatal("WTF leerling $ll niet gevonden in database?!?!!?");

			$oudennieuw[$ppl_id]['groepen'][$grp_id] = array('naam' => $total_naam, 'new' => true);
		}
	}
}

foreach ($oudennieuw as $ppl_id => $value) {
	$result = mysql_query_safe("SELECT grp_id, ppl2grp_id, naam FROM ppl2grp JOIN grp USING (grp_id) WHERE ppl_id = $ppl_id AND schooljaar = '$schooljaar'");
	while (($row = mysql_fetch_assoc($result))) {
		$oudennieuw[$ppl_id]['groepen'][$row['grp_id']]['old'] = $row['ppl2grp_id'];
		$oudennieuw[$ppl_id]['groepen'][$row['grp_id']]['naam'] = $row['naam'];
	}
}

foreach ($oudennieuw as $ppl_id => $value) {
	foreach ($value['groepen'] as $grp_id => $info) {
		if (isset($info['new']) && isset($info['old'])) continue;
		if (isset($info['new'])) {
			echo("voeg {$value['login']} aan groep {$info['naam']}\n");
			mysql_query_safe("INSERT INTO ppl2grp ( ppl_id, grp_id ) VALUES ( $ppl_id, $grp_id )");
		}
		if (isset($info['old'])) {
			echo("verwijder {$value['login']} uit groep  {$info['naam']}\n");
			mysql_query_safe("DELETE FROM ppl2grp WHERE ppl2grp_id = {$info['old']}");
		}
	}
}

$idx = $index['Docent'];
$keys = array('ID' => NULL, 'Achternaam' => NULL, 'Voornaam' => NULL, 'Tussenvoegsel' => NULL);
find_idxs($keys, $lines[$idx++], 'Docent');

while (($line = $lines[$idx++]) != '########') {
	$fields = explode("\t", $line);

	$login = htmlenc_iconv_trim($fields[$keys['ID']]);
	$naam0 = htmlenc_iconv_trim($fields[$keys['Achternaam']]);
	$naam1 = htmlenc_iconv_trim($fields[$keys['Voornaam']]);
	$naam2 = htmlenc_iconv_trim($fields[$keys['Tussenvoegsel']]);
	echo("docent $login $naam1 $naam2 $naam0\n");
	if ($naam0 == '') {
		$naam1 = "<i>nieuw</i>";
		$naam0 = $login;
	}

	$ppl_id = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '%s' AND active IS NOT NULL",
		mysql_escape_safe($login));

	if (!$ppl_id) {
		mysql_query_safe("INSERT INTO ppl ( login, naam0, naam1, naam2, type ) ".
			"VALUES ( '%s', '%s', '%s', '%s', 'personeel' )",
				mysql_escape_safe($login),
				mysql_escape_safe($naam0),
				mysql_escape_safe($naam1),
				mysql_escape_safe($naam2));
	} else mysql_query_safe("UPDATE ppl SET naam0 = '%s', naam1 = '%s', naam2 = '%s' WHERE ppl_id = $ppl_id", mysql_escape_safe($naam0), mysql_escape_safe($naam1), mysql_escape_safe($naam2));
}

$idx = $index['Les'];
$keys = array('Grp' => NULL, 'Doc' => NULL, 'Vak' => NULL);
find_idxs($keys, $lines[$idx++], 'Les');

while (($line = $lines[$idx++]) != '########') {
	$fields = explode("\t", $line);

	if ($fields[$keys['Grp']] == '') continue;
	if ($fields[$keys['Doc']] == '') continue;
	if ($fields[$keys['Vak']] == '') continue;
	$lesgroepen = explode(',', $fields[$keys['Grp']]);
	$docenten= explode(',', $fields[$keys['Doc']]);
	$vakken = explode(',', $fields[$keys['Vak']]);

	if (count($vakken) == 1) {
		$vak = $vakken[0];
		$vak_id = sprint_singular("SELECT vak_id FROM vak WHERE afkorting = '%s'", mysql_escape_safe($vak));
		if (!$vak_id) {
			mysql_query_safe("INSERT INTO vak ( afkorting ) VALUES ( '%s' )", mysql_escape_safe(htmlenc($vak)));
			$vak_id = mysql_insert_id();
		}

		//echo("ok vak $vak id $vak_id\n");
		foreach ($docenten as $docent) {
			$ppl_id = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '%s' and active IS NOT NULL AND type = 'personeel'", mysql_escape_safe($docent));
			if (!$ppl_id) fatal("huh? docent $docent niet gevonden?!?!!?");
			//echo("ok docent $docent id $ppl_id\n");

			foreach ($lesgroepen as $lesgroep) {
				// stamklas?
				if (preg_match('/\.(.*)$/', $lesgroep, $matches)) {
					if (isset($stamz[$matches[1]])) $lesgroep = $matches[1];
				} // else: lesgroep heeft geen punt en is bovenbouw stamklas

				$grp_id = sprint_singular("SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar'", mysql_escape_safe($lesgroep));
				if (!$grp_id) fatal("huh? lesgroep $lesgroep niet gevonden!?!?!");

				//echo("ok grp $lesgroep id $grp_id\n");
				$grp2vak_id = sprint_singular("SELECT grp2vak_id FROM grp2vak WHERE grp_id = $grp_id AND vak_id = $vak_id");
				if (!$grp2vak_id) {
					mysql_query_safe("INSERT INTO grp2vak ( grp_id, vak_id ) VALUES ( $grp_id, $vak_id )");
					$grp2vak_id = mysql_insert_id();
				}

				//echo("ok grp2vak $grp2vak_id\n");

				$doc2grp2vak_id = sprint_singular("SELECT doc2grp2vak_id FROM doc2grp2vak WHERE ppl_id = $ppl_id AND grp2vak_id = $grp2vak_id");
				//echo("meul\n");
				if (!$doc2grp2vak_id) {
					echo("docent $docent stond nog niet op $lesgroep / $vak\n");
					mysql_query_safe("INSERT INTO doc2grp2vak ( ppl_id, grp2vak_id ) VALUES ( $ppl_id, $grp2vak_id )");
				} //else echo("docent $docent staat al op $lesgroep / $vak\n");
			}
		}
	} else {
		echo("faal\n");
	       print_r(array('lesgroepen' => $lesgroepen, 'docenten' => $docenten, 'vakken' => $vakken));
	}
}

?>
