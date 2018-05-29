<?php
namespace  MC\Bundle\PrivateContentAccessBundle\Controller;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use eZ\Publish\API\Repository\Values\Content\Location;
use MC\Bundle\PrivateContentAccessBundle\Entity\PrivateAccess;
use MC\Bundle\PrivateContentAccessBundle\Form\PrivateAccessForm;
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
use EzSystems\RepositoryForms\Form\Type\FieldType\CheckboxFieldType;


class PrivateAccessController extends Controller
{
    /**
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function privateAccessAction(Request $request, Location $location = null)
    {
        $privateAccess = new PrivateAccess();

        $result = $this->getDoctrine()->getRepository('MCPrivateContentAccessBundle:PrivateAccess')->findOneBy(['locationId' => $location->getContentInfo()->mainLocationId]);

        /**
         * @var Form
         */
        $form = $this->createForm(PrivateAccessForm::class, $privateAccess, array(
            'action' => $this->generateUrl('private_access'),
            'method' => 'POST',
        ));

        if($result) {
            $form->add('activate', CheckboxFieldType::class, array(
                'data' => $result->getActivate()
            ));
        }

        if($location) {
            $form->add('locationId', HiddenType::class, array(
                'data' => $location->getContentInfo()->mainLocationId
            ));
        }

        $form->handleRequest($request);
        if ($request-> getMethod() == "POST") {

            $data = $request->request->get('private_access_form');

            $repository = $this->container->get('ezpublish.api.repository');
            $contentService = $repository->getLocationService();
            $locationInfo = $contentService->loadLocation($data['locationId']);

            //$password = $passwordEncoder->encodePassword($privateAccessForm, $privateAccessForm->getPlainPassword());

            $date = new \DateTime();
            $privateAccess->setCreated($date);
            $privateAccess->setPassword($data['plainPassword']['first']);
            $privateAccess->setLocationId($data['locationId']);
            $privateAccess->setActivate($data['activate']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($privateAccess);
            $entityManager->flush();

            return $this->redirectToLocation($locationInfo,'');
        }

        return $this->render(
            '@MCPrivateContentAccess/tabs/private_content_tab_form.html.twig',
            array('form' => $form->createView())
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