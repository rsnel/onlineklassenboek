<?
function curl_rooster_init() {
	$ch = curl_init();

	if (isset($_SERVER['HTTP_USER_AGENT'])) curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	else curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, true);
	//curl_setopt($ch, CURLINFO_REFERER, NULL);
	return $ch;
}


function curl_rooster_req($ch, $url) {
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	//curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
	echo('requesting: '.$url."\n");
	$ret = curl_exec($ch);
	//curl_setopt($ch, CURLOPT_HTTPHEADER, (array) NULL);

	if (!$ret) {
		curl_error($ch);
		exit;
	}
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);

	if (curl_errno($ch)) regular_error($http_path.'/', (array) NULL, 'Fout bij het laden van '.
		curl_getinfo($ch, CURLINFO_EFFECTIVE_URL).': '.curl_error($ch));

	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

	if (!strncasecmp($content_type, 'text/html', 9)) {
		$doc = new DOMDocument();
		libxml_use_internal_errors(true);
		$doc->loadHTML($ret);
		$xpath = new DOMXPath($doc);
		return $xpath;
	}

	return NULL;
}

?>
