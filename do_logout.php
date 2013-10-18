<? require("include/init.php");
mysql_log('logout');
session_destroy();
header('Location: index.php');
?>
