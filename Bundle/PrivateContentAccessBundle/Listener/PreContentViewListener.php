<?php
namespace MCC\Bundle\PrivateContentAccessBundle\Listener;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\Symfony\Event\PreContentViewEvent;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use Symfony\Component\Routing\Router;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PreContentViewListener
{
    private $permissionResolver;
    private $repository;
    private $contentService;
    private $em;

    /**
     * @var Router
     */
    private $router;
    private $session;

    public function __construct( PermissionResolver $permissionResolver, Repository $repository, ContentService $contentService, EntityManager $manager, $router, $session)
    {
        $this->permissionResolver = $permissionResolver;
        $this->repository = $repository;
        $this->contentService = $contentService;
        $this->em = $manager;
        $this->router = $router;
        $this->session = $session;
    }

    public function onPreContentView( PreContentViewEvent $event )
    {
        /**
         * @var $contentView ContentView
         */
        $contentView = $event->getContentView();

        try {
            $currentLocation = $contentView->getLocation();
            $content = $contentView->getContent();
        }catch (\Error $event){

        }
        $locationId = $currentLocation->id;

        $current_user = $this->permissionResolver->getCurrentUserReference();

        $result = $this->em->getRepository('MCPrivateContentAccessBundle:PrivateAccess')->findOneBy(['locationId' => $locationId, 'activate' => 1]);

        $eZUser = $this->repository->getCurrentUser();

        $canRead = $this->permissionResolver->canUser('private_content','read', $eZUser);

        if($result != NULL && $canRead){
            return new RedirectResponse($this->router->generate('form_private_access', ['location' => $currentLocation]), '301');
        }
    }
}