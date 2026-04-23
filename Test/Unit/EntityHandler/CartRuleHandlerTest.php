<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Phrase;
use Magento\OfflineShipping\Model\SalesRule\Rule as FreeShippingRule;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleSearchResultInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\CartRuleHandler;

final class CartRuleHandlerTest extends TestCase
{
    public function test_get_type_returns_cart_rule(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('cart_rule', $handler->getType());
    }

    public function test_create_sets_simple_free_shipping_when_simple_action_is_free_shipping(): void
    {
        $rule = $this->createRuleMock();
        $rule->expects($this->once())
            ->method('setSimpleFreeShipping')
            ->with(FreeShippingRule::FREE_SHIPPING_ITEM);

        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(42);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('save')->willReturn($savedRule);

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
        );

        $handler->create([
            'name' => 'Seed Rule — Free Ship',
            'simple_action' => 'free_shipping',
            'discount_amount' => 0.0,
        ]);
    }

    public function test_create_does_not_set_free_shipping_for_non_free_shipping_action(): void
    {
        $rule = $this->createRuleMock();
        $rule->expects($this->never())->method('setSimpleFreeShipping');

        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(7);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('save')->willReturn($savedRule);

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
        );

        $handler->create([
            'name' => 'Seed Rule — Percent Off',
            'simple_action' => 'by_percent',
            'discount_amount' => 10.0,
        ]);
    }

    public function test_create_saves_coupon_when_coupon_type_is_specific_coupon(): void
    {
        $rule = $this->createRuleMock();
        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(99);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->expects($this->once())->method('save')->willReturn($savedRule);

        $coupon = $this->createMock(CouponInterface::class);
        $coupon->expects($this->once())->method('setRuleId')->with(99)->willReturnSelf();
        $coupon->expects($this->once())->method('setCode')->with('SEEDCOUPON')->willReturnSelf();
        $coupon->expects($this->once())
            ->method('setType')
            ->with(CouponInterface::TYPE_MANUAL)
            ->willReturnSelf();
        $coupon->expects($this->once())->method('setUsageLimit')->willReturnSelf();

        $couponFactory = $this->createMock(CouponInterfaceFactory::class);
        $couponFactory->method('create')->willReturn($coupon);

        $couponRepository = $this->createMock(CouponRepositoryInterface::class);
        $couponRepository->expects($this->once())->method('save')->with($coupon)->willReturn($coupon);

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
            couponFactory: $couponFactory,
            couponRepository: $couponRepository,
        );

        $handler->create([
            'name' => 'Seed Rule — Coupon',
            'simple_action' => 'by_percent',
            'discount_amount' => 5.0,
            'coupon' => [
                'type' => 'specific_coupon',
                'code' => 'SEEDCOUPON',
                'uses_per_coupon' => 3,
            ],
        ]);
    }

    public function test_create_does_not_save_coupon_when_no_coupon_data(): void
    {
        $rule = $this->createRuleMock();
        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(15);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->expects($this->once())->method('save')->willReturn($savedRule);

        $couponRepository = $this->createMock(CouponRepositoryInterface::class);
        $couponRepository->expects($this->never())->method('save');

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
            couponRepository: $couponRepository,
        );

        $handler->create([
            'name' => 'Seed Rule — No Coupon',
            'simple_action' => 'by_percent',
            'discount_amount' => 5.0,
        ]);
    }

    public function test_create_coupon_succeeds_on_first_attempt(): void
    {
        $handler = $this->createHandlerForCouponSave(
            saveBehaviors: [fn (CouponInterface $c) => $c],
        );

        $handler->create($this->couponRuleData('FIRSTCODE'));
    }

    public function test_create_coupon_retries_with_suffixed_code_after_already_exists(): void
    {
        $couponRepository = $this->createMock(CouponRepositoryInterface::class);

        $codesSeen = [];
        $coupon = $this->createMock(CouponInterface::class);
        $coupon->method('setRuleId')->willReturnSelf();
        $coupon->method('setType')->willReturnSelf();
        $coupon->method('setUsageLimit')->willReturnSelf();
        $coupon->method('setCode')
            ->willReturnCallback(function (string $code) use (&$codesSeen, $coupon): CouponInterface {
                $codesSeen[] = $code;
                return $coupon;
            });

        $couponFactory = $this->createMock(CouponInterfaceFactory::class);
        $couponFactory->method('create')->willReturn($coupon);

        $attempts = 0;
        $couponRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function () use (&$attempts, $coupon): CouponInterface {
                $attempts++;
                if ($attempts === 1) {
                    throw new AlreadyExistsException(new Phrase('duplicate'));
                }
                return $coupon;
            });

        $rule = $this->createRuleMock();
        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(50);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('save')->willReturn($savedRule);

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
            couponFactory: $couponFactory,
            couponRepository: $couponRepository,
        );

        $handler->create($this->couponRuleData('RETRYCODE'));

        $this->assertCount(2, $codesSeen, 'setCode called once per attempt');
        $this->assertSame('RETRYCODE', $codesSeen[0]);
        $this->assertMatchesRegularExpression('/^RETRYCODE-[0-9A-F]{4}$/', $codesSeen[1]);
    }

    public function test_create_coupon_throws_runtime_after_three_already_exists(): void
    {
        $coupon = $this->createMock(CouponInterface::class);
        $coupon->method('setRuleId')->willReturnSelf();
        $coupon->method('setCode')->willReturnSelf();
        $coupon->method('setType')->willReturnSelf();
        $coupon->method('setUsageLimit')->willReturnSelf();

        $couponFactory = $this->createMock(CouponInterfaceFactory::class);
        $couponFactory->method('create')->willReturn($coupon);

        $duplicate = new AlreadyExistsException(new Phrase('dup'));
        $couponRepository = $this->createMock(CouponRepositoryInterface::class);
        $couponRepository->expects($this->exactly(3))
            ->method('save')
            ->willThrowException($duplicate);

        $rule = $this->createRuleMock();
        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(77);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('save')->willReturn($savedRule);

        $handler = $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
            couponFactory: $couponFactory,
            couponRepository: $couponRepository,
        );

        try {
            $handler->create($this->couponRuleData('DUPE'));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Could not create unique coupon code after 3 retries', $e->getMessage());
            $this->assertSame($duplicate, $e->getPrevious());
        }
    }

    public function test_clean_filters_by_seed_name_and_deletes_each_rule(): void
    {
        $ruleA = $this->createMock(RuleInterface::class);
        $ruleA->method('getRuleId')->willReturn(1);
        $ruleB = $this->createMock(RuleInterface::class);
        $ruleB->method('getRuleId')->willReturn(2);

        $searchResults = $this->createMock(RuleSearchResultInterface::class);
        $searchResults->method('getItems')->willReturn([$ruleA, $ruleB]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with('name', 'Seed Rule — %', 'like')
            ->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('getList')->with($searchCriteria)->willReturn($searchResults);

        $deleted = [];
        $ruleRepository->expects($this->exactly(2))
            ->method('deleteById')
            ->willReturnCallback(function (int $id) use (&$deleted): bool {
                $deleted[] = $id;
                return true;
            });

        $handler = $this->createHandler(
            ruleRepository: $ruleRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();

        $this->assertSame([1, 2], $deleted);
    }

    /**
     * @param list<callable(CouponInterface):CouponInterface> $saveBehaviors
     */
    private function createHandlerForCouponSave(array $saveBehaviors): CartRuleHandler
    {
        $coupon = $this->createMock(CouponInterface::class);
        $coupon->method('setRuleId')->willReturnSelf();
        $coupon->method('setCode')->willReturnSelf();
        $coupon->method('setType')->willReturnSelf();
        $coupon->method('setUsageLimit')->willReturnSelf();

        $couponFactory = $this->createMock(CouponInterfaceFactory::class);
        $couponFactory->method('create')->willReturn($coupon);

        $callIndex = 0;
        $couponRepository = $this->createMock(CouponRepositoryInterface::class);
        $couponRepository->expects($this->exactly(count($saveBehaviors)))
            ->method('save')
            ->willReturnCallback(function () use (&$callIndex, $saveBehaviors, $coupon): CouponInterface {
                $behavior = $saveBehaviors[$callIndex++];
                return $behavior($coupon);
            });

        $rule = $this->createRuleMock();
        $ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $ruleFactory->method('create')->willReturn($rule);

        $savedRule = $this->createMock(RuleInterface::class);
        $savedRule->method('getRuleId')->willReturn(10);

        $ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepository->method('save')->willReturn($savedRule);

        return $this->createHandler(
            ruleFactory: $ruleFactory,
            ruleRepository: $ruleRepository,
            couponFactory: $couponFactory,
            couponRepository: $couponRepository,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function couponRuleData(string $code): array
    {
        return [
            'name' => 'Seed Rule — Coupon',
            'simple_action' => 'by_percent',
            'discount_amount' => 1.0,
            'coupon' => [
                'type' => 'specific_coupon',
                'code' => $code,
            ],
        ];
    }

    private function createRuleMock(): RuleInterface
    {
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('setName')->willReturnSelf();
        $rule->method('setDescription')->willReturnSelf();
        $rule->method('setIsActive')->willReturnSelf();
        $rule->method('setWebsiteIds')->willReturnSelf();
        $rule->method('setCustomerGroupIds')->willReturnSelf();
        $rule->method('setFromDate')->willReturnSelf();
        $rule->method('setToDate')->willReturnSelf();
        $rule->method('setStopRulesProcessing')->willReturnSelf();
        $rule->method('setUsesPerCustomer')->willReturnSelf();
        $rule->method('setSimpleAction')->willReturnSelf();
        $rule->method('setDiscountAmount')->willReturnSelf();
        $rule->method('setDiscountQty')->willReturnSelf();
        $rule->method('setSortOrder')->willReturnSelf();
        $rule->method('setSimpleFreeShipping')->willReturnSelf();
        $rule->method('setCouponType')->willReturnSelf();

        return $rule;
    }

    private function createHandler(
        ?RuleInterfaceFactory $ruleFactory = null,
        ?RuleRepositoryInterface $ruleRepository = null,
        ?CouponInterfaceFactory $couponFactory = null,
        ?CouponRepositoryInterface $couponRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CartRuleHandler {
        return new CartRuleHandler(
            $ruleFactory ?? $this->createMock(RuleInterfaceFactory::class),
            $ruleRepository ?? $this->createMock(RuleRepositoryInterface::class),
            $couponFactory ?? $this->createMock(CouponInterfaceFactory::class),
            $couponRepository ?? $this->createMock(CouponRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
