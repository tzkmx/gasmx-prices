<?php
namespace GasmxPricesUpdate\Entity;


class GasStationPrice
{
    /**
     * @var int
     */
    protected $station_id;
    /**
     * @var string
     */
    protected $product;
    /**
     * @var float
     */
    protected $price;
    /**
     * @var \DateTime
     */
    protected $timestamp;
}