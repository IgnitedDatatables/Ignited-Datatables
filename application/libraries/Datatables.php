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
  * @version    0.2
  * @author     Vincent Bambico <metal.conspiracy@gmail.com>
  *             Peter Trerotola <petroz@mac.com>
  *             Yusuf Özdemir <yusuf@ozdemir.be>
  * @link       http://codeigniter.com/forums/viewthread/160896/
  */
  class Datatables
  {
    /**
    * CodeIgniter global variable
    *
    * @global object $ci
    * @name   $ci
    */
    protected $ci;

    /**
    * Copies an instance of CI
    */
    public function __construct()
    {
      $this->ci =& get_instance();
    }

    /**
    * Magic method to facilitate method overloading
    */
    public function __call($name, $args)
    {
      return call_user_func_array(array($this, $name), $args);
    }

    /**
    * Overload handler for generate method
    */
    public function generate()
    {
      $num = func_num_args();
      $args = func_get_args();

      switch($num)
      {
        case 1:
          $result = $this->__call('generate_from_sql', $args);
          break;
        case 3:
          $result = $this->__call('generate_from_tci', $args);
          break;
        case 4:
          $result = $this->__call('generate_from_join', $args);
          break;
        default;
          die('Invalid set of arguments passed.');
      }

      return $result;
    }

    /**
    * Builds all the necessary query segments and performs the main query based on passed arguments
    *
    * @param string $table
    * @param mixed $columns
    * @param string $index
    * @return string
    */
    public function generate_from_tci($table, $columns, $index)
    {
      $sLimit = $this->get_paging();
      $sOrder = $this->get_ordering($columns);
      $sWhere = $this->get_filtering($columns);
      $rResult = $this->get_display_data($table, $columns, $sWhere, $sOrder, $sLimit);
      $rResultFilterTotal = $this->get_data_set_length();
      $aResultFilterTotal = $rResultFilterTotal->result_array();
      $iFilteredTotal = $aResultFilterTotal[0]['FOUND_ROWS()'];
      $rResultTotal = $this->get_total_data_set_length($table, $index, $sWhere);
      $aResultTotal = $rResultTotal->result_array();
      $iTotal = $aResultTotal[0]['COUNT(' . $table . '.' . $index . ')'];
      return $this->produce_output($columns, $iTotal, $iFilteredTotal, $rResult);
    }

    /**
    * Builds all the necessary query segments and performs the main query based on passed arguments including join tables
    *
    * @param string $table
    * @param mixed $columns
    * @param string $index
    * @param mixed $jointables
    * @return string
    */
    public function generate_from_join($table, $columns, $index, $jointables)
    {
      $columns = $this->get_referenced_columns($columns, $table, $jointables);
      $tablenames = $this->get_aliased_tables($columns, $table, $jointables);
      $sLimit = $this->get_paging();
      $sOrder = $this->get_ordering($columns, $table);
      $sWhere = $this->get_filtering($columns, $jointables);
      $rResult = $this->get_display_data($tablenames, $columns, $sWhere, $sOrder, $sLimit);
      $rResultFilterTotal = $this->get_data_set_length();
      $aResultFilterTotal = $rResultFilterTotal->result_array();
      $iFilteredTotal = $aResultFilterTotal[0]['FOUND_ROWS()'];
      $rResultTotal = $this->get_total_data_set_length($table, $index, $sWhere, $tablenames);
      $aResultTotal = $rResultTotal->result_array();
      $iTotal = $aResultTotal[0]['COUNT(' . $table . '.' . $index . ')'];
      return $this->produce_output($columns, $iTotal, $iFilteredTotal, $rResult);
    }

    /**
    * Builds all the necessary query segments and performs the main query based on passed query
    *
    * @todo add the necessary code and additional functions to make it work
    * @param string $query
    * @return string
    */
    public function generate_from_sql($query)
    {
      //add sql mode code here
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

        if(empty($iDisplayLength))
          $sLimit = 'LIMIT 0,10';
        else
          $sLimit = 'LIMIT 0,' . $iDisplayLength;
      }

      return $sLimit;
    }

    /**
    * Creates a sorting query segment
    *
    * @param string $columns
    * @return string
    */
    protected function get_ordering($columns)
    {
      if($this->ci->input->post('iSortCol_0') != null)
      {
        $sOrder = 'ORDER BY ';

        for($i = 0; $i < intval($this->ci->input->post('iSortingCols')); $i++)
          $sOrder .= $columns[intval($this->ci->input->post('iSortCol_' . $i))] . ' ' . $this->ci->input->post('sSortDir_' . $i) . ', ';

        $sOrder = substr_replace($sOrder, '', -2);
        if($sOrder == 'ORDER BY') $sOrder = '';
      }

      return $sOrder;
    }

    /**
    * Creates a filtering query segment
    *
    * @param string $columns
    * @param mixed $jointables optional and is used for joins only
    * @return string
    */
    protected function get_filtering($columns, $jointables = null)
    {
      $sWhere = '';

      if(isset($jointables) && is_array($jointables))
      {
        $sWhere = 'WHERE ';

        foreach($jointables as $jt_col_key => $jt_col_val)
          $sWhere .= $jt_col_val['fk'] . ' AND ';

        $sWhere = substr_replace($sWhere, '', -4);
      }

      if($this->ci->input->post('sSearch') != '')
      {
        if(isset($jointables) && is_array($jointables))
          $sWhere .= ' AND ';
        else
          $sWhere .= 'WHERE ';

        $sWhere .= '(';

        for($i = 0; $i < count($columns); $i++)
          $sWhere .= $columns[$i] . " LIKE '%" . $this->ci->input->post('sSearch') . "%' OR ";

        $sWhere = substr_replace($sWhere, '', -3);
        $sWhere .= ')';
      }

      for($i = 0; $i < count($columns); $i++)
      {
        if($this->ci->input->post('bSearchable_' . $i) == 'true' && $this->ci->input->post('sSearch_' . $i) != '')
        {
          if($sWhere == '')
            $sWhere = 'WHERE ';
          else
            $sWhere .= ' AND ';

          $sWhere .= $columns[$i] . " LIKE '%" . $this->ci->input->post('sSearch_' . $i) . "%' ";
        }
      }

      return $sWhere;
    }

    /**
    * Combines all created query segments to build the main query
    *
    * @param string $table
    * @param string $columns
    * @param string $sWhere
    * @param string $sOrder
    * @param string $sLimit
    * @return object
    */
    protected function get_display_data($table, $columns, $sWhere, $sOrder, $sLimit)
    {
      return $this->ci->db->query
      ('
        SELECT SQL_CALC_FOUND_ROWS ' . str_replace(" , ", " ", implode(", ", $columns)) . '
        FROM ' . $table . '
        ' . $sWhere . '
        ' . $sOrder . '
        ' . $sLimit . '
      ');
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
    * @param string $table
    * @param string $index
    * @param string $sWhere
    * @param string $tablenames optional and is used for joins only
    * @return string
    */
    protected function get_total_data_set_length($table, $index, $sWhere, $tablenames = null)
    {
      $from = ($tablenames != null)? $tablenames : $table;

      return $this->ci->db->query
      ('
        SELECT COUNT(' . $table . '.' . $index . ')
        FROM ' . $from . '
        ' . $sWhere . '
      ');
    }

    /**
    * Creates a query segment with table references for column names
    *
    * @param mixed $columns
    * @param string $table
    * @param mixed $jointables
    * @return string
    */
    protected function get_referenced_columns($columns, $table, $jointables)
    {
      foreach($columns as $column)
        $tabledotcolumn[] = $table . '.' . $column;

      foreach($jointables as $jointable_key => $jointable)
        foreach($jointable['columns'] as $jcolumn_key => $jcolumn_val)
          $tabledotcolumn[] = $jointable_key . '.' . $jcolumn_val;

      return $tabledotcolumn;
    }

    /**
    * Creates a query segment with aliased table names
    *
    * @param mixed $columns
    * @param string $table
    * @param mixed $jointables
    * @return string
    */
    protected function get_aliased_tables($columns, $table, $jointables)
    {
      $tables = $table;

      if(is_array($jointables) && count($jointables) > 0)
        foreach ($jointables as $jointable_key => $jointable)
          $tables .= ', ' . $jointable_key;

      return $tables;
    }

    /**
    * Builds a JSON encoded string data
    *
    * @param string $columns
    * @param string $iTotal
    * @param string $iFilteredTotal
    * @param string $rResult
    * @return string
    */
    protected function produce_output($columns, $iTotal, $iFilteredTotal, $rResult)
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
          {
            /*
              you can manipulate your result data here
              like wrapping your result in additional html tags for example:

              $aaData[$row_key][] = '<span class="additionalTag">' . $col_val . '</span>';

              you can also add further logic based on column specific values
              but by default, I'm leaving it as queried
            */
            $aaData[$row_key][] = $col_val;
          }
        }

        /*
          add additional columns here
          like adding a Delete Row control for example:

          $aaData[$row_key][] = '<a href="#">Delete Button</a>';
        */
      }

      foreach($columns as $col_key => $col_val)
        $sColumnOrder .= $col_val . ',';

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