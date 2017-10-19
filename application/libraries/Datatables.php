<?php

	/**
	 * Ignited Datatables
	 *
	 * This is a wrapper class/library based on the native Datatables server-side implementation by Allan Jardine
	 * found at http://datatables.net/examples/data_sources/server_side.html for CodeIgniter
	 *
	 * @package    CodeIgniter
	 * @subpackage libraries
	 * @category   library
	 * @version    2.0.1 <beta>
	 * @author     Vincent Bambico <metal.conspiracy@gmail.com>
	 *             Yusuf Ozdemir <yusuf@ozdemir.be>
	 * @link       http://ellislab.com/forums/viewthread/160896/
	 */

	if (!defined('BASEPATH')) {
		exit('No direct script access allowed');
	}

	class Datatables {

		/**
		 * Global container variables for chained argument results
		 */

		private $ci;
		private $table;
		private $distinct;
		private $count_column;
		private $calc_total	= false;
		private $group_by	= array();
		private $select		= array();
		private $joins		= array();
		private $columns	= array();
		private $where		= array();
		private $or_where	= array();
		private $where_in	= array();
		private $like		= array();
		private $or_like	= array();
		private $filter		= array();
		private $add_columns	= array();
		private $edit_columns	= array();
		private $unset_columns	= array();
		private $oselect	= '';

		/**
		 * Copies an instance of CI
		 */

		public function __construct() {

			$this->ci	=& get_instance();

		}

		/**
		 * If calc_total is TRUE, the class queries the number of records twice.
		 * Once without filtering (recordsTotal) and once with filtering (recordsFiltered).
		 * Usually, recordsTotal is used to display something like "Showing 1 to 20 of 3000 entries (filtered from 10000 total entries)"
		 * If you aren't interested in the "(filtered from 10000 total entries)"-part, set calc_total to FALSE and it will set recordsTotal to 
		 * the same value as recordsFiltered.
		 *
		 * Default is FALSE because it can have a huge negative performance impact if you set it to TRUE.
		 *
		 * @since 2.0.1
		 *
		 * @param bool $calc_total
		 * @return mixed
		 */

		public function set_calc_total($calc_total = false) {

			$this->calc_total	= $calc_total;
			return $this;

		}

		/**
		 * If you establish multiple databases in config/database.php this will allow you to
		 * set the database (other than $active_group) - more info: https://www.codeigniter.com/userguide3/database/connecting.html#connecting-to-multiple-databases
		 *
		 * @param mixed $db_name
		 */

		public function set_database($db_name) {

			$db_data	= $this->ci->load->database($db_name, true);
			$this->ci->db	= $db_data;

		}

		/**
		 * Generates the SELECT portion of the query
		 *
		 * @since 2.0.1 Changed some variable names for consistency
		 *
		 * @param string $columns
		 * @param bool $escape
		 * @return mixed
		 */

		public function select($columns, $escape = true) {

			foreach ($this->explode(',', $columns) as $val) {

				$column			= trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $val));
				$column			= preg_replace('/.*\.(.*)/i', '$1', $column);			// get name after `.`

				$this->columns[]	=  $column;

				$this->select[$column]	=  trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));

			}

			$this->ci->db->select($columns, $escape);
			$this->oselect	= $columns;

			return $this;

		}

		/**
		 * Generates the DISTINCT portion of the query
		 *
		 * @param string $column
		 * @return mixed
		 */

		public function distinct($column) {

			$this->distinct		= $column;
			$this->ci->db->distinct($column);

			return $this;

		}

		/**
		 * Generates a custom GROUP BY portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param string $value
		 * @param bool $escape
		 * @return mixed
		 */

		public function group_by($value, $escape = true) {

			$this->group_by[]	= array('value'		=> $value,
							'escape'	=> $escape);

			$this->ci->db->group_by($value, $escape);

			return $this;

		}

		/**
		 * Generates the FROM portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param string $table
		 * @return mixed
		 */

		public function from($table) {

			$this->ci->db->from($table);								// Issue #78, fix by oobi
			$this->table			= $table;

			return $this;

		}

		/**
		 * Generates the JOIN portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param string $table
		 * @param string $cond
		 * @param string $type
		 * @param null|mixed $escape
		 * @return mixed
		 */

		public function join($table, $cond, $type = null, $escape = null) {

			$this->joins[]		= array('table'		=> $table,
							'cond'		=> $cond,
							'type'		=> $type,
							'escape'	=> $escape);

			$this->ci->db->join($table, $cond, $type, $escape);

			return $this;

		}

		/**
		 * Generates the WHERE portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param mixed $key
		 * @param string $value
		 * @param bool $escape
		 * @return mixed
		 */

		public function where($key, $value = null, $escape = true) {

			$this->where[]		= array('key'		=> $key,
							'value'		=> $value,
							'escape'	=> $escape);

			$this->ci->db->where($key, $value, $escape);

			return $this;

		}

		/**
		 * Generates the WHERE portion of the query
		 *
		 * @param mixed $key
		 * @param string $value
		 * @param bool $escape
		 * @return mixed
		 */

		public function or_where($key, $value = null, $escape = true) {

			$this->or_where[]	= array('key'		=> $key,
							'value'		=> $value,
							'escape'	=> $escape);

			$this->ci->db->or_where($key, $value, $escape);

			return $this;

		}

		/**
		 * Generates the WHERE IN portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param mixed $key
		 * @param string $value
		 * @param bool $escape
		 * @return mixed
		 */

		public function where_in($key, $value = null, $escape = true) {

			$this->where_in[]	= array('key'		=> $key,
							'value'		=> $value,
							'escape'	=> $escape);

			$this->ci->db->where_in($key, $value, $escape);

			return $this;

		}

		/**
		 * Generates the WHERE portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param mixed $key
		 * @param string $value
		 * @param bool $escape
		 * @return mixed
		 */

		public function filter($key, $value = null, $escape = true) {

			$this->filter[]		= array('key'		=> $key,
							'value'		=> $value,
							'escape'	=> $escape);

			return $this;

		}

		/**
		 * Generates a %LIKE% portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param mixed $field
		 * @param string $match
		 * @param mixed $side
		 * @param bool $escape
		 * @return mixed
		 */

		public function like($field, $match = '', $side = 'both', $escape = NULL) {

			$this->like[]		= array('field'		=> $field,
							'match'		=> $match,
							'side'		=> $side,
							'escape'	=> $escape);

			$this->ci->db->like($field, $match, $side, $escape);

			return $this;

		}

		/**
		 * Generates the OR %LIKE% portion of the query
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param mixed $field
		 * @param string $match
		 * @param mixed $side
		 * @param bool $escape
		 * @return mixed
		 */

		public function or_like($field, $match = '', $side = 'both', $escape = NULL) {

			$this->or_like[]	= array('field'		=> $field,
							'match'		=> $match,
							'side'		=> $side,
							'escape'	=> $escape);

			$this->ci->db->or_like($field, $match, $side, $escape);

			return $this;

		}

		/**
		 * Sets the column used for counting improving performance
		 *
		 * @param string $column
		 * @return mixed
		 */

		public function set_count_column($column) {

			$this->count_column	= $column;

			return $this;

		}

		/**
		 * Sets additional column variables for adding custom columns
		 *
		 * @since 2.0.1 Changed all variable names and switched to meaningful array-keys
		 *
		 * @param string $column
		 * @param string $content
		 * @param mixed $match_replacement
		 * @return mixed
		 */

		public function add_column($column, $content, $match_replacement = null) {

			$this->add_columns[$column]	= array('content'	=> $content,
								'replacement'	=> $this->explode(',', $match_replacement)	);

			return $this;

		}

		/**
		 * Sets additional column variables for editing columns
		 *
		 * @param string $column
		 * @param string $content
		 * @param mixed $match_replacement
		 * @return mixed
		 */

		public function edit_column($column, $content, $match_replacement) {

			$this->edit_columns[$column][]	= array('content'	=> $content,
								'replacement'	=> $this->explode(',', $match_replacement)	);

			return $this;

		}

		/**
		 * Unset column
		 *
		 * @param  string $column
		 * @return mixed
		 */

		public function unset_column($column) {

			$column				= array_flip(explode(',', $column));			// Issue #66, fix by ajindam
			$this->unset_columns		= array_merge($this->unset_columns, $column);

			return $this;

		}

		/**
		 * Builds all the necessary query segments and performs the main query based on results set from chained statements
		 *
		 * @param  string $output
		 * @param  string $charset
		 * @return string
		 */

		public function generate($output = 'json', $charset = 'UTF-8') {

			if (trim(strtolower($output)) == 'json') {
				$this->get_paging();
			}

			$this->get_ordering();
			$this->get_filtering();

			return $this->produce_output($output, $charset);

		}

		/**
		 * Generates the LIMIT portion of the query
		 *
		 * @return mixed
		 */

		private function get_paging() {

			$iStart		= $this->ci->input->post('start');
			$iLength	= $this->ci->input->post('length');

			if ($iLength != '' && $iLength != '-1') {
				$this->ci->db->limit($iLength, ($iStart) ? $iStart : 0);
			}

		}

		/**
		 * Generates the ORDER BY portion of the query
		 *
		 * @return mixed
		 */

		private function get_ordering() {

			$data	= $this->ci->input->post('columns');

			if ($this->ci->input->post('order')) {

				foreach ($this->ci->input->post('order') as $key) {

					if ($this->check_cType()) {

						$this->ci->db->order_by($data[$key['column']]['data'], $key['dir']);

					} else {

						$this->ci->db->order_by($this->columns[$key['column']], $key['dir']);

					}

				}

			}

		}

		/**
		 * Generates a %LIKE% portion of the query
		 *
		 * @return mixed
		 */

		private function get_filtering() {

			$mColArray	= $this->ci->input->post('columns');

			$sWhere		= '';
			$search		= $this->ci->input->post('search');
			$sSearch	= $this->ci->db->escape_like_str(trim($search['value']));

			$columns	= array_values(array_diff($this->columns, $this->unset_columns));

			if ($sSearch != '') {

				for ($i = 0; $i < count($mColArray); $i++) {

					if ($mColArray[$i]['searchable'] == 'true' && !array_key_exists($mColArray[$i]['data'], $this->add_columns)) {

						if ($this->check_cType()) {

							$sWhere	.= $this->select[$mColArray[$i]['data']]." LIKE '%".$sSearch."%' OR ";

						} else {

							$sWhere	.= $this->select[$this->columns[$i]]." LIKE '%".$sSearch."%' OR ";

						}

					}

				}

			}

			$sWhere	= substr_replace($sWhere, '', -3);

			if ($sWhere != '') {
				$this->ci->db->where('('.$sWhere.')');
			}

			// TODO : sRangeSeparator

			foreach ($this->filter as $val) {
				$this->ci->db->where($val['key'], $val['value'], $val['escape']);
			}

		}

		/**
		 * Compiles the select statement based on the other functions called and runs the query
		 *
		 * @since 2.0.1 Removed table-name because of issue #78, fix by oobi
		 *
		 * @return mixed
		 */

		private function get_display_result() {

			return $this->ci->db->get();

		}

		/**
		 * Builds an encoded string data. Returns JSON by default, and an array of aaData if output is set to raw.
		 *
		 * @since 2.0.1 output and charset are now only "sanitized" in this function and nowhere else
		 *
		 * @param  string $output
		 * @param  string $charset
		 * @return mixed
		 */

		private function produce_output($output, $charset) {
			
			// Initialize Variables

			$aaData		= array();
			$output		= trim(strtolower($output));
			$charset	= trim(strtolower($charset));
			$rResult	= $this->get_display_result();

			if ($output == 'json') {
				
				// Count records if necessary

				$iFilteredTotal	= $this->get_total_results(true);

				if ($this->calc_total === true) {
					$iTotal		= $this->get_total_results();
				} else {
					$iTotal		= $iFilteredTotal;
				}

			}
			
			// Process return-data

			foreach ($rResult->result_array() as $row_key => $row_val) {

				$aaData[$row_key]	= ($this->check_cType()) ? $row_val : array_values($row_val);

				foreach ($this->add_columns as $field => $val) {

					if ($this->check_cType()) {

						$aaData[$row_key][$field]	= $this->exec_replace($val, $aaData[$row_key]);

					} else {

						$aaData[$row_key][]		= $this->exec_replace($val, $aaData[$row_key]);

					}

				}

				foreach ($this->edit_columns as $modkey => $modval) {

					foreach ($modval as $val) {

						$aaData[$row_key][($this->check_cType()) ? $modkey : array_search($modkey, $this->columns)]	= $this->exec_replace($val, $aaData[$row_key]);

					}

				}

				$aaData[$row_key]	= array_diff_key($aaData[$row_key], ($this->check_cType()) ? $this->unset_columns : array_intersect($this->columns, $this->unset_columns));

				if (!$this->check_cType()) {

					$aaData[$row_key]	= array_values($aaData[$row_key]);

				}

			}

			if ($output == 'json') {

				$sOutput	= array('draw'			=> intval($this->ci->input->post('draw')),
							'recordsTotal'		=> $iTotal,
							'recordsFiltered'	=> $iFilteredTotal,
							'data'			=> $aaData		);

				if ($charset == 'utf-8') {
					return json_encode($sOutput);
				} else {
					return $this->jsonify($sOutput);
				}

			} else {

				return array('aaData' => $aaData);

			}

		}

		/**
		 * Get result count
		 *
		 * @since 2.0.1 First attempt at fixing the poor performance for big tables.
		 *              Takes some ideas from https://github.com/IgnitedDatatables/Ignited-Datatables/pull/122 and
		 *              https://github.com/IgnitedDatatables/Ignited-Datatables/pull/94
		 *
		 * @param  mixed   $filtering
		 * @return integer
		 */

		private function get_total_results($filtering = false) {

			// Set FROM early so table aliases are respected - Issue #78, fix by oobi
			$this->ci->db->from($this->table);

			if ($filtering) {
				$this->get_filtering();
			}

			foreach ($this->joins as $val) {
				$this->ci->db->join($val['table'], $val['cond'], $val['type'], $val['escape']);
			}

			foreach ($this->where as $val) {
				$this->ci->db->where($val['key'], $val['value'], $val['escape']);
			}

			foreach ($this->or_where as $val) {
				$this->ci->db->or_where($val['key'], $val['value'], $val['escape']);
			}

			foreach ($this->where_in as $val) {
				$this->ci->db->where_in($val['key'], $val['value'], $val['escape']);
			}

			foreach ($this->group_by as $val) {
				$this->ci->db->group_by($val['value'], $val['escape']);
			}

			foreach ($this->like as $val) {
				$this->ci->db->like($val['field'], $val['match'], $val['side'], 'escape');
			}

			foreach ($this->or_like as $val) {
				$this->ci->db->or_like($val['field'], $val['match'], $val['side'], 'escape');
			}

			if (strlen($this->distinct) > 0) {

				$this->ci->db->distinct($this->distinct);
				$this->ci->db->select($this->oselect);

			} else {
				
				if (strlen($this->count_column) > 0) {
					
					$this->ci->db->select('COUNT('.$this->count_column.') AS num_rows');
					$query	= $this->ci->db->get(null, null, null, false);
					$res	= $query->result();
					
					return $res[0]->num_rows;

				} else {

					$this->ci->db->select($this->oselect);
				
				}


			}			
			
			$subquery	= $this->ci->db->get_compiled_select();
			$countingsql	= "SELECT COUNT(*) FROM (" . $subquery . ") SqueryAux";
			$query		= $this->ci->db->query($countingsql);
			$result		= $query->row_array();
			$count		= $result['COUNT(*)'];
			
			return $count;

		}

		/**
		 * Runs callback functions and makes replacements
		 *
		 * @param  mixed  $custom_val
		 * @param  mixed  $row_data
		 * @return string $custom_val['content']
		 */

		private function exec_replace($custom_val, $row_data) {

			$replace_string			= '';

			// Go through our array backwards, else $1 (foo) will replace $11, $12 etc with foo1, foo2 etc
			$custom_val['replacement']	= array_reverse($custom_val['replacement'], true);

			if (isset($custom_val['replacement']) && is_array($custom_val['replacement'])) {

				//Added this line because when the replacement has over 10 elements replaced the variable "$1" first by the "$10"
				$custom_val['replacement']	= array_reverse($custom_val['replacement'], true);

				foreach ($custom_val['replacement'] as $key => $val) {

					$sval			= preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($val));

					if (preg_match('/(\w+::\w+|\w+)\((.*)\)/i', $val, $matches) && is_callable($matches[1])) {

						$func			= $matches[1];
						$args			= preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[,]+/", $matches[2], 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

						foreach ($args as $args_key => $args_val) {

							$args_val		= preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($args_val));
							$args[$args_key]	= (in_array($args_val, $this->columns)) ? ($row_data[($this->check_cType()) ? $args_val : array_search($args_val, $this->columns)]) : $args_val;

						}

						$replace_string		= call_user_func_array($func, $args);

					} elseif (in_array($sval, $this->columns)) {

						$replace_string		= $row_data[($this->check_cType()) ? $sval : array_search($sval, $this->columns)];

					} else {

						$replace_string		= $sval;

					}

					$custom_val['content']	= str_ireplace('$'.($key + 1), $replace_string, $custom_val['content']);

				}

			}

			return $custom_val['content'];

		}

		/**
		 * Check column type -> numeric or column name
		 *
		 * @since 2.0.1 Added caching to $column to prevent calling CodeIgniter's XSS-protection over and over
		 * @since 2.0.0
		 *
		 * @return bool
		 */

		private function check_cType() {

			static $column;

			if (is_null($column)) {
				$column	= $this->ci->input->post('columns');
			}

			if (is_numeric($column[0]['data'])) {

				return false;

			} else {

				return true;

			}

		}

		/**
		 * Return the difference of open and close characters
		 *
		 * @param string $str
		 * @param string $open
		 * @param string $close
		 * @return int $retval
		 */

		private function balanceChars($str, $open, $close) {

			$openCount	= substr_count($str, $open);
			$closeCount	= substr_count($str, $close);

			$retval		= $openCount - $closeCount;

			return $retval;

		}

		/**
		 * Explode, but ignore delimiter until closing characters are found
		 *
		 * @param string $delimiter
		 * @param string $str
		 * @param string $open
		 * @param string $close
		 * @return mixed $retval
		 */

		private function explode($delimiter, $str, $open = '(', $close=')') {

			$retval		= array();
			$hold		= array();
			$balance	= 0;
			$parts		= explode($delimiter, $str);

			foreach ($parts as $part) {

				$hold[]		= $part;
				$balance	+= $this->balanceChars($part, $open, $close);

				if ($balance < 1) {

					$retval[]	= implode($delimiter, $hold);
					$hold		= array();
					$balance	= 0;

				}

			}

			if (count($hold) > 0) {

				$retval[]	= implode($delimiter, $hold);

			}

			return $retval;

		}

		/**
		 * Workaround for json_encode's UTF-8 encoding if a different charset needs to be used
		 *
		 * @param mixed $result
		 * @return string
		 */

		private function jsonify($result = false) {

			if (is_null($result)) {
				return 'null';
			}

			if ($result === false) {
				return 'false';
			}

			if ($result === true) {
				return 'true';
			}

			if (is_scalar($result)) {

				if (is_float($result)) {

					return floatval(str_replace(',', '.', strval($result)));

				}

				if (is_string($result)) {

					static $jsonReplaces	= array(array('\\', '/', '\n', '\t', '\r', '\b', '\f', '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));

					return '"'.str_replace($jsonReplaces[0], $jsonReplaces[1], $result).'"';

				} else {

					return $result;

				}

			}

			$isList		= true;

			for ($i=0, reset($result); $i<count($result); $i++, next($result)) {

				if (key($result) !== $i) {

					$isList	= false;

					break;

				}

			}

			$json	= array();

			if ($isList) {

				foreach ($result as $value) {

					$json[]		= $this->jsonify($value);

				}

				return '['.join(',', $json).']';

			} else {

				foreach ($result as $key => $value) {

					$json[]		= $this->jsonify($key).':'.$this->jsonify($value);

				}

				return '{'.join(',', $json).'}';

			}

		}

		/**
		 * Returns the sql statement of the last query run
		 *
		 * @return type
		 */

		public function last_query() {

			return $this->ci->db->last_query();

		}

	}

/* End of file Datatables.php */
/* Location: ./application/libraries/Datatables.php */