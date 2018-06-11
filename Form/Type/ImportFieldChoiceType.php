<?php

namespace Sigmapix\Sonata\ImportBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ImportFieldChoiceType.
 */
class ImportFieldChoiceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        // We add format option for dealing with DateTimeConverter
        $resolver->setDefault('label_format', 'd/m/Y');
    }
}
