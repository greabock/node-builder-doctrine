<?php

namespace Greabock\NodeBuilder\Doctrine\Annotations;

use Doctrine\Common\Annotations\Annotation;
use Greabock\NodeBuilder\Support\Contracts\NodeInterface;

/**
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class Node extends Annotation implements NodeInterface
{
    const STRATEGY_RESTRICT = 0;
    const STRATEGY_MAGIC = 1;

    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var array
     */
    public $middleware = [];

    /**
     * @var string
     */
    public $setter;

    /**
     * @var int
     */
    public $strategy = Node::STRATEGY_RESTRICT;

    public function getSetter()
    {
        return $this->setter;
    }

    public function getMiddleWare():array
    {
        return $this->middleware;
    }

    public function getFieldName():string
    {
        return $this->fieldName;
    }

    public function getStrategy():int
    {
        return $this->strategy;
    }
}