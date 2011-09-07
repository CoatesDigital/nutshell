<?php
/**
 * @package nutshell
 */
namespace nutshell\core\loader
{
	use nutshell\Nutshell;

	use nutshell\helper\Object;

	use nutshell\core\exception\Exception;

	use nutshell\core\Component;
	use nutshell\plugin;
	
	/**
	 * Configuration node instance
	 * 
	 * @author guillaume
	 * @package nutshell
	 */
	class Loader extends Component
	{
		/**
		 * A list of valid containers and their paths.
		 * 
		 * @access private
		 * @var Array
		 */
		private $containers=array();
		
		/**
		 * Class name of a given key.
		 * @var Array
		 */
		private $classNames=array();
		
		/**
		 * Interface of a given key.
		 */
		private $interfaces=array();
		
		/**
		 * A container of loaded classes.
		 * 
		 * @var Array
		 */
		private $loaded		=array
		(
			'plugin'		=>array()
		);
		
		public static function autoload($className)
		{
			$namespace=Object::getNamespace($className);
			$className=Object::getBaseClassName($className);
			//Check for a plugin behaviour.
			if (strstr($namespace,'behaviour\\'))
			{
				list(,,$plugin)	=explode('\\',$namespace);
				$pathSuffix		='plugin'._DS_.$plugin._DS_.'behaviour'._DS_.$className.'.php';
				if (is_file($file=NS_HOME.$pathSuffix))
				{
					//Invoke the plugin.
					Nutshell::getInstance()->plugin->{ucfirst($plugin)};
				}
				else if (is_file($file=APP_HOME.$pathSuffix))
				{
					Nutshell::getInstance()->plugin->{ucfirst($plugin)};
				}
				else
				{
					throw new Exception('Unable to autoload class "'.$namespace.$className.'".');
				}
			}
		}
		
		/**
		 * 
		 */
		public static function register()
		{
			static::load(array());
		}
		
		public function __construct()
		{
			spl_autoload_register(__NAMESPACE__ .'\Loader::autoload');
		}
		
		public function registerContainer($name,$path,$namespace)
		{
			$this->containers[$name]=array
			(
				'path'		=>$path,
				'namespace'	=>$namespace
			);
		}
		
		private function loadClassDependencies($classname)
		{
			if($interfaces = class_implements($classname, false))
			{
				//Load class dependencies
				if (in_array('nutshell\behaviour\Native', $interfaces))
				{
					$classname::loadDependencies();
					$classname::registerBehaviours();
				}	
			}
			return $interfaces;
		}
		
		private function doLoad($key,Array $args=array())
		{
			//Is the object not loaded?
			if (!isset($this->loaded[$key]))
			{
				foreach($this->containers as $containerKey => &$container)
				{
					//No, so we need to load all of it's dependancies and initiate it.
					$dirBase=$container['path'];
					$namespaceBase=$container['namespace'];
					
					if (is_file($dirBaseFolderFile=$dirBase.lcfirst($key)._DS_.$key.'.php'))
					{
						require($dirBaseFolderFile);
						$this->classNames[$key] = $namespaceBase.lcfirst($key).'\\'.$key;
						$this->interfaces[$key] = $this->loadClassDependencies($this->classNames[$key]);
						break;
					}
					else if (is_file($dirBaseFile=$dirBase.$key.'.php'))
					{
						require($dirBaseFile);
						$this->classNames[$key] = $namespaceBase.$key;
						$this->interfaces[$key] = $this->loadClassDependencies($this->classNames[$key]);
						break;
					}
					// is it a folder with other components?
					else if (is_dir($dir=$dirBase.$key))
					{
						$localInstance = new Loader;
						$localInstance->registerContainer($key,$dir._DS_,$namespaceBase.$key.'\\');
						$this->classNames[$key] = get_class($this);
						$this->loaded[$key]=$localInstance;
						break;
					}
				}// end of foreach
				
				if(!isset($this->classNames[$key]))
				{
					throw new Exception("Loader can't load key {$key}.");
				}
			}

			// is it a loader?
			if ( $this->classNames[$key] == get_class($this) )
			{
				//returns the loader
				return $this->loaded[$key];
			}
						
			$className  = $this->classNames[$key];
			$interfaces = $this->interfaces[$key];
			
			if (in_array('nutshell\behaviour\Loadable', $interfaces))
			{
				#Initiate
				$localInstance      = $className::getInstance($args);
				$this->loaded[$key] = $localInstance;
				return $localInstance;
			}
			else
			{
				throw new Exception('Loader failed to load class "'.$className.'". This is likely because the container '
									.'handle you\'re using to handle the loading with doesn\'t impement the "Loadable" behaviour.');
			}
		}

		public function __get($key)
		{
			return $this->doLoad($key);
		}
		
		public function __call($key,$args)
		{
			return $this->doLoad($key,$args);
		}
	}
}