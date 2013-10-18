<? require("include/init.php");
check_nologin();
check_isset_array($_POST, 'login', 'password');
check_required_POST($http_path.'/', 'login', 'password');

function authserver($username, $password) {
	if (!($ch = curl_init('https://intranet.ovc.nl/auth/')))
		fatal_error('error initializing cURL');

	if (preg_match('/^[a-z]+$/', $username)) {
		if (!curl_setopt($ch, CURLOPT_USERPWD, 'OVC\\'.$username.':'.$password))
			fatal_error(curl_error($ch));
	} else if (!curl_setopt($ch, CURLOPT_USERPWD, 'LEERLING\\'.$username.':'.$password))
                        fatal_error(curl_error($ch));

	if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, true))
		fatal_error(curl_error($ch));

	if (!curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true))
		fatal_error(curl_error($ch));

	if (!curl_setopt($ch, CURLOPT_CAINFO, 'COMODOHigh-AssuranceSecureServerCA.crt')) 
		fatal_error(curl_error($ch));

	if (curl_exec($ch) === false)
		fatal_error(curl_error($ch));

	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($status == 200) return true;
	else return false;
}


$result = mysql_query_safe("SELECT KB_NAAM(naam0, naam1, naam2) naam, ".
	"type, ppl_id, login, timeout, teletop_username, teletop_password, ".
	"GROUP_CONCAT(caps.name) caps, toon_rooster ".
	"FROM caps ".
	"LEFT JOIN ppl2caps USING (cap_id) ".
	"RIGHT JOIN ppl USING (ppl_id) ".
	"LEFT JOIN ppl2teletop USING (ppl_id) ".
	"WHERE login = '%s' ".
	"AND active IS NOT NULL ".
	"AND password=PASSWORD('%s') GROUP BY ppl_id;",
	mysql_escape_safe(htmlspecialchars($_POST['login']), ENT_QUOTES, 'UTF-8'),
	mysql_escape_safe($_POST['password']));

if (!($row = mysql_fetch_assoc($result))) {
	if (!authserver(htmlspecialchars($_POST['login']), $_POST['password'])) {
		if (isset($_POST['lock_by'])) regular_error($http_path.'/', $_POST, 'Wachtwoord onjuist. '.
			'Wil je als iemand anders inloggen? Klik dan op '."'Onlineklassenboek'.");
			else regular_error($http_path.'/', $_POST, 'Gebruikersnaam <code>'.
			"${_POST['login']}</code> of wachtwoord onjuist. Gebruik je gebruikersnaam en wachtwoord van school.");
	} else {
		// authenticatieserver van school vindt ons lief
		// ken ik u?
		if (preg_match('/^[a-z]+$/', $_POST['login'])) {

		
		// leraar
		$result = mysql_query_safe("SELECT KB_NAAM(naam0, naam1, naam2) naam, ".
			"type, ppl_id, login, timeout, teletop_username, teletop_password, ".
			"GROUP_CONCAT(caps.name) caps, toon_rooster ".
			"FROM caps ".
			"LEFT JOIN ppl2caps USING (cap_id) ".
			"RIGHT JOIN ppl USING (ppl_id) ".
			"LEFT JOIN ppl2teletop USING (ppl_id) ".
			"JOIN ppl2altlogin USING (ppl_id) ".
			"WHERE altlogin = '%s' ".
			"AND active IS NOT NULL ".
			"GROUP BY ppl_id;",
			mysql_escape_safe(htmlspecialchars($_POST['login']), ENT_QUOTES, 'UTF-8'));

		} else {

		$result = mysql_query_safe("SELECT KB_NAAM(naam0, naam1, naam2) naam, ".
			"type, ppl_id, login, timeout, teletop_username, teletop_password, ".
			"GROUP_CONCAT(caps.name) caps, toon_rooster ".
			"FROM caps ".
			"LEFT JOIN ppl2caps USING (cap_id) ".
			"RIGHT JOIN ppl USING (ppl_id) ".
			"LEFT JOIN ppl2teletop USING (ppl_id) ".
			"WHERE login = '%s' ".
			"AND active IS NOT NULL ".
			"GROUP BY ppl_id;",
			mysql_escape_safe(htmlspecialchars($_POST['login']), ENT_QUOTES, 'UTF-8'));
		
		}

		if (!($row = mysql_fetch_assoc($result)))
			regular_error($http_path.'/', $_POST, 'Gebruiker onbekend, vraag de beheerder om je loginnaam aan je afkorting te koppelen');
		$_POST['login'] = $row['login']; // nodig voor locked_by
		
		// we updaten het wachtwoord van de gebruiker
		mysql_query_safe("UPDATE ppl SET password=PASSWORD('%s'), pw_reset_count = pw_reset_count + 1 WHERE login = '%s' AND active IS NOT NULL" , mysql_escape_safe($_POST['password']), mysql_escape_safe($row['login']));

		// we stellen het emailadres van een personeelslid in, als er geen 
		// emailadres is ingevuld
		if ($row['type'] == 'personeel') {
			mysql_query_safe("UPDATE ppl SET email = CONCAT((SELECT altlogin FROM ppl2altlogin WHERE ppl_id = {$row['ppl_id']}), '@ovc.nl') WHERE ppl_id = {$row['ppl_id']} AND email IS NULL");
		}
		//mysql_query_safe("UPDATE ppl SET email = '{$_POST['login']}@ovc.nl' WHERE active IS NOT NULL AND login = '{$row['login']}' AND email IS NULL AND type = 'personeel'");
	}
}

