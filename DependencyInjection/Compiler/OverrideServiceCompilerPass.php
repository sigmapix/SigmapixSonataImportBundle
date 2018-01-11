<?php

namespace Sigmapix\Sonata\ImportBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OverrideServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('sonata.admin.exporter');
        $definition->setClass('Sigmapix\Sonata\ImportBundle\Export\Exporter');
    }
}
