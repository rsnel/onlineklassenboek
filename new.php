<? include("include/init.php");
check_login();
if ($_SESSION['type'] == 'ouder') throw new Exception('ouders kunnen geen groepsnotities maken');

$reload = 0;

$week_options = gen_week_select($_GET['week'], 0, $week);
$dag_options = gen_dag_select($_GET['dag'], 0, $dag, 0, 0);
$lesuur_options = gen_lesuur_select($_GET['lesuur'], 0, $lesuur, 0);

$grp2vak_options =
	sprint_grp2vak_select($_GET['grp2vak_id'], 0, $grp2vak_id, 0);

if (!$grp2vak_options) throw new Exception('ingelogd persoon heeft geen lesgroepen en kan dus ook geen groepsnotities maken', 2);

if ($reload) {
	header("Location: new.php?index=$week&dag=$dag&".
		"lesuur=$lesuur&doelgroep=lesgroep&grp2vak_id=$grp2vak_id");
	exit;
}

$table = get_list_of_files();

$tags = '';
$tags .= 'voor cijfer: ';
$tags .= sprint_tag_checkbox('tags[]', 'et');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'so');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'mo');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'vht');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'vt');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'st');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'se');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'inleveren').' <!--periode: <select name="per"><option default value=""></option><option value="per1">1</option><option value="per2">2</option><option value="per3">3</option></select>--><br>';
$tags .= 'huiswerk: ';
$tags .= sprint_tag_checkbox('tags[]', 'maken');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'vertalen');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'nakijken');
$tags .= ' '.sprint_tag_checkbox('tags[]', 'leren').'<br>';
$tags .= 'planning: ';
$tags .= sprint_tag_checkbox('tags[]', 'in de les').' '.sprint_tag_checkbox('tags[]', 'dt').'<br>';

$result = mysql_query_safe("SELECT vaksite, vaksite_id FROM grp2vak2vaksite JOIN vaksites USING (vaksite_id) WHERE grp2vak_id = '$grp2vak_id'");
if (mysql_numrows($result)) {
	$vaksite = mysql_result($result, 0, 0);
	//$rownr = sprint_singular("SELECT cur FROM ppl2vaksiteprefs WHERE ppl_id = '{$_SESSION['ppl_id']}' AND vaksite_id = '%s'",
	//	mysql_escape_safe(mysql_result($result, 0, 1)));
}

gen_html_header("Nieuwe Notitie", '$("textarea:visible:first").focus();');
?>
<form name="notitie" action="do_new.php" method="POST" accept-charset="UTF-8">
<p>week: <? echo($week_options) ?> dag: <? echo($dag_options) ?>
lesuur: <? echo($lesuur_options) ?>
lesgroep/vak: <? echo($grp2vak_options) ?>
<br><textarea rows="3" cols="40" name="text">
<? echo(isset($_GET['text'])?$_GET['text']:'') ?>
</textarea><br>
<? echo($tags) ?><br>
<? if (isset($_SESSION['teletop_username']) && $_SESSION['teletop_username'] && $_SESSION['teletop_password'] && $vaksite) { ?>
<input type="checkbox" checked name="teletop" value="yes">Notitie opnemen in kolom 'Huiswerk' van TeleTOP&reg;<?
if ($rownr) { ?> met als regelnummer <? echo($rownr) ?>.<? }
?><br><br>
<? } ?>
<input type="submit" value="Opslaan">
<input type="hidden" name="doelgroep" value="lesgroep">
<input type="hidden" name="lln" value="<? echo($_GET['lln']) ?>">
</form>
<h3>Toetslegenda</h3>
<p>In het toetsprotocol voor de onderbouw, zijn de volgende vormen van toetsing vastgesteld:
<dl>
<dt>Eindtoets [et]</dt>
<dd>- hier wordt een afgerond deel van het curriculum getoetst, (<b>max &eacute;&eacute;n per dag en vier per week</b>)</dd>
<dt>Diagnostische toets [dt]</dt>
<dd>- door middel van een D-toets kunnen zowel leerling als docent inschatten in welke mate de leerling de stof beheerst</dd>
<dt>Vaardigheidstoets [vht]</dt>
<dd>- lezen/luisteren/rekenen, deze toetsen vereisen geen voorbereidend leerwerk</dd>
<dt>Schriftelijke overhoring [so]</dt>
<dd>- een beperkt deel van de leerstof wordt schriftelijk overhoord</dd>
<dt>Mondelinge overhoring [mo]</dt>
<dd>- een beperkt deel van de leerstof wordt mondeling overhoord</dd>
</dl> 
<p>In de bovenbouw worden geen eindtoetsen maar VT's gegeven, tag [vt].
<? //echo($table) ?>
<? mysql_close(); gen_html_footer(); ?>
