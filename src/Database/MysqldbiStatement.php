<?php

namespace vxPHP\Database;

/**
 * extends prepared statements
 */
class MysqldbiStatement extends \mysqli_stmt {

	protected $varsBound = FALSE;
	protected $results;

	/**
	 * Constructor wrapper
	 *
	 * @param Mysqldbi $link;
	 * @param string $query; the query of the prepared statement
	 */
	public function __construct(Mysqldbi $link, $query) {
		parent::__construct($link, $query);
	}

	/**
	 * provides fetch_assoc() for prepared statements
	 *
	 * @return NULL or associative array
	 */
	public function fetch_assoc() {
		if(!$this->varsBound) {
			$meta = $this->result_metadata();
			while(($col = $meta->fetch_field())) {
				$varsToBind[] = &$this->results[$col->name];
			}
			call_user_func_array(array($this, 'bind_result'), $varsToBind);
			$this->varsBound = TRUE;
		}

		if($this->fetch() === NULL) {
			return NULL;
		}

		$r = array();

		foreach($this->results as $k => $v) {
			$r[$k] = $v;
		}

		return $r;
	}
}