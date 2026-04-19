<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
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
        private readonly ResourceConnection $resource,
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
                try {
                    $options = $ratingObj->getOptions();
                    if (!isset($options[$rating - 1])) {
                        continue;
                    }
                    $optionId = (int) $options[$rating - 1]->getId();
                    if ($optionId <= 0) {
                        continue;
                    }
                    $ratingObj->setReviewId($review->getId())
                        ->addOptionVote($optionId, $productId);
                } catch (\Throwable $innerError) {
                    $this->logger->warning(sprintf(
                        'ReviewCreator: failed to apply rating vote for product %d: %s',
                        $productId,
                        $innerError->getMessage()
                    ));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'ReviewCreator: failed to create review for product %d: %s',
                $productId,
                $e->getMessage()
            ));
        }
    }

    /**
     * Delete all reviews attached to products whose SKU matches the seed prefix (SEED-%).
     * Cascading FKs remove review_detail + rating_option_vote rows.
     */
    public function cleanSeedReviews(): void
    {
        try {
            $connection = $this->resource->getConnection();
            $reviewTable = $this->resource->getTableName('review');
            $productTable = $this->resource->getTableName('catalog_product_entity');

            $select = $connection->select()
                ->from(['r' => $reviewTable], 'review_id')
                ->join(['p' => $productTable], 'r.entity_pk_value = p.entity_id', [])
                ->where('p.sku LIKE ?', 'SEED-%')
                ->where('r.entity_id = ?', $this->getProductReviewEntityId($connection));

            $ids = $connection->fetchCol($select);
            if ($ids === []) {
                return;
            }

            $connection->delete($reviewTable, ['review_id IN (?)' => $ids]);
        } catch (\Throwable $e) {
            $this->logger->warning('ReviewCreator: failed to clean seed reviews: ' . $e->getMessage());
        }
    }

    private function getProductReviewEntityId(AdapterInterface $connection): int
    {
        $select = $connection->select()
            ->from($this->resource->getTableName('review_entity'), 'entity_id')
            ->where('entity_code = ?', 'product');

        return (int) $connection->fetchOne($select);
    }
}
