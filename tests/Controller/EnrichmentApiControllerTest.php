<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Tests\Controller;

use Nikos\NrEnrichCore\Controller\EnrichmentApiController;
use Nikos\NrEnrichCore\Model\EnrichmentResult;
use Nikos\NrEnrichCore\Service\AiEnrichmentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for EnrichmentApiController.
 *
 * Tests the request-parsing and response-building logic without booting Symfony
 * or Pimcore. DataObject::getById() calls are not reachable here — these tests
 * focus on input validation and response structure.
 */
class EnrichmentApiControllerTest extends TestCase
{
    private AiEnrichmentService&MockObject $enrichmentService;

    protected function setUp(): void
    {
        $this->enrichmentService = $this->createMock(AiEnrichmentService::class);
    }

    public function testEnrichReturnsBadRequestOnEmptyBody(): void
    {
        $controller = new EnrichmentApiController($this->enrichmentService);
        $request    = new Request(content: '');

        $response = $controller->enrich($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testEnrichReturnsBadRequestOnInvalidJson(): void
    {
        $controller = new EnrichmentApiController($this->enrichmentService);
        $request    = new Request(content: 'not-json');

        $response = $controller->enrich($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testEnrichReturnsBadRequestWhenFieldsMissing(): void
    {
        $controller = new EnrichmentApiController($this->enrichmentService);
        $request    = new Request(content: json_encode([
            'objectId'  => 1,
            'className' => 'Product',
            // 'fields' missing
        ]));

        $response = $controller->enrich($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testHealthReturnsProviderStatuses(): void
    {
        $this->enrichmentService
            ->method('healthCheckAll')
            ->willReturn(['openai' => true, 'ollama' => false]);

        $controller = new EnrichmentApiController($this->enrichmentService);
        $response   = $controller->health();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('providers', $data);
        $this->assertFalse($data['overall']);
        $this->assertTrue($data['providers']['openai']);
        $this->assertFalse($data['providers']['ollama']);
    }

    public function testEnrichBulkReturnsBadRequestOnEmptyJobs(): void
    {
        $controller = new EnrichmentApiController($this->enrichmentService);
        $request    = new Request(content: json_encode(['jobs' => []]));

        $response = $controller->enrichBulk($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
