<?php

/**
 * NovaeZProtectedContentBundle.
 *
 * @package   Novactive\Bundle\eZProtectedContentBundle
 *
 * @author    Novactive
 * @copyright 2019 Novactive
 * @license   https://github.com/Novactive/eZProtectedContentBundle/blob/master/LICENSE MIT Licence
 */

declare(strict_types=1);

namespace Novactive\Bundle\eZProtectedContentBundle\Controller\Admin;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\HttpCache\Handler\ContentTagInterface;
use Ibexa\Core\Repository\SiteAccessAware\Repository;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedAccess;
use Novactive\Bundle\eZProtectedContentBundle\Form\ProtectedAccessType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class ProtectedAccessController
{
    public const GROUP='nova_protected_content';
    public const STATE_DEFAULT='default';
    public const STATE_PROTECTED='protected';

    public function __construct(
        protected readonly Repository $repository,
        protected readonly \Ibexa\Contracts\Core\Search\Handler $searchHandler,
        protected readonly \Ibexa\Contracts\Core\Persistence\Handler $persistenceHandler,
    ) { }

    /**
     * @Route("/handle/{locationId}/{access}", name="novaezprotectedcontent_bundle_admin_handle_form",
     *                                           defaults={"accessId": null})
     */
    //#[Route(path: '/handle/{locationId}/{access}', name: 'novaezprotectedcontent_bundle_admin_handle_form')]
    public function handle(
        int $locationId,
        Request $request,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        ContentTagInterface $responseTagger,
        ?ProtectedAccess $access = null,
    ): RedirectResponse {
        if ($request->isMethod('post')) {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
            $now = new DateTime();
            if (null === $access) {
                $access = new ProtectedAccess();
                $access->setCreated($now);
            }
            $form = $formFactory->create(ProtectedAccessType::class, $access);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $access->setUpdated($now);
                $entityManager->persist($access);
                $entityManager->flush();
                $responseTagger->addLocationTags([$location->id]);
                $responseTagger->addParentLocationTags([$location->parentLocationId]);

                $content = $location->getContent();
                $this->setState($content, SELF::STATE_DEFAULT);
                if ($access->isProtectChildren()) {
                    $this->updateChildrenState($content, SELF::STATE_DEFAULT);
                }
            }
        }

        return new RedirectResponse(
            $router->generate('ibexa.content.view', ['contentId' => $location->contentId,
                'locationId' => $location->id,
            ]).
            '#ibexa-tab-location-view-protect-content#tab'
        );
    }

    #[Route(path: '/remove/{locationId}/{access}', name: 'novaezprotectedcontent_bundle_admin_remove_protection')]
    public function remove(
        Location $location,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        int $access,
        ContentTagInterface $responseTagger
    ): RedirectResponse {
        $access = $entityManager->find(ProtectedAccess::class, $access);
        $entityManager->remove($access);
        $entityManager->flush();
        $responseTagger->addLocationTags([$location->id]);
        $responseTagger->addParentLocationTags([$location->parentLocationId]);

        $content = $location->getContent();
        $this->setState($content, SELF::STATE_DEFAULT);
        if ($access->isProtectChildren()) {
            $this->updateChildrenState($content, SELF::STATE_DEFAULT);
        }

        return new RedirectResponse(
            $router->generate('ibexa.content.view', ['contentId' => $location->contentId,
                'locationId' => $location->id,
            ]).
            '#ibexa-tab-location-view-protect-content#tab'
        );
    }

    protected function updateChildrenState(Content $content, string $state): void
    {
        $locations = $this->repository->getLocationService()->loadLocations($content->contentInfo);
        $pathStringArray = [];
        foreach ($locations as $location) {
            /** @var Location $location */
            $pathStringArray[] = $location->pathString;
        }

        if ($pathStringArray) {
            $query = new Query();
            $query->limit = 100;
            $query->filter = new Query\Criterion\LogicalAnd([
                new Query\Criterion\Subtree($pathStringArray)
            ]);
            $searchResult = $this->repository->getSearchService()->findContent($query);
            foreach ($searchResult->searchHits as $hit) {
                $this->setState($hit->valueObject, $state);
            }
        }
    }

    public function setState(Content $content, string $state): void
    {
        $group = $this->repository->getObjectStateService()->loadObjectStateGroupByIdentifier(self::GROUP);
        $state = $this->repository->getObjectStateService()->loadObjectStateByIdentifier($group,$state);
        $this->repository->getObjectStateService()->setContentState($content->getContentInfo(), $group, $state);
    }
}
