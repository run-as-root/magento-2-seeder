<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\NewsletterSubscriberHandler;

final class NewsletterSubscriberHandlerTest extends TestCase
{
    public function test_get_type_returns_newsletter_subscriber(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('newsletter_subscriber', $handler->getType());
    }

    public function test_create_saves_new_subscriber_when_load_by_email_returns_unpersisted(): void
    {
        $subscriber = $this->createSubscriberMock();
        $subscriber->expects($this->once())
            ->method('loadByEmail')
            ->with('jane@example.com')
            ->willReturnSelf();
        // New row: getId() returns null/0 → not merged with $existing
        $subscriber->method('getId')->willReturn(null);

        $subscriber->expects($this->once())->method('setEmail')->with('jane@example.com')->willReturnSelf();
        $subscriber->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $subscriber->expects($this->once())
            ->method('setStatus')
            ->with(Subscriber::STATUS_SUBSCRIBED)
            ->willReturnSelf();
        $subscriber->expects($this->once())->method('setCustomerId')->with(0)->willReturnSelf();
        $subscriber->expects($this->once())
            ->method('setStatusChangedAt')
            ->with($this->matchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'))
            ->willReturnSelf();
        $subscriber->expects($this->once())->method('save')->willReturnSelf();

        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($subscriber);

        $handler = $this->createHandler(subscriberFactory: $subscriberFactory);

        $handler->create(['email' => 'jane@example.com']);
    }

    public function test_create_uses_loaded_subscriber_when_one_already_exists(): void
    {
        $existing = $this->createSubscriberMock();
        $existing->method('getId')->willReturn(5);
        $existing->expects($this->once())->method('setEmail')->with('dup@example.com')->willReturnSelf();
        $existing->expects($this->once())->method('setStoreId')->willReturnSelf();
        $existing->expects($this->once())->method('setStatus')->willReturnSelf();
        $existing->expects($this->once())->method('setCustomerId')->willReturnSelf();
        $existing->expects($this->once())->method('setStatusChangedAt')->willReturnSelf();
        $existing->expects($this->once())->method('save')->willReturnSelf();

        $factoryInstance = $this->createSubscriberMock();
        $factoryInstance->expects($this->once())
            ->method('loadByEmail')
            ->with('dup@example.com')
            ->willReturn($existing);
        // Setters on factoryInstance should NOT be called — merge-path overwrites $subscriber
        $factoryInstance->expects($this->never())->method('setEmail');
        $factoryInstance->expects($this->never())->method('save');

        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($factoryInstance);

        $handler = $this->createHandler(subscriberFactory: $subscriberFactory);
        $handler->create(['email' => 'dup@example.com']);
    }

    public function test_create_uses_defaults_when_optional_keys_missing(): void
    {
        $subscriber = $this->createSubscriberMock();
        $subscriber->method('loadByEmail')->willReturnSelf();
        $subscriber->method('getId')->willReturn(null);

        $subscriber->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $subscriber->expects($this->once())
            ->method('setStatus')
            ->with(Subscriber::STATUS_SUBSCRIBED)
            ->willReturnSelf();
        $subscriber->expects($this->once())->method('setCustomerId')->with(0)->willReturnSelf();

        $subscriber->method('setEmail')->willReturnSelf();
        $subscriber->method('setStatusChangedAt')->willReturnSelf();
        $subscriber->method('save')->willReturnSelf();

        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($subscriber);

        $handler = $this->createHandler(subscriberFactory: $subscriberFactory);
        $handler->create(['email' => 'defaults@example.com']);
    }

    public function test_create_passes_through_full_data(): void
    {
        $subscriber = $this->createSubscriberMock();
        $subscriber->method('loadByEmail')->willReturnSelf();
        $subscriber->method('getId')->willReturn(null);

        $subscriber->expects($this->once())->method('setEmail')->with('full@example.com')->willReturnSelf();
        $subscriber->expects($this->once())->method('setStoreId')->with(2)->willReturnSelf();
        $subscriber->expects($this->once())
            ->method('setStatus')
            ->with(Subscriber::STATUS_UNSUBSCRIBED)
            ->willReturnSelf();
        $subscriber->expects($this->once())->method('setCustomerId')->with(77)->willReturnSelf();
        $subscriber->method('setStatusChangedAt')->willReturnSelf();
        $subscriber->method('save')->willReturnSelf();

        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($subscriber);

        $handler = $this->createHandler(subscriberFactory: $subscriberFactory);
        $handler->create([
            'email' => 'full@example.com',
            'store_id' => 2,
            'subscriber_status' => Subscriber::STATUS_UNSUBSCRIBED,
            'customer_id' => 77,
        ]);
    }

    public function test_clean_deletes_subscribers_with_example_emails(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function (string $table, $where): int {
                $this->assertSame('newsletter_subscriber', $table);
                $this->assertSame(['subscriber_email LIKE ?' => '%@example.%'], $where);
                return 1;
            });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $handler = $this->createHandler(resource: $resource);
        $handler->clean();
    }

    private function createSubscriberMock(): Subscriber
    {
        return $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadByEmail', 'getId', 'save'])
            ->addMethods(['setEmail', 'setStoreId', 'setStatus', 'setCustomerId', 'setStatusChangedAt'])
            ->getMock();
    }

    private function createHandler(
        ?SubscriberFactory $subscriberFactory = null,
        ?ResourceConnection $resource = null,
    ): NewsletterSubscriberHandler {
        return new NewsletterSubscriberHandler(
            $subscriberFactory ?? $this->createMock(SubscriberFactory::class),
            $resource ?? $this->createMock(ResourceConnection::class),
        );
    }
}
