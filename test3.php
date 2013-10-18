<? require_once('include/init.php');
require_once('include/rooster_lib.php');
check_login();
if ($_SESSION['ppl_id'] != 3490) throw new Exception(2, 'test.php not for production use');

$fp = fopen('/home/rsnel/public_html/json1.ordered', 'r');
if (!$fp) echo 'error opening bla<br>';

$json = fread($fp, 16384);
$ret = json_decode($json);
echo $json;
echo json_last_error();
print_r($ret);
?>
