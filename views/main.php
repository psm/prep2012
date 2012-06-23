<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title>API PREP 2012</title>
	<meta name="Apple-mobile-web-app-status-bar-style" content="black" />
	<meta name="Viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
	<meta name="Apple-mobile-web-app-capable" content="yes" />
	
	<link rel="stylesheet" type="text/css" href="/resources/handle.php?t=css&f=reset,main,shared" media="screen" />
	
	<script type="text/javascript" src="http://use.typekit.com/yij0jkl.js"></script>
	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
	
</head>
<?
$ultimo = $_SESSION['pattern']? : 1;
	
$clases = array('cruces', 'lineas', 'diamantes', 'dots', 'diagonales');
unset($clases[$ultimo]);
$nuevo = array_rand($clases);
$_SESSION['pattern'] = $nuevo;
?>
<body class="<?=$clases[$nuevo];?>">

	<div id="container">
		<h1>We can haz API</h1>
		
		<h2>Hora de salir a beber un rato, lo único que tienes que saber:</h2>
		<pre>/(presidente|diputados|senadores)/(todos|ultimo|dump)?start=unixTS&amp;end=unixTS</pre>
		<p>O le puedes preguntar a <a href="http://twitter.com/unRob">unRob</a>!</p>
	</div>
</body>
