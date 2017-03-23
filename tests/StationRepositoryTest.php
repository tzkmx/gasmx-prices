<?php

use PHPUnit\Framework\TestCase;
use GasmxPricesUpdate\Repository\StationRepository;
use Doctrine\DBAL\Connection;


class StationRepositoryTest extends TestCase
{
    public function testInsertQueryIsCalled()
    {
        $prophecy = $this->prophesize(Connection::class)
        ->willImplement('Doctrine\DBAL\Driver\Connection');

        $prophecy->insert('places', [
            'place_id' => 1,
            'latitud' => 90,
            'longitud' => 15,
        ])
            ->shouldBeCalled()
            ->willReturn(1);

        $dbal = $prophecy->reveal();

        $repo = new StationRepository($dbal);
        $r = $repo->updatePlace(1, ['latitud' => 90, 'longitud' => 15]);
        $this->assertEquals(1, $r);
    }
}
