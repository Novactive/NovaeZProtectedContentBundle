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

namespace Novactive\Bundle\eZProtectedContentBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\Core\MVC\Symfony\Event\PreContentViewEvent;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedAccess;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedTokenStorage;
use Novactive\Bundle\eZProtectedContentBundle\Form\RequestEmailProtectedAccessType;
use Novactive\Bundle\eZProtectedContentBundle\Form\RequestProtectedAccessType;
use Novactive\Bundle\eZProtectedContentBundle\Repository\ProtectedTokenStorageRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PreContentView
{
    /**
     * @var PermissionResolver
     */
    private $permissionResolver;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        PermissionResolver $permissionResolver,
        EntityManagerInterface $manager,
        FormFactoryInterface $factory,
        RequestStack $requestStack
    ) {
        $this->permissionResolver = $permissionResolver;
        $this->entityManager      = $manager;
        $this->formFactory        = $factory;
        $this->requestStack       = $requestStack;
    }

    public function onPreContentView(PreContentViewEvent $event)
    {
        $contentView = $event->getContentView();

        if (!$contentView instanceof ContentView) {
            return;
        }

        if ('full' !== $contentView->getViewType()) {
            return;
        }

        $content = $contentView->getContent();

        $protections = $this->entityManager->getRepository(ProtectedAccess::class)->findByContent($content);

        if (0 == count($protections)) {
            return;
        }
        $contentView->setCacheEnabled(false);
        $canRead = $this->permissionResolver->canUser('private_content', 'read', $content);

        if (!$canRead) {
            $request = $this->requestStack->getCurrentRequest();

            if ($request->query->has('mail')
                && $request->query->has('token')
                && !$request->query->has('waiting_validation')
            ) {
                /** @var ProtectedTokenStorageRepository $protectedTokenStorageRepository */
                $protectedTokenStorageRepository = $this->entityManager->getRepository(ProtectedTokenStorage::class);
                $unexpiredToken = $protectedTokenStorageRepository->findUnexpiredBy([
                    'content_id'      => $content->id,
                    'token'           => $request->get('token'),
                    'mail'            => $request->get('mail')
                ]);

                if (count($unexpiredToken) > 0 ) {
                    $canRead = true;
                }
            } else {
                $cookies = $request->cookies;
                foreach ($cookies as $name => $value) {
                    if (PasswordProvided::COOKIE_PREFIX !== substr($name, 0, \strlen(PasswordProvided::COOKIE_PREFIX))) {
                        continue;
                    }
                    if (str_replace(PasswordProvided::COOKIE_PREFIX, '', $name) !== $value) {
                        continue;
                    }
                    foreach ($protections as $protection) {
                        /** @var ProtectedAccess $protection */
                        if (md5($protection->getPassword()) === $value) {
                            $canRead = true;
                        }
                    }
                }
            }
        }
        $contentView->addParameters(['canReadProtectedContent' => $canRead]);

        if (!$canRead) {
            if ($this->getContentProtectionType($protections) == 'by_mail') {
                $form = $this->formFactory->create(RequestEmailProtectedAccessType::class);
                $contentView->addParameters(['requestProtectedContentEmailForm' => $form->createView()]);
            } else {
                $form = $this->formFactory->create(RequestProtectedAccessType::class);
                $contentView->addParameters(['requestProtectedContentPasswordForm' => $form->createView()]);
            }
        }
    }

    private function getContentProtectionType(array $protections): string {
        foreach ($protections as $protection) {
            /** @var ProtectedAccess $protection */
            if ( !is_null($protection->getPassword()) && $protection->getPassword() != '' ) {
                return 'by_password';
            }
        }
        return 'by_mail';
    }
}
