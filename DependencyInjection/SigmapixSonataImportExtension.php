<?php

namespace Sigmapix\Sonata\ImportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class SigmapixSonataImportExtension.
 */
class SigmapixSonataImportExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('config.yml');
        $this->registerFormTheme($container);
    }

    private function registerFormTheme(ContainerBuilder $container)
    {
        $resources = $container->hasParameter('twig.form.resources') ?
            $container->getParameter('twig.form.resources') : [];

        array_unshift($resources, '@SigmapixSonataImport/Form/fields.html.twig');
        $container->setParameter('twig.form.resources', $resources);
    }
}
