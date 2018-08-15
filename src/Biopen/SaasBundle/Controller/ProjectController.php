<?php

namespace Biopen\SaasBundle\Controller;

use Biopen\SaasBundle\Controller\AbstractSaasController;
use Biopen\SaasBundle\Helper\SaasHelper;
use Symfony\Component\HttpFoundation\Request;
use Biopen\SaasBundle\Document\Project;
use Symfony\Component\HttpFoundation\Response;
use Biopen\CoreBundle\Document\Configuration;
use Biopen\CoreBundle\DataFixtures\MongoDB\LoadTileLayers;
use Application\Sonata\UserBundle\Form\Type\RegistrationFormType;
use Biopen\CoreBundle\Document\User;
use FOS\UserBundle\Model\UserInterface;
use Biopen\CoreBundle\DataFixtures\MongoDB\LoadConfiguration;

class ProjectController extends AbstractSaasController
{
    protected function isAuthorized()
    {
        $sassHelper = new SaasHelper();
        return $sassHelper->isRootProject();
    }

    protected function getOdmForProject($project)
    {
        $odm = $this->get('doctrine_mongodb')->getManager();
        $odm->getConfiguration()->setDefaultDB($project->getDbName());
        return $odm;
    }

    protected function generateUrlForProject($project, $route = 'biopen_homepage')
    {
        return 'http://' . $project->getDomainName() . '.' . $this->container->getParameter('saas_base_url') . $this->generateUrl($route);
    }

    public function createAction(Request $request)
    {
        if (!$this->isAuthorized()) return $this->redirectToRoute('biopen_homepage');

        $project = new Project();

        $projectForm = $this->createFormBuilder($project)
            ->add('name', null, array('required' => true))
            ->add('domainName', null, array('required' => true))
            ->getForm();

        if ($projectForm->handleRequest($request)->isValid())
        {
            dump($project);
            // "/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)/"
            $odm = $this->get('doctrine_mongodb')->getManager();
            $odm->persist($project);    
            $odm->flush();

            $projectOdm = $this->getOdmForProject($project);

            $confLoader = new LoadConfiguration();
            $configuration = $confLoader->load($projectOdm);

            $configuration->setAppName($project->getName());
            $configuration->setAppBaseline("");

            $projectOdm->flush();

            $url = $this->generateUrlForProject($project, 'biopen_saas_initialize_project');
            return $this->redirect($url);
        }
        
        return $this->render('@BiopenSaasBundle/projects/create.html.twig', ['form' => $projectForm->createView()]);
    }
    
    public function homeAction()
    {        
        if (!$this->isAuthorized()) return $this->redirectToRoute('biopen_homepage');

        $odm = $this->get('doctrine_mongodb')->getManager();
        $repository = $odm->getRepository('BiopenSaasBundle:Project');

        $projects = $repository->findAll();
        foreach ($projects as $project) {
            $project->setHomeUrl($this->generateUrlForProject($project));
        }

        return $this->render('@BiopenSaasBundle/home.html.twig', array('projects' => $projects));        
    }  

    public function initializeAction(Request $request)  
    {
        $odm = $this->get('doctrine_mongodb')->getManager();
        $users = $odm->getRepository('BiopenCoreBundle:User')->findAll();
        if (count($users) > 0) return $this->redirectToRoute('biopen_homepage');

        $userManager = $this->container->get('fos_user.user_manager');
        $user = $userManager->createUser();  
        
        $form = $this->get('form.factory')->create(RegistrationFormType::class, $user);

        if ($form->handleRequest($request)->isValid()) {
            $user = $form->getData();
            $user->setRoles(array('ROLE_SUPER_ADMIN','ROLE_ADMIN', 'ROLE_SONATA_ADMIN'));
            $userManager->updateUser($user, true);  

            $this->get('session')->getFlashBag()->add('success', 'Administrateur créé !');
            $response = $this->redirectToRoute('sonata_admin_dashboard');                      

            $this->authenticateUser($user, $response);

            return $response;
        }

        return $this->render('@BiopenSaasBundle/projects/initialize.html.twig', ['form' => $form->createView()]);
    }

    protected function authenticateUser(UserInterface $user, Response $response)
    {
        try {
            $this->get('fos_user.security.login_manager')->loginUser(
                $this->container->getParameter('fos_user.firewall_name'),
                $user,
                $response
            );
        } catch (AccountStatusException $ex) { }
    }
}
