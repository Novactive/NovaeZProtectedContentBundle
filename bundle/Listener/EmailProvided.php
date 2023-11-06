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

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedAccess;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedTokenStorage;
use Novactive\Bundle\eZProtectedContentBundle\Form\RequestEmailProtectedAccessType;
use Swift_Message;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Swift_Mailer;
use Symfony\Component\Translation\TranslatorInterface;

class EmailProvided
{
    protected const SENDMAIL_ERROR = 'Impossible d\'envoyer le lien formaté à l\'adresse mail %s';
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;
    /**
     * @var Swift_Mailer
     */
    private $mailer;
    /**
     * @var Swift_Message
     */
    protected $messageInstance;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        FormFactoryInterface $formFactory,
        Swift_Mailer $mailer,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    )
    {
        $this->formFactory   = $formFactory;
        $this->mailer        = $mailer;
        $this->entityManager = $entityManager;
        $this->translator    = $translator;
        $this->messageInstance = new Swift_Message();
    }

    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $form = $this->formFactory->create(RequestEmailProtectedAccessType::class);

        $request = $event->getRequest();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data           = $form->getData();
            $contentId      = intval($data['content_id']);
            $randomString   = bin2hex(random_bytes(16));
            $token          = substr($randomString, 0, 16);

            $access         = new ProtectedTokenStorage();
            $access->setMail($data['email']);
            $access->setContentId($contentId);
            $access->setCreated(new DateTime());
            $access->setToken($token);

            $this->entityManager->persist($access);
            $this->entityManager->flush();

            $currentUrl = $request->getScheme().'://'.$request->getHost().$request->getBaseUrl().$request->getRequestUri();
            $accessUrl  = $currentUrl."?mail=".$data['email']."&token=".$token;
            $this->sendMail($contentId, $data['email'], $accessUrl);
            $response   = new RedirectResponse($request->getRequestUri()."?waiting_validation=".$data['email']);
            $response->setPrivate();
            $event->setResponse($response);
        }
    }

    /**
     * @throws Exception
     */
    private function sendMail(int $contentId, string $receiver, string $link): void {
        /** @var ProtectedAccess $protectedAccess */
        $protectedAccess = $this->entityManager->getRepository(ProtectedAccess::class)->findOneBy(['contentId' => $contentId]);

        $message = $this->messageInstance
            ->setSubject('Access to protected content')
            ->setFrom('noreply@culture.gouv.fr')
            ->setTo($receiver)
            ->setContentType('text/html')
            ->setBody(
                $protectedAccess->getEmailMessage()
                . "</br><a href='$link'>".$this->translator->trans('mail.link')."</a>"
            );

        try {
            $this->mailer->send($message);
        } catch (Exception $exception) {
            throw new Exception(sprintf(self::SENDMAIL_ERROR, $receiver));
        }
    }
}
