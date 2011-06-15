<?php
namespace nutshell\plugin\logger\writer
{
	use nutshell\plugin\logger\Level;
	
	class Pattern
	{
		const LOG_LEVEL = '%P';
		
		const MESSAGE = '%m';
		
		const DATE = '%d';
		
		const NEW_LINE = '%n';
		
		const PERCENT = '%%';
		
		const EXTRA_BLOCK_START = '-- DATA START --';
		
		const EXTRA_BLOCK_END = '-- DATA END --';
		
		private static $contextTopatterns = array(
			Writer::CTX_LOG_LEVEL => self::LOG_LEVEL,
		);
		
		/**
		 * 
		 * @param String $out
		 */
		protected static function conditionalNewLine($out)
		{
			//inserts a new line if none is present at the end
			if (substr($out, -1, 1) != "\n") 
			{
				return "\n";
			}
			
			return '';
		}
		
		/**
		 * 
		 * Enter description here ...
		 * @param String $pattern
		 * @param mixed $msg
		 * @param array $context
		 * @return String the computed string
		 */
		public static function resolve($pattern, $msg, $context, $extraData)
		{
			$context = self::filterContext($context);
			$context = self::extendContext(self::convertToString($msg), $context);
			$out = str_replace(array_keys($context), array_values($context), $pattern);
			
			//processes extra data for debug
			if(!is_null($extraData)) 
			{
				$out .= self::conditionalNewLine($out) . self::EXTRA_BLOCK_START . "\n";
				
				if(is_object($extraData) && $extraData instanceof \Exception) 
				{
					$out .= $extraData->__toString();
				}
				else {
					$out .= print_r($extraData, true);
				}
				
				$out .= self::conditionalNewLine($out) . self::EXTRA_BLOCK_END . "\n";
			}
			return $out;
		}
		
		/**
		 * Convert the argument to a safe string representation
		 * 
		 * @param mixed $msg
		 * @return String 
		 */
		protected static function convertToString($msg)
		{
			if(is_null($msg))
			{
				return '';
			}
			elseif(is_array($msg)) 
			{
				return print_r($msg, true);
			}
			
			return $msg;
		}
		
		/**
		 * 
		 * @param String $msg
		 * @param array $context
		 */
		protected static function extendContext($msg, array $context)
		{
			$context[self::MESSAGE] = $msg;
			$context[self::NEW_LINE] = "\n";
			$context[self::DATE] = date('c');
			$context[self::PERCENT] = '%';
			if(array_key_exists(self::LOG_LEVEL, $context))
			{
				$context[self::LOG_LEVEL] = Level::toString($context[self::LOG_LEVEL]);
			}
			return $context;
		}
	
		
		/**
		 * 
		 * 
		 * @param array $context
		 */
		protected static function filterContext(array $context)
		{
			foreach($context as $key => $value)
			{
				if(array_key_exists($key, self::$contextTopatterns))
				{
					$final[self::$contextTopatterns[$key]] = $value;
				}
			}
			
			return $final;
		}
	}
}