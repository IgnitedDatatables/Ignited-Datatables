<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

error_reporting(0);

/**
 * Ignited Datatables
 *
 * This is a wrapper class/library based on the native Datatables server-side implementation by Allan Jardine
 * found at http://datatables.net/examples/data_sources/server_side.html for CodeIgniter
 *
 * This version includes preliminary but working support for iSeries/DB2 databases and query speed/logical 
 * improvements over former versions.
 *
 * @package    CodeIgniter
 * @subpackage libraries
 * @category   library
 * @version    2.1
 * @author     Vincent Bambico <metal.conspiracy@gmail.com>
 *             Yusuf Ozdemir <yusuf@ozdemir.be>
 *             Matt Parnell <mparnell@dmp.com>
 * @link       http://ellislab.com/forums/viewthread/160896/
 * @link       https://github.com/IgnitedDatatables/Ignited-Datatables
 */
class Datatables
{
    /**
     * Global container variables for chained argument results
     *
     */
    private $ci;
    private $table;
    private $distinct;
    private $group_by = [];
    private $having = [];
    private $select = [];
    private $joins = [];
    private $columns = [];
    private $where = [];
    private $or_where = [];
    private $where_in = [];
    private $like = [];
    private $or_like = [];
    private $filter = [];
    private $add_columns = [];
    private $edit_columns = [];
    private $unset_columns = [];
    private $preorder = [];
    private $noBuiltinSearch = false;
    private $debug = false;
    private $offset = 0;
    private $length = null;
    private $hasFiltered = false;
    private $fullstop = false;
    private $limit = "";
    private $transport = "POST";
    private $db;
    private $isDb2 = false;

    /**
     * Copies an instance of CI
     */
    public function __construct($config = null) {
        $this->ci =& get_instance();

        if (!empty($config['transport']) && $config['transport'] == 'GET') {
            $this->params = $this->ci->input->get();
        } else {
            $this->params = $this->ci->input->post();
        }
        $this->transport = $config['transport'];

        if (!empty($config['preorder'])) {
            $this->preorder = $config['preorder'];
        }

        if ($config['db2']) {
            $this->isDb2 = TRUE;
        }

        if (ENVIRONMENT != 'production') {
            if (!empty($config['debug'])) {
                $this->debug = (bool)$config['debug'];
            }
        }

        if (!empty($config['noBuiltinSearch'])) {
            $this->noBuiltinSearch = true;
        }

        if (isset($config['offset'])) {
            $this->offset = $config['offset'];
        }

        if (isset($config['fullstop'])) {
            $this->fullstop = $config['fullstop'];
        }

        $this->db = $this->ci->db;
    }

    /**
     * If you establish multiple databases in config/database.php this will allow you to
     * set the database (other than $active_group) - more info: http://ellislab.com/forums/viewthread/145901/#712942
     */
    public function set_database($db_name) {
        $this->db = $this->ci->load->database($db_name, true);
    }

    /**
     * Specify the datatable response data
     */
    public function set_params($params) {
        $this->params = $params;
    }

