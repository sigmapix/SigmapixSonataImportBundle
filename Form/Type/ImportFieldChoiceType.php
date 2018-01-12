<?php
namespace Sigmapix\Sonata\ImportBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Class ImportFieldChoiceType
 * @package Sigmapix\Sonata\ImportBundle\Form\Type
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
}