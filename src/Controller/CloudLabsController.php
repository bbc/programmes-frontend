<?php
declare(strict_types = 1);
namespace App\Controller;

use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Service\ProgrammesService;
use Symfony\Component\HttpFoundation\Response;

class CloudLabsController extends BaseController
{
    public function __invoke(string $action)
    {
        // if no action, assume show
        if ($action == '') {
            $action = 'show';
        }

        $methodName = $action . 'Action';

        if (!method_exists($this, $methodName)) {
            throw $this->createNotFoundException(sprintf(
                'CloudLabs Action not found. Expected one of %s but got "%s"',
                '"' . implode('", "', $this->validActionNames()) . '"',
                $action
            ));
        }

        return $this->forward(self::class . '::' . $methodName);
    }

    public function showAction()
    {
        return $this->render('cloud_labs/show.html.twig');
    }

    public function analyticsAction()
    {
        return new Response('hai');
    }

    public function advertsAction(ProgrammesService $programmesService)
    {
        $programme = $programmesService->findByPidFull(new Pid('n13xtmd5'));
        $this->setContextAndPreloadBranding($programme);

        return $this->renderWithChrome('cloud_labs/adverts.html.twig');
    }

    public function jsUserAction(ProgrammesService $programmesService)
    {
        $programme = $programmesService->findByPidFull(new Pid('b006m86d'));
        $this->setAtiContentLabels('wibble', 'wibble');
        $this->setContextAndPreloadBranding($programme);

        return $this->renderWithChrome('cloud_labs/js_user.html.twig');
    }

    private function validActionNames(): array
    {
        // Valid actions are based upon the methods in this class that end
        // with 'Action'
        $validActions = [];

        foreach (get_class_methods($this) as $method) {
            if (substr($method, -6) == 'Action') {
                $validActions[] = substr($method, 0, -6);
            }
        }

        return $validActions;
    }
}
