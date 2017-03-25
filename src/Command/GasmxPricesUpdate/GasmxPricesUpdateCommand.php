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

class GasmxPricesUpdateCommand extends Command {
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
	    ->setName('gasprices:pricesupdate')
            ->setDescription('Download Places API Data and store it in database')
            ->setHelp('The command help text goes here')
            ->addArgument('prices', InputArgument::REQUIRED, 'Endpoint for download of PRICES data')
            ->addOption('no-cache', 'f', InputOption::VALUE_NONE, 'Ignore cache')
        ;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('gasprices:pricesupdate options ' . var_export($input->getOptions(),true) );
        $output->writeln('Prices endpoint received: ' . $input->getArgument('prices'));

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
            Promise\settle($this->promises)->wait();
        } catch (\Throwable $e) {
            $output->writeln($e->getMessage());
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

    protected function receivePrices( ResponseInterface $response ) {
        $this->responses[__FUNCTION__]['value'] = $pricesXML = $response->getBody()->getContents();
        $crawler = new Crawler($pricesXML);
        $n = $crawler->children()->slice(0,1);
        echo $n->html(), "\n";
        $n->children()->each(function(Crawler $node, $i) {
            var_dump($node->attr('type'));
            echo $node->html(), "\n";
            echo var_export($node->extract(['_text', 'type', 'update_time']), true), "\n";
        });

        //file_put_contents($this->cache_path . '/dump_prices.xml', $response->getBody());
    }

    protected function handleException( \Exception $e ) {
        echo $e->getMessage(), "\n";
    }

    protected function prepareClient() {
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
