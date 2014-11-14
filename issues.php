<? include("include/init.php");
check_login();
//check_isset_array($_GET, 'doelgroep', 'grp2vak_id', 'lln_id');
//check_isnonempty_array($_GET, 'doelgroep', 'grp2vak_id', 'lln_id');
check_isset_array($_GET, 'doelgroep');
check_isnonempty_array($_GET, 'doelgroep');
$doelgroep = $_GET['doelgroep'];
//$grp2vak_id = mysql_escape_safe($_GET['grp2vak_id']);
//$lln_id = mysql_escape_safe($_GET['grp2vak_id']);

$schooljaar_first = substr($schooljaar_long, 0, 4);
$schooljaar_last = substr($schooljaar_long, 5, 4);

switch ($doelgroep) {
	case 'zelf':
		$result = mysql_query_safe(<<<EOQ
SELECT notities.notitie_id, CONCAT(agenda.week, CASE agenda.dag
		WHEN 1 THEN 'ma'
		WHEN 2 THEN 'di'
		WHEN 3 THEN 'wo'
		WHEN 4 THEN 'do'
		ELSE 'vr' END, agenda.lesuur) uur,
	CONCAT(orig.text, ' ', IFNULL(GROUP_CONCAT(DISTINCT
		CONCAT('[', tag, ']') SEPARATOR ''), '')
	) text, notities.creat `datum/tijd`, GROUP_CONCAT(KB_NAAM(ppl.naam0, ppl.naam1, ppl.naam2)) naam, naam klas,
CONCAT('<a href="vvv_zelf.php?notitie_id=', notities.notitie_id, '">sluiten</a>') sluiten
FROM ppl2agenda
JOIN ppl2agenda AS anderen USING (agenda_id)
JOIN ppl ON ppl.ppl_id = anderen.ppl_id
JOIN agenda USING (agenda_id)
JOIN notities USING (notitie_id)
LEFT JOIN tags2notities AS moretags USING (notitie_id)
LEFT JOIN tags ON tags.tag_id = moretags.tag_id
JOIN notities AS orig ON orig.notitie_id = notities.parent_id
LEFT JOIN agenda AS agenda2 ON agenda2.notitie_id = notities.parent_id
LEFT JOIN grp2vak2agenda ON grp2vak2agenda.agenda_id = agenda2.agenda_id
LEFT JOIN grp2vak USING (grp2vak_id)
LEFT JOIN grp USING (grp_id)
WHERE ppl2agenda.ppl_id = {$_SESSION['ppl_id']}
AND anderen.ppl_id != {$_SESSION['ppl_id']}
AND notities.text IS NULL
AND notities.creat >= '{$schooljaar_first}0801'
AND notities.creat < '{$schooljaar_last}0801'
GROUP BY notities.notitie_id
ORDER BY IF(agenda.week < {$lesweken[0]}, 1, 0), agenda.week, agenda.dag, agenda.lesuur
EOQ
		);
		$table = sprint_table($result);
		mysql_free_result($result);
		break;
	default:
		throw new Exception('onmogelijke doelgroep', 2);
}

gen_html_header('Issues');
status(); ?>
<? echo($table); ?>
<? gen_html_footer(); ?>
