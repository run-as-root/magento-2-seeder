<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedStatusCommand extends Command
{
    public function __construct(
        private readonly ResourceConnection $resource,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed:status');
        $this->setDescription('Show counts of seeder-created entities in the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->resource->getConnection();
        $t = fn (string $name): string => $this->resource->getTableName($name);

        $counts = [
            'Products (SEED-*)'         => (int) $conn->fetchOne(
                $conn->select()->from($t('catalog_product_entity'), new \Zend_Db_Expr('COUNT(*)'))
                    ->where('sku LIKE ?', 'SEED-%')
            ),
            'Categories (non-root)'     => (int) $conn->fetchOne(
                $conn->select()->from($t('catalog_category_entity'), new \Zend_Db_Expr('COUNT(*)'))
                    ->where('entity_id > ?', 2)
            ),
            'Customers'                 => (int) $conn->fetchOne(
                $conn->select()->from($t('customer_entity'), new \Zend_Db_Expr('COUNT(*)'))
            ),
            'Orders'                    => (int) $conn->fetchOne(
                $conn->select()->from($t('sales_order'), new \Zend_Db_Expr('COUNT(*)'))
            ),
            'CMS pages (seed-*)'        => (int) $conn->fetchOne(
                $conn->select()->from($t('cms_page'), new \Zend_Db_Expr('COUNT(*)'))
                    ->where('identifier LIKE ?', 'seed-%')
            ),
            'CMS blocks (seed-*)'       => (int) $conn->fetchOne(
                $conn->select()->from($t('cms_block'), new \Zend_Db_Expr('COUNT(*)'))
                    ->where('identifier LIKE ?', 'seed-%')
            ),
            'Reviews on SEED products'  => (int) $conn->fetchOne(
                $conn->select()->from(['r' => $t('review')], new \Zend_Db_Expr('COUNT(*)'))
                    ->join(['p' => $t('catalog_product_entity')], 'r.entity_pk_value = p.entity_id', [])
                    ->where('p.sku LIKE ?', 'SEED-%')
            ),
        ];

        $output->writeln('<info>Seeded entities in database:</info>');
        foreach ($counts as $label => $count) {
            $output->writeln(sprintf('  %-26s %d', $label . ':', $count));
        }

        return Command::SUCCESS;
    }
}
