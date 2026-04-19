<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration\Console\Command;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Console\Command\SeedCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end smoke test: spins the db:seed command against a real Magento
 * install, verifies exit 0, and checks row counts grew as expected.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
final class SeedCommandSmokeTest extends TestCase
{
    public function test_generates_customers_and_products_deterministically(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $customerRepo = $objectManager->create(CustomerRepositoryInterface::class);
        $productRepo = $objectManager->create(ProductRepositoryInterface::class);
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)->create();

        $customersBefore = $customerRepo->getList($searchCriteria)->getTotalCount();
        $productsBefore = $productRepo->getList($searchCriteria)->getTotalCount();

        $command = $objectManager->create(SeedCommand::class);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--generate' => 'customer:5,product:5',
            '--seed' => '42',
        ]);

        self::assertSame(
            0,
            $exitCode,
            "db:seed exited non-zero. Output:\n" . $tester->getDisplay()
        );

        $customersAfter = $customerRepo->getList($searchCriteria)->getTotalCount();
        $productsAfter = $productRepo->getList($searchCriteria)->getTotalCount();

        self::assertSame(
            $customersBefore + 5,
            $customersAfter,
            'Expected 5 new customers from --generate=customer:5'
        );
        self::assertGreaterThanOrEqual(
            $productsBefore + 5,
            $productsAfter,
            'Expected at least 5 new products from --generate=product:5'
        );
    }
}
