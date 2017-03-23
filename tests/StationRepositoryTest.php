<?php

use PHPUnit\Framework\TestCase;
use GasmxPricesUpdate\Repository\StationRepository;
use Doctrine\DBAL\Connection;


class StationRepositoryTest extends TestCase
{
    public function testInsertQueryIsCalled()
    {
        $dbal = $this->getMockBuilder(Connection::class)
            ->setMethods(['insert'])
            ->getMock();

        $dbal->expects($this->once())
            ->method('insert')
            ->with(
                $this->equalto('places'),
                $this->equalto([
                    'place_id' => 1,
                    'latitud' => 90,
                    'longitud' => 15,
                ])
            );

        $repo = new StationRepository($dbal);
        $repo->updatePlace(1, ['latitud' => 90, 'longitud' => 15]);
    }
}
