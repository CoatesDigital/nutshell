<?php
/**
 * @package nutshell-plugin
 * @author guillaume
 */
namespace nutshell\plugin\mvc
{
	use nutshell\core\plugin\PluginExtension;
	use nutshell\behaviour\Loadable;
	use nutshell\Nutshell;
	
	/**
	 * @author guillaume
	 * @package nutshell-plugin
	 * @abstract
	 */
	abstract class Model implements Loadable
	{
		/**
		 * Stores a local pointer to Nutshell
		 */ 
		public $nutshell = null;
		
		/**
		 * Stores a local pointer to plugin loader. 
		 */
		public $plugin	 = null;
		
		/**
		 * Stores a local pointer to model loader.
		 */
		public $model    = null;
		
		public function __construct()
		{
			$this->nutshell = Nutshell::getInstance();
			if (isset($this->nutshell))
			{
				$this->plugin = $this->nutshell->plugin;
				$loader=$this->nutshell->getLoader();
				if (isset($loader))
				{
					$this->model = $loader('model');
				}
			}
		}
		
		public static function getInstance(Array $args=array())
		{
			$className=get_called_class();
			if (!isset($GLOBALS['NUTSHELL_MODEL'][$className]))
			{
				$instance=new static();
				if (method_exists($instance,'init'))
				{
					call_user_func_array(array($instance,'init'),$args);
				}
				$GLOBALS['NUTSHELL_MODEL'][$className]=$instance;
			}
			return $GLOBALS['NUTSHELL_MODEL'][$className];
		}
	}
}
?>