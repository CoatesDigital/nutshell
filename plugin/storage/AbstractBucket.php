<?php
/**
 * @package nutshell-plugin
 * @author Timothy Chandler<tim.chandler@spinifexgroup.com>
 */
namespace nutshell\plugin\storage
{
	use nutshell\core\plugin\PluginExtension;
	use nutshell\plugin\storage\exception\StorageException;
	
	/**
	 * @package nutshell-plugin
	 * @author Timothy Chandler<tim.chandler@spinifexgroup.com>
	 */
	abstract class AbstractBucket extends PluginExtension
	{
		abstract public function put($location=array(),$contents=null,$append=false);
		abstract public function get($location=array());
		abstract public function delete($location=array());
		abstract public function copy($fromLocation=array(),$toLocation,$autoCreateDir=false);
		abstract public function move($fromLocation=array(),$toLocation,$autoCreateDir=false);
		
		private $basePath		=null;
		private $storagePattern	='$file';
		
		public function __construct($path)
		{
			parent::__construct();
			$this->setPath($path);
		}
		
		public function setPath($path)
		{
			$path=str_replace('\\','/',$path);
			if (substr($path,-1,1)!='/')
			{
				$path.='/';
			}
			$this->basePath=$path;
			return $this;
		}
		
		public function getPath()
		{
			if ($this->basePath)
			{
				return $this->basePath;
			}
			else
			{
				throw new StorageException(StorageException::PATH_NOT_SET, 'Path has not been set.');
			}
		}
		
		public function getParsedPath($location)
		{
			return $this->getPath().$this->parseStoragePattern($location);
		}
		
		public function setStoragePattern($pattern)
		{
			$this->storagePattern=(string)$pattern;
			return $this;
		}
		
		public function getStoragePattern()
		{
			return $this->storagePattern;
		}
		
		public function parseStoragePattern($keyVals)
		{
			$pattern=$this->getStoragePattern();
			foreach ($keyVals as $key=>$value)
			{
				$pattern=str_replace('$'.$key,$value,$pattern);
			}
			return $pattern;
		}
	}
}
?>
