#!/usr/bin/php5
<?
# databasefuncties en configuratie
include "../include/init.php";

$config = array(
	'ZERMELO_CATEGORY_IGNORE' =>  array('OVERIG', '#uit', 'Algemeen'),
	'ZERMELO_GROUP_IGNORE' => array('#allen'),
	'ZERMELO_ENCODING' => 'ISO-8859-1',
);

function config($key) {
	global $config;
	if (!isset($config[$key])) fatal_error("config key $key not set");
	return $config[$key];
}
	
//
// functions to read udmz file
//

function fix_charset_whitespace($string) {
        return iconv(config('ZERMELO_ENCODING'), 'UTF-8', trim($string));
}

function custom_explode($line) {
        return array_map('fix_charset_whitespace', explode("\t", trim($line)));
}

// the first element of the legenda is the uuid, we don't
// care about it's column title
function get_legenda($line) {
        return array_slice(custom_explode($line), 1);
}

function arrayarray(&$dest, $exploded, $val) {
        $next = &$dest[array_shift($exploded)];
        if ($exploded) arrayarray($next, $exploded, $val);
        else $next = $val;
}

function known_section($line, $preambule) {
        if (isset($preambule[trim($line)])) return true;
        else return false;
}

function add_record(&$curr, $legenda, $line) {
        $fields = custom_explode($line);
        $id = array_shift($fields);
        $deficit = count($legenda) - count($fields);
        if ($deficit < 0) fatal_error('deficit in udmz add_record can\'t be negative, but is?!?!');
        while ($deficit--) $fields[] = NULL;
        $curr[$id] = array_combine($legenda, $fields);
}

function read_udmz_lines($lines) {
        $out = array();
        $title = 'PREAMBULE';
        $curr = array();
        $legenda = get_legenda($lines[0]);

        for ($i = 1; $i < count($lines); $i++) {
                if (trim($lines[$i]) == '########') {
                        if (isset($curr)) {
                                arrayarray($out, explode('.', $title), $curr);
                                unset($curr);
                        }
                        if (known_section($lines[$i + 1], $out['PREAMBULE'])) {
                                $title = trim($lines[$i + 1]);
                                $curr = array();
                                $legenda = get_legenda($lines[$i+2]);
                        }
                        $i += 2;
                } else if (isset($curr)) add_record($curr, $legenda, $lines[$i]);
        }

        return $out;
}

function read_udmz_file($file) {
        if (!($lines = gzfile($file)))
                fatal_error("unable to read and decompress $file");
        return read_udmz_lines($lines);
}

function checkset($array, $name, $fields) {
        foreach ($fields as $field) if (!isset($array[$field])) {
                fatal_error("required field $field missing from $name");
        }
}

$stamz = array();
$ppl2grp = array();
$ppl = array();
$grp = array();

$udmz = read_udmz_file($argv[1]);

checkset($udmz, 'udmz file', array('Groep', 'Leerling', 'Docent', 'Les'));

foreach ($udmz['Leerling'] as $category => $list) {
	if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
	//echo("$category\n");
	foreach ($list as $id => $row) {
		checkset($row, "Leerling.$category", array('LASTNAME', 'FIRSTNAME', 'BETWEENNAME', 'BASICCLASS'));
		$stamz[$row['BASICCLASS']] = $category;

		$ppl[$id] = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '$id'");
		if ($ppl[$id]) {
			mysql_query_safe("UPDATE ppl SET naam0 = '%s', naam1 = '%s', naam2 = '%s' WHERE login = '$id'",
				mysql_escape_safe(htmlspecialchars($row['LASTNAME'], ENT_QUOTES, 'UTF-8')),
				mysql_escape_safe(htmlspecialchars($row['FIRSTNAME'], ENT_QUOTES, 'UTF-8')),
				mysql_escape_safe(htmlspecialchars($row['BETWEENNAME'], ENT_QUOTES, 'UTF-8')));
		} else {
			echo("nieuwe leerling $id {$row['FIRSTNAME']} {$row['BETWEENNAME']} {$row['LASTNAME']}\n");
			mysql_query_safe("INSERT INTO ppl (login, naam0, naam1, naam2, type ) VALUES ( '$id', '%s', '%s', '%s', 'leerling' )",
				mysql_escape_safe(htmlspecialchars($row['LASTNAME'], ENT_QUOTES, 'UTF-8')),
				mysql_escape_safe(htmlspecialchars($row['FIRSTNAME'], ENT_QUOTES, 'UTF-8')),
				mysql_escape_safe(htmlspecialchars($row['BETWEENNAME'], ENT_QUOTES, 'UTF-8')));
			$ppl[$id] = mysql_insert_id();
		}

		$grp[$row['BASICCLASS']] = sprint_singular("SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar' AND stamklas = 1 AND grp_type_id = 2",
				mysql_escape_safe(htmlspecialchars($row['BASICCLASS'], ENT_QUOTES, 'UTF-8')));

		if (!$grp[$row['BASICCLASS']]) {
			echo("nieuwe stamklas {$row['BASICCLASS']}\n");
			mysql_query_safe("INSERT INTO grp ( naam, schooljaar, stamklas, grp_type_id ) VALUES ( '%s', '$schooljaar', 1, 2 )",
				mysql_escape_safe(htmlspecialchars($row['BASICCLASS'], ENT_QUOTES, 'UTF-8')));

			$grp[$row['BASICCLASS']] = mysql_insert_id();
		}

		$ppl2grp[$ppl[$id]] = array();
		$ppl2grp[$ppl[$id]][$grp[$row['BASICCLASS']]] = array('old' => 0, 'new' => 1);
	}
}
foreach ($udmz['Docent'] as $id => $row) {
	checkset($row, 'Docent', array ('Voornaam', 'Tussenvoegsel', 'Achternaam', 'e-mail'));
	$ppl[$id] = sprint_singular("SELECT ppl_id FROM ppl WHERE login = '$id'");
	if ($ppl[$id]) {
		// todo update info
		//echo("bekende docent $id {$row['Voornaam']} {$row['Tussenvoegsel']} {$row['Achternaam']} {$row['e-mail']}\n");
	} else {
		echo("nieuwe docent $id\n");
		if ($row['Achternaam'] == '') {
			$row['Achternaam'] = $id;
			$row['Voornaam'] = 'nieuw';
		}
		mysql_query_safe("INSERT INTO ppl (login, naam0, naam1, naam2, email, type ) VALUES ( '$id', '%s', '%s', '%s', '%s', 'personeel' )",
			mysql_escape_safe(htmlspecialchars($row['Achternaam'], ENT_QUOTES, 'UTF-8')),
			mysql_escape_safe(htmlspecialchars($row['Voornaam'], ENT_QUOTES, 'UTF-8')),
			mysql_escape_safe(htmlspecialchars($row['Tussenvoegsel'], ENT_QUOTES, 'UTF-8')),
			mysql_escape_safe(htmlspecialchars($row['e-mail'], ENT_QUOTES, 'UTF-8')));
		$ppl[$id] = mysql_insert_id();
	}
}

