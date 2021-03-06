<?php
/**
 * @package nutshell-plugin
 * @author Timothy Chandler <tim.chandler@spinifexgroup.com>
 */
namespace nutshell\plugin\mvcCrud
{
	use nutshell\plugin\mvc\Model;
	/**
	 * @author Timothy Chandler <tim.chandler@spinifexgroup.com>
	 * @package nutshell-plugin
	 */
	class MySQL extends Model
	{
		public $dbName		=null;	  //dbName
		public $name	   =null;     // table name
		public $primary	   =array();  // array with primary keys.
		public $primary_ai =true;     // is the pk auto increment? Only works if count($primary) == 1
		public $columns	   =array();  // array with columns
		public $autoCreate =true;     // should create the table if it doesn't exist?
		
		protected $types	            =array();
		protected $columnNames        =array(); // stores column names
		
		// the following to 4 properties are intended to speed up query building.
		protected $columnNamesListStr        ='';       // stores column names list separated by ','.
		protected $defaultInsertColumns      = array(); // when the primary key is auto increment, the primary key isn't present as an insert column
		protected $defaultInsertColumnsStr   = '';
		protected $defaultInsertPlaceHolders ='';       // part of an insert statement.		
		
		public function __construct()
		{
			parent::__construct();
			
			$this->configure();				
			
			if (!empty($this->name) && (count($this->primary)>0) && !empty($this->columns))
			{
				if (isset($this->db))
				{
					// only creates the table when $autoCreate is true & This is a mySQL database
					if ($this->autoCreate && stristr(get_class($this->db), 'mysql')) 
					{
						$this->createTable();
					}
				}
				else
				{
					throw new Exception('DB connection not defined in config.');
				}
			}
			else
			{
				throw new Exception('CRUD Model is misconfigured. Name, Primary Key and Columns must be defined.');
			}
		}
		
		protected function configure() {
			if (!is_array($this->primary)){
				throw new Exception('Primary Key has to be an array.');
			}
				
			$this->columnNames = array_keys($this->columns);
			$this->columnNamesListStr = '`'. implode('`,`', $this->columnNames) . '`';
				
			// doesn't make much sense inserting an auto increment column using default settings.
			if (($this->primary_ai) && (count($this->primary)==1))
			{
				$this->defaultInsertColumns = array_diff($this->columnNames, $this->primary);
			} else {
				$this->defaultInsertColumns = &$this->columnNames;
			}
				
			$this->defaultInsertColumnsStr = '`' . implode('`,`',$this->defaultInsertColumns) . '`';
			$this->defaultInsertPlaceHolders = rtrim(str_repeat('?,',count($this->defaultInsertColumns)),',');
		}
		
		protected function createTable() {
			$columns=array();
			if ((count($this->primary)==1) && ($this->primary_ai))
			{
				$localAutoCreate = ' AUTO_INCREMENT ';
			} else {
				$localAutoCreate = '';
			}
			
			foreach ($this->columns as $name=>$typeDef)
			{
				if ($this->isPrimaryKey($name))
				{
					$columns[]			= '`' . $name.'` '.$typeDef.' NOT NULL '.$localAutoCreate;
				}
				else
				{
					$columns[]			= '`' . $name.'` '.$typeDef.' NULL';
				}
			}
			$columns=implode(',',$columns);
			
			//Create the table (if it doesn\'t already exist).
			$dbPrefix = $this->getDbPrefix();
			$query=
							" CREATE TABLE IF NOT EXISTS {$dbPrefix}`{$this->name}`
							(
{$columns},
							PRIMARY KEY (`".implode('`,`',$this->primary)."`)
							) ENGINE=INNODB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_bin
";
			
			$this->db->query($query);
		}
		
		/**
		 * Inserts a record into the database.
		 * When $fields are defined, this is the list of fields to be used when inserting.
		 * This function returns the assigned PK when using auto increment.
		 * @param array $record
		 * @param array $fields
		 * @return int
		 */
		public function insert(Array $record, Array $fields=array())
		{
			// all fields (default) ?
			if (count($fields)==0)
			{
				// this part of the code is intended to be fast.
				$placeholders	=&$this->defaultInsertPlaceHolders;
				$keys			=&$this->defaultInsertColumnsStr;
			} else {
				$placeholders = rtrim(str_repeat('?,',count($record)),',');
				$keys         = '`' . implode('`,`',$fields) . '`';
			}
			
			$dbPrefix = $this->getDbPrefix();
			$query=
<<<SQL
			INSERT INTO {$dbPrefix}`{$this->name}`
				({$keys})
			VALUES
				({$placeholders});
SQL;
die($query);
			return $this->db->insert($query,$record);
		}
		
