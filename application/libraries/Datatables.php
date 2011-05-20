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
  * @version    0.4
  * @author     Vincent Bambico <metal.conspiracy@gmail.com>
  *             Yusuf Ozdemir <yusuf@ozdemir.be>
  * @link       http://codeigniter.com/forums/viewthread/160896/
  */
  class Datatables
  {
    /**
    * Global container variables for chained argument results
    *
    * @global object $ci
    * @global string $table
    * @global mixed $columns
    * @global string $index
    * @global mixed $joins
    * @global string $join_table
    * @global mixed $custom_columns
    * @global string $filter
    * @global string $aliased_tables
    * @global mixed $referenced_columns
    */
    protected $ci;
    protected $table;
    protected $columns;
    protected $index;
    protected $joins;
    protected $custom_columns;
    protected $filter;
    protected $aliased_tables;
    protected $referenced_columns;  

    /**
    * Copies an instance of CI
    */
    public function __construct()
    {
      $this->ci =& get_instance();
    }

    /**
    * Sets the table, columns, and default index column in which data will be fetched from
    *
    * @return string
    */
    public function get($table, $columns, $index = NULL)
    {
      $this->table = $table;
      $this->columns = explode(',', str_replace(' ', '', $columns));
      $this->index = ($index == NULL)? $this->columns[0] : $index;
      return $this;
    }

    /**
    * Sets which columns to fetch from the specified table
    *
    * @return string
    */
    public function select($columns)
    {
      // todo : if columns is empty, return error..
      $this->columns = explode(',', str_replace(' ', '', $columns));
      return $this;
    }

    /**
    * Sets the primary table in which data will be fetched from
    *
    * @return string
    */
    public function from($table)
    {
      $this->table = $table;
      return $this;
    }

    /**
    * Sets the default index column to be used
    *
    * @return string
    */
    public function using($index = NULL)
    {
      $this->index = ($index == NULL)? $this->columns[0] : $index;
      return $this;
    }

    /**
    * Sets join statement variables
    *
    * @return mixed
    */
    public function join($table, $columns, $fk)
    {
      $this->joins[$table]['columns'] = $columns;
      $this->joins[$table]['fk'] = $fk;
      return $this;
    }

    /**
    * Sets additional column variables to facilitate custom columns
    *
    * @return mixed
    */
    public function add_column($column, $content, $match_replacement = NULL)
    {
      $this->custom_columns[$column] = array($content, $match_replacement);
      return $this;
    }

    /**
    * Sets filtering variable to facilitate custom filters
    *
    * @return string
    */
    public function where($condition)
    {
      $this->filter = $condition;
      return $this;
    }

    /**
    * Builds all the necessary query segments and performs the main query based on results set from chained statements
    *
    * @return string
    */
    public function generate()
    {
      $this->aliased_tables = $this->get_aliased_tables();
      $this->referenced_columns = $this->get_referenced_columns();
      $sLimit = $this->get_paging();
      $sOrder = $this->get_ordering();
      $sWhere = $this->get_filtering();
      $rResult = $this->get_display_data($sWhere, $sOrder, $sLimit);
      $rResultFilterTotal = $this->get_data_set_length();
      $aResultFilterTotal = $rResultFilterTotal->result_array();
      $iFilteredTotal = $aResultFilterTotal[0]['FOUND_ROWS()'];
      $rResultTotal = $this->get_total_data_set_length($sWhere);
      $aResultTotal = $rResultTotal->result_array();
      $iTotal = $aResultTotal[0]["COUNT($this->table.$this->index)"];
      return $this->produce_output($iTotal, $iFilteredTotal, $rResult);
    }

    /**
    * Creates a pagination query segment
    *
    * @return string
    */
    protected function get_paging()
    {
      $sLimit = '';

      if($this->ci->input->post('iDisplayStart') && $this->ci->input->post('iDisplayLength') != '-1')
        $sLimit = 'LIMIT ' . $this->ci->input->post('iDisplayStart') . ', ' . $this->ci->input->post('iDisplayLength');
      else
      {
        $iDisplayLength = $this->ci->input->post('iDisplayLength');
        $sLimit = (empty($iDisplayLength))? 'LIMIT 0,10' : 'LIMIT 0,' . $iDisplayLength;
      }

      return $sLimit;
    }

    /**
    * Creates a sorting query segment
    *
    * @return string
    */
    protected function get_ordering()
    {
      $sOrder = '';

      if($this->ci->input->post('iSortCol_0') != NULL)
      {
        $sColArray = ($this->ci->input->post('sColumns'))? explode(',', $this->ci->input->post('sColumns')) : $this->referenced_columns;

        for($i = 0; $i < intval($this->ci->input->post('iSortingCols')); $i++)
          if($sColArray[intval($this->ci->input->post('iSortCol_' . $i))] && in_array($sColArray[intval($this->ci->input->post('iSortCol_' . $i))], $this->referenced_columns))
            $sOrder .= $sColArray[intval($this->ci->input->post('iSortCol_' . $i))] . ' ' . $this->ci->input->post('sSortDir_' . $i) . ', ';

        $sOrder = ($sOrder == '') ? '' : 'ORDER BY ' . $sOrder;
        $sOrder = substr_replace($sOrder, '', -2);
      }

      return $sOrder;
    }

    /**
    * Creates a filtering query segment
    *
    * @return string
    */
    protected function get_filtering()
    {
      $sWhere = '';

      if(isset($this->joins) && is_array($this->joins))
      {
        $sWhere = 'WHERE ';

        foreach($this->joins as $jt_col_key => $jt_col_val)
          $sWhere .= $jt_col_val['fk'] . ' AND ';

        $sWhere = substr_replace($sWhere, '', -4);
      }

      if($this->ci->input->post('sSearch') != '')
      {
        $sWhere .= (isset($this->joins) && is_array($this->joins))? ' AND ' : 'WHERE ';
        $sWhere .= '(';

        $sColArray = ($this->ci->input->post('sColumns'))? explode(',', $this->ci->input->post('sColumns')) : $this->referenced_columns;

        for($i = 0; $i < count($sColArray); $i++)
          if($this->ci->input->post('bSearchable_' . $i) == 'true')
            if($sColArray[$i] && in_array($sColArray[$i], $this->referenced_columns))
              $sWhere .= $sColArray[$i] . " LIKE '%" . mysql_real_escape_string($this->ci->input->post('sSearch')) . "%' OR ";

        $sWhere = substr_replace($sWhere, '', -3);
        $sWhere .= ')';
      }

      for($i = 0; $i < count($this->referenced_columns); $i++)
      {
        if($this->ci->input->post('bSearchable_' . $i) == 'true' && $this->ci->input->post('sSearch_' . $i) != '')
        {
          $sWhere .= ($sWhere == '')? 'WHERE ' : ' AND ';
          $sWhere .= $this->referenced_columns[$i] . " LIKE '%" . mysql_real_escape_string($this->ci->input->post('sSearch_' . $i)) . "%' ";
        }
      }

      if(isset($this->filter) && $this->filter != NULL)
      {
        $sWhere .= ($sWhere == '')? 'WHERE ' : ' AND ';
        $sWhere .= $this->filter;
      }

      return $sWhere;
    }

    /**
    * Combines all created query segments to build the main query
    *
    * @param string $sWhere
    * @param string $sOrder
    * @param string $sLimit
    * @return object
    */
    protected function get_display_data($sWhere, $sOrder, $sLimit)
    {
      return $this->ci->db->query
      ("
        SELECT SQL_CALC_FOUND_ROWS " . str_replace(' , ', ' ', implode(', ', $this->referenced_columns)) . "
        FROM $this->aliased_tables
        $sWhere
        $sOrder 
        $sLimit
      ");
    }

    /**
    * Gets all matched rows
    *
    * @return object
    */
    protected function get_data_set_length()
    {
      return $this->ci->db->query('SELECT FOUND_ROWS()');
    }

    /**
    * Gets the count of all rows found
    *
    * @param string $sWhere
    * @return string
    */
    protected function get_total_data_set_length($sWhere)
    {
      return $this->ci->db->query
      ("
        SELECT COUNT($this->table.$this->index)
        FROM $this->aliased_tables
        $sWhere
      ");
    }

    /**
    * Creates a query segment with table references for column names
    *
    * @return string
    */
    protected function get_referenced_columns()
    {
      foreach($this->columns as $column)
        $tabledotcolumn[] = $this->table . '.' . $column;

      if(is_array($this->joins) && count($this->joins) > 0)
        foreach($this->joins as $jointable_key => $jointable)
          foreach($jointable['columns'] as $jcolumn_key => $jcolumn_val)
            $tabledotcolumn[] = $jointable_key . '.' . $jcolumn_val;

      return $tabledotcolumn;
    }

    /**
    * Creates a query segment with aliased table names
    *
    * @param mixed $jointables
    * @return string
    */
    protected function get_aliased_tables()
    {
      $tables = $this->table;

      if(is_array($this->joins) && count($this->joins) > 0)
        foreach($this->joins as $jointable_key => $jointable)
          $tables .= ', ' . $jointable_key;

      return $tables;
    }

    /**
    * Builds a JSON encoded string data
    *
    * @param string $iTotal
    * @param string $iFilteredTotal
    * @param string $rResult
    * @return string
    */
    protected function produce_output($iTotal, $iFilteredTotal, $rResult)
    {
      $aaData = array();
      $sColumnOrder = '';

      foreach($rResult->result_array() as $row_key => $row_val)
      {
        foreach($row_val as $col_key => $col_val)
        {
          if($row_val[$col_key] == 'version')
            $aaData[$row_key][$col_key] = ($aaData[$row_key][$col_key] == 0)? '-' : $col_val;
          else
            $aaData[$row_key][] = $col_val;
        }

        if(isset($this->custom_columns) && is_array($this->custom_columns))
        {
          foreach($this->custom_columns as $cus_col_key => $cus_col_val)
          {
            if(isset($cus_col_val[1]) && is_array($cus_col_val[1]))
            {
              foreach($cus_col_val[1] as $cus_colr_key => $cus_colr_val)
                $cus_col_val[0] = str_ireplace('$' . ($cus_colr_key + 1), $aaData[$row_key][array_search($cus_colr_val, $this->referenced_columns)], $cus_col_val[0]);

              $aaData[$row_key][] = $cus_col_val[0];
            }
            else
              $aaData[$row_key][] = $cus_col_val[0];
          }
        }
      }

      foreach($this->referenced_columns as $col_key => $col_val)
        $sColumnOrder .= $col_val . ',';

      if(isset($this->custom_columns) && is_array($this->custom_columns))
        foreach($this->custom_columns as $cus_col_key => $cus_col_val)
          $sColumnOrder .= $cus_col_key . ',';

      $sColumnOrder = substr_replace($sColumnOrder, '', -1);

      $sOutput = array
      (
        'sEcho'                => intval($this->ci->input->post('sEcho')),
        'iTotalRecords'        => $iTotal,
        'iTotalDisplayRecords' => $iFilteredTotal,
        'aaData'               => $aaData,
        'sColumns'             => $sColumnOrder
      );

      return json_encode($sOutput);
    }
  }
/* End of file Datatables.php */
/* Location: ./application/libraries/Datatables.php */