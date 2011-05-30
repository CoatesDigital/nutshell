<?php
class DB
{
	/**
	 * @var PDO
	 * @access private
	 */
	private $connection	=null;
	
	/**
	 * @var array
	 * @access private
	 */
	private $lastQuery=array
	(
		'statement'		=>false,
		'params'		=>false,
		'sql'			=>false,
		'resultSet'		=>false,
		'numResults'	=>0,
		'affectedRows'	=>0,
		'lastError'		=>false
	);
	
	/**
	 * Class constructor.
	 * 
	 * @param $host - Hostname or IP address to connect to.
	 * @param $port - The port number to connect with.
	 * @param $database - The name of the database which all transactions will run through.
	 * @param $username - The username to connect with.
	 * @param $password - The password to connect with.
	 * @return void
	 */
	public function __construct($host='localhost',$port=null,$database=null,$username=null,$password='')
	{
		if (!empty($host))
		{
			if ($host=='localhost')$host='127.0.0.1';
			try
			{
				$this->connection=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$database,$username,$password);
			}
			catch(PDOException $exception)
			{
				throw new Exception($exception->getMessage());
			}
		}
		else
		{
			$args=func_get_args();
			throw new Exception('Unable to establish connection to database. Invalid details given to class constructor. '.print_r($args,true));
		}
		return true;
	}
	
	/**
	 * Overloader method for dealing with a case where the PDO object is lost on a variable.
	 * 
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		$this->disconnect();
	}
	
	/**
	 * Disconnects the database connection.
	 * 
	 * @access public
	 * @return $this
	 */
	public function disconnect()
	{
//		$this->connection=null;//PDO closes connection if connection is destructed.
		unset($this->connection);
		return $this;
	}
	
	/**
	 * Checks whether the DB manager has secured a valid DB connection or not.
	 * 
	 * @return boolean true if a connection to the server could be established, false otherwise
	 */
	public function isConnected() {
		return !is_null($this->connection);
	}
	
	/**
	 * Resets the last query array.
	 * 
	 * @access private
	 * @return $this
	 */
	private function resetLastQueryParams()
	{
		if ($this->lastQuery['statement'] instanceof PDOStatement)
		{
			$this->lastQuery['statement']->closeCursor();
		}
		unset($this->lastQuery);
		$this->lastQuery=array
		(
			'statement'		=>false,
			'params'		=>false,
			'sql'			=>false,
			'resultSet'		=>false,
			'numResults'	=>0,
			'affectedRows'	=>0
		);
		return $this;
	}
	
	/**
	 * The main internal method for executing the different types of statements.
	 * 
	 * @access private
	 * @param $type - The type of statement to execute.
	 * @param $args - Any arguments to pass to the statement (these are used as bound parameters). 
	 * @return PDOStatement
	 */
	public function executeStatement($type='query',$args=array(),$usePrepared=true)
	{
		$this->resetLastQueryParams();
		$return=false;
		if (!empty($args[0]))
		{
			if ($args[0]!=$this->lastQuery['sql'] || !$usePrepared)
			{
				$this->lastQuery['sql']=$args[0];
				$this->lastQuery['statement']=$this->connection->prepare($args[0],array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));
			}
		}
		else if (empty($args[0]) && empty($this->lastQuery['sql']))
		{
			throw new Exception('Unable to execute statement. Query was empty!');
		}
		if (isset($args[1]))
		{
			if (!is_array($args[1]))
			{
				$this->lastQuery['params']=&$args;
				for ($i=1,$j=count($args); $i<$j; $i++)
				{
					$this->lastQuery['statement']->bindParam($i,$args[$i]);
				}
			}
			else
			{
				$i=1;
				$this->lastQuery['params']=&$args[1];
				foreach ($args[1] as &$val)
				{
					$this->lastQuery['statement']->bindParam($i,$val);
					$i++;
				}
			}
		}
