<? 
require_once('include/init.php');
if (isset($_SESSION['ppl_id']) && isset($_SESSION['orig_ppl_id'])) {
	// er is iemand ingelogd, sla de voorkeur op
	mysql_query_safe("UPDATE ppl SET toon_rooster = %s WHERE ppl_id = '%s'",
		$_GET['show']?1:0,
		mysql_escape_safe($_SESSION['orig_ppl_id']));
}
$_SESSION['toon_rooster'] = $_GET['show']?1:0;
unset($_GET['show']);
header('Location: '.$http_path.'/'.sprint_url_parms($_GET));
?>
