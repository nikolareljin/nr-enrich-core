<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Tests\Service;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Nikos\NrEnrichCore\Model\EnrichmentResult;
use Nikos\NrEnrichCore\Service\AiEnrichmentService;
use Nikos\NrEnrichCore\Service\Provider\AiProviderInterface;
use Nikos\NrEnrichCore\Service\Provider\AiProviderResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\Concrete;
use Psr\Log\NullLogger;

/**
 * Unit tests for AiEnrichmentService.
 *
 * Uses a mock provider and a stub DataObject to verify that:
 *  - The correct provider is resolved by name.
 *  - enrichField() reads the current value, calls complete(), and writes the result.
 *  - A Pimcore version is created when createVersion=true.
 *  - enrichObject() collects results and swallows per-field errors.
 */
class AiEnrichmentServiceTest extends TestCase
{
    private AiProviderInterface&MockObject $mockProvider;
    private AiEnrichmentService $service;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(AiProviderInterface::class);
        $this->mockProvider->method('getName')->willReturn('openai');

        $this->service = new AiEnrichmentService(
            providers: [$this->mockProvider],
            defaultProvider: 'openai',
            logger: new NullLogger(),
        );
    }

    public function testEnrichFieldCallsProviderAndWritesValue(): void
    {
        $config = new EnrichmentConfig(
            className: 'Product',
            fieldName: 'description',
            promptTemplate: 'Improve: {{ value }}',
            createVersion: false,
        );

        $object = $this->createObjectStub('Old description');

        $this->mockProvider
            ->expects($this->once())
            ->method('complete')
            ->with(
                $this->stringContains('Old description'),
                $this->identicalTo($config)
            )
            ->willReturn(new AiProviderResponse(
                content: 'Improved description',
                model: 'gpt-4o',
                provider: 'openai',
                promptTokens: 10,
                completionTokens: 20,
            ));

        $result = $this->service->enrichField($object, $config);

        $this->assertInstanceOf(EnrichmentResult::class, $result);
        $this->assertSame('description', $result->fieldName);
        $this->assertSame('Old description', $result->originalValue);
        $this->assertSame('Improved description', $result->enrichedValue);
        $this->assertSame(30, $result->tokensUsed);
        $this->assertFalse($result->versionCreated);
    }

    public function testEnrichFieldCreatesVersionWhenConfigured(): void
    {
        $config = new EnrichmentConfig(
            className: 'Product',
            fieldName: 'description',
            promptTemplate: 'Improve: {{ value }}',
            createVersion: true,
        );

        $object = $this->createObjectStub('Some text');

        $this->mockProvider
            ->method('complete')
            ->willReturn(new AiProviderResponse('Enriched', 'gpt-4o', 'openai', 5, 10));

        $object->expects($this->once())->method('saveVersion');

        $result = $this->service->enrichField($object, $config);
        $this->assertTrue($result->versionCreated);
    }

    public function testEnrichFieldThrowsForUnknownProvider(): void
    {
        $config = new EnrichmentConfig(
            className: 'Product',
            fieldName: 'description',
            promptTemplate: 'Improve: {{ value }}',
            provider: 'nonexistent',
        );

        $object = $this->createObjectStub('text');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');

        $this->service->enrichField($object, $config);
    }

    public function testEnrichObjectSwallowsPerFieldErrors(): void
    {
        $configs = [
            new EnrichmentConfig('Product', 'description', 'Improve: {{ value }}'),
            new EnrichmentConfig('Product', 'name', 'Shorten: {{ value }}'),
        ];

        // First field throws, second succeeds.
        $object = $this->createObjectStub('text');

        $this->mockProvider
            ->method('complete')
            ->willReturnCallback(static function () {
                static $calls = 0;
                $calls++;

                if ($calls === 1) {
                    throw new \RuntimeException('API error');
                }

                return new AiProviderResponse('Short name', 'gpt-4o', 'openai', 3, 5);
            });

        $results = $this->service->enrichObject($object, $configs);

        // Only the successful result should be in the array.
        $this->assertCount(1, $results);
        $this->assertSame('name', $results[0]->fieldName);
    }

    public function testGetProviderNames(): void
    {
        $this->assertSame(['openai'], $this->service->getProviderNames());
    }

    public function testHealthCheckAll(): void
    {
        $this->mockProvider->method('healthCheck')->willReturn(true);
        $this->assertSame(['openai' => true], $this->service->healthCheckAll());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create a partial mock of Concrete with working getter/setter/save.
     */
    private function createObjectStub(string $fieldValue): Concrete&MockObject
    {
        $object = $this->getMockBuilder(Concrete::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDescription', 'setDescription', 'getName', 'setName'])
            ->onlyMethods(['getId', 'getClassName', 'save', 'saveVersion'])
            ->getMock();

        $object->method('getId')->willReturn(1);
        $object->method('getClassName')->willReturn('Product');
        $object->method('getDescription')->willReturn($fieldValue);
        $object->method('getName')->willReturn($fieldValue);
        $object->method('setDescription')->willReturnSelf();
        $object->method('setName')->willReturnSelf();
        $object->method('save')->willReturnSelf();
        $object->method('saveVersion')->willReturn(null);

        return $object;
    }
}
