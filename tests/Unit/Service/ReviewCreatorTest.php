<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Service\ReviewCreator;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReviewCreatorTest extends TestCase
{
    public function test_create_saves_review_with_spec_fields(): void
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->willReturn(1);
        $review->expects($this->once())->method('setEntityId')->with(1)->willReturnSelf();
        $review->expects($this->once())->method('setEntityPkValue')->with(42)->willReturnSelf();
        $review->expects($this->once())->method('setStatusId')->with(Review::STATUS_APPROVED)->willReturnSelf();
        $review->expects($this->once())->method('setTitle')->with('Great product')->willReturnSelf();
        $review->expects($this->once())->method('setDetail')->with('Truly loved it')->willReturnSelf();
        $review->expects($this->once())->method('setNickname')->with('happycat')->willReturnSelf();
        $review->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $review->expects($this->once())->method('setStores')->with([0, 1])->willReturnSelf();
        $review->expects($this->once())->method('save')->willReturnSelf();
        $review->method('getId')->willReturn(99);

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $ratingFactory = $this->createMock(RatingFactory::class);
        $ratingFactory->method('create')->willReturn($this->buildRatingWithNoOptions());

        $logger = $this->createMock(LoggerInterface::class);

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);
        $creator->create(42, [
            'nickname' => 'happycat',
            'title'    => 'Great product',
            'detail'   => 'Truly loved it',
            'rating'   => 3,
        ]);
    }

    public function test_create_uses_product_entity_code_for_entity_id(): void
    {
        $review = $this->createMock(Review::class);
        $review->expects($this->once())
            ->method('getEntityIdByCode')
            ->with(Review::ENTITY_PRODUCT_CODE)
            ->willReturn(7);
        $review->expects($this->once())->method('setEntityId')->with(7)->willReturnSelf();
        $review->method('setEntityPkValue')->willReturnSelf();
        $review->method('setStatusId')->willReturnSelf();
        $review->method('setTitle')->willReturnSelf();
        $review->method('setDetail')->willReturnSelf();
        $review->method('setNickname')->willReturnSelf();
        $review->method('setStoreId')->willReturnSelf();
        $review->method('setStores')->willReturnSelf();
        $review->method('save')->willReturnSelf();
        $review->method('getId')->willReturn(1);

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $ratingFactory = $this->createMock(RatingFactory::class);
        $ratingFactory->method('create')->willReturn($this->buildRatingWithNoOptions());

        $logger = $this->createMock(LoggerInterface::class);

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);
        $creator->create(1, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 3,
        ]);
    }

    public function test_create_applies_rating_option_vote(): void
    {
        $review = $this->buildPermissiveReview(777);

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        // Build five option mocks with distinct ids; rating=4 should pick index 3 (id=40).
        $options = [];
        foreach ([10, 20, 30, 40, 50] as $optId) {
            $option = new class($optId) {
                public function __construct(private int $optId) {}
                public function getId(): int { return $this->optId; }
            };
            $options[] = $option;
        }

        $ratingObj = $this->createMock(Rating::class);
        $ratingObj->method('getOptions')->willReturn($options);
        $ratingObj->expects($this->once())
            ->method('setReviewId')
            ->with(777)
            ->willReturnSelf();
        $ratingObj->expects($this->once())
            ->method('addOptionVote')
            ->with(40, 123)
            ->willReturnSelf();

        // Rating used as the collection — iterable via load() returning array of rating objects.
        $collection = $this->createMock(Rating::class);
        $collection->method('addEntityFilter')->willReturnSelf();
        $collection->method('setPositionOrder')->willReturnSelf();
        $collection->method('load')->willReturn([$ratingObj]);

        $rating = $this->createMock(Rating::class);
        $rating->method('getResourceCollection')->willReturn($collection);

        $ratingFactory = $this->createMock(RatingFactory::class);
        $ratingFactory->method('create')->willReturn($rating);

        $logger = $this->createMock(LoggerInterface::class);

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);
        $creator->create(123, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 4,
        ]);
    }

    public function test_create_with_invalid_rating_skips_rating_step(): void
    {
        $review = $this->buildPermissiveReview(1);

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $ratingFactory = $this->createMock(RatingFactory::class);
        // Rating factory must NOT be called for invalid rating.
        $ratingFactory->expects($this->never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);
        $creator->create(1, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 0,
        ]);

        $creator->create(1, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 99,
        ]);
    }

    public function test_create_with_default_store_id_uses_1(): void
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->willReturn(1);
        $review->method('setEntityId')->willReturnSelf();
        $review->method('setEntityPkValue')->willReturnSelf();
        $review->method('setStatusId')->willReturnSelf();
        $review->method('setTitle')->willReturnSelf();
        $review->method('setDetail')->willReturnSelf();
        $review->method('setNickname')->willReturnSelf();
        $review->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $review->expects($this->once())->method('setStores')->with([0, 1])->willReturnSelf();
        $review->method('save')->willReturnSelf();
        $review->method('getId')->willReturn(1);

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $ratingFactory = $this->createMock(RatingFactory::class);
        $ratingFactory->method('create')->willReturn($this->buildRatingWithNoOptions());

        $logger = $this->createMock(LoggerInterface::class);

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);
        $creator->create(1, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 3,
            // no store_id
        ]);
    }

    public function test_create_swallows_exception_as_warning(): void
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->willReturn(1);
        $review->method('setEntityId')->willReturnSelf();
        $review->method('setEntityPkValue')->willReturnSelf();
        $review->method('setStatusId')->willReturnSelf();
        $review->method('setTitle')->willReturnSelf();
        $review->method('setDetail')->willReturnSelf();
        $review->method('setNickname')->willReturnSelf();
        $review->method('setStoreId')->willReturnSelf();
        $review->method('setStores')->willReturnSelf();
        $review->method('save')->willThrowException(new \RuntimeException('db down'));

        $reviewFactory = $this->createMock(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $ratingFactory = $this->createMock(RatingFactory::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $creator = new ReviewCreator($reviewFactory, $ratingFactory, $logger);

        // Must not re-throw.
        $creator->create(1, [
            'nickname' => 'n',
            'title'    => 't',
            'detail'   => 'd',
            'rating'   => 3,
        ]);
    }

    private function buildPermissiveReview(int $reviewId): Review
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->willReturn(1);
        $review->method('setEntityId')->willReturnSelf();
        $review->method('setEntityPkValue')->willReturnSelf();
        $review->method('setStatusId')->willReturnSelf();
        $review->method('setTitle')->willReturnSelf();
        $review->method('setDetail')->willReturnSelf();
        $review->method('setNickname')->willReturnSelf();
        $review->method('setStoreId')->willReturnSelf();
        $review->method('setStores')->willReturnSelf();
        $review->method('save')->willReturnSelf();
        $review->method('getId')->willReturn($reviewId);

        return $review;
    }

    private function buildRatingWithNoOptions(): Rating
    {
        $collection = $this->createMock(Rating::class);
        $collection->method('addEntityFilter')->willReturnSelf();
        $collection->method('setPositionOrder')->willReturnSelf();
        $collection->method('load')->willReturn([]);

        $rating = $this->createMock(Rating::class);
        $rating->method('getResourceCollection')->willReturn($collection);

        return $rating;
    }
}
