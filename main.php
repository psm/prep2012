<?
namespace app;
use \MongoDate;

class Main extends \Enchinga\Controller {
	
	private static $codes = array(
		200 => 'Ok',
		201 => 'Created',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		406 => 'Not Acceptable',
		409 => 'Conflict',
		418 => 'I\'m a Teapot'
	);
	
	
	
	public function index()
	{
		$data['version'] = \enchinga\VERSION;
		$this->view('main', $data);
	}
	

	public function fetch($tipo, $cuantos)
	{
		$code = 200;
		$cuantos = $cuantos? : 'ultimo';
		if ( !in_array($cuantos, array('ultimo', 'todos', 'dump')) ) {
			self::response(array(
				'error'=>"No me enseño el PSM a darte $cuantos para $tipo",
				"ayuda" => "Tal vez debas votar por el PSM para Rey del Eje central"
			), 418);
			exit;
		}
		
		$uno = $cuantos=='ultimo';
		$fields = $find = array($tipo, 'fecha');
		$q = $this->db->resultados->set($fields);
		if ( $uno ) {
			$data = $this->db->resultados->order('fecha', -1)->limit(1)->find();
			$result = array(
						'timestamp' => current($data)->fecha->sec
							)+current($data)->$tipo;
		} else {
			$end = $this->get('end')? : time();
			$start = $this->get('start')? : time()-60*10;
			$where = $cuantos == 'dump'? array() : array(
				'fecha' => array(
					'$gte' => new MongoDate($start),
					'$lte' => new MongoDate($end)
				)
			);
			$data = $q->find($where);
			if ($data){
				
				$res = array();
				foreach ( $data as $resultado ) {
					$res[] = array('timestamp'=>$resultado->fecha->sec)+$resultado->$tipo;
				}
				
				$result = array(
					'total' => count($data),
					'resultados' => array_values($res)
				);
			} else {
				$code = 404;
				$result = array('error' => 'No tengo resultados para estas fechas', 'start'=>$start, 'end'=>$end);
			}
		}
		
		self::response($result, $code);
	}
	
	
	public function response($resultSet, $code=200)
	{
		$message = self::$codes[$code];
		header("HTTP/1.1 $code $message");
		#header("Content-type: application/json");
		
		echo json_encode($resultSet);
	}
	
}
