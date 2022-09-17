<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class OptionBuilder
{
    protected Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function buildOptions(): array
    {
        $result = $this->db->fetchAllAssociative("select * from options");
        $data = [];
        foreach ($result as $row) {
            $data[$row['name']] = $row['value'];
        }
        return $data;
    }
}