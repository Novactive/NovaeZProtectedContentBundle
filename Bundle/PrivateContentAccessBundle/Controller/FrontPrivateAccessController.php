<?php
namespace  MCC\Bundle\PrivateContentAccessBundle\Controller;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\REST\Server\Input\Parser\Criterion\LocationId;
use MCC\Bundle\PrivateContentAccessBundle\Entity\PrivateAccess;
use MCC\Bundle\PrivateContentAccessBundle\Form\PrivateAccessForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FrontPrivateAccessController extends Controller
{
    public function askPasswordAction($locationId, Request $request)
    {
        $repository = $this->container->get('ezpublish.api.repository');
        $contentService = $repository->getLocationService();
        $location = $contentService->loadLocation($locationId);
        //$content = $repository->getContentService()->loadContent($location->getContentInfo()->id);

        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, array(
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => false
            ))
            ->add('locationId', HiddenType::class, array(
                'data' => $location->id
            ))
            ->add('Valider', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $repository = $this->container->get('ezpublish.api.repository');
            $contentService = $repository->getLocationService(); //getContentService();
            $locationInfo = $contentService->loadLocation($session->get('locationid')); //loadContentInfo( $session->get('locationid') );

            $result = $this->getDoctrine()->getRepository('MCPrivateContentAccessBundle:PrivateAccess')->findOneBy(['locationId' => $data['locationId'], 'password' => $data['password'] ,'activate' => 1]);
            if($result != NULL){

                return $this->redirectToLocation($locationInfo,'');
            }
        }

        return $this->render(
            '@MCCPrivateContentAccess/full/ask_password_form.html.twig',
            array('location' => $location, 'noLayout' => false, 'form' => $form->createView())
        );
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @param string $uriFragment
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToLocation(Location $location, string $uriFragment = ''): RedirectResponse
    {
        return $this->redirectToRoute('_ezpublishLocation', [
            'locationId' => $location->id,
            '_fragment' => $uriFragment,
        ]);
    }
}