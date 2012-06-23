<?
$strict = $_GET['legible']!='l';
$tipo = $_GET['t'];
$files = explode(',',$_GET['f']);
$ext = $tipo;
$commentType = array('start'=>'/*', 'end'=>'*/');
$contentType = $tipo =='css'? 'text/css' : 'application/javascript';

$fcopy = $files;
sort($fcopy);
$base = dirname(__FILE__);
$cacheName = md5($base.join(',', $fcopy));

$cacheDir = "$base/cache/$tipo";
$fileDir = "$base/$tipo";
$cache = "$cacheDir/$cacheName";

$fmtimes = array();
clearstatcache();
array_walk($files, function($file, $i){
	global $fmtimes, $fileDir, $tipo;
	$fmtimes[] = @filemtime("$fileDir/$file.$tipo");
});

$apc = function_exists('apc_fetch');

header('Content-type: '.$contentType);
if( $apc AND $strict AND apc_exists($cacheName) AND apc_fetch("{$cacheName}_ts")>max($fmtimes) ){
	echo "/* $cacheName */\n\n";
	echo apc_fetch($cacheName);
	exit;
}

$buffer = array();
foreach($files as $file){
	$f = "$fileDir/$file.$tipo";
	if ( !file_exists($f) ){
		$buffer[] = "/*--- missing: $file ---*/";
		continue;
	}
	
	$buffer[] = "/*--- $file ---*/";
	
	$parsed = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!i', '', file_get_contents($f));
	
	if($strict && $tipo=='css') {
		$parsed = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $parsed);
		$stuff = '/\s?([{};:,])\s?/';
		$parsed = preg_replace($stuff, "$1", $parsed);
	}
	$buffer[] = trim($parsed);
}

$output = join("\n\n", $buffer);
if( $apc AND $strict ){
	apc_store($cacheName, $output, 3600*24);
	apc_store("{$cacheName}_ts", time(), 3600*24);
} else {
	echo "/* legible */\n";
}
echo $output;

/* End: handle.php */