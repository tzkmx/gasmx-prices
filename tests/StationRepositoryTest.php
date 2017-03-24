<?php

use PHPUnit\Framework\TestCase;
use GasmxPricesUpdate\Repository\StationRepository;
use Doctrine\DBAL\Connection;


class StationRepositoryTest extends TestCase
{
    /**
     * @var Prophecy\Prophecy\ObjectProphecy
     */
    private $prophecy;
    private $iii;

    public function setUp()
    {
        $this->prophecy = $this->prophesize(Connection::class)
            ->willImplement('Doctrine\DBAL\Driver\Connection');
    }
    public function testFirstInsertQuerySuccess()
    {
        $this->prophecy->insert('places', [
            'place_id' => 1,
            'latitud' => 90,
            'longitud' => 15,
        ])
            ->shouldBeCalled()
            ->willReturn(1);

        $dbal = $this->prophecy->reveal();

        $repo = new StationRepository($dbal);
        $r = $repo->updatePlace(1, ['latitud' => 90, 'longitud' => 15]);
        $this->assertEquals(1, $r);
    }

    public function testSecondInsertQueryRejected()
    {
        $this->prophecy->insert('places', [
            'place_id' => 2,
            'latitud' => 90,
            'longitud' => 15,
        ])
            ->shouldBeCalled()
            ->willReturn(false);

        $dbal = $this->prophecy->reveal();

        $repo = new StationRepository($dbal);
        $r = $repo->updatePlace(2, ['latitud' => 90, 'longitud' => 15]);
        $this->assertEquals(false, $r);
    }
}
