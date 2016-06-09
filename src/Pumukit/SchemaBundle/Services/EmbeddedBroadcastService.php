<?php

namespace Pumukit\SchemaBundle\Services;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\EmbeddedBroadcast;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EmbeddedBroadcastService
{
    private $dm;
    private $repo;
    private $mmsService;
    private $dispatcher;
    private $disabledBroadcast;
    private $authorizationChecker;
    private $router;
    private $templating;

    /**
     * Constructor
     */
    public function __construct(DocumentManager $documentManager, MultimediaObjectService $mmsService, MultimediaObjectEventDispatcherService $dispatcher, AuthorizationCheckerInterface $authorizationChecker, EngineInterface $templating, Router $router, $disabledBroadcast)
    {
        $this->dm = $documentManager;
        $this->repo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->mmsService = $mmsService;
        $this->dispatcher = $dispatcher;
        $this->authorizationChecker = $authorizationChecker;
        $this->templating = $templating;
        $this->router = $router;
        $this->disabledBroadcast = $disabledBroadcast;
    }

    /**
     * Set public embedded broadcast
     *
     * @param  MultimediaObject $multimediaObject
     * @param  string           $type
     * @param  boolean          $executeFlush
     * @return MultimediaObject
     */
    public function setByType(MultimediaObject $multimediaObject, $type = EmbeddedBroadcast::TYPE_PUBLIC, $executeFlush = true)
    {
        $embeddedBroadcast = $this->createEmbeddedBroadcastByType($type);
        $multimediaObject->setEmbeddedBroadcast($embeddedBroadcast);
        $this->dm->persist($multimediaObject);
        if ($executeFlush) {
            $this->dm->flush();
        }

        return $multimediaObject;
    }

