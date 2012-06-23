<?php
namespace app;

class Errores extends \Enchinga\Controller
{

	public function missing_controller($controller)
	{
		$this->viewJSON(array(
			'error' => 'No se de que hablas con tus "democraciases" y "'.$controller.'eses"'
		));
	}
	
	public function missing_method($controller, $method)
	{
		$this->viewJSON(array(
			'error' => 'Ah chingá! Y cómo se le hace el "'.$method.'" a su "'.$controller.'"?'
		));
	}
	
	
}