<?php

namespace Sigmapix\Sonata\ImportBundle\Admin;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;

trait ImportableAdminTrait
{
    /**
     * @var Form
     */
    private $importForm;

    protected function configureRoutes(RouteCollection $collection)
    {
        /* @var AbstractAdmin $this */
        $collection
                ->add('import', 'upload/{fileName}')
                ->add('upload')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getImportFormBuilder(array $headers)
    {
        /* @var AbstractAdmin $this */
        $this->formOptions['data_class'] = $this->getClass();

        $formBuilder = $this->getFormContractor()->getFormBuilder(
            "import_form", $this->formOptions
        );

        $this->defineImportFormBuilder($formBuilder, $headers);

        return $formBuilder;
    }

    protected function buildImportForm(array $headers)
    {
        /* @var AbstractAdmin $this */
        if ($this->importForm) {
            return;
        }

        $this->importForm = $this->getImportFormBuilder($headers)->getForm();
    }

    // todo: use defineFormBuilder for import Action and upload Action

    public function defineImportFormBuilder(FormBuilderInterface $formBuilder, array $headers)
    {
        /* @var AbstractAdmin $this */
        $mapper = new FormMapper($this->getFormContractor(), $formBuilder, $this);
        $this->configureImportFields($mapper);
        $trans = $this->getConfigurationPool()->getContainer()->get('translator');

        foreach ($formBuilder as $field) {
            /* @var FormBuilder $field */
            if ($field->getType()->getInnerType() instanceof EntityType) continue;
            if ($field->getPropertyPath() && $field->getPropertyPath()->getLength() > 1) {
                $mapper->add((string)$field->getPropertyPath(), 'choice', ['choices' => $headers, 'data' => $this->nearest($field->getName(), $headers, $trans), 'mapped' => false]);
            } else if ((string)$field->getPropertyPath() === 'id') {
                $mapper->add($field->getName(), 'choice', ['choices' => $headers, 'data' => $this->nearest($field->getName(), $headers, $trans), 'mapped' => false]);
            } else {
                $mapper->add($field->getName(), 'choice', ['choices' => $headers, 'data' => $this->nearest($field->getName(), $headers, $trans)]);
            }
        }
        $formBuilder->add('import', SubmitType::class);

        $this->attachInlineValidator();
    }

    public function configureActionButtons($admin, $object = null)
    {
        $buttonList = parent::configureActionButtons($admin, $object);

        $buttonList['import'] = array(
            'template' => 'SigmapixSonataImportBundle:Button:import_button.html.twig',
        );

        return $buttonList;
    }

    public function getImportForm(array $headers)
    {
        /* @var AbstractAdmin $this */
        $this->buildImportForm($headers);

        return $this->importForm;
    }

    private function nearest($input, $words, $trans)
    {
        // TODO $input should be the $field, to try both 'name' and 'propertyPath' attributes
        $closest = "";
        $shortest = -1;

        foreach ($words as $word) {
            $lev = levenshtein($input, $word);
            $levCase = levenshtein(strtolower($input), strtolower($word));
            $levTrans = levenshtein($trans->trans($input), $word);
            $lev = min([$lev, $levCase, $levTrans]);
            if ($lev === 0) {
                $closest = $word;
                $shortest = 0;
                break;
            }
            if ($lev <= $shortest || $shortest < 0) {
                $closest  = $word;
                $shortest = $lev;
            }
        }

        return $closest;
    }

    /**
     * This method can be overloaded in your Admin service.
     * It's called from importAction.
     *
     * @param Request $request
     * @param Form $form
     *
     * @return Response|null
     */
    public function preImport(Request $request, Form $form)
    {
    }

    /**
     * This method can be overloaded in your Admin service.
     * It's called from importAction.
     *
     * @param Request $request
     * @param UploadedFile   $file
     * @param Form $form
     * @param mixed $results
     *
     * @return Response|null
     */
    public function postImport(Request $request, UploadedFile $file, Form $form, $results)
    {
    }
}
