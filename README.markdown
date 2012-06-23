#Enchinga

Para empezar, edita `main.php`. Si quieres urls bonitos, hay que hacer lo siguiente:

##Copiar config.default.php a config.php
Esto es importante, si no, no vas a tener acceso a la base de datos de tu elección!

##Apache `.htaccess`
	RewriteEngine On
	#para usar urls de CSS/JS limpios tipo: /css/archivo1,archivo2,archivoN.css
	RewriteRule ^(css|js)/([\w,]+)(\.\1)?(l*)$ resources/handle.php?t=$1&f=$2&legible=$4 [NC,L]
	
	RewriteCond %{REQUEST_URI} !-d
	RewriteCond %{REQUEST_URI} !-f
	RewriteCond $1 !^(index\.php) 
	RewriteRule ^/?(.*)/?$ /index.php?/$1 [NC,L];

##Nginx `nginx.conf`
	server {
		[...]
		#para usar urls de CSS/JS limpios tipo: /css/archivo1,archivo2,archivoN.css
		rewrite ^/(css|js)/([\w,]+)(\.\1)?(l*)$ /resources/handle.php?t=$1&f=$2&legible=$4 last;

		location / {
			try_files $uri $uri/ /index.php last;
			index index.php;
		}
	}
			
Un día que no me de huevita, documento cómo usar los drivers de la base de datos, pero ahí va el hint:

<span style="color: #0000BB">
&lt;?php&nbsp;$this<span style="color: #007700">-&gt;</span>db<span style="color: #007700">-&gt;</span>nombreDeLaTabla<span style="color: #007700">-&gt;</span>set<span style="color: #007700">(</span><span style="color: #DD0000">'campos,a,seleccionar'</span><span style="color: #007700">)-&gt;</span>find<span style="color: #007700">(array(</span><span style="color: #DD0000">'condicion'</span><span style="color: #007700">=&gt;</span><span style="color: #DD0000">'valor'</span><span style="color: #007700">));</span>?&gt;</span>