		/**
		 * This function inserts a record such as ( 'key' => 'value', 'key2' => 'value2', ... );
		 * This function returns the assigned PK when using auto increment.
		 * @param array $record
		 * @return int
		 */
		public function insertAssoc(Array $record)
		{
			return $this->insert(array_values($record), array_keys($record));
		}
		
		/**
		 * Reads rows from the database.
		 * If $whereKeyVals isn't given, reads all rows.
		 * If $readColumns isn't given, read all columns.
		 * @param array  $whereKeyVals
		 * @param array  $readColumns
		 * @param string $additionalPartSQL
		 * @return Array
		 */
		public function read($whereKeyVals = array(), $readColumns = array(), $additionalPartSQL='')
		{
			$whereKeySQL = '';
			$whereKeyValues = array();
				
			// is there a "where"?
			if (count($whereKeyVals)>0)
			{
				// filters by the given where
				$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues);
				$whereKeySQL = " WHERE ".$whereKeySQL;
			} 
			
			// are columns to be read defined?
			if (count($readColumns)>0) 
			{
				// reads only selected columns
				$columnsSQL = '`' . implode('`,`', $readColumns) . '`';
			} else {
				// reads all columns
				$columnsSQL = $this->columnNamesListStr;
			}
			
			$dbPrefix = $this->getDbPrefix();
			$query = " SELECT {$columnsSQL} FROM {$dbPrefix}`{$this->name}` {$whereKeySQL} {$additionalPartSQL} ";
			
			return $this->db->getResultFromQuery($query,$whereKeyValues);
		}
		
		/**
		 * Return a string serving as a prefix for the table name in the query. If the dbName property in the class is set to null or an empty
		 * value, then no prefix will be generated and the query will run on the currently selected database.
		 * @return string
		 */
		protected function getDbPrefix() {
			return $this->dbName ? '`' . $this->dbName . '`.' : '';
		}

		/**
		 * This method creates the where part of a query. It returns the sql string ($whereKeySQL) and their values ($whereKeyValues).
		 * @param array $whereKeyVals
		 * @param string $whereKeySQL
		 * @param array $whereKeyValues
		 * @return string
		 */
		protected function getWhereSQL($whereKeyVals, &$whereKeySQL, &$whereKeyValues)
		{
			$whereKeySQL = array();
			$whereKeyValues = array();
			
			//Quick update is possible if $whereKeyVals is numeric and PK is composed by only one column.
			if (is_numeric($whereKeyVals) && (count($this->primary)==1))
			{
				$whereKeySQL = " `{$this->primary[0]}` = ? ";
				$whereKeyValues[] = (int) $whereKeyVals;
			}
			//More specific keyval matching.
			else if (is_array($whereKeyVals))
			{
				$where=array();
				foreach ($whereKeyVals as $key=>$value)
				{
					$where[]= '`' . $key . '` = ?';
					$whereKeyValues[] = $value;
				}
				$whereKeySQL=implode(' AND ',$where);
			}
			else
			{
		 		throw new Exception('$whereKeyVals is invalid. Specify an array of key value pairs or a single numeric for primay key match.');
			}
		}
		
		public function update($updateKeyVals,$whereKeyVals)
		{
			//Create the set portion of the query.
			$set=array();
			foreach (array_keys($updateKeyVals) as $key)
			{
				$set[] = '`' . $key . '` = ?';
			}
			$set=implode(',',$set);

			$whereKeySQL = '';
			$whereKeyValues = array();
			$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues);
	
			$dbPrefix = $this->getDbPrefix();
			$query=<<<SQL
			UPDATE {$dbPrefix}`{$this->name}`
			SET {$set}
			WHERE {$whereKeySQL};
SQL;
			return $this->db->update($query,array_merge(array_values($updateKeyVals),$whereKeyValues));
		}
		
		/**
		 * Deletes records filtering by $whereKeyVals.
		 * @param mixed $whereKeyVals
		 */
		public function delete($whereKeyVals)
		{
			$whereKeySQL = '';
			$whereKeyValues = array();
			$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues);
			
			$dbPrefix = $this->getDbPrefix();
			$query=" DELETE FROM {$dbPrefix}`{$this->name}` WHERE {$whereKeySQL} ";
			 
			return $this->db->delete($query,$whereKeyValues);
		}
	
		/**
		 * This function returns TRUE if $field_name is a primary key.
 		 * @param string $field_name
		 * @return bool
		 */
		public function isPrimaryKey($field_name)
		{
			return in_array($field_name, $this->primary);
		}
	}
}
?>