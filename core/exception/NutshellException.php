<?php
/**
 * @package nutshell
 * @author Dean Rather
 */
namespace nutshell\core\exception
{
	use nutshell\Nutshell;
	use nutshell\core\Component;
	use nutshell\core\exception\ConfigException;
	use \Exception;
	
	class NutshellException extends Exception
	{
		/*
		 * Error Codes
		 */
		
		/** The default error code. Please don't use this. Define your own error codes in your Exception Class */
		const GENERIC_ERROR				= 0;
		
		/** A regular PHP error */
		const PHP_ERROR					= 1;
		
		/** A Fatal php error */
		const PHP_FATAL_ERROR			= 2;
		
		const INVALID_APP_PATH			= 10;
		const INVALID_PROPERTY			= 11;
		const READ_ONLY					= 12;
		
		/*
		 * Instance Properties
		 */
		
		public $codeDescription	= '';
		public $code			= '';
		public $codeNumber		= 0;
		public $debug			= '';
		
		/**
		 * Receives an Error Code, and optionally one or many debug variables.
		 * The error code is for displaying to the user and identifying the exception type within the system
		 * The debug variables are for display in dev mode, and for logging
		 */
		public function __construct($code=0, $debug=null)
		{
			$debug = func_get_args();
			
			/**
			 * If they didn't pass in an exception code:
			 * treat all input as debug info,
			 * set exception code to 0
			 */
			if(!is_int($code))
			{
				$code = self::GENERIC_ERROR;
			}
			else
			{
				array_shift($debug);
			}
			
			// Get the code's description using Reflection
			$reflection = new \ReflectionClass($this);
			$constants = $reflection->getConstants();
			$this->codeDescription = array_search($code, $constants);
			
			// Alter the code to be prefixed with the class name
			$this->codeNumber = $code;
			$this->code = $this->getCodePrefix().'-'.$code;
			
			// store the debug info
			$this->debug = $debug;
		}
		
		
		/*
		 * Static Attributes
		 */
		
		
		/**
		* Prevents recursion.
		* @var bool
		*/
		private static $blockRecursion = false;
		
		/**
		 * Error handler used before the class was created.
		 * @var bool
		 */
		private static $oldErrorHandler = false;
		
		/**
		 * Exception handler used before the class was created.
		 * @var bool
		 */
		private static $oldExceptionHandler = false;
		
		
		
		/*
		 * Member Functions
		 */
		
		public function getCodeNumber()
		{
			return $this->codeNumber;
		}
		
		/**
		 * Gets the prefix to be used on error codes.
		 * If this is a NutshellException it will return Nutshell
		 * If this is a BTLException it will return BTL
		 * Doesn't return the namespace part
		 */
		private function getCodePrefix()
		{
			$className = get_class($this);
			$className = explode('\\', $className);
			$className = array_pop($className);
			$className = str_replace('Exception', '', $className);
			return $className;
		}
		
		
		/**
		 * Logs the result of getDescription()
		 */
		public function log()
		{
			self::logMessage($this->getDescription());
		}
	
		
		/**
		 * Generates a nice desription of exception
		 * Good for returning to the client (in dev mode) or logging.
		 * @param Exception $exception the exception 
		 * @param String $format html or json
		 */
		public function getDescription($format='html')
		{
			// args passed into errant function
			$args = null;
			$trace = $this->getTrace();
			if(
				isset($trace[0])
				&& isset($trace[0]['args'])
				&& isset($trace[0]['args'][4])
			)
			{
				$args = $trace[0]['args'][4];
				$args = self::slice_array_depth($args, 3);
			}
			
			$description = array
			(
				'ERROR'				=> true,
				'CLASS'				=> $this->getClassHierarchy($this),
				'CODE'				=> $this->code,
				'CODE_DESCRIPTION'	=> $this->codeDescription,
				'FILE'				=> $this->file,
				'LINE'				=> $this->line,
				'DEBUG'				=> $this->debug,
				'STACK'				=> "\n".$this->getTraceAsString(),
				'SERVER'			=> $_SERVER,
				'POST'				=> $_POST,
				'GET'				=> $_GET,
				'ARGS'				=> $args,
				'RAW'				=> Nutshell::getInstance()->request->getRaw()
			);
			
			if($format=='array')
			{
				$description['STACK'] = $this->getTrace();
			}
			elseif($format=='json')
			{
				$description = json_encode($description);
			}
			elseif($format=='html')
			{
				// Just to make them take up less lines...
				$description['SERVER'] = json_encode($description['SERVER']);
				$description['DEBUG'] = json_encode($description['DEBUG']);
				$description['ARGS'] = json_encode($description['ARGS']);
				$description['POST'] = json_encode($description['POST']);
				$description['GET'] = json_encode($description['GET']);
				$description['CLASS'] = json_encode($description['CLASS']);
				
				$description = '<pre>'.print_r($description, true).'</pre>';
			}
			else
			{
				// Display in a sensible way for logging.
				$temp = array();
				foreach($description as $key => $val)
				{
					$temp[] = "$key: $val";
				}
				$description = implode("\n", $temp);
			}
			
			return $description;
		}
		
		/**
		 * Thanks http://stackoverflow.com/a/4209217/14966
		 * @param  [type] $object [description]
		 * @return [type]         [description]
		 */
		function getClassHierarchy($object)
		{
			if (!is_object($object)) return false;
			$hierarchy = array();
			$class = get_class($object);
			do {
				$hierarchy[] = $class;
			} while (($class = get_parent_class($class)) !== false);
			return $hierarchy;
		}
		
