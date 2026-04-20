<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class CartRuleHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly RuleInterfaceFactory $ruleFactory,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly CouponInterfaceFactory $couponFactory,
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'cart_rule';
    }

    public function create(array $data): int
    {
        $rule = $this->ruleFactory->create();
        $rule->setName($data['name']);
        $rule->setDescription($data['description'] ?? '');
        $rule->setIsActive((bool) ($data['is_active'] ?? 1));
        $rule->setWebsiteIds($data['website_ids'] ?? [1]);
        $rule->setCustomerGroupIds($data['customer_group_ids'] ?? [0, 1, 2, 3]);
        $rule->setFromDate($data['from_date'] ?? null);
        $rule->setStopRulesProcessing((bool) ($data['stop_rules_processing'] ?? 0));
        $rule->setUsesPerCustomer((int) ($data['uses_per_customer'] ?? 0));
        $rule->setSimpleAction($data['simple_action']);
        $rule->setDiscountAmount((float) $data['discount_amount']);
        $rule->setDiscountQty((int) ($data['discount_qty'] ?? 0));
        $rule->setSortOrder((int) ($data['sort_order'] ?? 0));

        if (!empty($data['to_date'])) {
            $rule->setToDate($data['to_date']);
        }

        if ($data['simple_action'] === 'free_shipping') {
            $rule->setSimpleFreeShipping(Rule::FREE_SHIPPING_ITEM);
        }

        $couponType = ($data['coupon']['type'] ?? null) === 'specific_coupon'
            ? Rule::COUPON_TYPE_SPECIFIC
            : Rule::COUPON_TYPE_NO_COUPON;
        $rule->setCouponType($couponType);

        $saved = $this->ruleRepository->save($rule);

        if ($couponType === Rule::COUPON_TYPE_SPECIFIC && !empty($data['coupon']['code'])) {
            $this->createCouponForRule((int) $saved->getRuleId(), $data['coupon']);
        }

        return (int) $saved->getRuleId();
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('name', 'Seed Rule — %', 'like')
            ->create();

        foreach ($this->ruleRepository->getList($searchCriteria)->getItems() as $rule) {
            $this->ruleRepository->deleteById($rule->getRuleId());
        }
    }

    private function createCouponForRule(int $ruleId, array $couponData): void
    {
        $attempts = 0;
        do {
            $coupon = $this->couponFactory->create();
            $coupon->setRuleId($ruleId);
            $coupon->setCode($couponData['code']);
            // TYPE_MANUAL = rule-author-supplied specific code (no auto generation on the rule),
            // which matches how our data generator produces deterministic coupon codes.
            $coupon->setType(CouponInterface::TYPE_MANUAL);
            $coupon->setUsageLimit((int) ($couponData['uses_per_coupon'] ?? 0));
            try {
                $this->couponRepository->save($coupon);
                return;
            } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                $couponData['code'] = $couponData['code'] . '-' . strtoupper(bin2hex(random_bytes(2)));
                $attempts++;
            }
        } while ($attempts < 3);

        throw new \RuntimeException('Could not create unique coupon code after 3 retries');
    }
}
