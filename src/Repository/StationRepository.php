<?php

namespace GasmxPricesUpdate\Repository;
use Doctrine\DBAL\Driver\Connection;

class StationRepository
{
    /**
     * @var Connection
     */
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function updatePlace(int $place_id, $fields)
    {
        $row = array_merge(['place_id' => $place_id], $fields);
        return $this->db->insert('places', $row);
    }

}