foreach ($udmz['Groep'] as $category => $list) {
	if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
	foreach ($list as $id => $row) {
		if (in_array($id, config('ZERMELO_GROUP_IGNORE'))) continue;

		if (isset($stamz[$id])) {
			if ($stamz[$id] != $category) fatal_error("stamklas in andere categorie!?!?!");
			continue; // doe niks, want stamklassen hebben we al
		}

		checkset($row, "Groep.$category", array ('SET'));

		$grp["$category.$id"] = sprint_singular("SELECT grp_id FROM grp WHERE naam = '%s' AND schooljaar = '$schooljaar' AND stamklas = 0 AND grp_type_id = 2",
				mysql_escape_safe(htmlspecialchars("$category.$id", ENT_QUOTES, 'UTF-8')));
		if (!$grp["$category.$id"]) {
			echo("nieuwe clustergroep $category.$id\n");
			mysql_query_safe("INSERT INTO grp ( naam, schooljaar, stamklas, grp_type_id ) VALUES ( '%s', '$schooljaar', 0, 2 )",
				mysql_escape_safe(htmlspecialchars("$category.$id", ENT_QUOTES, 'UTF-8')));

			$grp["$category.$id"] = mysql_insert_id();
		}

		foreach (explode(',', $row['SET']) as $leerlingnummer) {
			if (!isset($ppl2grp[$ppl[$leerlingnummer]])) fatal_error("$leerlingnummer heeft geen stamklas");
			$ppl2grp[$ppl[$leerlingnummer]][$grp["$category.$id"]] = array('old' => 0, 'new' => 1);
		}
	}
}
//print_r($ppl2grp);

$result = mysql_query("SELECT ppl_id, grp_id FROM ppl2grp JOIN ppl USING (ppl_id) JOIN grp USING (grp_id) WHERE schooljaar = '$schooljaar'");

while ($row = mysql_fetch_assoc($result)) {
	if (!isset($ppl2grp[$row['ppl_id']]))
		$ppl2grp[$row['ppl_id']] = array();

	if (isset($ppl2grp[$row['ppl_id']][$row['grp_id']])) 
		$ppl2grp[$row['ppl_id']][$row['grp_id']]['old'] = 1;
	else $ppl2grp[$row['ppl_id']][$row['grp_id']] = array('old' => 1, 'new' => 0);
}

foreach ($ppl2grp as $ppl_id => $grps) {
	foreach ($grps as $grp_id => $data) {
		if ($data['old'] == $data['new']) continue;
		echo("ppl_id=$ppl_id grp_id=$grp_id old={$data['old']} new={$data['new']}\n");
		if ($data['old'] == 1 && $data['new'] == 0) 
			mysql_query_safe("DELETE FROM ppl2grp WHERE ppl_id = $ppl_id AND grp_id = $grp_id");
		else if ($data['old'] == 0 && $data['new'] == 1)
			mysql_query_safe("INSERT INTO ppl2grp ( ppl_id, grp_id ) VALUES ( $ppl_id, $grp_id )");
		else fatal_error("impossible!");
	}
}

//print_r($ppl);
//print_r($grp2ppl);
/*
foreach ($udmz['Docent'] as $id => $row) {
	checkset($row, 'Docent', array ('Voornaam', 'Tussenvoegsel', 
		'Achternaam', 'e-mail'));
	echo($id.' '.$row['Voornaam'].' '.$row['Tussenvoegsel'].' '.$row['Achternaam'].' '.$row['e-mail']."\n");
}
 */
?>
