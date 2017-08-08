<?php

namespace Sigmapix\Sonata\ImportBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SigmapixSonataImportBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'SonataAdminBundle';
    }
}
