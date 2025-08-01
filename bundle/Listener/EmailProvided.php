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
use Novactive\Bundle\eZProtectedContentBundle\Repository\ProtectedTokenStorageRepository;
use Ramsey\Uuid\Uuid;
use Swift_Message;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(
        FormFactoryInterface $formFactory,
        Swift_Mailer $mailer,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        ParameterBagInterface $parameterBag
    )
    {
        $this->formFactory     = $formFactory;
        $this->mailer          = $mailer;
        $this->entityManager   = $entityManager;
        $this->translator      = $translator;
        $this->parameterBag    = $parameterBag;
        $this->messageInstance = new Swift_Message();

    }

    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        // ÉTAPE 2 - On reçoit le formulaire avec l'adresse email de l'internaute (+ le contentID).
        // On va créer un token (ProtectedTokenStorage) et générer un email avec un lien qui sera utilisé dans l'étape 3.
        // On affiche à internaute un message pour lui demander de vérifier ses mails.
        $form = $this->formFactory->create(RequestEmailProtectedAccessType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data      = $form->getData();
            $contentId = intval($data['content_id']);
            $token     = Uuid::uuid4()->toString();
            $access    = new ProtectedTokenStorage();

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

        // ÉTAPE 3 - L'internaute a cliqué sur le lien dans le mail généré à l'étape 2
        // On retrouve le token et on crée un cookie
        // On redirige l'internaute vers la page demandée.
        if ($request->query->has('mail')
            && $request->query->has('token')
            && !$request->query->has('waiting_validation')
        ) {
            $token = $request->get('token');
            $mail = $request->get('mail');

            /** @var ProtectedTokenStorageRepository $protectedTokenStorageRepository */
            $protectedTokenStorageRepository = $this->entityManager->getRepository(ProtectedTokenStorage::class);
            $unexpiredTokenList = $protectedTokenStorageRepository->findUnexpiredBy([
                //'content_id'      => $content->id, // TODO, On a pas le contentID ... est ce que on le met dans le line du mail ? On en a pas forcément besoin ... ?
                'token'           => $token,
                'mail'            => $mail
            ]);

            if (count($unexpiredTokenList) > 0 ) {
                $uri = $request->getRequestUri();
                $path = parse_url($uri, PHP_URL_PATH);
                $response = new RedirectResponse($path);
                $response->setPrivate();

                foreach ($unexpiredTokenList as $unexpiredToken) {
                    $hash   = self::hash($unexpiredToken->getContentId());
                    $cookie = new Cookie(PasswordProvided::COOKIE_PREFIX.$hash, $hash, strtotime('now + 24 hours')); // TODO réflexion sur la durées
                    $response->headers->setCookie($cookie);
                    // TODO Supprimer/invalider le token ?
                }

                $event->setResponse($response);
            }
        }
    }

    public static function hash(int $contentId): string
    {
        return md5((string) $contentId);
    }

    public static function isValidHash(string $hash, int $contentId): bool
    {
        return $hash === self::hash($contentId);
    }

    /**
     * @throws Exception
     */
    private function sendMail(int $contentId, string $receiver, string $link): void {
        /** @var ProtectedAccess $protectedAccess */
        $protectedAccess = $this->entityManager->getRepository(ProtectedAccess::class)->findOneBy(['contentId' => $contentId]);

        $mailLink = "<a href='$link'>".$this->translator->trans('mail.link', [], 'ezprotectedcontent')."</a>";
        $bodyMessage = str_replace('{{ url }}', $mailLink, $protectedAccess->getEmailMessage());

        $message = $this->messageInstance
            ->setSubject($this->translator->trans('mail.subject', [], 'ezprotectedcontent'))
            ->setFrom($this->parameterBag->get('default_sender_email'))
            ->setTo($receiver)
            ->setContentType('text/html')
            ->setBody(
                $bodyMessage
            );

        try {
            $this->mailer->send($message);
        } catch (Exception $exception) {
            throw new Exception(sprintf(self::SENDMAIL_ERROR, $receiver));
        }
    }
}