    /**
     * Create embedded broadcast by type
     *
     * @param  string            $type
     * @return EmbeddedBroadcast
     */
    public function createEmbeddedBroadcastByType($type = null)
    {
        $embeddedBroadcast = new EmbeddedBroadcast();
        switch ($type) {
        case EmbeddedBroadcast::TYPE_PASSWORD:
            $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_PASSWORD);
            $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_PASSWORD);
            break;
        case EmbeddedBroadcast::TYPE_LOGIN:
            $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_LOGIN);
            $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_LOGIN);
            break;
        case EmbeddedBroadcast::TYPE_GROUPS:
            $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_GROUPS);
            $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_GROUPS);
            break;
        default:
            $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_PUBLIC);
            $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_PUBLIC);
            break;
        }

        return $embeddedBroadcast;
    }

    /**
     * Create public embedded broadcast
     *
     * @return EmbeddedBroadcast
     */
    public function createPublicEmbeddedBroadcast()
    {
        return $this->createEmbeddedBroadcastByType(EmbeddedBroadcast::TYPE_PUBLIC);
    }

    /**
     * Clone resource
     *
     * @param  EmbeddedBroadcast $embeddedBroadcast
     * @return EmbeddedBroadcast
     */
    public function cloneResource(EmbeddedBroadcast $embeddedBroadcast)
    {
        $new = new EmbeddedBroadcast();
        $new->setType($embeddedBroadcast->getType());
        $new->setName($embeddedBroadcast->getName());
        if ($password = $embeddedBroadcast->getPassword()) {
            $new->setPassword($password);
        }
        if ($groups = $embeddedBroadcast->getGroups()) {
            foreach ($groups as $group) {
                $new->addGroup($group);
            }
        }

        return $new;
    }

    /**
     * Get all broadcast types
     *
     * @return array
     */
    public function getAllTypes()
    {
        if ($this->disabledBroadcast) {
            return array(
                         EmbeddedBroadcast::TYPE_PUBLIC => EmbeddedBroadcast::NAME_PUBLIC,
                         EmbeddedBroadcast::TYPE_LOGIN => EmbeddedBroadcast::NAME_LOGIN,
                         EmbeddedBroadcast::TYPE_GROUPS => EmbeddedBroadcast::NAME_GROUPS
                         );
        }

        return array(
                     EmbeddedBroadcast::TYPE_PUBLIC => EmbeddedBroadcast::NAME_PUBLIC,
                     EmbeddedBroadcast::TYPE_PASSWORD => EmbeddedBroadcast::NAME_PASSWORD,
                     EmbeddedBroadcast::TYPE_LOGIN => EmbeddedBroadcast::NAME_LOGIN,
                     EmbeddedBroadcast::TYPE_GROUPS => EmbeddedBroadcast::NAME_GROUPS
                     );
    }

    /**
     * Update type and name
     *
     * @param MultimediaObject $multimediaObject
     * @param string           $type
     * @param boolean          $executeFlush
     */
    public function updateTypeAndName($type, MultimediaObject $multimediaObject, $executeFlush = true)
    {
        $embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast();
        if (!$embeddedBroadcast) {
            $embeddedBroadcast = $this->createPublicEmbeddedBroadcast();
            $multimediaObject->setEmbeddedBroadcast($embeddedBroadcast);
        }
        $allTypes = $this->getAllTypes();
        if (($type !== $embeddedBroadcast->getType()) && array_key_exists($type, $allTypes)) {
            $embeddedBroadcast->setType($type);
            $embeddedBroadcast->setName($allTypes[$type]);
            $this->dm->persist($multimediaObject);
            if ($executeFlush) {
                $this->dm->flush();
            }
            $this->dispatcher->dispatchUpdate($multimediaObject);
        }
}

    /**
     * Update password
     *
     * @param string           $password
     * @param MultimediaObject $multimediaObject
     * @param boolean          $executeFlush
     */
    public function updatePassword($password, MultimediaObject $multimediaObject, $executeFlush = true)
    {
        $embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast();
        if (!$embeddedBroadcast) {
            $embeddedBroadcast = $this->createPublicEmbeddedBroadcast();
            $multimediaObject->setEmbeddedBroadcast($embeddedBroadcast);
        }
        if ($password !== $embeddedBroadcast->getPassword()) {
            $embeddedBroadcast->setPassword($password);
            $this->dm->persist($multimediaObject);
            if ($executeFlush) {
                $this->dm->flush();
            }
            $this->dispatcher->dispatchUpdate($multimediaObject);
        }
    }

    /**
     * Add group to embeddedBroadcast
     *
     * @param Group $group
     * @param MultimediaObject $multimediaObject
     * @param boolean $executeFlush
     */
    public function addGroup(Group $group, MultimediaObject $multimediaObject, $executeFlush = true)
    {
        $embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast();
        if (!$embeddedBroadcast) {
            $embeddedBroadcast = $this->createPublicEmbeddedBroadcast();
            $multimediaObject->setEmbeddedBroadcast($embeddedBroadcast);
        }
        if (!$embeddedBroadcast->containsGroup($group)) {
            $embeddedBroadcast->addGroup($group);
            $this->dm->persist($multimediaObject);
            if ($executeFlush) {
                $this->dm->flush();
            }
            $this->dispatcher->dispatchUpdate($multimediaObject);
        }
    }

    /**
     * Delete group from embedded Broadcasr
     *
     * @param Group $group
     * @param MultimediaObject $multimediaObject
     * @param boolean $executeFlush
     */
    public function deleteGroup(Group $group, MultimediaObject $multimediaObject, $executeFlush = true)
    {
        $embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast();
        if (!$embeddedBroadcast) {
            $embeddedBroadcast = $this->createPublicEmbeddedBroadcast();
            $multimediaObject->setEmbeddedBroadcast($embeddedBroadcast);
        }
        if ($embeddedBroadcast->containsGroup($group)) {
            $embeddedBroadcast->removeGroup($group);
            $this->dm->persist($multimediaObject);
            if ($executeFlush) {
                $this->dm->flush();
            }
            $this->dispatcher->dispatchUpdate($multimediaObject);
        }
    }

    /**
     * Can User play multimediaObject
     *
     * @param  MultimediaObject $multimediaObject
     * @param  User             $user
     * @param  string           $phpAuthPassword
     * @param  boolean          $forceAuth
     * @return
     */
    public function canUserPlayMultimediaObject(MultimediaObject $multimediaObject, User $user = null, $phpAuthPassword, $forceAuth = false)
    {
        $embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast();
        if (!$embeddedBroadcast) {
            return true;
        }
        if (EmbeddedBroadcast::TYPE_PUBLIC === $embeddedBroadcast->getType()) {
            return true;
        }
        if (EmbeddedBroadcast::TYPE_LOGIN === $embeddedBroadcast->getType()) {
            return $this->isUserLoggedIn($user, $forceAuth);
        }
        if (EmbeddedBroadcast::TYPE_GROUPS === $embeddedBroadcast->getType()) {
            return $this->isUserLoggedInAndInGroups($multimediaObject, $user, $forceAuth);
        }
        if (EmbeddedBroadcast::TYPE_PASSWORD === $embeddedBroadcast->getType()) {
            return $this->isPasswordCorrect($multimediaObject, $phpAuthPassword);
        }

        return $this->renderErrorNotAuthenticated($forceAuth, $user);
    }

    /**
     * Is user related to multimedia object
     *
     * @param  MultimediaObject $multimediaObject
     * @param  User             $user
     * @return boolean
     */
    public function isUserRelatedToMultimediaObject(MultimediaObject $multimediaObject, User $user = null)
    {
        if (!$user) {
            return false;
        }
        $userGroups = $user->getGroups()->toArray();
        if ($embeddedBroadcast= $multimediaObject->getEmbeddedBroadcast()) {
            $playGroups = $embeddedBroadcast->getGroups()->toArray();
        } else {
            $playGroups = array();
        }
        $adminGroups = $multimediaObject->getGroups()->toArray();
        $commonPlayGroups = array_intersect($playGroups, $userGroups);
        $commonAdminGroups = array_intersect($adminGroups, $userGroups);
        $userIsOwner = $this->mmsService->isUserOwner($user, $multimediaObject);

        return $commonPlayGroups || $commonAdminGroups || $userIsOwner;
    }

    private function isAuthenticatedFully(User $user = null)
    {
        if (!$user) {
            return false;
        }

        return $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');
    }

    private function isUserLoggedIn(User $user = null, $forceAuth = false)
    {
        if ($this->isAuthenticatedFully($user)){
            return true;
        }

        return $this->renderErrorNotAuthenticated($forceAuth, $user);
    }

    private function isUserLoggedInAndInGroups(MultimediaObject $multimediaObject, User $user = null, $forceAuth = false)
    {
        if ($this->isAuthenticatedFully($user)){
            if ($this->isUserRelatedToMultimediaObject($multimediaObject, $user)) {
                return true;
            }
        }

        return $this->renderErrorNotAuthenticated($forceAuth, $user);
    }

    private function isPasswordCorrect(MultimediaObject $multimediaObject, $phpAuthPassword)
    {
        if ($embeddedBroadcast = $multimediaObject->getEmbeddedBroadcast()) {
            $embeddedPassword = $embeddedBroadcast->getPassword();
            if (($phpAuthPassword == $embeddedPassword) && (null !== $embeddedPassword)) {
                return true;
            }
        }

        return $this->renderErrorPassword($multimediaObject);
    }

    private function renderErrorNotAuthenticated($forceAuth = false, User $user = null)
    {
        if ($forceAuth && !$this->isAuthenticatedFully($user)) {
            throw new AccessDeniedException('Unable to access this page!');
        }
        $renderedView = $this->templating->render('PumukitWebTVBundle:Index:403forbidden.html.twig', array('show_forceauth' => true));

        return new Response($renderedView, 403);

    }

    private function renderErrorPassword(MultimediaObject $multimediaObject)
    {
        $seriesUrl = $this->router->generate('pumukit_webtv_series_index', array('id' => $multimediaObject->getSeries()->getId()), true);
        $redReq = new RedirectResponse($seriesUrl, 302);

        return new Response($redReq->getContent(), 401, array('WWW-Authenticate' => 'Basic realm="Resource not public."'));
    }

    /**
     * Delete all embedded broadcasts from group
     *
     * @param Group
     */
    public function deleteAllFromGroup(Group $group)
    {
        $multimediaObjects = $this->repo->findWithGroupInEmbeddedBroadcast($group);
        foreach ($multimediaObjects as $multimediaObject) {
            $this->deleteGroup($group, $multimediaObject, false);
        }
        $this->dm->flush();
    }
}