    /**
     * Generates the SELECT portion of the query
     *
     * @param string $columns
     * @param bool $backtick_protect
     * @return mixed
     */
    public function select($columns, $backtick_protect = true) {
        foreach ($this->explode(',', $columns) as $val) {
            $column = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $val));
            $column = preg_replace('/.*\.(.*)/i', '$1', $column); // get name after `.`
            $this->columns[] = $column;
            $this->select[$column] = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
        }

        $this->db->select($columns, $backtick_protect);

        return $this;
    }

    /**
     * Generates the DISTINCT portion of the query
     *
     * @param string $column
     * @return mixed
     */
    public function distinct($column) {
        $this->distinct = $column;
        $this->db->distinct($column);

        return $this;
    }

    /**
     * Generates a custom GROUP BY portion of the query
     *
     * @param string $val
     * @return mixed
     */
    public function group_by($val) {
        $this->group_by[] = $val;
        $this->db->group_by($val);

        return $this;
    }

    /**
     * Generates a custom HAVING portion of the query
     *
     * @param string $val
     * @return mixed
     */
    public function having($val) {
        $this->having[] = $val;
        $this->db->having($val);

        return $this;
    }

    /**
     * Generates the FROM portion of the query
     *
     * @param string $table
     * @return mixed
     */
    public function from($table) {
        $this->table = $table;

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
    public function join($table, $fk, $type = null) {
        $this->joins[] = [$table, $fk, $type];
        $this->db->join($table, $fk, $type);

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
    public function where($key_condition, $val = null, $backtick_protect = true) {

        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);

            if ($this->isDb2) {
                $val = "'" . $val . "'";
            }
        }

        if ($this->isDb2) {
            $backtick_protect = false;
        }

        $this->where[] = [$key_condition, $val, $backtick_protect];

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
    public function or_where($key_condition, $val = null, $backtick_protect = true) {
        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);
        }

        $this->or_where[] = [$key_condition, $val, $backtick_protect];

        return $this;
    }

    /**
     * Generates the WHERE IN portion of the query
     *
     * @param mixed $key_condition
     * @param string $val
     * @param bool $backtick_protect
     * @return mixed
     */
    public function where_in($key_condition, $val = null) {
        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);
        }

        $this->where_in[] = [$key_condition, $val];

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
    public function filter($key_condition, $val = null, $backtick_protect = true) {
        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);
        }

        $this->filter[] = [$key_condition, $val, $backtick_protect];

        return $this;
    }

    /**
     * Generates a %LIKE% portion of the query
     *
     * @param mixed $key_condition
     * @param string $val
     * @param bool $backtick_protect
     * @return mixed
     */
    public function like($key_condition, $val = null, $side = 'both') {
        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);
        }

        $this->like[] = [$key_condition, $val, $side];

        return $this;
    }

    /**
     * Generates the OR %LIKE% portion of the query
     *
     * @param mixed $key_condition
     * @param string $val
     * @param bool $backtick_protect
     * @return mixed
     */
    public function or_like($key_condition, $val = null, $side = 'both') {
        if (is_string($key_condition)) {
            $key_condition = iconv('UTF-8', 'ASCII//TRANSLIT', $key_condition);
        }

        if (is_string($val)) {
            $val = iconv('UTF-8', 'ASCII//TRANSLIT', $val);
        }

        $this->or_like[] = [$key_condition, $val, $side];

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
    public function add_column($column, $content, $match_replacement = null) {
        $this->add_columns[$column] = ['content' => $content, 'replacement' => $this->explode(',', $match_replacement)];

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
    public function edit_column($column, $content, $match_replacement) {
        $this->edit_columns[$column][] = ['content' => $content, 'replacement' => $this->explode(',', $match_replacement)];

        return $this;
    }

    /**
     * Sets row index
     *
     * @param string $field
     * @return mixed
     */
    public function set_row_index($field) {
        $this->row_index = $field;

        return $this;
    }

    /**
     * Unset column
     *
     * @param string $column
     * @return mixed
     */
    public function unset_column($column) {
        $column = explode(',', $column);
        $this->unset_columns = array_merge($this->unset_columns, $column);

        return $this;
    }

    /**
     * Builds all the necessary query segments and performs the main query based on results set from chained statements
     *
     * @param string $output
     * @param string $charset
     * @return string
     */
    public function generate($output = 'json', $charset = 'UTF-8') {
        if (strtolower($output) == 'json' || $output == 'array') {
            $this->get_paging();
        }

        $this->get_ordering();
        $this->setupUserWhere();

        return $this->produce_output(strtolower($output), strtolower($charset));
    }

    /**
     * Generates the LIMIT portion of the query
     *
     * @return mixed
     */
    private function get_paging() {
        $iStart = $this->params['start'];
        $iLength = $this->params['length'];

        if ($iLength != '' && $iLength != '-1') {
            $this->limit = "LIMIT " . $iLength . ($iStart ? ', ' . $iStart : '');
        } elseif ($this->isDb2) {
            $this->limit = "LIMIT 250";
        }

        $this->length = $iLength;
    }

    /**
     * Generates the ORDER BY portion of the query
     *
     * @return mixed
     */
    private function get_ordering() {
        $Data = $this->params['columns'];
        $this->params['order'] = $this->ci->input->post('order');
        $orderBy = "";

        if (!empty($this->preorder)) {
            $orderBy .= $this->preorder . ($this->params['order'] ? ", " : '');
        }

        if ($this->params['order']) {
            $i = 0;
            foreach ($this->params['order'] as $odr) {
                $colIdx = (int)$odr['column'] + $this->offset;
                switch (strtolower($odr['dir'])) {
                    case "desc" :
                        $dir = "desc";
                        break;
                    case "asc" :
                    default :
                        $dir = "asc";
                        break;
                }

                if ($colIdx >= 0) {
                    if ($i > 0) {
                        $orderBy .= ", ";
                    }

                    $orderBy .= " " . $Data[$colIdx]['data'] . " " . $dir;

                    $i++;
                }
            }
        }

        if ($orderBy != "") {
            $this->db->order_by($orderBy);
        }
    }

    /**
     * Generates a %LIKE% portion of the query
     *
     * @return mixed
     */
    private function get_filtering() {
        if (!$this->noBuiltinSearch && !$this->hasFiltered) {
            $mColArray = $this->ci->input->post('columns');
            $sWhere = '';
            if ($this->transport = "POST") {
                $search = $this->ci->input->post('search');
            } else {
                $search = $this->ci->input->get('search');
            }

            if (!$this->isDb2) {
                $sSearch = $this->db->escape_like_str(trim($search['value']));
            } else {
                $sSearch = $this->db2_escape($search['value']);

                // select a sample of each column
                // determine the type as date or not since db2 doesn't do LIKE with dates
                $q = $this->db->query('SELECT ' . implode(",", $this->columns) . " FROM " . $this->table . " LIMIT 10");
                if ($q) {
                    $sampleData = $q->result_array();
                    if ($sampleData) {
                        foreach ($sampleData as $sample) {
                            $i = 0;
                            foreach ($sample as $col) {
                                if ($col && empty($mColArray[$i]['type'])) {
                                    if ($this->validateDate($col)) {
                                        $mColArray[$i]['type'] = 'date';
                                    } else {
                                        $mColArray[$i]['type'] = 'normal';
                                    }
                                }
                                $i++;
                            }
                        }
                    }
                }
            }

            if (!empty($sSearch)) {
                for ($i = 0; $i < count($mColArray); $i++) {
                    if ($mColArray[$i]['searchable'] == 'true' && !array_key_exists($mColArray[$i]['data'] + $this->offset, $this->add_columns)) {
                        if (isset($this->columns[$i + $this->offset])) {
                            if (!$this->isDb2) {
                                if ($this->check_cType()) {
                                    $sWhere .= $this->select[$mColArray[$i]['data'] + $this->offset] . " LIKE '%" . $sSearch . "%' OR ";
                                } else {
                                    $sWhere .= $this->select[$this->columns[$i + $this->offset]] . " LIKE '%" . $sSearch . "%' OR ";
                                }
                            } else {
                                if ((is_numeric($sSearch) || $this->validateDate($sSearch)) && $mColArray[$i + $this->offset]['type'] == 'date') {
                                    if ($this->check_cType()) {
                                        $sWhere .= "VARCHAR_FORMAT(" . $this->select[$mColArray[$i]['data'] + $this->offset] . ", 'YYYYMMDD') = '" . $sSearch . "' OR ";
                                        $sWhere .= "VARCHAR_FORMAT(" . $this->select[$mColArray[$i]['data'] + $this->offset] . ", 'YYYYMMDD') LIKE '%" . $sSearch . "%' OR ";
                                    } else {
                                        $sWhere .= "VARCHAR_FORMAT(" . $this->select[$this->columns[$i + $this->offset]] . ", 'YYYYMMDD') = '" . $sSearch . "' OR ";
                                        $sWhere .= "VARCHAR_FORMAT(" . $this->select[$this->columns[$i + $this->offset]] . ", 'YYYYMMDD') LIKE '%" . $sSearch . "%' OR ";
                                    }
                                } else {
                                    if ($mColArray[$i + $this->offset]['type'] != 'date') {
                                        if ($this->check_cType()) {
                                            $sWhere .= $this->select[$mColArray[$i]['data'] + $this->offset] . " LIKE '%" . $sSearch . "%' OR ";
                                            $sWhere .= $this->select[$mColArray[$i]['data'] + $this->offset] . " = '" . $sSearch . "' OR ";
                                        } else {
                                            $sWhere .= $this->select[$this->columns[$i + $this->offset]] . " LIKE '%" . $sSearch . "%' OR ";
                                            $sWhere .= $this->select[$this->columns[$i + $this->offset]] . " = '" . $sSearch . "' OR ";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($sWhere != '') {
                $sWhere = substr_replace($sWhere, '', -3);
            }

            $this->hasFiltered = true;

            if ($sWhere != '') {
                return ' WHERE (' . $sWhere . ') ';
            }
        }

        return "";
    }

    private function setupUserWhere() {
        foreach ($this->like as $val) {
            $this->db->like($val[0], $val[1], $val[2]);
        }

        foreach ($this->or_like as $val) {
            $this->db->or_like($val[0], $val[1], $val[2]);
        }

        foreach ($this->filter as $val) {
            $this->db->where($val[0], $val[1], $val[2]);
        }

        foreach ($this->where as $val) {
            $this->db->where($val[0], $val[1], $val[2]);
        }

        foreach ($this->or_where as $val) {
            $this->db->or_where($val[0], $val[1], $val[2]);
        }

        foreach ($this->where_in as $val) {
            $this->db->where_in($val[0], $val[1]);
        }
    }

    /**
     * Builds an encoded string data. Returns JSON by default, and an array of aaData if output is set to raw.
     *
     * @param string $output
     * @param string $charset
     * @return mixed
     */
    private function produce_output($output, $charset) {
        $aaData = [];

        $baseSelect = $this->db->get_compiled_select($this->table);
        $filter = $this->get_filtering();

        if ($filter != "") {
            if (strpos($baseSelect, 'WHERE ') !== false) {
                $filteredSelect = str_replace('WHERE ', $filter . " AND ", $baseSelect);
            } else {
                if (strpos($baseSelect, 'ORDER BY') !== false) {
                    $filteredSelect = str_replace('ORDER BY', $filter . " ORDER BY", $baseSelect);
                } elseif (strpos($baseSelect, 'LIMIT') !== false) {
                    $filteredSelect = str_replace('LIMIT', $filter . " LIMIT", $baseSelect);
                } else {
                    $filteredSelect = $baseSelect . $filter;
                }
            }
        } else {
            $filteredSelect = $baseSelect;
        }

        if ($this->fullstop) {
            var_dump([
                $baseSelect,
                $filteredSelect
            ]);
            exit();
        }

        $rResult = $this->db->query($filteredSelect . " " . $this->limit); // filtered results

        if (is_object($rResult)) {
            $rResult = $rResult->result_array();
        } else {
            $rResult = [];
        }

        if ($output == 'json' || $output == 'array') {
            $iTotal = $this->get_total_results($baseSelect);
            $iFilteredTotal = $this->get_total_results($filteredSelect);
        }

        foreach ($rResult as $row_key => $row_val) {
            $aaData[$row_key] = ($this->check_cType()) ? $row_val : array_values($row_val);

            foreach ($this->add_columns as $field => $val) {
                if ($this->check_cType()) {
                    $aaData[$row_key][$field] = $this->exec_replace($val, $aaData[$row_key]);
                } else {
                    $aaData[$row_key][] = $this->exec_replace($val, $aaData[$row_key]);
                }
            }

            foreach ($this->edit_columns as $modkey => $modval) {
                foreach ($modval as $val) {
                    $aaData[$row_key][($this->check_cType()) ? $modkey : array_search($modkey, $this->columns)] = $this->exec_replace($val, $aaData[$row_key]);
                }
            }

            if (!empty($this->row_index)) {
                $aaData[$row_key][($this->check_cType()) ? $this->row_index : array_search($this->row_index, $this->columns)] = intval($this->params['start']) + $row_key + 1;
            }

            $aaData[$row_key] = array_diff_key($aaData[$row_key], ($this->check_cType()) ? $this->unset_columns : array_intersect($this->columns, $this->unset_columns));

            $aaData[$row_key] = array_values($aaData[$row_key]);
        }

        if ($output == 'json' || $output == 'array') {
            $sOutput = [
                'draw' => intval($this->params['draw']),
                'recordsTotal' => $iTotal,
                'recordsFiltered' => $iFilteredTotal,
                'data' => $aaData
            ];

            if ($this->debug) {
                $sOutput['baseSelect'] = trim(preg_replace('/\s+/', ' ', $baseSelect));
                $sOutput['filteredSelect'] = trim(preg_replace('/\s+/', ' ', $filteredSelect));
            }

            if ($charset == 'utf-8') {
                return ($output == 'array') ? $sOutput : json_encode($sOutput);
            } else {
                return $this->jsonify($sOutput);
            }
        } else {
            return ['aaData' => $aaData];
        }
    }

    /**
     * Get result count
     *
     * @return integer
     */
    private function get_total_results($sql = "") {
        if ($sql) {
            $query = $this->db->query("SELECT COUNT(*) CNT FROM (" . $sql . ") x");

            if (is_object($query)) {
                $result = $query->result_array();
                $count = $result[0]['CNT'];
            } else {
                $query = $this->db->query($sql);
                if ($query) {
                    $result = $query->result_array();

                    if ($result) {
                        return count($result);
                    }
                }

                $count = 0;
            }
        }

        return $count;
    }

    /**
     * Runs callback functions and makes replacements
     *
     * @param mixed $custom_val
     * @param mixed $row_data
     * @return string $custom_val['content']
     */
    private function exec_replace($custom_val, $row_data) {
        $replace_string = '';

        // Go through our array backwards, else $1 (foo) will replace $11, $12 etc with foo1, foo2 etc
        $custom_val['replacement'] = array_reverse($custom_val['replacement'], true);

        if (isset($custom_val['replacement']) && is_array($custom_val['replacement'])) {
            //Added this line because when the replacement has over 10 elements replaced the variable "$1" first by the "$10"
            $custom_val['replacement'] = array_reverse($custom_val['replacement'], true);
            foreach ($custom_val['replacement'] as $key => $val) {
                $sval = preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($val));

                if (preg_match('/(\w+::\w+|\w+)\((.*)\)/i', $val, $matches) && is_callable($matches[1])) {
                    $func = $matches[1];
                    $args = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[,]+/", $matches[2], 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

                    foreach ($args as $args_key => $args_val) {
                        $args_val = preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($args_val));
                        $args[$args_key] = (in_array($args_val, $this->columns)) ? ($row_data[($this->check_cType()) ? $args_val : array_search($args_val, $this->columns)]) : $args_val;
                    }

                    $replace_string = call_user_func_array($func, $args);
                } elseif (in_array($sval, $this->columns)) {
                    $replace_string = $row_data[($this->check_cType()) ? $sval : array_search($sval, $this->columns)];
                } else {
                    $replace_string = $sval;
                }

                $custom_val['content'] = str_ireplace('$' . ($key + 1), $replace_string, $custom_val['content']);
            }
        }

        return $custom_val['content'];
    }

    /**
     * Check column type -numeric or column name
     *
     * @return bool
     */
    private function check_cType() {
        $column = $this->params['columns'];
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
     * @return string $retval
     */
    private function balanceChars($str, $open, $close) {
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
    private function explode($delimiter, $str, $open = '(', $close = ')') {
        $retval = [];
        $hold = [];
        $balance = 0;
        $parts = explode($delimiter, $str);

        foreach ($parts as $part) {
            $hold[] = $part;
            $balance += $this->balanceChars($part, $open, $close);

            if ($balance < 1) {
                $retval[] = implode($delimiter, $hold);
                $hold = [];
                $balance = 0;
            }
        }

        if (count($hold) > 0) {
            $retval[] = implode($delimiter, $hold);
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
                static $jsonReplaces = [['\\', '/', '\n', '\t', '\r', '\b', '\f', '"'], ['\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"']];

                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $result) . '"';
            } else {
                return $result;
            }
        }

        $isList = true;

        for ($i = 0, reset($result); $i < count($result); $i++, next($result)) {
            if (key($result) !== $i) {
                $isList = false;
                break;
            }
        }

        $json = [];

        if ($isList) {
            foreach ($result as $value) {
                $json[] = $this->jsonify($value);
            }

            return '[' . join(',', $json) . ']';
        } else {
            foreach ($result as $key => $value) {
                $json[] = $this->jsonify($key) . ':' . $this->jsonify($value);
            }

            return '{' . join(',', $json) . '}';
        }
    }

    /**
     * returns the sql statement of the last query run
     *
     * @return type
     */
    public function last_query() {
        return $this->db->last_query();
    }

    private function db2_escape($value) {
        $value = trim($value);
        if (empty($value)) {
            return FALSE;
        }

        if ($value !== 0 && is_int($value)) {
            return ( int )$value;
        }

        $value = ( string )$value; // cast as a string

        $return = '';
        for ($i = 0; $i < strlen($value); ++$i) {
            $char = $value [$i];
            $ord = ord($char);
            if ($char !== "'" && $char !== ";" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126)
                $return .= $char;
            else
                $return .= '\\x' . dechex($ord);
        }
        return $return;
    }

    private function validateDate($date) {
        $date = str_replace("/", "-", $date);
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
/* End of file Datatables.php */
/* Location: ./application/libraries/Datatables.php */
