<?php

namespace GasmxPricesUpdate\Command\GasmxPricesUpdate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Symfony\Component\DomCrawler\Crawler;

class GasmxPricesUpdateCommand extends Command {
    protected $cache_path;
    /**
     * @var ClientInterface
     */
    protected $client;

    protected $promises;
    protected $responses;
    protected $nodes;

    protected $ignore_cache = false;

	protected function configure() {
        $this
			->setName('gasprices:download')
            ->setDescription('The command description goes here')
            ->setHelp('The command help text goes here')
            ->addArgument('places', InputArgument::REQUIRED, 'Endpoint for download of PLACES data')
            ->addArgument('prices', InputArgument::REQUIRED, 'Endpoint for download of PRICES data')
            ->addOption('no-cache', 'f', InputOption::VALUE_NONE, 'Ignore cache')
        ;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('gasprices:download options ' . var_export($input->getOptions(), true) );
        $output->writeln('Places endpoint received: ' . $input->getArgument('places'));
        $output->writeln('Prices endpoint received: ' . $input->getArgument('prices'));
        $this->cache_path = realpath(__DIR__ . '/../../../var/cache');
        if($input->getOption('no-cache')) {
            $output->writeln('ignoring cache, force download');
            $this->ignore_cache = true;
        }
        $this->getApiData($input, $output);
    }

    protected function getApiData(InputInterface $input, OutputInterface $output) {
        $this->prepareClient();
        try {
            $this->makeRequest($input->getArgument('prices'), 'receivePrices');
            $this->makeRequest($input->getArgument('places'), 'receivePlaces');
            Promise\settle($this->promises)->wait();
        } catch (RequestException $e) {
            $output->writeln('<error>End with error</error>');
        } finally {

        }
    }

    protected function makeRequest( $endpoint, $receiver ) {
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
	    $this->responses[__FUNCTION__]['value'] = $response->getBody()->getContents();
	    $crawler = new Crawler($this->responses[__FUNCTION__]['value']);
	    try {
	        $this->noaddress = 0;
            $crawler
                ->filterXPath('//place')
                ->slice(2, 200)
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
    protected function receiveRGR( ResponseInterface $response, $nodeId ) {
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
        $this->responses[__FUNCTION__]['value'] = $response->getBody()->getContents();
        //file_put_contents($this->cache_path . '/dump_prices.xml', $response->getBody());
    }

    protected function handleException( \Exception $e ) {
        echo $e->getMessage();
    }

    protected function prepareClient() {
	    if((! is_null($this->client)) &&
	        in_array(class_implements(get_class($this->client)), [ClientInterface::class])
        ) {
	        return;
        }
        if($this->ignore_cache) {
	        $this->client = new Client();
	        return;
        }
	    $stack = HandlerStack::create();
	    $cache = new FilesystemCache($this->cache_path);
	    $storage = new DoctrineCacheStorage($cache);
	    $strategy = new PublicCacheStrategy($storage);
        $stack->push(new CacheMiddleware($strategy), 'cache');
        $this->client = new Client(['handler' => $stack]);
    }
}
