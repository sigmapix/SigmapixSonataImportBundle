<?php

namespace Sigmapix\Sonata\ImportBundle\Admin;

use Port\Steps\Step\ValueConverterStep;
use Port\Steps\StepAggregator;
use Port\ValueConverter\DateTimeValueConverter;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Builder\FormContractorInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait ImportableAdminTrait
{
    /**
     * Options to set to the form (ie, validation_groups).
     *
     * @var array
     */
    protected $formOptions = [];
    /**
     * @var Form
     */
    private $importForm;

    /**
     * {@inheritdoc}
     */
    abstract public function getClass();

    /**
     * @return Pool
     */
    abstract public function getConfigurationPool();

    /**
     * @return FormContractorInterface
     */
    abstract public function getFormContractor();

    /**
     * {@inheritdoc}
     */
    public function getImportFormBuilder(array $headers)
    {
        $this->formOptions['data_class'] = $this->getClass();

        $formBuilder = $this->getFormContractor()->getFormBuilder(
            'import_form', $this->formOptions
        );

        $this->defineImportFormBuilder($formBuilder, $headers);

        return $formBuilder;
    }

    // todo: use defineFormBuilder for import Action and upload Action

    public function defineImportFormBuilder(FormBuilderInterface $formBuilder, array $headers)
    {
        /** @var AbstractAdmin $this */
        $mapper = new FormMapper($this->getFormContractor(), $formBuilder, $this);
        $this->configureImportFields($mapper);
        $trans = $this->getConfigurationPool()->getContainer()->get('translator');

        $oldValue = ini_get('mbstring.substitute_character');
        ini_set('mbstring.substitute_character', 'none');
        foreach ($formBuilder as $field) {
            /* @var FormBuilder $field */
            if ($field->getType()->getInnerType() instanceof EntityType) {
                continue;
            }
            if ($field->getPropertyPath() && $field->getPropertyPath()->getLength() > 1) {
                $mapper->add(
                    (string) $field->getPropertyPath(), ChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans),
                    'mapped' => false,
                    'label' => $field->getOption('label'),
                ]);
            } elseif ('id' === (string) $field->getPropertyPath()) {
                $mapper->add($field->getName(), ChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans),
                    'mapped' => false,
                    'label' => $field->getOption('label'),
                ]);
            } else {
                $mapper->add($field->getName(), ChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans),
                    'label' => $field->getOption('label'),
                ]);
            }
        }
        ini_set('mbstring.substitute_character', $oldValue);
        $formBuilder->add('import', SubmitType::class);

        $this->attachInlineValidator();
    }

    public function configureActionButtons($admin, $object = null)
    {
        $buttonList = parent::configureActionButtons($admin, $object);

        $buttonList['import'] = [
            'template' => 'SigmapixSonataImportBundle:Button:import_button.html.twig',
        ];

        return $buttonList;
    }

    public function getImportForm(array $headers)
    {
        $this->buildImportForm($headers);

        return $this->importForm;
    }

    public function configureImportSteps(StepAggregator $workflow)
    {
        $dateTimeFields = [];
        foreach ($this->importForm as $f) {

            /** @var Form $f */
            /** @var FieldDescription $fieldOptions */
            $fieldOptions = $f->getConfig()->getOption('sonata_field_description');
            if ($fieldOptions && ('datetime' === $fieldOptions->getMappingType() || 'date' === $fieldOptions->getMappingType())) {
                $dateTimeFields[] = $f->getName();
            }
        }
        $converter = new DateTimeValueConverter('d/m/Y');
        $converterStep = new ValueConverterStep();
        foreach ($dateTimeFields as $dateTimeField) {
            $converterStep->add('['.$dateTimeField.']', $converter);
        }

        $workflow->addStep($converterStep);
    }

    /**
     * This method can be overloaded in your Admin service.
     * It's called from importAction.
     *
     * @param Request $request
     * @param Form    $form
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
     * @param Request      $request
     * @param UploadedFile $file
     * @param Form         $form
     * @param mixed        $results
     *
     * @return Response|null
     */
    public function postImport(Request $request, UploadedFile $file, Form $form, $results)
    {
    }

    /**
     * @param FormMapper $formMapper
     */
    abstract protected function configureImportFields(FormMapper $formMapper);

    /**
     * Attach the inline validator to the model metadata, this must be done once per admin.
     */
    abstract protected function attachInlineValidator();

    protected function configureRoutes(RouteCollection $collection)
    {
        /* @var AbstractAdmin $this */
        $collection
                ->add('import', 'upload/{fileName}')
                ->add('upload')
        ;
    }

    protected function buildImportForm(array $headers)
    {
        if ($this->importForm) {
            return;
        }

        $this->importForm = $this->getImportFormBuilder($headers)->getForm();
    }

    private function nearest($input, $words, $trans)
    {
        // TODO $input should be the $field, to try both 'name' and 'propertyPath' attributes
        $closest = '';
        $shortest = -1;

        foreach ($words as $word) {
            $wordASCII = mb_convert_encoding($word, 'ASCII');
            $lev = levenshtein($input, $wordASCII);
            $levCase = levenshtein(strtolower($input), strtolower($wordASCII));
            $levTrans = levenshtein($trans->trans($input), $wordASCII);
            $lev = min([$lev, $levCase, $levTrans]);
            if (0 === $lev) {
                $closest = $word;
                $shortest = 0;
                break;
            }
            if ($lev <= $shortest || $shortest < 0) {
                $closest = $word;
                $shortest = $lev;
            }
        }

        return $closest;
    }
}
