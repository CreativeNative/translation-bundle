<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\GroupBatch;

#[CoversClass(GroupBatch::class)]
final class GroupBatchTest extends TestCase
{
    /** 25 groups of one entity: two full batches plus the trailing partial one. */
    public function testWriteModeFlushesEveryTenGroupsAndOnceMoreAtTheEnd(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $entityManager->expects(self::exactly(25))->method('detach');

        $batch = new GroupBatch($entityManager, true);

        for ($i = 0; $i < 25; ++$i) {
            $batch->settle([new \stdClass()]);
            $batch->tick();
        }

        $batch->finish();
    }

    public function testDryRunNeverFlushesButStillDetaches(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::exactly(25))->method('detach');

        $batch = new GroupBatch($entityManager, false);

        for ($i = 0; $i < 25; ++$i) {
            $batch->settle([new \stdClass()]);
            $batch->tick();
        }

        $batch->finish();
    }

    /** A group is never split: everything settled before a tick is detached together, whatever its size. */
    public function testEverySettledEntityOfABatchIsDetachedAtTheBoundary(): void
    {
        /** @var \ArrayObject<int, object> $detached */
        $detached      = new \ArrayObject();
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('detach')->willReturnCallback(static function (object $entity) use ($detached): void {
            $detached->append($entity);
        });

        $batch = new GroupBatch($entityManager, false);
        $group = [new \stdClass(), new \stdClass(), new \stdClass()];

        for ($i = 0; $i < GroupBatch::SIZE - 1; ++$i) {
            $batch->settle([new \stdClass()]);
            $batch->tick();
        }

        self::assertCount(0, $detached, 'nothing is detached before the boundary');

        $batch->settle($group);
        $batch->tick();

        self::assertCount(GroupBatch::SIZE - 1 + 3, $detached);
        self::assertSame($group, \array_slice($detached->getArrayCopy(), -3));
    }
}
