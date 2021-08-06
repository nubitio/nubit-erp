<?php

namespace App\Utils;

use DB\SQL;
use PDO;

class ModelUtils
{

    protected SQL $db;

    public function __construct(SQL $db)
    {
        $this->db = $db;
    }

    public function setUp($table, $sort, $order = 'ASC', $filter = null)
    {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : $sort;
        $order = isset($_POST['order']) ? strval($_POST['order']) : $order;
        $filterRules = isset($_POST['filterRules']) ? ($_POST['filterRules']) : '';
        $offset = ($page - 1) * $rows;
        $result = array();
        $where = "1=1";
        $sorts = explode(',', $sort);
        $orders = explode(',', $order);
        $sort_items = array();
        foreach ($sorts as $index => $column) {
            $type = $orders[$index];
            $sort_items[] = "$column $type";
        }
        $order_str = join(',', $sort_items);
        if (!empty($filterRules)) {
            $filterRules = json_decode($filterRules, true);
            $where = $this->filterQuery($filterRules);
        }
        if ($filter != null) {
            $where .= " AND " . $filter;
        }
        $rs = $this->db->query("SELECT count(*) FROM $table WHERE $where");
        $row = $rs->fetch(PDO::FETCH_NUM);
        $result["total"] = $row[0];
        $items = $this->db->exec("
          SELECT
            *
          FROM
            $table
          WHERE $where
          ORDER BY $order_str
          LIMIT $offset,$rows
        ");
        $result["rows"] = $items;
        return $result;
    }

    public function filterQuery($filterArray)
    {
        $num_filter = count($filterArray);
        $where = "1=1";
        if ($num_filter > 0) {
            $where .= " AND ";
            $tmpdatafield = "";
            for ($i = 0; $i < $num_filter; $i++) {
                $filterdatafield = $filterArray[$i]['field'];
                $filtervalue = $filterArray[$i]['value'];
                if ($tmpdatafield == "") {
                    $tmpdatafield = $filterdatafield;
                } else if ($tmpdatafield <> $filterdatafield) {
                    $where .= " AND ";
                }
                switch ($filterArray[$i]['op']) {
                    case 'contains':
                        $where .= $filterdatafield . " LIKE '%" . $filtervalue . "%'";
                        break;
                    case 'equal':
                        $where .= $filterdatafield . " = '" . $filtervalue . "'";
                        break;
                    case 'notequal':
                        $where .= $filterdatafield . " <> '" . $filtervalue . "'";
                        break;
                    case 'beginwith':
                        $where .= $filterdatafield . " LIKE '" . $filtervalue . "%'";
                        break;
                    case 'endwith':
                        $where .= $filterdatafield . " LIKE '%" . $filtervalue . "'";
                        break;
                    case 'less':
                        $where .= $filterdatafield . " < '" . $filtervalue . "'";
                        break;
                    case 'lessorequal':
                        $where .= $filterdatafield . " <= '" . $filtervalue . "'";
                        break;
                    case 'greater':
                        $where .= $filterdatafield . " > '" . $filtervalue . "'";
                        break;
                    case 'greaterorequal':
                        $where .= $filterdatafield . " >= '" . $filtervalue . "'";
                        break;
                }
                $tmpdatafield = $filterdatafield;
            }
        }
        return $where;
    }
}