		/**
		 * Takes a multi-dimensional array and returns it with all nodes after a certain depth clipped.
		 * Great for dumping recursive or huge arrays.
		 * If passed an object, casts it to an array.
		 * @param  array   $array the multidimensional array
		 * @param  integer $depth how many nodes deep are allowed
		 * @return array          the new multidimensional array
		 */
		private static function slice_array_depth($array, $depth=0)
		{
			// If it's an object, cast it to an array
			if(is_object($array)) $array = (array)$array;
			
			foreach($array as $key => $value)
			{
				// If it's an object, cast it to an array
				if(is_object($value)) $value = (array)$value;
				
				// If it's an array
				if(is_array($value))
				{
					// This node is an array, and we're permitted to go deeper
					if($depth > 0)
					{
						// Replace this node with a sliced version of itself, which can only go one step deeper than this
						$array[$key] = self::slice_array_depth($value, $depth - 1);
					}
					else // This node is an array, and we're at our depth
					{
						$array[$key] = 'Clipped';
					}
				}
				else // This node is not an array, add it
				{
					$array[$key] = $value;
				}
			}
			return $array;
		}
		
		/*
		 * Static Methods
		 */
		
		public static function register() 
		{
			Component::load(array());
		}
		
		
		/**
		 * Logs a message if Nutshell has a loader.
		 * @param string $message
		 */
		public static function logMessage($message)
		{
			if (!strlen($message)) return;
			try
			{
				$nutInst = Nutshell::getInstance();
				if ($nutInst->hasPluginLoader())
				{
					$log = $nutInst->plugin->Logger();
					$log->fatal($message); // todo, sometimes it's not 'fatal'
				} 
				else 
				{
					user_error("Failed to load logger: $message", E_USER_ERROR);
				}
			}
			catch (Exception $e) 
			{
				//falling back to the system logger
				error_log($message);
			}
		}
		
		/**
		 * This method treats (and logs) errors.
		 * @param int $errno
		 * @param string $errstr
		 * @param string $errfile
		 * @param int    $errline
		 * @param array $errcontext
		 */
		public static function treatError($errno, $errstr = null, $errfile = null, $errline = null, array $errcontext = null)
		{
			$exception = new NutshellException(self::PHP_ERROR, $errstr);
			$exception->code = $errno;
			$exception->file = $errfile;
			$exception->line = $errline;
			self::treatException($exception);
		}
		
		/**
		 * This method is called when an exception happens.
		 * @param Exception $exception
		 */
		public static function treatException($exception, $format='html')
		{
			if (!self::$blockRecursion)
			{
				self::$blockRecursion = true;
				
				// Create the message
				if($exception instanceof ConfigException)
				{
					header('HTTP/1.1 500 Config Exception');
					die('ERROR: ' . $exception->getCode() .' '. $exception->debug[0]);
				}
				elseif($exception->code == E_STRICT)
				{
					// The logger fails to load in the case of a "<function name>  should be compatible with that of <parent function name>" error
					// Not dying here, 'cause these can be logged. Will roll through again and cause a recursive error error.
					$message = 'ERROR: ' . $exception->getCode() .' '. $exception->debug[0];
				}
				elseif($exception instanceof LoggerException)
				{
					header('HTTP/1.1 500 Logger Exception');
					die('ERROR: ' . $exception->getCode() .' '. $exception->debug[0]);
				}
				elseif($exception instanceof NutshellException)
				{
					$message = $exception->getDescription($format);
				}
				else
				{
					$message = "NON NUTSHELL EXCEPTION! ";
					$message .= $exception->getTraceAsString();
					$message .= nl2br($exception);
				}
				
				// Log the message
				self::logMessage($message);
				
				// Echo the message
				if(Nutshell::getInstance()->config->application->mode=='development')
				{
					header('HTTP/1.1 500 Application Error');
					echo $message;
				}
			}
			else // Recursion
			{
				// Echo the message
				header('HTTP/1.1 500 Recursive Error Error');
				$message = 'ERROR: ' . $exception->getCode() .' '. $exception->debug[0];
				die($message);
			}
		}
		
		/**
		 * This function is called when PHP is shutting down.
		 * It will get called after a fatal error, so we'll try and handle that here.
		 * You can't really handle fatal errors, but we can try, and might at least
		 * get a log out of it.
		 */
		public static function shutdown()
		{ 
			$error=error_get_last();
			if($error)
			{
				self::treatError
				(
					self::PHP_FATAL_ERROR,
					$error['message'],
					$error['file'],
					$error['line']
				);
			}
		}
		
		/**
		 * This function sets exception/error handlers. Before this call, no error is treated by this class.
		 * All errors are logged.
		 * Errors are shown in the user interface only if NS_ENV (environment variable) is set to "dev". So, errors won't be shown in production.
		 * 
		 * Sets the default Exception Handler to treatException()
		 * Sets the default Error Handler to treatError()
		 */
		public static function setHandlers()
		{
			self::$oldExceptionHandler = set_exception_handler('nutshell\core\exception\NutshellException::treatException');
			self::$oldErrorHandler = set_error_handler('nutshell\core\exception\NutshellException::treatError');
			register_shutdown_function('nutshell\core\exception\NutshellException::shutdown'); 
		}
	}
}
