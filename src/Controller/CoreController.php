<?php

namespace App\Controller;

use App\Helper\SaasHelper;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CoreController extends GoGoController
{
    public function homeAction($force = false, DocumentManager $dm, SessionInterface $session)
    {
        $sassHelper = new SaasHelper();
        if (!$force && $this->getParameter('use_as_saas') && $sassHelper->isRootProject()) {
            return $this->redirectToRoute('gogo_saas_home');
        }

        $config = $dm->getRepository('App\Document\Configuration')->findConfiguration();
        if (!$config && $this->getParameter('use_as_saas')) {
            $url = 'http://'.$this->getParameter('base_url').$this->generateUrl('gogo_saas_home');

            return $this->redirect($url);
        }
        if (!$config->getActivateHomePage()) {
            return $this->redirectToRoute('gogo_directory');
        }

        // Get Wrapper List
        $listWrappers = $dm->getRepository('App\Document\Wrapper')->findAllOrderedByPosition();
        $mainCategory = $dm->getRepository('App\Document\Category')->findOneByIsRootCategory(true);
        $mainOptions = $mainCategory ? $mainCategory->getOptions() : [];
        $config = $dm->getRepository('App\Document\Configuration')->findConfiguration();

        $session->clear();

        return $this->render('home.html.twig', [
            'listWrappers' => $listWrappers,
            'mainOptions' => $mainOptions,
            'config' => $config, ]);
    }

    public function headerAction($title = 'GoGoCarto', DocumentManager $dm)
    {
        $config = $dm->getRepository('App\Document\Configuration')->findConfiguration();
        $listAbouts = $dm->getRepository('App\Document\About')->findAllOrderedByPosition();
        $countPartners = count($dm->getRepository('App\Document\Partner')->findAll());
        $parameters['config'] = $config;
        $parameters['listAbouts'] = $listAbouts;
        $parameters['countPartners'] = $countPartners;

        return $this->render('header.html.twig', [
            'title' => $title,
            'config' => $config,
            'listAbouts' => $listAbouts,
            'countPartners' => $countPartners,
            'renderedFromController' => true, ]);
    }

    public function partnersAction(DocumentManager $dm)
    {
        $repository = $dm->getRepository('App\Document\Partner');
        $config = $dm->getRepository('App\Document\Configuration')->findConfiguration();

        $listPartners = $repository->findAllOrderedByPosition();

        return $this->render('partners.html.twig', ['listPartners' => $listPartners, 'config' => $config]);
    }

    public function helpAction()
    {
        return $this->render('admin/pages/help.html.twig');
    }
}