<?php
namespace Sigmapix\Sonata\ImportBundle\Step;

use Port\Steps\Step;

class AddParentStep implements Step
{
    public function __construct(string $parentPropertyName = null, $parent = null)
    {
        $this->parentPropertyName = $parentPropertyName;
        $this->parent = $parent;
    }

    public function process($item, callable $next)
    {
        $item[$this->parentPropertyName] = $this->parent;
        return $next($item);
    }
}