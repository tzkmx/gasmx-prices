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

class GasmxPricesUpdateCommand extends Command {
    protected $cache_path;
    /**
     * @var ClientInterface
     */
    protected $client;

    protected $promises;

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
        $this->client = new Client();
        try {
            $this->makeRequest($input->getArgument('prices'), 'receivePrices');
            $this->makeRequest($input->getArgument('places'), 'receivePlaces');
            Promise\settle($this->promises)->wait();
        } catch (RequestException $e) {
            $output->writeln('<error>End with error</error>');
        } finally {
            var_export(get_object_vars($this));
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
	    echo __FUNCTION__, "\n";
	    $this->promises[__FUNCTION__]['value'] = $response->getBody();
        //file_put_contents($this->cache_path . '/dump_places.xml', $response->getBody());
    }

    protected function receivePrices( ResponseInterface $response ) {
        echo __FUNCTION__, "\n";
        $this->promises[__FUNCTION__]['value'] = $response->getBody();
        //file_put_contents($this->cache_path . '/dump_prices.xml', $response->getBody());
    }

    protected function handleException( \Exception $e ) {
        $e->getMessage();
    }
}