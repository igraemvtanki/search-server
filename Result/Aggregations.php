<?php

/*
 * This file is part of the SearchBundle for Symfony2.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Mmoreram\SearchBundle\Result;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Class Aggregations.
 */
class Aggregations implements IteratorAggregate
{
    /**
     * @var Aggregation[]
     *
     * Aggregations
     */
    private $aggregations = [];

    /**
     * @var int
     *
     * Total elements
     */
    private $totalElements;

    /**
     * Aggregations constructor.
     *
     * @param int $totalElements
     */
    public function __construct(int $totalElements)
    {
        $this->totalElements = $totalElements;
    }

    /**
     * Add aggregation value.
     *
     * @param string      $name
     * @param Aggregation $aggregation
     */
    public function addAggregation(
        string $name,
        Aggregation $aggregation
    ) {
        $this->aggregations[$name] = $aggregation;
    }

    /**
     * Get aggregations.
     *
     * @return Aggregation[]
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * Get aggregation.
     *
     * @param string $name
     *
     * @return null|Aggregation
     */
    public function getAggregation(string $name) : ? Aggregation
    {
        return $this->aggregations[$name] ?? null;
    }

    /**
     * Get total elements.
     *
     * @return int
     */
    public function getTotalElements() : int
    {
        return $this->totalElements;
    }

    /**
     * Retrieve an external iterator.
     *
     * @link  http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     *                     <b>Traversable</b>
     *
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new ArrayIterator($this->aggregations);
    }
}