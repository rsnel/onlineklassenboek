<? include("include/init.php");
check_nologin();
gen_html_header('Aanmaken', '$("input:text:visible:first").focus();');
status();
?>
<p><form action="do_aanmaken.php" accept-charset="UTF-8" method="post">
<fieldset>
<legend>Aanmaken leerlingaccount</legend>
Hier kun je een leerlingaccount aanmaken met behulp van je TeleTOP&reg; account.
 Vul je leerlingnummer/afkorting, aanmaakcode en emailadres in. Klik
daarna op 'Verzend'. Je krijgt een email met daarin een link naar een pagina
waarop je je wachtwoord kunt instellen.

<p>Ouders gebruiken de bovenstaande link 'Ouders' om een account aan te maken.

<table>
<tr><td>Leerlingnummer/Afkorting</td>
<td><input type="text" name="userid" value="<? echo($_GET['userid']) ?>"></td></tr>
<tr><td>Emailadres</td>
<td><input type="text" name="email" value="<? echo($_GET['email']) ?>"></td></tr>
<tr><td>Aanmaakcode</td>
<td><input type="text" name="aanmaakcode" value="<? echo($_GET['aanmaakcode']) ?>"></td></tr>
</table>
<p>
<input type="submit" value="Verzend">
</fieldset>
</form>

<? gen_html_footer(); ?>
<? include("include/init.php");
check_nologin();
gen_html_header('Wachtwoord Vergeten', '$("input:text:visible:first").focus();');
status();
?>
<p><form action="do_pw_reset_request.php" method="post" accept-charset="UTF-8">
<fieldset>
<legend>Wachtwoord Vergeten</legend>
Als je je wachtwoord niet meer weet, dan kun je op deze pagina je 
gebruikersnaam intoetsen (voor een docent is dat de afkorting, voor een leerling het leerlingnummer en ouders gebruiken hun zelfbedachte inlognaam). Je krijgt dat een mailtje op het emailadres dat we 
van jou hebben. In dat mailtje zit een link naar een pagina waar je een nieuw 
wachtwoord kunt instellen.

<table>
<tr><td>Gebruikersnaam</td>
<td><input type="text" name="userid" value="<? echo($_GET['userid']) ?>"></td></tr>
</table>
<p>Toon aan dat je een mens bent door de twee onderstaande woorden in te typen.
<? require_once('include/recaptcha.php'); recaptcha_ask(); ?>
<p>
<input type="submit" value="Verzend">
</fieldset>
</form>

<? gen_html_footer(); ?>
