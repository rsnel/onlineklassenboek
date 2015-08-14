#!/usr/bin/php5
<?
# databasefuncties en configuratie
include "../include/init.php";

$result = mysql_query_safe(<<<EOQ
SELECT DISTINCT ppl_id, login, email, altlogin, KB_NAAM(naam0, naam1, naam2) naam
FROM grp
JOIN grp2vak USING (grp_id)
JOIN doc2grp2vak USING (grp2vak_id)
JOIN ppl USING (ppl_id)
LEFT JOIN ppl2altlogin USING (ppl_id)
WHERE schooljaar = '$schooljaar'
AND altlogin IS NULL
EOQ
);

while ($row = mysql_fetch_assoc($result)) {
	echo("{$row['ppl_id']} {$row['login']} {$row['altlogin']} {$row['email']} {$row['naam']}\n");
}

?>
