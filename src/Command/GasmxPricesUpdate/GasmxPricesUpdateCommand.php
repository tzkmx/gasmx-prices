<?php

namespace GasmxPricesUpdate\Command\GasmxPricesUpdate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GasmxPricesUpdateCommand extends Command {

	protected function configure() {
        $this
			->setName('gasmx-prices-update:example')
            ->setDescription('The command description goes here')
            ->setHelp('The command help text goes here');
		
		// extra command line arguments and options go here.          
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('gasmx-prices-update:example called');
        // your command code goes here.
    }
}