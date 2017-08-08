<?php

namespace Sigmapix\Sonata\ImportBundle\Controller;

use Sigmapix\Sonata\ImportBundle\Admin\ImportableAdminTrait;
use Sigmapix\Sonata\ImportBundle\Service\ImportService;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class CRUDController extends Controller
{

    public function uploadAction(Request $request)
    {
        /** @var $admin ImportableAdminTrait */
        $admin = $this->admin;

        // todo: $admin->checkAccess('import');
        $admin->setFormTabs(array('default'=>array('groups' => array())));
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
                    return $this->redirect($admin->generateUrl('import', array('fileName' => $fileName)));
                }
            }
            // todo: show an error message if the form failed validation
        }

        return $this->render('SigmapixSonataImportBundle:CRUD:base_import_form.html.twig', array(
            'action' => 'upload',
            'form' => $form->createView(),
            'object' => null
        ), null);
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
                $file = new UploadedFile($this->getParameter('import_directory') . $fileName, $fileName);

                $headers = $is->getHeaders($file);

                /** @var $form \Symfony\Component\Form\Form */
                $form = $admin->getImportForm($headers);
                $form->handleRequest($request);

                if ($form->isSubmitted()) {
                    if ($form->isValid()) {

                        $preResponse = $admin->preImport($request, $form);
                        if ($preResponse !== null) {
                            return $preResponse;
                        }

                        if ($is->import($file, $form)) {

                            $postResponse = $admin->postImport($request, $file, $form);
                            if ($postResponse !== null) {
                                return $postResponse;
                            }

                            $this->addFlash("success", "ok"); // todo: show a success message
                        } else {
                            $this->addFlash("error", "ko"); // todo: show an error message
                        }

                        return $this->redirect($admin->generateUrl('list'));
                    }
                    // todo: show an error message if the form failed validation
                }
            } catch (FileException $e) {
                // TODO: show an error message if the file is missing
            }
        } else {
            // todo: show an error message if the fileName is missing
        }

        return $this->render('SigmapixSonataImportBundle:CRUD:base_import_form.html.twig', array(
            'action' => 'import',
            'form' => $form->createView(),
            'object' => null
        ), null);
    }
}