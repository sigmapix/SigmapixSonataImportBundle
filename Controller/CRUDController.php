<?php

namespace Sigmapix\Sonata\ImportBundle\Controller;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Sigmapix\Sonata\ImportBundle\Admin\ImportableAdminTrait;
use Sigmapix\Sonata\ImportBundle\Service\ImportService;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CRUDController extends Controller
{
    /**
     * Export data to specified format.
     *
     * @param Request $request
     *
     * @throws AccessDeniedException If access is not granted
     * @throws \RuntimeException     If the export format is invalid
     *
     * @return Response
     */
    public function exportAction(Request $request)
    {
        $this->admin->checkAccess('export');

        $format = $request->get('format');

        // NEXT_MAJOR: remove the check
        if (!$this->has('sonata.admin.admin_exporter')) {
            @trigger_error(
                'Not registering the exporter bundle is deprecated since version 3.14.'
                .' You must register it to be able to use the export action in 4.0.',
                E_USER_DEPRECATED
            );
            $allowedExportFormats = (array) $this->admin->getExportFormats();

            $class = $this->admin->getClass();
            $filename = sprintf(
                'export_%s_%s.%s',
                strtolower(substr($class, strripos($class, '\\') + 1)),
                date('Y_m_d_H_i_s', strtotime('now')),
                $format
            );
            $exporter = $this->get('sonata.admin.exporter');
        } else {
            $adminExporter = $this->get('sonata.admin.admin_exporter');
            $allowedExportFormats = $adminExporter->getAvailableFormats($this->admin);
            $filename = $adminExporter->getExportFilename($this->admin, $format);
            $exporter = $this->get('sonata.exporter.exporter');
        }

        if (!\in_array($format, $allowedExportFormats)) {
            throw new \RuntimeException(
                sprintf(
                    'Export in format `%s` is not allowed for class: `%s`. Allowed formats are: `%s`',
                    $format,
                    $this->admin->getClass(),
                    implode(', ', $allowedExportFormats)
                )
            );
        }

        $defaultHeaders = array_map(function () {return ''; }, $this->admin->getExportFields());

        return $exporter->getResponse(
            $format,
            $filename,
            $this->admin->getDataSourceIterator(),
            $defaultHeaders
        );
    }

    public function uploadAction(Request $request)
    {
        /** @var $admin ImportableAdminTrait */
        $admin = $this->admin;

        // todo: $admin->checkAccess('import');
        $admin->setFormTabs(['default' => ['groups' => []]]);
        $formBuilder = $this->createFormBuilder();
        $formBuilder
            ->add('importFile', FileType::class)
            ->add('upload', SubmitType::class)
        ;
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $files = $request->files->get('form');
                if (!empty($files) && array_key_exists('importFile', $files)) {
                    /** @var $file UploadedFile */
                    $file = $files['importFile'];
                    $fileName = md5(uniqid());

                    $file->move($this->getParameter('import_directory'), $fileName);

                    return $this->redirect($admin->generateUrl('import', ['fileName' => $fileName]));
                }
            }
            // todo: show an error message if the form failed validation
        }

        return $this->render('SigmapixSonataImportBundle:CRUD:base_import_form.html.twig', [
            'action' => 'upload',
            'form' => $form->createView(),
            'object' => null,
        ], null);
    }

    public function importAction(Request $request)
    {
        /** @var $admin ImportableAdminTrait */
        $admin = $this->admin;

        /** @var $is ImportService */
        $is = $this->get('sigmapix.sonata.import.service');

        // todo: $admin->checkAccess('import');

        $fileName = $request->get('fileName');
        if (!empty($fileName)) {
            try {
                $file = new UploadedFile($this->getParameter('import_directory').$fileName, $fileName);

                $headers = $is->getHeaders($file);

                /** @var $form \Symfony\Component\Form\Form */
                $form = $admin->getImportForm($headers);
                $form->handleRequest($request);

                if ($form->isSubmitted()) {
                    $preResponse = $admin->preImport($request, $form);
                    if (null !== $preResponse) {
                        return $preResponse;
                    }

                    try {
                        $results = $is->import($file, $form, $admin);

                        $postResponse = $admin->postImport($request, $file, $form, $results);

                        if (null !== $postResponse) {
                            return $postResponse;
                        }

                        $this->addFlash('success', 'message_success');
                    } catch (ConstraintViolationException $constraintViolationException) {
                        $this->addFlash('error', $constraintViolationException->getMessage());
                    } catch (\Exception $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }

                    return $this->redirect($admin->generateUrl('list'));
                }
            } catch (FileException $e) {
                // TODO: show an error message if the file is missing
            }
        }
        // todo: show an error message if the fileName is missing

        return $this->render('SigmapixSonataImportBundle:CRUD:base_import_form.html.twig', [
            'action' => 'import',
            'form' => $form->createView(),
            'object' => null,
        ], null);
    }
}
