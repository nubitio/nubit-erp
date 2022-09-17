<?php

namespace App\Infrastructure\Common;

use Doctrine\DBAL\Connection;

class DataGridLoader
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function load($table, $sort, $order = 'ASC', $filter = null): array
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
        $row = $this->connection->fetchNumeric("SELECT count(*) FROM $table WHERE $where");
        $result["total"] = $row[0];
        $items = $this->connection->fetchAllAssociative("
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

    public function filterQuery($filterArray): string
    {
        $numFilter = count($filterArray);
        $where = "1=1";
        if ($numFilter > 0) {
            $where .= " AND ";
            $tmpDataField = "";
            for ($i = 0; $i < $numFilter; $i++) {
                $filterDataField = $filterArray[$i]['field'];
                $filterValue = $filterArray[$i]['value'];
                if ($tmpDataField == "") {
                    $tmpDataField = $filterDataField;
                } else if ($tmpDataField <> $filterDataField) {
                    $where .= " AND ";
                }
                switch ($filterArray[$i]['op']) {
                    case 'contains':
                        $where .= $filterDataField . " LIKE '%" . $filterValue . "%'";
                        break;
                    case 'equal':
                        $where .= $filterDataField . " = '" . $filterValue . "'";
                        break;
                    case 'notequal':
                        $where .= $filterDataField . " <> '" . $filterValue . "'";
                        break;
                    case 'beginwith':
                        $where .= $filterDataField . " LIKE '" . $filterValue . "%'";
                        break;
                    case 'endwith':
                        $where .= $filterDataField . " LIKE '%" . $filterValue . "'";
                        break;
                    case 'less':
                        $where .= $filterDataField . " < '" . $filterValue . "'";
                        break;
                    case 'lessorequal':
                        $where .= $filterDataField . " <= '" . $filterValue . "'";
                        break;
                    case 'greater':
                        $where .= $filterDataField . " > '" . $filterValue . "'";
                        break;
                    case 'greaterorequal':
                        $where .= $filterDataField . " >= '" . $filterValue . "'";
                        break;
                }
                $tmpDataField = $filterDataField;
            }
        }
        return $where;
    }
}