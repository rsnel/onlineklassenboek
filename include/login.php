<? 
// deze include laat een loginscherm zien, alleen aanroepen vanuit index.php aub,
// gesplitst om index.php overzichtelijk te houden
if (isset($_GET['lock_by']) && $_GET['lock_by'] != '') {
	if ($_SESSION['orig_login'] != $_GET['lock_by'])
		throw new Exception('lock_by parameter onjuist', 2);
	gen_html_header("Unlock", '$("#password").focus();');
	status(); ?>
<form id="unlock" action="do_login.php" method="post">
<fieldset style="margin: 0 auto;">
<legend>Unlock</legend>
<p>Typ hier je wachtwoord om verder te gaan waar je gebleven was.
<P><table>
<input type="hidden" name="login" value="<? echo($_GET['lock_by']) ?>">
<input type="hidden" name="lock_by" value="<? echo($_GET['lock_by']) ?>">
<tr><td>Wachtwoord</td><td><input id="password" type="password" name="password"></td></tr>
</table>
<input type="submit" value="Unlock">
</fieldset>
<?
	gen_html_footer();
	exit;
}
session_regenerate_id();
session_destroy();
gen_html_header("Inloggen", <<<EOT
$(document).scrollTop(0);
//var off = $("#placeholder").offset();
//$("#loginstuff").offset(off);
//$("#draggable").offset(off);
//$("#draggable").show();
//$("#draggable").draggable();
//$("#draggable").ready(function () { 
//	$("#loginstuff").show("slow");
//	$("input[value=\'\']:visible:first").focus();
$('#login').focus();
//});
EOT
, '');
status(); ?>
<!--<p>
<center><blink><b>Nieuw:</b></blink> Stap de <i>Brave New World</i> van TeleTOP&reg; binnen, <a href="http://ovc1.teletop.nl/">==KLIK HIER!!!==</a></center>
<center><a href="http://ovc1.teletop.nl"><img width="468" height="60" src="images/teletop_banner.png"></a></center>
<p>
<center>
<div style="width: 480px">
<center>
De offici&euml;le pilot van het online klassenboek is be&euml;indigd.
</center>
</div>-->
<p>
<!--<div id="placeholder" style="width: 720px; height: 320px; z-index: -100"></div>-->
<!--<div style="display: none; position: absolute; z-index: 100" id="draggable"><img height="320" width="720" src="images/barbrady.jpg"></div>-->
<!--<div style="width: 720px; height: 320px; display: none; position: absolute; z-index: 50" id="loginstuff">-->
<p>

<div style="text-align: left" id="loginstuff">
<form action="do_login.php" method="post">
<fieldset style="margin: 0 auto;">
<legend>Login</legend>
Om deze website te kunnen gebruiken moet je
<a href="http://nl.wikipedia.org/wiki/Cookies">cookies</a> en
<a href="http://nl.wikipedia.org/wiki/Javascript">javascript</a> aan hebben staan.
Gebruikers van het onlineklassenboek loggen in met hun gebruikersnaam en wachtwoord van school.
<!-- Medewerkers van school loggen in met hun schoolaccount.-->
<!--Leerlingen loggen in met hun leerlingnummer en medewerkers van school doen dat met
hun afkorting. Ouders loggen in met hun zelfgekozen code.-->
<P><table>
<tr><td>Gebruikersnaam</td><td><input id="login" type="text" name="login" value="<? echo(isset($_GET['login'])?$_GET['login']:'') ?>"></td></tr>
<tr><td>Wachtwoord</td><td><input type="password" name="password"></td></tr>
</table>
<input type="submit" value="Login">
<!--<p>Heb je nog geen account en wel een Aanmaakcode? Klik dan bovenaan
de pagina op 'Aanmaken'.-->
</fieldset>
</form>
</div>
</center>
<p>
<div>
<form action="nologin.php" method="get">
<fieldset style="margin: 0 auto;">
<legend>Toegang voor leerlingen en ouders</legend>
Leerlingen en ouders kunnen het klassenboek eenvoudig inkijken door de klas of het leerlingnummer
in te voeren. Persoonlijke notities, die docenten voor jou hebben gemaakt, kun je natuurlijk alleen zien als je inlogt. 
<p>
Klas/leerlingnummer <input type="text" name="q"><br>
<input type="submit" value="Zoeken">
</fieldset>
</form>
</div>
<p>
<fieldset style="margin: 0 auto;">
<legend>Actueel</legend>
<ul>
<li>[2014-03-01] Het klassenboek draait nu op de server van school. Problemen? Het aanspreekpunt blijft snelr@ovc.nl</li>
<li>[2013-09-30] Vanaf vandaag is het klassenboek officieel in gebruik. Vragen/opmerkingen en commentaar kan gestuurd worden naar de beheerder: snelr@ovc.nl</li>
</ul>
</fieldset>
<!--<div id="result">Javascript experiment</div>-->
<? gen_html_footer(); exit; // exit is required (this is included from index.php) ?>