if (isset($_POST['lock_by']) && $_SESSION['ppl_id']) {
	if ($_POST['login'] != $_POST['lock_by']) throw new Exception('login en lock_by niet gelijk', 2);
	$_SESSION['last_load_time'] = $load_time;
	if (!isset($_SESSION['old_location'])) throw new Exception('old_location not set in session', 2);

	if ($_SESSION['old_post']) {
		if ($_SESSION['old_get']) {
			unset($_SESSION['old_get']);
			unset($_SESSION['old_post']);
			$old = $_SESSION['old_location'];
			unset($_SESSION['old_location']);
			regular_error($http_path.'/', (array)NULL,
				'Het resumen van '.$old.' kan niet; '.
				'er is zowel POST als GET data excuses voor de eventuele valse hoop');
		}
		unset($_SESSION['old_get']);
		unset($_SESSION['old_post']);
		$old = $_SESSION['old_location'];
		unset($_SESSION['old_location']);
		regular_error($http_path.'/', (array)NULL,
			'Het resumen van '.$old.' is niet '.
			'geimplementeerd, met excuses voor de eventuele valse hoop');
	} 

	header('Location: '.$_SESSION['old_location'].sprint_url_parms($_SESSION['old_get']));
} else {
	$_SESSION['type'] = $row['type'];
	$_SESSION['name'] = $row['naam'];
	$_SESSION['login'] = $row['login'];
	$_SESSION['orig_login'] = $row['login'];
	$_SESSION['last_load_time'] = $load_time;
	$_SESSION['timeout'] = $row['timeout'];
	$_SESSION['orig_ppl_id'] = $row['ppl_id'];
	$_SESSION['ppl_id'] = $row['ppl_id'];
	$_SESSION['toon_rooster'] = $row['toon_rooster'];
	if ($row['caps'] != '') $_SESSION['caps'] = explode(',', $row['caps']);
	mysql_query_safe("INSERT INTO ppl2phpsessid (ppl_id, phpsessid) VALUES ('{$_SESSION['ppl_id']}', '%s')", session_id());
	header('Location: '.$http_path.'/');
} 
unset($_SESSION['old_get']);
unset($_SESSION['old_post']);
unset($_SESSION['old_location']);
// disable teletop login
if (false && $row['teletop_username'] && $row['teletop_password']) {

	// experiment met mcrypt
	$td = mcrypt_module_open('rijndael-256', '', 'cbc', '');
	mcrypt_generic_init($td, $_POST['password'], 'TeleTop&reg;01234567890123456789');
	$safe_password = rtrim(mdecrypt_generic($td, $row['teletop_password']), "\0");
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);

	$_SESSION['teletop_username'] = $row['teletop_username'];
	$_SESSION['teletop_password'] = $safe_password;

	$ch = curl_teletop_init();
	curl_teletop_req($ch, '/tt/abvo/lms.nsf/f-MyCourses?OpenForm');

}

mysql_log('login_success');
?>
