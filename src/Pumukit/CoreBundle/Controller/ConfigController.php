<?php

namespace Pumukit\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ConfigController extends Controller implements AdminControllerInterface
{
    /**
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     * @Route("/admin/show/parameters/")
     *
     * @throws \Exception
     */
    public function showParametersAction(): BinaryFileResponse
    {
        $pathFile = realpath($this->container->getParameter('kernel.root_dir').'/../app/config/parameters_deploy.yml');
        if (!$pathFile) {
            throw new \Exception('Error reading parameters_deploy.yml');
        }

        $response = new BinaryFileResponse($pathFile);
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'parameters_deploy.yml'
        );

        return $response;
    }
}
