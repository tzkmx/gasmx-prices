<?php

namespace GasmxPricesUpdate\Command\GasmxPricesUpdate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Pimple\Container;
use GuzzleHttp\Pool;

class GasmxPlacesUpdateCommand extends Command {
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var Container
     */
    protected $container;

    protected $promises;
    protected $responses;
    protected $nodes;

    protected $ignore_cache = false;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure() {
        $this
			->setName('gasprices:placesupdate')
            ->setDescription('Download Places API Data and store it in database')
            ->setHelp('The command help text goes here')
            ->addArgument('places', InputArgument::REQUIRED, 'Endpoint for download of PLACES data')
            ->addArgument('prices', InputArgument::REQUIRED, 'Endpoint for download of PRICES data')
            ->addOption('no-cache', 'f', InputOption::VALUE_NONE, 'Ignore cache')
        ;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('gasprices:placesupdate options ' . var_export($input->getOptions(),true) );
        $output->writeln('Places endpoint received: ' . $input->getArgument('places'));
        $output->writeln('Prices endpoint received: ' . $input->getArgument('prices'));

        if($input->getOption('no-cache')) {
            $output->writeln('ignoring cache, force download');
            $this->ignore_cache = true;
        }
        $this->getApiData($input, $output);
        foreach($this->soFars as $msg) {
            $output->writeln($msg);
        }
    }

    protected function getApiData(InputInterface $input, OutputInterface $output) {
        $this->prepareClient();
        try {
            $this->makeRequest($input->getArgument('prices'), 'receivePrices');
            $this->makeRequest($input->getArgument('places'), 'receivePlaces');
            Promise\settle($this->promises)->wait();
        } catch (\Throwable $e) {
            $output->writeln($e->getMessage());
        } finally {
            $this->soFar(__METHOD__);
        }
    }

    protected function makeRequest( $endpoint, $receiver ) {
        $this->soFar(__METHOD__);
        $this->promises[$receiver] = $this->client->getAsync( $endpoint );
        $this->promises[$receiver]->then(
            function($response) use($receiver) {
                echo 'Received ' . $receiver . ' response', "\n";
                call_user_func([$this, $receiver], $response);
            },
            function($exception) {
                call_user_func([$this, 'handleException'], $exception);
            }
        );
    }

    protected function receivePlaces( ResponseInterface $response ) {
        $this->soFar(__METHOD__);
	    $this->responses[__FUNCTION__]['value'] = $response->getBody()->getContents();
	    $crawler = new Crawler($this->responses[__FUNCTION__]['value']);
	    try {
	        $this->noaddress = 0;
            $crawler
                ->filterXPath('//place')
                ->slice(2, 2000)
                ->each(function ($node, $i) {
                    $x = (float)$node->filterXPath("//x")->text();
                    $y = (float)$node->filterXPath("//y")->text();
                    if($x == 0 && $y == 0) {
                        $this->noaddress++;
                        return;
                    }
                    $placeId = $node->attr('place_id');
                    $this->makeReverseGeocodingRequest($y, $x, $placeId, $node);
                });
            echo "Estaciones sin coordenadas: {$this->noaddress}\n";
        } catch(\Exception $e) {
	        echo $e->getMessage();
        }
    }
    protected function makeReverseGeocodingRequest($latitud, $longitud, $placeId, Crawler $node) {
        // http://maps.googleapis.com/maps/api/geocode/json?latlng=22.3953,-97.8934
	    $url = sprintf('http://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s', $latitud, $longitud);
	    $this->nodes[$placeId] = $node;

	    $this->makeRequest($url, "receiveReverseGeocoding_{$placeId}");
    }
    public function __call($name, $arguments)
    {
        if(0 === strpos($name, 'receiveReverseGeocoding')) {
            $id = str_replace('receiveReverseGeocoding_', '', $name);
            $this->receiveRGR( $arguments[0], $id );
        }
    }
    public function soFar($caller = null)
    {
        $mpeak = memory_get_peak_usage(true);
        if(isset($this->mpeak)) {
            $diff = $mpeak - $this->mpeak;
            $this->mpeak = $mpeak;
        } else {
            $this->soFars = array();
            $this->mpeak = $diff = $mpeak;
        }
        $caller = (string)$caller;

        $mu = round($mpeak / 1000000, 2) . 'MB';
        $l = count($this->soFars);
        $msg = "{$caller}\tUsed memory so far: {$mu}\tDifference:{$diff}\n";
        if($l === 0) {
            $this->soFars[] = $msg;
            return;
        }
        if($msg !== $this->soFars[$l - 1]) {
            $this->soFars[] = $msg;
        }
    }
    protected function receiveRGR( ResponseInterface $response, $nodeId ) {
        $this->soFar(__METHOD__);
        gc_collect_cycles();
	    $preResult = json_decode($response->getBody()->getContents(), true);
	    if($preResult['status'] === 'OK') {
	        echo $preResult['results'][0]['formatted_address'], "\n";
	        foreach($preResult['results'] as $result) {
	            if(in_array('administrative_area_level_1', $result['types'])) {
	                foreach($result['address_components'] as $comp) {
	                    if(in_array('administrative_area_level_1', $comp['types'])) {
	                        echo $comp['long_name'], "\n";
                        }
                    }
                }
            }
	        //var_dump($preResult['results']);
        }
	    $node = $this->nodes[$nodeId];
        $node->children()->each(function($node, $i) {
            $name = $node->nodeName();
            if($name === 'location') {
                $node->children()->each(function($node, $i) {
                    echo "{$node->nodeName()}\t{$node->text()}\n";
                });
                return;
            }
            echo "{$name}\t{$node->text()}\n";
        });
    }


    protected function receivePrices( ResponseInterface $response ) {
        $this->soFar(__METHOD__);
        $this->responses[__FUNCTION__]['value'] = $response->getBody()->getContents();
        //file_put_contents($this->cache_path . '/dump_prices.xml', $response->getBody());
    }

    protected function handleException( \Exception $e ) {
        $this->soFar(__METHOD__);
        echo $e->getMessage(), "\n";
    }

    protected function prepareClient() {
        $this->soFar(__METHOD__);
	    if((! is_null($this->client)) &&
	        in_array(class_implements(get_class($this->client)), [ClientInterface::class])
        ) {
	        return;
        }
        if($this->ignore_cache) {
	        $this->client = $this->container['http.client.nocache'];
	        return;
        }
        $this->client = $this->container['http.client.cache'];
    }
}
