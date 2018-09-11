<?php

namespace Sigmapix\Sonata\ImportBundle\Admin;

use Port\Steps\Step\ValueConverterStep;
use Port\Steps\StepAggregator;
use Port\ValueConverter\DateTimeValueConverter;
use Sigmapix\Sonata\ImportBundle\Form\Type\ImportFieldChoiceType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Builder\FormContractorInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Trait ImportableAdminTrait
 * @package Sigmapix\Sonata\ImportBundle\Admin
 */
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
     *
     * @throws \ReflectionException
     */
    public function getImportFormBuilder(array $headers)
    {
        $class = $this->hasActiveSubClass() ? $this->getActiveSubClass() : $this->getClass();
        if ((new \ReflectionClass($class))->isAbstract()) {
            // If $class is Abstract, then use the first one.
            // Developers should then instantiate the good class by overriding DoctrineWrite::writeItem()
            $class = array_values($this->getSubClasses())[0];
        }

        $this->formOptions['data_class'] = $class;

        $formBuilder = $this->getFormContractor()->getFormBuilder(
            'import_form', $this->formOptions
        );

        $this->defineImportFormBuilder($formBuilder, $headers);

        return $formBuilder;
    }

    /**
     * @param FormBuilderInterface $formBuilder
     * @param array $headers
     * todo: use defineFormBuilder for import Action and upload Action
     */
    public function defineImportFormBuilder(FormBuilderInterface $formBuilder, array $headers)
    {
        /** @var AbstractAdmin $this */
        $mapper = new FormMapper($this->getFormContractor(), $formBuilder, $this);
        $this->configureImportFields($mapper);
        /** @var ContainerInterface $container */
        $container = $this->getConfigurationPool()->getContainer();
        $trans = $container->get('translator');

        $oldValue = ini_get('mbstring.substitute_character');
        ini_set('mbstring.substitute_character', 'none');
        foreach ($formBuilder as $field) {
            /* @var FormBuilder $field */
            if ($field->getType()->getInnerType() instanceof EntityType) {
                continue;
            }
            $propertyPath = $field->getPropertyPath();
            if ($propertyPath && $propertyPath->getLength() > 1) {
                $mapper->add(
                    (string) $propertyPath, ImportFieldChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans),
                    'mapped' => false,
                    'label' => $field->getOption('label')
                ]);
            } elseif ((string) $propertyPath === 'id') {
                $mapper->add($field->getName(), ImportFieldChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans),
                    'mapped' => false,
                    'label' => $field->getOption('label')
                ]);
            } else {
                $mapper->add($field->getName(), ImportFieldChoiceType::class, [
                    'choices' => $headers,
                    'data' => $this->nearest($field->getOption('label'), $headers, $trans, $field->getOption('translation_domain')),
                    'mapped' => $field->getOption('mapped'),
                    'label' => $field->getOption('label'),
                    'label_format' => $field->getOption('label_format'), // This will be used for DateTimeConverter
                    'translation_domain' => $field->getOption('translation_domain'),
                ]);
            }
        }
        ini_set('mbstring.substitute_character', $oldValue);
        $formBuilder->add('import', SubmitType::class);
        $this->attachInlineValidator();
    }

    /**
     * @param $admin
     * @param null $object
     * @return mixed
     */
    public function configureActionButtons($admin, $object = null)
    {
        $buttonList = parent::configureActionButtons($admin, $object);
        $buttonList['import'] = [
            'template' => 'SigmapixSonataImportBundle:Button:import_button.html.twig'
        ];
        return $buttonList;
    }

    /**
     * @param array $headers
     * @return Form
     * @throws \ReflectionException
     */
    public function getImportForm(array $headers)
    {
        $this->buildImportForm($headers);
        return $this->importForm;
    }

    /**
     * @param StepAggregator $workflow
     */
    public function configureImportSteps(StepAggregator $workflow)
    {
        $dateTimeFields = [];
        foreach ($this->importForm as $f) {
            /** @var Form $f */
            /** @var FieldDescription $fieldOptions */
            $fieldOptions = $f->getConfig()->getOption('sonata_field_description');
            if ($fieldOptions && ('datetime' === $fieldOptions->getMappingType() || 'date' === $fieldOptions->getMappingType() || $f->getConfig()->getOption('label_format'))) {
                $dateTimeFields[$f->getName()] = $f->getConfig()->getOption('label_format');
            }
        }
        $converterStep = new ValueConverterStep();
        foreach ($dateTimeFields as $dateTimeField => $dateTimeFormat) {
            $converter = new DateTimeValueConverter($dateTimeFormat);
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

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        /* @var AbstractAdmin $this */
        $collection
                ->add('import', 'upload/{fileName}')
                ->add('upload')
        ;
    }

    /**
     * @param array $headers
     * @throws \ReflectionException
     */
    protected function buildImportForm(array $headers)
    {
        if ($this->importForm) {
            return;
        }
        $this->importForm = $this->getImportFormBuilder($headers)->getForm();
    }

    /**
     * @param $input
     * @param $words
     * @param TranslatorInterface $trans
     * @param string $domain
     * @return string
     */
    private function nearest($input, $words, TranslatorInterface $trans, $domain = null)
    {
        // TODO $input should be the $field, to try both 'name' and 'propertyPath' attributes
        $domain = $domain ?: 'messages';
        $closest = '';
        $shortest = -1;

        foreach ($words as $word) {
            $wordASCII = mb_convert_encoding($word, 'ASCII');
            $lev = levenshtein($input, $wordASCII);
            $levCase = levenshtein(strtolower($input), strtolower($wordASCII));
            $levTrans = levenshtein($trans->trans($input, [], $domain), $wordASCII);
            $lev = min([$lev, $levCase, $levTrans]);
            if ($lev === 0) {
                $closest = $word;
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
