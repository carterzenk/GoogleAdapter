<?php
/**
 * This file is part of the CalendArt package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace CalendArt\Adapter\Google\Test\Criterion;

use ReflectionMethod;
use PHPUnit_Framework_TestCase;
use CalendArt\Adapter\Google\Criterion\AbstractCriterion;

class AbstractCriterionTest extends PHPUnit_Framework_TestCase
{
    private $stub;

    public function setUp()
    {
        $this->stub = $this->getMockForAbstractClass(
            AbstractCriterion::class,
            ['foo', [$this->getMockForAbstractClass(AbstractCriterion::class, ['bar']),
                     $this->getMockForAbstractClass(AbstractCriterion::class, ['baz'])]]
        );
    }

    public function testConstructor()
    {
        $this->assertSame('foo', $this->stub->getName());
        $this->assertCount(2, iterator_to_array($this->stub));
    }

    public function testClone()
    {
        $clone = iterator_to_array(clone $this->stub);

        $this->assertCount(2, $clone);
        $this->assertContainsOnlyInstancesOf(AbstractCriterion::class, $clone);
    }

    /**
     * @dataProvider getProvider
     * @param $criterion
     */
    public function testGetCriterion($criterion)
    {
        $refl = new ReflectionMethod(AbstractCriterion::class, 'getCriterion');
        $refl->setAccessible(true);
        $criterion = $refl->invoke($this->stub, $criterion);

        $this->assertInstanceOf(AbstractCriterion::class, $criterion);
    }

    /**
     * @dataProvider getProvider
     * @param $criterion
     */
    public function testDeleteCriterion($criterion)
    {
        $this->assertCount(2, iterator_to_array($this->stub));

        $refl = new ReflectionMethod(AbstractCriterion::class, 'deleteCriterion');
        $refl->setAccessible(true);
        $refl->invoke($this->stub, $criterion);

        $this->assertCount(1, iterator_to_array($this->stub));
    }

    public function getProvider()
    {
        return [['bar'],
                [$this->getMockForAbstractClass(AbstractCriterion::class, ['bar'])]];
    }

    /**
     * @dataProvider methodProvider
     *
     * @expectedException        \CalendArt\Adapter\Google\Exception\CriterionNotFoundException
     * @expectedExceptionMessage The criterion `fubar` was not found. Available criterions are the following : [`bar`, `baz`]
     * @param $method
     */
    public function testWrongCriterion($method)
    {
        $refl = new ReflectionMethod(AbstractCriterion::class, $method . 'Criterion');
        $refl->setAccessible(true);
        $refl->invoke($this->stub, 'fubar');
    }

    public function methodProvider()
    {
        return [['get'], ['delete']];
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Can't merge two different criteria. Had `foo` and `bar`
     */
    public function testMergeNotSameName()
    {
        $this->stub->merge($this->getMockForAbstractClass(AbstractCriterion::class, ['bar']));
    }

    /**
     * @dataProvider mergeProvider
     *
     * @param AbstractCriterion $source
     * @param AbstractCriterion $criterion
     * @param integer $expected Number of expected criteria
     */
    public function testMerge(AbstractCriterion $source = null, AbstractCriterion $criterion = null, $expected)
    {
        $source    = $source ?: $this->stub;
        $criterion = $criterion ?: $this->stub;

        $merge = $source->merge($criterion);

        $this->assertCount($expected, iterator_to_array($merge));
    }

    public function mergeProvider()
    {
        $recursive = $this->getMockForAbstractClass(AbstractCriterion::class,
                                                    ['foo', [$this->getMockForAbstractClass(AbstractCriterion::class, ['bar']),
                                                             $this->getMockForAbstractClass(AbstractCriterion::class, ['fubar'])]]);

        $notRecursive = $this->getMockForAbstractClass(AbstractCriterion::class, ['foo']);

        return [[$notRecursive, $notRecursive, 0],
                [$notRecursive, $this->stub, 0],
                [$recursive, $notRecursive, 0],
                [$this->stub, $recursive, 3]];
    }
}

