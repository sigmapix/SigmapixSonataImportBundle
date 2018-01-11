<?php

namespace Sigmapix\Sonata\ImportBundle;

use Sigmapix\Sonata\ImportBundle\DependencyInjection\Compiler\OverrideServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SigmapixSonataImportBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new OverrideServiceCompilerPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'SonataAdminBundle';
    }
}
