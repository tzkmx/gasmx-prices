<?php
namespace GasmxPricesUpdate\Command\GasmxPricesUpdate;

use Doctrine\DBAL\Connection;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SqliteSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Command\Command;
use Pimple\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PlacesTableGeneratorCommand extends Command
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var SqliteSchemaManager
     */
    protected $sm;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var Table
     */
    protected $table;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
            ->setName('gasprices:createdb')
            ->setDescription('Prepares the database to store prices entries')
            ->addOption('force-create', 'f', InputOption::VALUE_OPTIONAL, 'Forces creation/rebuild of Places database')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->container['db.logging'] = true;
        }
        $this->conn = $this->container['db'];

        $this->sm = $this->conn->getSchemaManager();

        $tableSchema = $this->getTableSchema();
        $placesTableExists = in_array( $tableSchema, $this->sm->listTables() );

        $forceSchema = $input->getOption('force-create');
        if( (!$placesTableExists) || $forceSchema ) {
            $dbp = $this->conn->getDatabasePlatform();

            $fromSchema = $this->sm->createSchema();
            $targetSchema = $this->getCustomSchema();

            $sqlDiff = $fromSchema->getMigrateToSql($targetSchema, $dbp);

            foreach($sqlDiff as $stmt) {
                $this->conn->exec($stmt);
            }
            $output->write(count($sqlDiff) === 0
                ? 'Schema not needing rebuilding'
                : 'Schema rebuilt'
            );
            $output->writeln(', table prepared for storage');
        }
    }
    protected function getCustomSchema()
    {
        $tables = [
            $this->getTableSchema(),
        ];
        $schema = new Schema($tables);

        return $schema;
    }
    protected function getTableSchema()
    {
        if( $this->table ) {
            return $this->table;
        }
        $table = new Table('places');

        $table->addColumn('place_id', 'integer', [
            'unsigned' => true,
        ]);
        $table->addColumn('price_regular', 'decimal', [
            'precision' => 5,
            'scale' => 2,
            'notnull' => false,
        ]);
        $table->addColumn('price_premium', 'decimal', [
            'precision' => 5,
            'scale' => 2,
            'notnull' => false,
        ]);
        $table->addColumn('price_diesel', 'decimal', [
            'precision' => 5,
            'scale' => 2,
            'notnull' => false,
        ]);
        $table->addColumn('latitud', 'decimal', [
            'precision' => 7,
            'scale' => 4,
        ]);
        $table->addColumn('longitud', 'decimal', [
            'precision' => 7,
            'scale' => 4,
        ]);
        $table->addColumn('state', 'string', [
            'length' => 64,
            'default' => 'N/D',
        ]);
        $table->addColumn('update_time', 'datetime');

        return $table;
    }
}
