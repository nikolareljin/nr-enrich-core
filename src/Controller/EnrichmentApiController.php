<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Controller;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Nikos\NrEnrichCore\Service\AiEnrichmentService;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * REST API for triggering AI enrichment from the Pimcore admin UI or external systems.
 *
 * All endpoints require an authenticated Pimcore admin session or a valid API token.
 * Authentication is enforced by Pimcore's admin firewall — no additional checks needed here.
 */
class EnrichmentApiController extends FrontendController
{
    public function __construct(
        private readonly AiEnrichmentService $enrichmentService,
    ) {
    }

    /**
     * POST /admin/nrec/enrich
     *
     * Enrich one or more fields on a single DataObject synchronously.
     *
     * Request body (JSON):
     * {
     *   "objectId":  123,
     *   "className": "Product",
     *   "fields": [
     *     {
     *       "fieldName": "description",
     *       "promptTemplate": "Improve this product description: {{ value }}",
     *       "provider": "openai"
     *     }
     *   ]
     * }
     */
    public function enrich(Request $request): JsonResponse
    {
        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $objectId = (int) ($data['objectId'] ?? 0);
        $className = (string) ($data['className'] ?? '');
        $fieldDefs = $data['fields'] ?? [];

        if ($objectId <= 0 || $className === '' || empty($fieldDefs)) {
            return new JsonResponse(
                ['error' => 'objectId, className, and fields are required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $object = DataObject::getById($objectId);
        if (!$object) {
            return new JsonResponse(['error' => "Object $objectId not found."], Response::HTTP_NOT_FOUND);
        }
        if (!$object instanceof Concrete) {
            return new JsonResponse(
                ['error' => "Object $objectId is not a concrete data object."],
                Response::HTTP_BAD_REQUEST
            );
        }

        $configs = array_map(
            fn(array $f) => EnrichmentConfig::fromArray(array_merge(['className' => $className], $f)),
            $fieldDefs
        );

        try {
            $results = $this->enrichmentService->enrichObject($object, $configs);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'results' => array_map(fn($r) => $r->toArray(), $results),
        ]);
    }

    /**
     * POST /admin/nrec/enrich/bulk
     *
     * Enrich multiple objects. Each entry follows the same schema as /enrich.
     *
     * Request body (JSON):
     * { "jobs": [ { "objectId": 1, "className": "Product", "fields": [...] }, ... ] }
     */
    public function enrichBulk(Request $request): JsonResponse
    {
        $data = $this->parseJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $jobs = $data['jobs'] ?? [];
        if (empty($jobs)) {
            return new JsonResponse(['error' => '"jobs" array is required.'], Response::HTTP_BAD_REQUEST);
        }

        $allResults = [];

        foreach ($jobs as $job) {
            $objectId = (int) ($job['objectId'] ?? 0);
            $className = (string) ($job['className'] ?? '');
            $fieldDefs = $job['fields'] ?? [];

            if ($objectId <= 0 || $className === '' || empty($fieldDefs)) {
                $allResults[] = ['objectId' => $objectId, 'error' => 'Invalid job definition.'];
                continue;
            }

            $object = DataObject::getById($objectId);
            if (!$object) {
                $allResults[] = ['objectId' => $objectId, 'error' => 'Object not found.'];
                continue;
            }
            if (!$object instanceof Concrete) {
                $allResults[] = ['objectId' => $objectId, 'error' => 'Object is not a concrete data object.'];
                continue;
            }

            $configs = array_map(
                fn(array $f) => EnrichmentConfig::fromArray(array_merge(['className' => $className], $f)),
                $fieldDefs
            );

            try {
                $results = $this->enrichmentService->enrichObject($object, $configs);
                $allResults[] = [
                    'objectId' => $objectId,
                    'results' => array_map(fn($r) => $r->toArray(), $results),
                ];
            } catch (\Throwable $e) {
                $allResults[] = ['objectId' => $objectId, 'error' => $e->getMessage()];
            }
        }

        return new JsonResponse(['success' => true, 'jobs' => $allResults]);
    }

    /**
     * GET /admin/nrec/health
     *
     * Returns health status for all configured AI providers.
     */
    public function health(): JsonResponse
    {
        $checks = $this->enrichmentService->healthCheckAll();

        return new JsonResponse([
            'providers' => $checks,
            'overall' => !in_array(false, $checks, true),
        ]);
    }

    /**
     * Decode JSON request body. Returns an error JsonResponse on failure.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function parseJson(Request $request): array|JsonResponse
    {
        $content = $request->getContent();
        if ($content === '') {
            return new JsonResponse(['error' => 'Empty request body.'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }
}
