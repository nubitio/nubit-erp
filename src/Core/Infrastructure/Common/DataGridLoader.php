<?php

namespace App\Core\Infrastructure\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class DataGridLoader
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param array<int, array<string, mixed>> $filter
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function load(string $table, string $sort, string $order = 'ASC', ?array $filter = null): array
    {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : $sort;
        $order = isset($_POST['order']) ? strval($_POST['order']) : $order;
        $filterRules = isset($_POST['filterRules']) ? ($_POST['filterRules']) : '';
        $offset = ($page - 1) * $rows;
        $result = [];
        $where = '1=1';
        $sorts = explode(',', $sort);
        $orders = explode(',', $order);
        $sort_items = [];
        foreach ($sorts as $index => $column) {
            $type = $orders[$index];
            $sort_items[] = "$column $type";
        }
        $order_str = join(',', $sort_items);
        if (!empty($filterRules) || null != $filter) {
            /** @var array<int, array<string, mixed>> $filterRules */
            $filterRules = json_decode(strval($filterRules), true) ?? [];
            $where = $this->buildFilterQuery([...$filterRules, ...$filter ?? []]);
        }
        $row = $this->connection->fetchNumeric("SELECT count(*) FROM $table WHERE $where");

        if (!$row) {
            throw new Exception('Error while fetching data');
        }

        $result['total'] = $row[0];
        $items = $this->connection->fetchAllAssociative("
          SELECT
            *
          FROM
            $table
          WHERE $where
          ORDER BY $order_str
          LIMIT $offset,$rows
        ");
        $result['rows'] = $items;

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $filterArray
     */
    public function buildFilterQuery(array $filterArray): string
    {
        $numFilter = count($filterArray);
        $where = '1=1';
        if ($numFilter > 0) {
            $where .= ' AND ';
            $tmpDataField = '';
            for ($i = 0; $i < $numFilter; ++$i) {
                $filterDataField = $filterArray[$i]['field'];
                $filterValue = $filterArray[$i]['value'] ?? '';
                if ('' == $tmpDataField) {
                    $tmpDataField = $filterDataField;
                } elseif ($tmpDataField != $filterDataField) {
                    $where .= ' AND ';
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
                    case 'in':
                        $where .= $filterDataField . ' IN (' . $filterValue . ')';
                        break;
                    case 'notin':
                        $where .= $filterDataField . ' NOT IN (' . $filterValue . ')';
                        break;
                    case 'isnull':
                        $where .= $filterDataField . ' IS NULL';
                        break;
                    case 'isnotnull':
                        $where .= $filterDataField . ' IS NOT NULL';
                        break;
                }
                $tmpDataField = $filterDataField;
            }
        }

        return $where;
    }
}
