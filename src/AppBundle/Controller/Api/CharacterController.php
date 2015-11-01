<?php

namespace AppBundle\Controller\Api;

use AppBundle\Controller\AbstractController;
use AppBundle\Controller\ApiControllerInterface;
use AppBundle\Entity\ApiCredentials;
use AppBundle\Entity\Corporation;
use JMS\SecurityExtraBundle\Annotation\Secure;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * Character controller.
 */
class CharacterController extends AbstractController implements ApiControllerInterface {

    /**
     * @Route("/characters", name="api.characters", options={"expose"=true})
     * @Secure(roles="ROLE_USER")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {

        $user = $this->getUser();

        $characters = $this->getDoctrine()->getRepository('AppBundle:Character')
            ->findBy(['user' => $user ]);

        $json = $this->get('serializer')->serialize($characters, 'json');

        return $this->jsonResponse($json);

    }

    /**
     * Creates a new character entity.
     *
     * @Route("/characters", name="api.character_create.validate", options={"expose"=true})
     * @Secure(roles="ROLE_USER")
     * @Method("POST")
     */
    public function createAction(Request $request)
    {
        $content = $request->request;

        $key = $this->get('app.apikey.manager')
            ->buildInstanceFromRequest($content);

        $validator = $this->get('validator');

        $errors = $validator->validate($key);

        if (count($errors) > 0){
            return $this->getErrorResponse($errors);
        }

        try {
            $result = $this->get('app.apikey.manager')
                ->validateAndUpdateApiKey($key);

            $arr = $result->toArray();

            $arr['result']['key']['api_key'] = $key->getApiKey();
            $arr['result']['key']['verification_code'] = $key->getVerificationCode();
            $arr['result']['key']['access_mask'] = $key->getAccessMask();
            $arr['result']['key']['type'] = $key->getType();


            $corps = $this->getDoctrine()->getRepository('AppBundle:Corporation');

            foreach ($arr['result']['key']['characters'] as $i => $c){
                $exists = $corps->findOneBy(['eve_id' => $c['corporationID']]);

                $arr['result']['key']['characters'][$i]['best_guess'] = $exists instanceof Corporation;
            }

            return $this->jsonResponse(json_encode($arr));

        } catch (\Exception $e){
            return $this->jsonResponse(json_encode(['message' => $e->getMessage(), 'code' => 400]), 400);
        }

    }

    /**
     * Creates a new character entity.
     *
     * @Route("/characters/final", name="api.character_create.finalize", options={"expose"=true})
     * @Secure(roles="ROLE_USER")
     * @Method("POST")
     */
    public function createFinalAction(Request $request){
        $content = $request->request;

        $initialKey = $content->get('full_key', null);
        $selected_char = $content->get('char', null);

        $keyManager = $this->get('app.apikey.manager');
        $key = $keyManager->buildInstanceFromRequest($content);

        $validator = $this->get('validator');

        $errors = $validator->validate($key);

        if (count($errors) > 0 ){
            return $this->getErrorResponse($errors);
        }

        $keyManager->updateKey($key, $initialKey['result']['key']);

        $key->setEveCharacterId($selected_char['characterID'])
            ->setEveCorporationId($selected_char['corporationID']);


        $all_chars = $initialKey['result']['key']['characters'];
        $cmanager = $this->get('app.character.manager');
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $addedChars = [];
        foreach($all_chars as $c){
            $char = $cmanager->createCharacter($c);
            $char->addApiCredential($key);
            $char->setUser($user);

            $em->persist($char);

            array_push($addedChars, $char);
        }

        try {
            $em->flush();

            return  $this->jsonResponse($this->get('jms_serializer')->serialize($addedChars, 'json'));
        } catch (\Exception $e){
            return $this->jsonResponse(json_encode(['message' => $e->getMessage(), 'code' => 400]), 400);
        }
    }

}
