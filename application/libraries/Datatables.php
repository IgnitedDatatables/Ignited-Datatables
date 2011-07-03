<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
  /**
  * Ignited Datatables
  *
  * This is a wrapper class/library based on the native Datatables server-side implementation by Allan Jardine
  * found at http://datatables.net/examples/data_sources/server_side.html for CodeIgniter
  *
  * @package    CodeIgniter
  * @subpackage libraries
  * @category   library
  * @version    0.5.3
  * @author     Vincent Bambico <metal.conspiracy@gmail.com>
  *             Yusuf Ozdemir <yusuf@ozdemir.be>
  * @link       http://codeigniter.com/forums/viewthread/160896/
  */
  class Datatables
  {
    /**
    * Global container variables for chained argument results
    *
    */
    protected $ci;
    protected $table;
    protected $select = array();
    protected $joins = array();
    protected $columns = array();
    protected $where = array();
    protected $add_columns = array();
    protected $edit_columns = array();
    protected $unset_columns = array();

    /**
    * Copies an instance of CI
    */
    public function __construct()
    {
      $this->ci =& get_instance();
    }

    /**
    * Generates the SELECT portion of the query
    *
    * @param string $columns
    * @param bool $backtick_protect
    * @return mixed
    */
    public function select($columns, $backtick_protect = TRUE)
    {
      foreach($this->explode(',', $columns) as $val)
      {
        $column = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $val)); 
        $this->columns[] =  $column;
        $this->select[$column] =  trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
      }
      $this->ci->db->select($columns, $backtick_protect);
      return $this;
    }

    /**
    * Generates the FROM portion of the query
    *
    * @param string $table
    * @return mixed
    */
    public function from($table)
    {
      $this->table = $table;
      $this->ci->db->from($table);
      return $this;
    }

    /**
    * Generates the JOIN portion of the query
    *
    * @param string $table
    * @param string $fk
    * @param string $type
    * @return mixed
    */
    public function join($table, $fk, $type = NULL)
    {
      $this->joins[] = array($table, $fk, $type);
      $this->ci->db->join($table, $fk, $type);
      return $this;
    }

    /**
    * Generates the WHERE portion of the query
    *
    * @param mixed $key_condition
    * @param string $val
    * @param bool $backtick_protect
    * @return mixed
    */
    public function where($key_condition, $val = NULL, $backtick_protect = TRUE)
    {
      $this->where[] = array($key_condition, $val, $backtick_protect);
      $this->ci->db->where($key_condition, $val, $backtick_protect);
      return $this;
    }

    /**
    * Sets additional column variables for adding custom columns
    *
    * @param string $column
    * @param string $content
    * @param string $match_replacement
    * @return mixed
    */
    public function add_column($column, $content, $match_replacement = NULL)
    {
      $match_replacement = $this->explode(',', $match_replacement);
      array_walk($match_replacement, create_function('&$val', '$val = trim($val);'));
      $this->add_columns[$column] = array('content' => $content, 'replacement' => $match_replacement);
      return $this;
    }

    /**
    * Sets additional column variables for editing columns
    *
    * @param string $column
    * @param string $content
    * @param string $match_replacement
    * @return mixed
    */
    public function edit_column($column, $content, $match_replacement)
    {
      $match_replacement = $this->explode(',', $match_replacement);
      array_walk($match_replacement, create_function('&$val', '$val = trim($val);'));
      $this->edit_columns[$column][] = array('content' => $content, 'replacement' => $match_replacement);
      return $this;
    }

    /**
    * Unset column
    *
    * @param string $column
    * @return mixed
    */
    public function unset_column($column)
    {
      $this->unset_columns[] = $column;
      return $this;
    }

    /**
    * Builds all the necessary query segments and performs the main query based on results set from chained statements
    *
    * @return string
    */
    public function generate()
    {
      $this->get_paging();
      $this->get_ordering();
      $this->get_filtering();
      return $this->produce_output();
    }

    /**
    * Generates the LIMIT portion of the query
    *
    * @return mixed
    */
    protected function get_paging()
    {
      $iStart = $this->ci->input->post('iDisplayStart');
      $iLength = $this->ci->input->post('iDisplayLength');
      $this->ci->db->limit(($iLength != '' && $iLength != '-1')? $iLength : 10, ($iStart)? $iStart : 0);
    }

    /**
    * Generates the ORDER BY portion of the query
    *
    * @return mixed
    */
    protected function get_ordering()
    {
      if ($this->check_mDataprop())
        $sColArray = $this->get_mDataprop();
      elseif ($this->ci->input->post('sColumns'))
        $sColArray = explode(',', $this->ci->input->post('sColumns'));
      else
        $sColArray = $this->columns;

      $sColArray = array_values(array_diff($sColArray, $this->unset_columns));
      $columns = array_values(array_diff($this->columns, $this->unset_columns));

      for($i = 0; $i < intval($this->ci->input->post('iSortingCols')); $i++)
        if(isset($sColArray[intval($this->ci->input->post('iSortCol_' . $i))]) && in_array($sColArray[intval($this->ci->input->post('iSortCol_' . $i))], $columns) && $this->ci->input->post('bSortable_'.intval($this->ci->input->post('iSortCol_' . $i))) == 'true')
          $this->ci->db->order_by($sColArray[intval($this->ci->input->post('iSortCol_' . $i))], $this->ci->input->post('sSortDir_' . $i));
    }

    /**
    * Generates the LIKE portion of the query
    *
    * @return mixed
    */
    protected function get_filtering()
    {
      if ($this->check_mDataprop())
        $sColArray = $this->get_mDataprop();
      elseif ($this->ci->input->post('sColumns'))
        $sColArray = explode(',', $this->ci->input->post('sColumns'));
      else
        $sColArray = $this->columns;

      $sWhere = '';
      $sSearch = mysql_real_escape_string($this->ci->input->post('sSearch'));
      $columns = array_values(array_diff($this->columns, $this->unset_columns));

      if($sSearch != '')
        for($i = 0; $i < count($sColArray); $i++)
          if($this->ci->input->post('bSearchable_' . $i) == 'true' && in_array($sColArray[$i], $columns))
            $sWhere .= $this->select[$sColArray[$i]] . " LIKE '%" . $sSearch . "%' OR ";

      $sWhere = substr_replace($sWhere, '', -3);

      if($sWhere != '')
        $this->ci->db->where('(' . $sWhere . ')');
    }

    /**
    * Compiles the select statement based on the other functions called and runs the query
    *
    * @return mixed
    */
    protected function get_display_result()
    {
      return $this->ci->db->get();
    }

    /**
    * Builds a JSON encoded string data
    *
    * @return string
    */
    protected function produce_output()
    {
      $aaData = array();
      $rResult = $this->get_display_result();
      $iTotal = $this->get_total_results();
      $iFilteredTotal = $this->get_total_results(TRUE);

      foreach($rResult->result_array() as $row_key => $row_val)
      {
        $aaData[$row_key] = ($this->check_mDataprop())? $row_val : array_values($row_val);

        foreach($this->add_columns as $field => $val)
          if($this->check_mDataprop())
            $aaData[$row_key][$field] = $this->exec_replace($val, $aaData[$row_key]);
          else
            $aaData[$row_key][] = $this->exec_replace($val, $aaData[$row_key]);

        foreach($this->edit_columns as $modkey => $modval)
          foreach($modval as $val)
            $aaData[$row_key][($this->check_mDataprop())? $modkey : array_search($modkey, $this->columns)] = $this->exec_replace($val, $aaData[$row_key]);

        $aaData[$row_key] = array_diff_key($aaData[$row_key], ($this->check_mDataprop())? $this->unset_columns : array_intersect($this->columns, $this->unset_columns));

        if(!$this->check_mDataprop())
          $aaData[$row_key] = array_values($aaData[$row_key]);
      }

      $sColumns = $this->columns;
      $sColumns = array_diff($this->columns, $this->unset_columns);
      $sColumns = array_merge_recursive($sColumns, array_keys($this->add_columns));

      $sOutput = array
      (
        'sEcho'                => intval($this->ci->input->post('sEcho')),
        'iTotalRecords'        => $iTotal,
        'iTotalDisplayRecords' => $iFilteredTotal,
        'aaData'               => $aaData,
        'sColumns'             => implode(',', $sColumns)
      );

      return json_encode($sOutput);
    }

    /**
    * Get result count
    *
    * @return integer
    */
    protected function get_total_results($filtering = FALSE)
    {
      if($filtering)
        $this->get_filtering();

      foreach($this->joins as $val)
        $this->ci->db->join($val[0], $val[1], $val[2]);

      foreach($this->where as $val)
        $this->ci->db->where($val[0], $val[1], $val[2]);

      return $this->ci->db->count_all_results($this->table);
    }

    /**
    * Runs callback functions and makes replacements
    *
    * @param mixed $custom_val
    * @param mixed $row_data
    * @return string $custom_val['content']
    */
    protected function exec_replace($custom_val, $row_data)
    {
      $replace_string = '';

      if(isset($custom_val['replacement']) && is_array($custom_val['replacement']))
      {
        foreach($custom_val['replacement'] as $key => $val)
        {
          if(preg_match('/callback\_(\w+)\((.+)\)/i', $val, $matches))
          {
            $func = $matches[1];
            $args = preg_split('/(?<!\\\),+/', $matches[2]);
            array_walk($args, create_function('&$val', '$val = trim($val);'));
            array_walk($args, create_function('&$val', '$val = str_replace("\,", ",", $val);'));

            foreach($args as $args_key => $args_val)
              if(in_array($args_val, $this->columns))
                $args[$args_key] = $row_data[($this->check_mDataprop())? $args_val : array_search($args_val, $this->columns)];

            $replace_string = call_user_func_array($func, $args);
          }
          elseif(in_array($val, $this->columns))
            $replace_string = $row_data[($this->check_mDataprop())? $val : array_search($val, $this->columns)];
          else
            $replace_string = $val;

          $custom_val['content'] = str_ireplace('$' . ($key + 1), $replace_string, $custom_val['content']);
        }
      }

      return $custom_val['content'];
    }

    /**
    * Check mDataprop
    *
    * @return bool
    */
    protected function check_mDataprop()
    {
      if (!$this->ci->input->post('mDataProp_0')) return FALSE;

      for($i = 0; $i < intval($this->ci->input->post('iColumns')); $i++)
        if(!is_numeric($this->ci->input->post('mDataProp_' . $i)))
          return TRUE;

      return FALSE;
    }

    /**
    * Get mDataprop order
    *
    * @return mixed
    */
    protected function get_mDataprop()
    {
      $mDataProp = array();

      for($i = 0; $i < intval($this->ci->input->post('iColumns')); $i++)
        $mDataProp[] = $this->ci->input->post('mDataProp_' . $i);

      return $mDataProp;
    }

    /**
    * Return the difference of open and close characters
    *
    * @param string $str
    * @param string $open
    * @param string $close
    * @return string $retval
    */
    protected function balanceChars($str, $open, $close)
    {
      $openCount = substr_count($str, $open);
      $closeCount = substr_count($str, $close);
      $retval = $openCount - $closeCount;
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
    protected function explode($delimiter, $str, $open='(', $close=')') 
    {
      $retval = array();
      $hold = array();
      $balance = 0;
      $parts = explode($delimiter, $str);

      foreach ($parts as $part) 
      {
        $hold[] = $part;
        $balance += $this->balanceChars($part, $open, $close);
        if ($balance < 1)
        {
          $retval[] = implode($delimiter, $hold);
          $hold = array();
          $balance = 0;
        }
      }

      if (count($hold) > 0)
        $retval[] = implode($delimiter, $hold);

      return $retval;
    }
  }
/* End of file Datatables.php */
/* Location: ./application/libraries/Datatables.php */