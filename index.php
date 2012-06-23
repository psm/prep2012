<?php
/**
 * Enchinga 2.0b
 *
 * Framework pequeño para hacer cosas en chinga.
 *
 * @copyright 2012 Partido Surrealista Mexicano
 * @author Roberto Hidalgo
 */
namespace enchinga{
	const VERSION = "2.0-compact";

	//El path absoluto a la carpeta de sistema, /enchinga, fuera del document root;
	define('SYSPATH', realpath($enchinga));
	//El path absoluto a la carpeta de la aplicación, ó DOCUMENT_ROOT;
	define('APPPATH', realpath($app));


	/**
	* HTTP: el maestro de ceremonias
	* 
	* Esta clase estática nomás corre el programa y rutea las madres
	* Tiene propiedades estáticas que chance y puede que luego sirvan al 
	* controlador instanciado.
	*
	* @package	Enchinga
	* @version 2.0b
	* @author	Roberto Hidalgo
	* @copyright Partido Surrealista Mexicano
	*/
	class http {
	
		//El request completo
		public static $request;
		//La base absoluta de este archivo
		protected static $root = '';
		protected static $base = '/';
		//El array de $_GET localizado
		public static $_GET = array();
		//Un placeholder para \enchinga\librerias\Config
		public static $config;
		//La instancia del objeto que llamó el Router
		private static $instance;
		//Los métodos permitidos de HTTP por default
		private static $allowedMethods = array('get', 'post');
	
	
		/**
		 * Inicializa el framework
		 *
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public static function init()
		{
			$uri = str_replace('?'.$_SERVER['QUERY_STRING'], '', trim($_SERVER['REQUEST_URI'], '/'));
		
			self::$request = new \StdClass;
			self::$request->method = $_SERVER['REQUEST_METHOD'];
			self::$request->segments = array();
			if( strlen($uri)>0 ){
				self::$request->segments = explode('/', urldecode($uri));
			}
				
			self::$request->isAjax = (bool) isset($_SERVER['HTTP_X_REQUESTED_WITH']);

			self::$root = dirname(__FILE__).'/';
		
			self::route();
		}
	
	
		/**
		 * Enruta el request al controller dentro del app necesario
		 *
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public static function route()
		{
			//tomamos una copia de los segmentos
			$s = self::$request->segments;
		
			//Cargamos el objeto de Config
			$config = new Config;
			//Y sacamos los datos de app/config/general.php
			$general = $config->config;
			self::$allowedMethods = $general->allowedMethods? (array) $general->allowedMethods : self::$allowedMethods;
		
			self::$config = $config;
			$rutas = $config->config->rutas;
			
			if( $rutas && count($s) > 0 ){
				//Si hay rutas en app/config/rutas.php y no estamos visitando /
				list($page, $method, $args) = self::translate($s, $rutas);
			} else {
				//Si tenémos un controller
				if( isset($s[0]) && ($s[0]!='main' && $s[0]!='index') ){
					$page = $s[0];
				} else {
					//El default lo toma de app/config/general::mainController, si no, es main; 
					$page = $general->mainController? : 'main';
				}
				//El método default siempre es index;
				$method = isset($s[1])? $s[1] : 'index';
				$args = array_splice($s,2);
			}
		
			$method = $method? : 'index';
		
			if( self::$request->method != 'GET' ){
				//Si el método de HTTP no es GET, entonces appendeamos el nómbre del método
				//Sí el método de HTTP no está dentro de la lista de los permitidos, usamos POST
				$srm = strtolower(self::$request->method);
				$suffix = in_array($srm, self::$allowedMethods)? $srm : 'post';
				$method .= "_$suffix";
			}
		
			//Requerimos el archivo que contiene el controlador que instanciaremos
			$controllerFile = APPPATH."/$page.php";
			if( file_exists($controllerFile) ) {
				require $controllerFile;
				//Namespaceamos el nombre del controller
				$claseController = "\\app\\$page";
				//Y lo examinamos por reflexión;
				$controller = new \ReflectionClass($claseController);
				
				/*
					Si podemos llamar Instancia->método está determinado por:
					- Instancia tiene un método _call
					- Instancia tiene un método <$method> y este es público
				*/
				if( $controller->hasMethod('__call') || ($controller->hasMethod($method) && $controller->getMethod($method)->isPublic()) ){
					//Métemos el query como un array asociativo a las propiedades de \enchinga\http
					self::$_GET = parse_str($_SERVER['QUERY_STRING']);
					//Instanciamos el objeto y lo metemos a cache
					self::$instance = $controller->newInstance();
					//Sí tenemos el método Instancia::__call y no existe Instancia::$method
					if( $controller->hasMethod('__call') && !$controller->hasMethod($method) ){
						// Llamamos el método call con los argumentos del URL, tomando como el primer elemento el nómbre del 
						// método que pretendíamos llamar
						$controller->getMethod('__call')->invokeArgs(self::$instance, array($method, $args));
					} else {
						// No existe call, y existe Instancia::método, así que lo ejecutamos
						$controller->getMethod($method)->invokeArgs(self::$instance, $args);
					}
				} else {
					//No está implementado el método, llamamos un error
					$error_controller = $config->config->errorController? : 'errores';
					$error_file = APPPATH."/$error_controller.php";
					if (file_exists($error_file)){
						//Tenemos un controlador de erorres
						require_once $error_file;
						$instance = new \App\Errores();
						$instance->code = 404;
						$instance->missing_method($page, $method);
					} else {
						//No tenemos un controller de Errores
						throw new Exception("No encontré un método $method para $page", 404);
					}
					
					
				}
			
			} else {
				//No está implementado el Controlador, llamamos un error
				$error_controller = $config->config->errorController? : 'errores';
				$error_file = APPPATH."/$error_controller.php";
				if (file_exists($error_file)){
					//Tenemos un controlador de erorres
					require_once $error_file;
					$instance = new \App\Errores();
					$instance->code = 404;
					$instance->missing_controller($page);
				} else {
					//No tenemos un controller de Errores
					throw new Exception("No pude ubicar el controlador «{$page}»", 404);
					//throw new \Exception("$page.php no existe", 404);
				}
			
			}
		}
	
	
		/**
		 * Traduce los segmentos a las rutas,
		 *
		 * @param array $segments Los segmentos del request
		 * @param array $routes Las rutas de app/config/rutas.php
		 * @return array El controlador, método y argumentos traducidos
		 * @author Roberto Hidalgo
		 */
		private static function translate($segments, $routes)
		{
			$original_controller = $segments[0];
			$original_method = $segments[1];
			$args = array_splice($segments, 2);
			//El URI original del request
			$req_uri = join('/',self::$request->segments);
		
			foreach($routes as $regex => $replacement){
				//Escapamos el regex y colocamos los flags de [u]nicode, case [i]nsensitive y nuevas línea[s]
				$regex = '/^'.str_replace('/', '\/', $regex).'$/uis';
			
				//reseteamos el array de matches
				$matches = array();
				if( preg_match($regex, $req_uri, $matches) ){
					// Si hay un match, tomamos los elementos con los que reemplazaremos el request
					// y los traducimos, reemplazando dónde sea necesario desde el URI original
					$replacements = explode('/', $replacement);
					$translation = explode('/', preg_replace($regex, $replacement, $req_uri));
				
					//Regresamos inmediatamente los resultados
					return array(
						$translation[0], //controlador
						$translation[1], //método
						array_splice($translation,2) //argumentos
					);
				}
			}
		
			return array(
				$original_controller,
				$original_method,
				$args
			);
		
		}
	
	
		/**
		 * Regresamos la única instancia del Controller que debemos haber instanciado
		 *
		 * Esto regresa null si lo llamo desde el constructor de mi instancia
		 *
		 * @return \app\Controller
		 * @author Roberto Hidalgo
		 */
		public function instance()
		{
			return self::$instance;
		}
	
	} //end HTTP
	
	
	/**
	 * Regresa la instancia actual de Enchinga
	 *
	 * @return Enchinga\Controller
	 * @author Roberto Hidalgo
	 */
	function instance(){
		return http::instance();
	}
	

	/**
	 * Controller, el objeto feliz que nos da felicidad y facilidad
	 *
	 * @package	Enchinga
	 * @abstract
	 * @author	Roberto Hidalgo
	 * @copyright 2012 Partido Surrealista Mexicano
	 */
	abstract class Controller extends HTTP
	{
	
		//dónde guardo las librerías para poderlas pedir en chinga con __get
		protected $librerias = array();
		public static $request;
	
		/**
		 * Construye el objeto principal, del que heredo todas las bondades
		 * del framework
		 *
		 * @author	Roberto Hidalgo
		 * @return	object	el objeto principal
		 */
		public function __construct()
		{
			// Saco todas las variables interesantes de HTTP para poder 
			// pedirlas desde mis controllers
			$this->request = parent::$request;
			$this->base = parent::$base;
			$this->root = parent::$root;
			$this->host = "http://".$_SERVER['HTTP_HOST'];	
			
			if( file_exists("config.php") ){
			
				require "config.php";
				$vars = get_defined_vars();

				$config = array();
				foreach( $vars as $key => $value ){
					if( !in_array( $key, array('GLOBALS', '_SERVER', '_GET', '_POST', '_COOKIE', '_FILES') ) ){
						$value = is_array($value)? (object) $value : $value;
						$config[$key] = $value;
					}
				}
			
				$this->config = (object) $config;
			}
			
		}
	
	
	
		/**
		 * Autocarga las librerías de app/config/autoload
		 *
		 * @param string $cosas las librerías a requerir 
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function autoload( $cosas )
		{
			if ($cosas) {
				// Si no tenemos librerías, ni pa qué seguir. Config me regresa objetos, así 
				// que typecastearé
				$cosas = (array) $cosas;
			
				// Sólo podemos auto-cargar helpers y librerías, ya que lo demás es lazy loading
				$permitidos = array( 'helpers', 'librerias' );
			
			
				foreach ($cosas as $tipo=>$archivos) {
					if (!in_array($tipo, $permitidos)) {
						// No está permitido el tipo, continua con el resto
						continue;
					}
				
					switch( $tipo) {
						case 'helpers':
							foreach ($archivos as $archivo) {
								self::helper($archivo);
							}
						break;
						case 'librerias':
							foreach ($archivos as $archivo) {
								self::carga($archivo);
								$lib = new \ReflectionClass($archivo);
								if ($lib->hasProperty('auto_setup') && $lib->getProperty('auto_setup')){
									$a = str_replace('enchinga\\', '', $archivo);
									$this->$a;
								}
							}
						break;
						default:
							die('No puedo auto-cargar '.$tipo);
						break;
					}
				
				}
			}
		}
	
	
		/**
		 * Carga helpers sin el autoloader
		 *
		 * @param string $archivo 
		 * @return void
		 * @author Roberto Hidalgo
		 */ 
		public function helper($archivo)
		{
			$relative_path = "/helpers/$archivo.php";
			if (file_exists(SYSPATH.$relative_path)) {
				require SYSPATH.$relative_path;
			} elseif (file_exists(APPPATH.$relative_path) ) {
				require APPPATH.$relative_path;
			} else {
				$tipo = substr($tipo, 0, -1);
				throw new Exception("No existe el $tipo $archivo");
			}
		}
	
	
		/**
		 * Instancía una librería y regresa el objeto de cache;
		 *
		 * @param	string	$libreria	El nombre no namespaceado de la librería
		 * @return	object				La librería instanciada
		 * @author	Roberto Hidalgo
		 */
		public function __get($libreria)
		{
			// Por mamar verga, si luego cambio cómo guardo las librerías, hago un alias
			$libs = &$this->librerias;
			if (!in_array($libreria, array_keys($libs))) {
				// $libreria no se ha instanciado todavía
				$clase = "enchinga\\$libreria";
				$c = new $clase;
				$libs[$libreria] = &$c;
			}
			
			return $libs[$libreria];
		}
	
	
		/**
		 * Método de conveniencia para sacar el nombre del controller de esta instancia
		 *
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function __toString()
		{
			return str_replace('app\\', '', get_class($this));
		}
	
	
		/**
		 * Redirect
		 *
		 * @param	string	$donde	La ubicación a la que lo voy a mandar
		 * @return	void
		 * @author	Roberto Hidalgo
		 */
		public function location($donde, $die=true)
		{
			// Le quitamos el forward slash, que luego se caga todo
			$donde = strpos('/',$donde)==0? substr($donde, 1) : $donde;
			// Y le meto el URL completo;
			header("Location: $this->base$donde");
			// Por si algo se jode...
			echo "Redirigiendo a $this->base$donde";
			if ( $die ) { die(); }
		}
	
	
		/**
		 * Shortcut para $_GET
		 *
		 * @param	string	$que		La variable que quiero ó null, para todo el array
		 * @return	void
		 * @author	Roberto Hidalgo
		 */
		public function get( $que=NULL )
		{
			if ($que) {
				return $_GET[$que];
			} else {
				return $_GET;
			}
		}
	
	
	
		/**
		 * Shortcut para $_POST
		 *
		 * @param	string	$que		La variable que quiero ó null, para todo el array
		 * @return	void
		 * @author	Roberto Hidalgo
		 */
		public function post( $que=NULL )
		{
			if ($que) {
				return $_POST[$que];
			} else {
				return $_POST;
			}
		}
	
		/**
		 * Shortcut para $_FILES
		 *
		 * @param	string	$que		La variable que quiero ó null, para todo el array
		 * @return	void
		 * @author	Jaime Rodas
		 */
		public function files( $que=NULL )
		{
			if ($que) {
				return $_FILES[$que];
			} else {
				return $_FILES;
			}
		}
	
	
	
		/**
		 * Vista templateada ó no
		 *
		 * @param	string	$view		El path relativo a la aplicación de la vista
		 * @param	string	$data		El array de datos que voy a mandar
		 * @param	string	$plantilla	El nombre de la plantilla ó false si no quiero plantilla
		 * @return	void 
		 * @author	Roberto Hidalgo
		 */
		public function view($_view, $_data=array(), $_plantilla=FALSE)
		{
			$_data = $_data? : array();
			extract($_data);
			if ($_plantilla===FALSE) {
				require APPPATH."/views/$_view.php";
			} else {
				$body = '';
				ob_start();
				require APPPATH."/views/$_view.php";
				$body = ob_get_clean();
				require APPPATH."/views/$_plantilla.php";
			}
		}
	
	
	
		/**
		 * Output de JSON con header adecuado
		 *
		 * @param string $stuff 
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function viewJSON($stuff)
		{
			header('Content-type: text/json');
			echo json_encode($stuff);
		}
	
	
	}
	

	
	/**
	 * El Lazyloader de configuraciones de la aplicación
	 *
	 * @author	Roberto Hidalgo
	 * @package enchinga
	 */
	class Config {
	
		private $configs = array();
	
		/**
		 * Método mágico para cargar un archivo de configuración al objeto enchinga\Config->nombreDelArchivo
		 *
		 * @param string $archivo 
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function __get($archivo)
		{		
			if( !in_array($archivo, array_keys($this->configs)) ){
				$file = APPPATH."/$archivo.php";
				if( file_exists($file) ){
					$config = $this->load($file);
					$archivo = strtolower($archivo);
					$this->configs[$archivo] = (object) $config;

				} else {
					throw new \Exception("No existe $file /$archivo.php");
				}
			}
		
			return $this->configs[$archivo];
		
		}
	
	
		/**
		 * Hace la carga del archivo y parsea las variables a un objeto
		 *
		 * @param string $archivo 
		 * @return void
		 * @author Roberto Hidalgo
		 */
		private function load($archivo)
		{
			require "$archivo";
			unset($archivo);
			$vars = get_defined_vars();

			$config = array();
			foreach( $vars as $key => $value ){
				$value = is_array($value)? (object) $value : $value;
				$config[$key] = $value;
			}
				
			return $config;
		}
	
	
		/**
		 * Regresa la versión del framework
		 *
		 * @static
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function version()
		{
			return VERSION;
		}
		
	
	}
	
	
	
	/**
	 * Pendejaditas de sesión
	 *
	 * @package	Enchinga
	 * @author	Roberto Hidalgo
	 */
	class Session {
	
		private $vars = array();
	
	
		public function __construct()
		{
			session_start();
		}
	
	
		/**
		 * Método mágico para meter una variable $nombre, con valor $valor a la sesión
		 *
		 * @param	string	$nombre	El nombre de la variable a almacenar
		 * @param	string	$valor	El valor de la variable
		 * @return	void
		 * @author	Roberto Hidalgo
		 */
		public function __set($nombre, $valor)
		{
			if( !session_id() ){
				session_start();
			}
		
			//Si valor == 0 | false | null, vacía esta variable
			if( empty($valor) ){
				//delete de los pobres
				$this->__unset($nombre);
			}
		
			if( is_array($valor) || is_object($valor) ){
				$valor = serialize($valor);
			}
			$this->vars[$nombre] = $valor;
			$_SESSION[$nombre] = $valor;
		}
	
	
		/**
		 * Método mágico para sacar el valor de una variable de sesión
		 *
		 * @param	string	$que 
		 * @return	void
		 * @author	Roberto Hidalgo
		 */
		public function __get($que)
		{
			if( !session_id() ){
				session_start();
			}
		
			if( !isset($this->vars[$que]) && isset($_SESSION[$que]) ){
				//metamos a cache
				$this->vars[$que] = unserialize($_SESSION[$que]);
			}
			return $this->vars[$que];

		}
	
		public function __unset($que)
		{
			if( !session_id() ){
				session_start();
			}
			unset($this->vars[$que]);
			unset($_SESSION[$que]);
		}
	
	}//end sesión


	/**
	 * EL DAL (Database Access Layer) de Enchinga
	 *
	 * @package	Enchinga
	 * @author	Roberto Hidalgo
	 */
	class db {
	
	
		protected $dbo;
		public static $last_query = '';
	
		/**
		 * Instancia una conexión a la base de datos.
		 *
		 * @param string $set La configuración que queremos usar, por default db
		 * @param array $config La configuración específica que queremos usar
		 * @author Roberto Hidalgo
		 */
		public function __construct($set = false, $config=false)
		{
			if( !$config ){
				$base = \enchinga\http::$config->config;
				$config = $set? $base->$set : $base->db;	
			} else {
				require 'db/exception.php';
			}
		
			$driver = 'enchinga\db\\'.strtolower($config->driver).'\\factory';
			$factory = new \ReflectionClass($driver);
		
			$this->dbo = $factory->newInstance($config);
		}
	
	
		/**
		 * Instanciar una conexión con los parámetros del archivo de configuración, referenciados por nombre de variable 
		 *
		 * @param string $config El nombre de la variable en el archivo de configuración
		 * @return enchinga\db
		 * @author Roberto Hidalgo
		 */
		public static function usa($config)
		{
			return new \enchinga\db($config);
		} 
	
	
		/**
		 * El método mágico para regresar una colección/tabla sobre el cual interactuar.
		 *
		 * @param string $cual El nombre de la tabla
		 * @return enchinga\db\driver El objeto active Record de la base de datos
		 * @author Roberto Hidalgo
		 */
		public function __get($cual){
			return $this->dbo->newObject($cual);
		}
	
	
		/**
		 * Ejecutar directamente un comando en la base de datos
		 *
		 * @param string $q El comando a regresar
		 * @return void FALSE si algo falló, el resultado directo si funcionó el query
		 * @author Roberto Hidalgo
		 */
		public function query($q){		
			return $this->dbo->query($q);
		}
	
	
		/**
		 * El último query que se envió a la base de datos
		 *
		 * @return void
		 * @author Roberto Hidalgo
		 */
		public function last_query()
		{
			return self::$last_query;
		}
	
	
		/**
		 * La versión de la base de datos
		 *
		 * @return string La versión de la base de datos
		 * @author Roberto Hidalgo
		 */
		public function version(){
			return $this->dbo->version();
		}
	
	
	}


	/**
	 * Excepciones con die, por razones de performance
	 *
	 * @package enchinga
	 * @author Roberto Hidalgo
	 */
	class Exception extends \Exception{
	
		static private $codes = array(
			404 => 'Not Found'
		);
	
	
		/**
		 * Al throw una nueva excepción, podemos generar HTML pitero para mostrar el error fatal
		 *
		 * @param string $error 
		 * @param string $titulo 
		 * @author Roberto Hidalgo
		 */
		public function __construct($message=null, $code=404)
		{
			parent::__construct($message, $code);
			header("Status: $code {self::$codes[$code]}");
			$backtrace = self::backtrace();
			echo "<html><head><title>o_O</title></head><body><h1>Error $code</h1><p>$message</p>$backtrace</body></html>";
			//este exit es para que no me mande más exceptions a menos que sean necesarios
			exit;
		}
	
	
		public function backtrace()
		{
		
			$result = '';
			if ( ENV == 'produccion' ){
				return '';
			}
		
		
			$url = ini_get('xdebug.file_link_format')? : false;
		
			$result = '<h2>Stack:</h2>';
			$filename = self::shortpath($this->file);
		
			$link = "$filename @ $this->line";
			if( $url ){
				$href = str_replace(array('%f', '%l'), array($this->file, $this->line), $url);
				$link = "<a href=\"$href\">$link</a>";
			}
			$result .= "<p>[main] $link </p>";
		
			foreach( self::getTrace() as $ex ){
				extract($ex);
				$filename = self::shortpath($file);
				$link = "$filename @ $line";
			
				$function = "$class{$type}$function";
			
				if( $url ){
					$href = str_replace(array('%f', '%l'), array($file, $line), $url);
					$link = "<a href=\"$href\">$link</a>";
				}
				$result .= "<p>[$function] $link </p>";
			}
		
			return $result;
		}
	
	
		public static function shortpath($path)
		{
			return str_replace(dirname(SYSPATH), '', $path);
		}
	
	}
	
	
	
	
	if($_SERVER['SCRIPT_NAME']=='/index.php'){
		http::init();
	}

}

namespace enchinga\db\Mongo {
	use MongoId;
	use MongoRegex;
	use MongoDate;
	use Mongo;

	class Factory extends Mongo
	{

		public $connection = NULL;
		private $clean = NULL;

		public function __construct($config)
		{
			$options = array('database' => $config->database);
			if($config->user && $config->password){
				$options['username'] = $config->user;
				$options['password'] = $config->password;
			}
			try {
				$connection = parent::__construct("mongodb://$config->host", $options);
				$this->clean = $connection;
				$this->connection = $this->selectDB($config->database);
			} catch(Exception $e){
				throw new Exception("DANG! No me pude conectar a la db: {$e->getMessage()}", "Mongo Se cagó");
			}
		}
	
	
		public function query($stuff)
		{
			$result = $this->connection->command($stuff);
			return $result;
		}
	
	
		public function newObject($table)
		{
			return new driver($table, $this->connection);
		}
	
	
		public function version()
		{
			$v = $this->selectDB('admin')->command(array('buildinfo'=>TRUE));
			return $v['version'];
		}
	
	}
	

	/**
	 * Driver de MongoDB para Enchinga
	 *
	 * @package enchinga\db
	 * @subpackage mongo
	 * @author Roberto Hidalgo
	 */
	class Driver extends \MongoCollection
	{
		/**
		 * El límite de elementos que regresar
		 *
		 * @var int
		 */
		protected $limit = false;
		/**
		 * El arreglo de instrucciones para ordenar los elementos resultantes
		 *
		 * @var array
		 */
		protected $sort;
		/**
		 * La cantidad de elementos que saltar, una vez se hayan ordenado
		 *
		 * @var string
		 */
		protected $skip;
		/**
		 * El arreglo de condiciones para filtrar los elementos a regresar
		 *
		 * @var array
		 */
		protected $where = array();
		/**
		 * Las propiedades a regresar de los elementos resultantes
		 *
		 * @var array
		 */
		protected $set = array();
		/**
		 * La colección que estamos usando
		 *
		 * @var string
		 */
		private $coleccion;
		/**
		 * El último error de la última operación
		 *
		 * @var string
		 */
		private static $last_error;
	
	
		/**
		 * Al construir este objeto, tenemos acceso a una colección tipo MongoDB
		 *
		 * @param string $coleccion El nombre de la colección sobre el que actuaremos
		 * @param MongoDB $link El Objeto MongoDB que estamos utilizando
		 * @see enchinga\db\driver::construct(), enchinga\db::__get()
		 * @uses enchinga\db\mongo\factory	Tengo que pasarle un objeto construído por la fábrica de este driver
		 * @author Roberto Hidalgo
		 */	
		public function __construct($coleccion, $link)
		{
			$this->dbo = $link;
			return parent::__construct($link, $coleccion);
		}
		
	
		/**
		 * Limitar la cantidad de resultados que regresará la DB
		 * 
		 * Este método es chainable, para ser usado así: <code><?php $this->db->Coleccion->limit(5)->find(); ?></code>
		 *
		 * @param int $qty La cantidad de resultados
		 * @return object
		 * @author Roberto Hidalgo
		 */
		public function limit($qty = FALSE)
		{
			if ($qty) {
				$this->limit = $qty;
			}
			return $this;
		}
	
	
		/**
		 * Ordena los elementos resultantes por la condición especificada
		 * 
		 * Este método es chainable, para ser usado así: <code><?php $this->db->Coleccion->order('_id', -1)->find(); ?></code>
		 * 
		 * @todo Permitir varias condiciones de órden
		 * @param string $by El nombre del campo
		 * @param mixed $type El tipo de órden, ya sea SQL ("ASC", "DESC") ó [1,-1]
		 * @return object
		 * @author Roberto Hidalgo
		 */
		public function order($by, $type=-1)
		{
			$operators = array(
				'ASC' => 1,
				'DESC' => -1
			);
		
		
			if ( !is_array($by) ) {
				$type = is_string($type)? $operators[strtoupper($type)] : $type;
				$order = array($by => $type);
			} else {			
				if ( array_keys($by) === range(0, count($by) - 1) ){
					//si es un array no asociativo, lo hacemos assoc
					$type = is_string($by[1])? $operators[strtoupper($by[1])] : $by[1];
					$order = array($by[0] => $type);
				} else {
					$order = $by;
				}
			}
		
			$this->sort = $order;
			return $this;
		}
	
	
		/**
		 * Alias para {@link enchinga\db\Mongo\Driver::set()}
		 *
		 * @return object
		 * @see enchinga\db\mongo\Driver::set()
		 * @author Roberto Hidalgo
		 */
		public function get()
		{
			return self::set(func_get_args());
		}
	
	
		/**
		 * Especificar las propiedades de los elementos a regresar
		 * 
		 * Este método es chainable, para ser usado así: <code><?php $this->db->set(array('_id', 'nombre'))->find(); ?></code>
		 *
		 * @param mixed $args Acepta un string delimitado por comas con las propiedades, un array con las propiedades necesarias ó dos argumentos: el nombre de la propiedad y el valor de la misma, al estilo Mongo
		 * @see http://www.mongodb.org/display/DOCS/Querying#Querying-FieldSelection
		 * @return object
		 * @author Roberto Hidalgo
		 */
		public function set()
		{
			$args = func_get_args();
			if (count($args)==1) {
				if (is_array($args[0]) || is_object($args[0])) {
					$set = (array) $args[0];
				
					if ( array_keys($set) === range(0, count($set) - 1) ){
						//si es un array no asociativo, lo hacemos assoc
						$set = array_fill_keys($set, '1');
					}
				
				
					$this->set = $this->set + (array) $set;
				} else {
					$this->set += array_fill_keys(explode(',', $args[0]), 1);
					/*foreach (explode(',', $args[0]) as $key) {
						$this->set[trim($key)] = 1;
					}*/
				}
			
			} elseif (count($args)==2) {
				$this->set[$args[0]] = $args[1];
			}
		
			return $this;
		}
	
	
		/**
		 * Saltar n cantidad de elementos de la colección a regresar
		 * 
		 * Este método es chainable, para ser usado así: <code><?php $this->db->skip(3)->find(); ?></code>
		 *
		 * @param int $qty La cantidad de  elementos a saltar
		 * @return object
		 * @author Roberto Hidalgo
		 */
	
		public function skip($qty)
		{
			$this->skip = $qty;
			return $this;
		}
	
	
		/**
		 * Ejecutar los elementos, regresando un array con los objetos encontrados
		 *
		 * @param array $where Las condiciones para seleccionar elementos de la colección
		 * @param boolean $parse Si deseamos limpiar las condiciones y convertirlas a código válido de MongoDB {@uses parseConditions()}
		 * @return mixed Un arreglo con los elementos encontrados ó FALSE si no encontró elementos
		 * @author Roberto Hidalgo
		 */
		public function find($where=array(), $parse=TRUE)
		{
			$where = $parse? self::parseConditions($where) : $where;
		
			$cursor = parent::find($where, $this->set);
		
			if (count($this->set) > 0) {
				$cursor->fields($this->set);
			}
		
			if ($cursor->count() > 0) {
				if ($this->limit) {
					$cursor = $cursor->limit($this->limit);
					if ($cursor->count(TRUE)==0) {
						return FALSE;
					}
				}
				if ($this->sort) {
				
					$cursor = $cursor->sort($this->sort);
				}
				if ($this->skip) {
					$cursor = $cursor->skip($this->skip);
				}
				$results = array();
				$count = $cursor->count(TRUE);
			
				//Mongo regresa un iterador, no un arreglo contable, así que lo convierto a uno, ejecutando en este paso el query
				$results = array();
				foreach ($cursor as $id => $object) {
					$o = (object) $object;
					$o->id = $id;
					$results[$id] = $o;
				}
				return $results;
			}
			self::cleanup();
			return FALSE;
		}
	
	
		/**
		 * Buscar un sólo elemento de la colección, regresando un objeto con el elemento encontrado
		 *
		 * @param string $where Las condiciones para seleccionar elementos de la colección
		 * @return object El elemento encontrado ó FALSE, de no encontrar nada
		 * @author Roberto Hidalgo
		 */
		public function findOne($where, $parse = TRUE)
		{
			$where = $parse? self::parseConditions($where) : $where;
			$cursor = parent::findOne($where, $this->set);
			if ($cursor) {
				$cursor['id'] = "{$cursor['_id']}";
				$cursor = (object) $cursor;
			} else {
				$cursor = FALSE;
			}
			return $cursor;
		}
	
	
		/**
		 * Insertar un objeto en la colección
		 *
		 * @param boolean $safe Si el driver debe esperar a que el insert se haya realizado
		 * @see http://us.php.net/manual/en/mongocollection.insert.php
		 * @return mixed Si pedimos que nos notifique de algún error, regresa FALSE en caso de fallar, de lo contrario regresa el set de propiedades del objeto insertado
		 * @author Roberto Hidalgo
		 */
		public function insert($safe=FALSE)
		{
			$set = (array) $this->set;
			try {
				parent::insert($set, array('safe' => $safe));
				$set['id'] = $set['_id'].'';
				return (object) $set;
			} catch (\MongoCursorException $e) {
				self::$last_error = $e->getMessage();
				return FALSE;
			}
		
		}
	
	
		/**
		 * Eliminar un objeto de la colección
		 * 
		 * En caso de que no se especifiquen condiciones, este método eliminará TODOS los objetos de la colección
		 *
		 * @param array $where Las condiciones para seleccionar los objetos a eliminar
		 * @param array $options 'single' Si deseamos sólo eliminar un elemento, y 'parse' si procesaremos las condiciones y convertirlas a código válido de MongoDB {@uses parseConditions()}
		 * @return boolean El resultado de la operación
		 * @author Roberto Hidalgo
		 */
		public function remove($where, $options=array())
		{
			$options = $options? : array('single' => FALSE, 'parse'=> TRUE);
			$where = $options['parse']? self::parseConditions($where) : $where;
			return parent::remove($where, $options);
		}
	
	
	
		/**
		 * Regresa la cantidad total de elementos en esta colección
		 * 
		 * Aunque \MongoCollection cuenta con el método \MongoCollection::count(), por estandarizar el proceso con otros drivers
		 * implementamos este método. {@see enchinga\db\Driver::total()}
		 *
		 * @return int La cantidad de elementos en esta colección
		 * @author Roberto Hidalgo
		 */
		public function total()
		{
			return self::count();
		}
	
	
	
		/**
		 * Actualizar las propiedades de los elementos especificados
		 *
		 * @param array $where Las condiciones con las cuales especificaremos los elemenos a actualizar
		 * @param array $options Las opciones de la actualización: '(boolean) upsert' si queremos insertar el elemento de no existir, por default FALSE; '(boolean) multiple' si queremos actualizar varios elementos de la colección, por default TRUE; '(boolean) safe' si debemos esperar a que el driver responda con el resultado de la operación, por default FALSE; y 'parse' si procesaremos las condiciones y las convertiremos a código válido de MongoDB {@uses parseConditions()}
		 * @return boolean El resultado de la operación
		 * @author Roberto Hidalgo
		 */
		public function update($where, $options=array())
		{
			$defaults = array(
				'upsert'	=> FALSE,
				'multiple'	=> TRUE,
				'safe'		=> FALSE,
				'parse'		=> TRUE
			);
			$options += $defaults;
		
			$where = $options['parse']? self::parseConditions($where) : $where;
			unset($options['parse']);
		
			$set = $this->set;
		
			if (!preg_grep('/^\$/', array_keys($set))) {
				$set = array('$set' => $set);
			}
		
			try {
				$result = parent::update((object) $where, $set, $options);
				if ($options['safe'] && !$result['updatedExisting']) {
					return FALSE;
				}
			} catch (\MongoCursorException $e) {
				self::$last_error = $e->getMessage();
				return FALSE;
			}
		
			return TRUE;
		}
	
	
		/**
		 * Regresa el último error de la última operación en esta colección
		 *
		 * @return string
		 * @author Roberto Hidalgo
		 */
		public function last_error()
		{
			return self::$last_error;
		}
	
	
	
		/**
		 * Resetear las condiciones, límite, órden y propiedades de requests pasados
		 *
		 * @return void
		 * @author Roberto Hidalgo
		 */
		private function cleanup()
		{
			$this->set = array();
			$this->limit = FALSE;
			$this->sort = NULL;
			$this->order = array();
		}
	
	
		/**
		 * Limpiar las condiciones a código válido de MongoDB
		 * 
		 * Test Cases que pasa:
		 * <code><?php $where = array(
		 * 	'normal = pants<conSalmones',
		 * 	'operadores Mongo' => array('$nin'=>array(1,2,3)),
		 * 	'inArray' => array(1,2,3),
		 * 	'regex' => '~= /poo/',
		 * 	'operadores SQL'=> ">= pants",
		 * 	'fecha nativa' => new MongoDate(),
		 * 	'string a int' => '1',
		 * 	'float a int' => '0.1',
		 * 	'pendejada no typecasteada' => '0.9 volts' ,
		 * 	'string como string' => "'1'",
		 * 	'bool' => true 
		 * );
		 * $where = '4f1dfcb923066c27b7367b42'; ?></code>
		 *
		 *
		 * @param mixed $where Un arreglo con las condiciones a limpiar ó un string ó objeto MongoId que convertiremos a un objeto MongoId
		 * @return array Las condiciones limpias
		 * @author Roberto Hidalgo
		 */
		private function parseConditions($where)
		{
			if ($string = is_string($where) || $where instanceOf MongoId) {
				if ($string) {
					$where = new MongoId($where);
				}
				return array('_id' => $where);
			}
		
			$operadores = array(
				'=' => '',
				'!=' => '$ne',
				'<='  =>  '$lte',
				'>='  => '$gte',
				'!='  => '$ne',
				'or'  => '$or',
				'||'  => '$or',
				'not' => '$not',
				'!'   =>  '$not',
				'>'	=> '$gt',
				'<' => '$lt'
			);
		
			$ops = array();
			if ($where===NULL) {
				return $ops;
			}
			foreach ($where as $sujeto=>$predicado) {
				//asumimos que todo es [poo] => 'pants'
			
				if (is_int($sujeto)) {
					// 'poo = pants'
					preg_match("/^(?P<sujeto>[\w.\d]+)\s*(?P<operador>(!=|=|<=|>=)+)\s*(?P<predicado>.+)/i", $predicado, $matches);
					$sujeto = $matches['sujeto'];
					$predicado = $matches['predicado'];
					$operador = $operadores[$matches['operador']];
				
					if ($operador){
						$predicado = array($operador => $predicado);
					}
				
					if (is_numeric($predicado)) {
						$predicado = !strpos($predicado,'.') ? intval($predicado) : (float) $predicado;
					}
				}
			
				if (is_string($predicado) ) {
					trim($predicado);
					if (preg_match('/^(?P<operador>(([~!<>]=[>]?)|[<>])+)\s*(?P<predicado>.+)/', $predicado, $matches)) {
						// [poo] => '!= pants'
						if ($matches['operador'] == '~=') {
							$predicado = new MongoRegex($matches['predicado']);
						} else {
							if (is_numeric($matches['predicado'])) {
								$matches['predicado'] = !strpos($matches['predicado'],'.') ? intval($matches['predicado']) : (float) $matches['predicado'];
							}
						
							$predicado = array($operadores[$matches['operador']] => $matches['predicado']);
						}
					}
				}
			
				if (is_array($predicado) && !preg_grep('/^\$/', array_keys($predicado)) && $sujeto!='$or' ) {
					// [poo] => array('p','a','n','t','s') pero no [poo] => array('$nin' => array('p','a','n','t','s'))
					$predicado = array('$in' => $predicado);
				}

				$ops[$sujeto] = $predicado;
			}
			return $ops;
		}

	
	}
	
	
}


namespace {

function e($que, $dump=false){
	if ( $dump || is_bool($que) ){
		var_dump($que);
	} elseif( is_array($que) || is_object($que) ){
		print_r($que);
	} else {
		echo $que;
	}
}

function fecha($timestamp) {
	$minuto = 60;
	$hora = 60*$minuto;
	$dia = 24*$hora;
	$diff = time()-$timestamp;
		
	if($timestamp==null){
		return "nunca";
	}
	
	if($diff<60){
		//hace diff segundos;
		return "hace $diff segundos";
	} elseif ($diff>60 AND $diff<$hora)  {
		//hace diff minutos;
		$minutos = floor($diff/$minuto);
		return "hace $minutos minutos";
	} elseif ($diff>=$hora AND $diff<$dia) {
		//hace diff horas;
		$horas = floor($diff/$hora);
		$mas = $diff%$hora>$hora/2? 'más de ' : 'poco más de ' ; //si es más de media hora, tons ponemos más de : poco más de
		$plural = $horas==1? '' : 's';
		return "hace $mas$horas hora$plural";
	} elseif ($diff>=$dia AND $diff<30*$dia){
		//hace x dias
		$dias = floor($diff/$dia);
		$plural = $dias==1? '' : 's';
		return "hace $dias día$plural";
	} else {
		return fechaAbsoluta($timestamp);
	}
}

function fechaAbsoluta($timestamp){
	$formato = '%A %e de %B de %Y - %r';
	return ucfirst(strftime($formato, $timestamp));
}

}
