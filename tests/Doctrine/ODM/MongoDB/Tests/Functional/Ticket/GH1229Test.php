<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTest;

use function assert;
use function count;
use function end;

class GH1229Test extends BaseTest
{
    /** @var string */
    protected $firstParentId;

    /** @var string */
    protected $secondParentId;

    public function setUp(): void
    {
        parent::setUp();

        $firstParent = new GH1229Parent();
        $this->dm->persist($firstParent);

        $secondParent = new GH1229Parent();
        $this->dm->persist($secondParent);

        $firstParent->addChild(new GH1229Child('type a'));
        $firstParent->addChild(new GH1229ChildTypeB('type b'));

        $this->dm->flush();
        $this->dm->clear();

        $this->firstParentId  = $firstParent->id;
        $this->secondParentId = $secondParent->id;
    }

    /**
     * @group m
     */
    public function testMethodAWithoutClone()
    {
        $firstParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->firstParentId);
        assert($firstParent instanceof GH1229Parent);
        $this->assertNotNull($firstParent);

        $secondParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->secondParentId);
        assert($secondParent instanceof GH1229Parent);
        $this->assertNotNull($secondParent);

        foreach ($firstParent->getChildren() as $child) {
            if ($child->getOrder() !== 0) {
                continue;
            }

            $firstParent->removeChild($child);
            $secondParent->addChild($child);

            $this->dm->flush();
            $actualChildren = $secondParent->getChildren();

            $this->assertNotSame($actualChildren, $child);

            [, $parent] = $this->uow->getParentAssociation(end($actualChildren));
            $this->assertSame($this->secondParentId, $parent->id);
        }

        $this->dm->clear();

        $firstParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->firstParentId);
        assert($firstParent instanceof GH1229Parent);
        $this->assertNotNull($firstParent);
        $this->assertCount(0, $firstParent->getChildren());

        $secondParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->secondParentId);
        assert($secondParent instanceof GH1229Parent);
        $this->assertNotNull($secondParent);
        $this->assertCount(2, $secondParent->getChildren());

        $children = $secondParent->getChildren();

        $this->assertInstanceOf(GH1229Child::CLASSNAME, $children[0]);
        $this->assertInstanceOf(GH1229ChildTypeB::CLASSNAME, $children[1]);
    }

    /**
     * @group m
     */
    public function testMethodAWithClone()
    {
        $firstParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->firstParentId);
        assert($firstParent instanceof GH1229Parent);
        $this->assertNotNull($firstParent);

        $secondParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->secondParentId);
        assert($secondParent instanceof GH1229Parent);
        $this->assertNotNull($secondParent);

        foreach ($firstParent->getChildren() as $child) {
            if ($child->getOrder() !== 0) {
                continue;
            }

            $firstParent->removeChild($child);
            $secondParent->addChild(clone $child);

            $this->dm->flush();
        }

        $this->dm->clear();

        $firstParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->firstParentId);
        assert($firstParent instanceof GH1229Parent);
        $this->assertNotNull($firstParent);
        $this->assertCount(0, $firstParent->getChildren());

        $secondParent = $this->dm->find(GH1229Parent::CLASSNAME, $this->secondParentId);
        assert($secondParent instanceof GH1229Parent);
        $this->assertNotNull($secondParent);
        $this->assertCount(2, $secondParent->getChildren());

        $children = $secondParent->getChildren();

        $this->assertInstanceOf(GH1229Child::CLASSNAME, $children[0]);
        $this->assertInstanceOf(GH1229ChildTypeB::CLASSNAME, $children[1]);
    }
}

/** @ODM\Document */
class GH1229Parent
{
    public const CLASSNAME = self::class;

    /** @ODM\Id */
    public $id;

    /** @ODM\EmbedMany(discriminatorField="_class") */
    protected $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    /**
     * @return GH1229Child[]
     */
    public function getChildren()
    {
        return $this->children->toArray();
    }

    public function addChild(GH1229Child $child)
    {
        $child->setOrder(count($this->children));
        $this->children->add($child);
    }

    public function removeChild(GH1229Child $child)
    {
        $this->children->removeElement($child);
        $this->reorderChildren($child->getOrder(), -1);
    }

    /**
     * @param int $starting
     * @param int $change
     */
    public function reorderChildren($starting, $change)
    {
        foreach ($this->children as $child) {
            if ($child->getOrder() < $starting) {
                continue;
            }

            $child->setOrder($child->getOrder() + $change);
        }
    }
}

/** @ODM\EmbeddedDocument */
class GH1229Child
{
    public const CLASSNAME = self::class;

    /** @ODM\Field(type="string") */
    public $name;

    /** @ODM\Field(type="int") */
    public $order = 0;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param int $order
     *
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}

/** @ODM\EmbeddedDocument */
class GH1229ChildTypeB extends GH1229Child
{
    public const CLASSNAME = self::class;
}
