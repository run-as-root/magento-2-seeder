<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Psr\Log\LoggerInterface;

class ReviewCreator
{
    public function __construct(
        private readonly ReviewFactory $reviewFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a single review for a product from the provided spec.
     *
     * Spec keys (all required):
     *   - nickname: string
     *   - title: string
     *   - detail: string
     *   - rating: int 1-5
     * Optional:
     *   - store_id: int (default 1)
     */
    public function create(int $productId, array $spec): void
    {
        try {
            $storeId = (int) ($spec['store_id'] ?? 1);

            $review = $this->reviewFactory->create();
            $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE));
            $review->setEntityPkValue($productId);
            $review->setStatusId(Review::STATUS_APPROVED);
            $review->setTitle($spec['title']);
            $review->setDetail($spec['detail']);
            $review->setNickname($spec['nickname']);
            $review->setStoreId($storeId);
            $review->setStores([0, $storeId]);
            $review->save();

            $rating = (int) $spec['rating'];
            if ($rating < 1 || $rating > 5) {
                return;
            }

            $ratings = $this->ratingFactory->create()
                ->getResourceCollection()
                ->addEntityFilter('product')
                ->setPositionOrder()
                ->load();

            foreach ($ratings as $ratingObj) {
                $options = $ratingObj->getOptions();
                if (!isset($options[$rating - 1])) {
                    continue;
                }
                $optionId = (int) $options[$rating - 1]->getId();
                $ratingObj->setReviewId($review->getId())
                    ->addOptionVote($optionId, $productId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'ReviewCreator: failed to create review for product %d: %s',
                $productId,
                $e->getMessage()
            ));
        }
    }
}