//		var_dump($this->lastQuery);
		if ($this->lastQuery['statement']->execute())
		{
			$this->lastQuery['resultSet']		=$this->lastQuery['statement']->fetchAll();
			$this->lastQuery['numResults']		=count($this->lastQuery['resultSet']);
			$this->lastQuery['affectedRows']	=0;
			switch ($type)
			{
				case 'query':
				{
					$this->lastQuery['affectedRows']=$this->lastQuery['statement']->rowCount();
					if (stristr($this->lastQuery['sql'],'SELECT'))
					{
						$return=$this->lastQuery['numResults'];
					}
					else
					{
						$return=$this->lastQuery['affectedRows'];
					}
					break;
				}
				case 'select':
				{
					$return=$this->lastQuery['numResults'];
					break;
				}
				case 'insert':
				{
					$this->lastQuery['affectedRows']=$this->lastQuery['statement']->rowCount();
					$this->lastQuery['insertId']	=$this->connection->lastInsertId();
					if ($this->lastQuery['insertId']!=='0')
					{
						$return=$this->lastQuery['insertId'];
					}
					else
					{
						$return=true;
					}
					break;
				}
				case 'update':
				{
					$this->lastQuery['affectedRows']=$this->lastQuery['statement']->rowCount();
					$return							=$this->lastQuery['affectedRows'];
					break;
				}
				case 'delete':
				{
					$this->lastQuery['affectedRows']=$this->lastQuery['statement']->rowCount();
					$return							=$this->lastQuery['affectedRows'];
					break;
				}
			}
		}
		@list(,,$this->lastQuery['lastError'])		=$this->lastQuery['statement']->errorInfo();
		return $return;
	}
	
	/**
	 * Executes any given query.
	 * 
	 * @access public
	 * @param ... Parameters to bind.
	 * @return PDOStatement
	 */
	public function query()
	{
		$args=func_get_args();
		return $this->executeStatement('query',$args);
	}
	
	/**
	 * Executes an insert query.
	 * 
	 * @access public
	 * @param ... Parameters to bind.
	 * @return mixed - Either a PDOStatement or the number of affected rows.
	 */
	public function insert()
	{
		$args=func_get_args();
		return $this->executeStatement('insert',$args);
	}
	
	/**
	 * Executes a select query.
	 * 
	 * @access public
	 * @return mixed - Either a PDOStatement or the number of results in the selection.
	 */
	public function select()
	{
		$args=func_get_args();
		return $this->executeStatement('select',$args);
	}
	
	/**
	 * Executes an update query.
	 * 
	 * @access public
	 * @return Mixed - Either a PDOStatement or the number of affected rows.
	 */
	public function update()
	{
		$args=func_get_args();
		return $this->executeStatement('update',$args);
	}
	
	/**
	 * Executes a delete query.
	 * 
	 * @access public
	 * @return mixed - Either a PDOStatement or the number of affected rows.
	 */
	public function delete()
	{
		$args=func_get_args();
		return $this->executeStatement('delete',$args);
	}
	
	/**
	 * Returns the last SQL Error generated from whatever query caused it.
	 * 
	 * @access public
	 * @return mixed
	 */
	public function getLastError()
	{
		return $this->lastQuery['lastError'];
	}
	
	/**
	 * Returns the affected rows count of the lasts executed query.
	 * 
	 * @access public
	 * @return mixed - NULL if there has not been a query executed. A number otherwise.
	 */
	public function getAffectedRows()
	{
		return $this->lastQuery['affectedRows'];
	}
	
	/**
	 * Returns the number of results count of the lasts executed query.
	 * 
	 * @access public
	 * @return mixed - NULL if there has not been a query executed. A number otherwise.
	 */
	public function getNumResults()
	{
		return $this->lastQuery['numResults'];
	}
	
	/**
	 * Returns the PDOStatement from the last executed query.
	 * 
	 * @access public
	 * @return mixed - NULL if there has not been a query executed. A PDOSTatement object otherwise.
	 */
	public function getLastStatement()
	{
		return $this->lastQuery['statement'];
	}
	
	/**
	 * Returns the last query string from the last executed query.
	 * 
	 * @access public
	 * @return Mixed - NULL if there has not been a query executed. A String otherwise.
	 */
	public function getLastQuery()
	{
		return $this->lastQuery['sql'];
	}
	
	/**
	 * Returns an array containing information about the last executed query.
	 * 
	 * @access public
	 * @return Array
	 */
	public function getLastQueryObject()
	{
		return $this->lastQuery;
	}
	
	/**
	 * Returns the result of the last executed query.
	 * 
	 * @access public
	 * @param $returnFormat - The format to return the results. Valid values are: 'mixed','indexed','assoc'
	 * @return Mixed - False for no result, an array of results otherwise.
	 */
	public function result($returnFormat='mixed')
	{
		$return=false;
		if ($this->lastQuery['resultSet']===false)
		{
			if ($this->lastQuery['statement'] instanceof PDOStatement)
			{
				$this->lastQuery['resultSet']=$this->lastQuery['statement']->fetchAll();
			}
		}
		if ($this->lastQuery['resultSet']!==false)
		{
			switch ($returnFormat)
			{
				case 'indexed':
				{
					$return=array();
					for ($i=0; $i<$this->lastQuery['numResults']; $i++)
					{
						$return[$i]=array();
						foreach ($this->lastQuery['resultSet'][$i] as $key=>$resultItem)
						{
							if (is_int($key))
							{
								$return[$i][]=$resultItem;
							}
						}
					}
					break;
				}
				case 'assoc':
				{
					$return=array();
					for ($i=0; $i<$this->lastQuery['numResults']; $i++)
					{
						$return[$i]=array();
						foreach ($this->lastQuery['resultSet'][$i] as $key=>$resultItem)
						{
							if (is_string($key))
							{
								$return[$i][$key]=$resultItem;
							}
						}
					}
					break;
				}
				case 'mixed':
				default:	$return=$this->lastQuery['resultSet'];
			}
		}
		return $return;
	}

	public function truncate($table)
	{
		return $this->query('TRUNCATE TABLE '.$table);
	}
}