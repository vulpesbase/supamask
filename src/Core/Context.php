<?php

namespace Supamask\Core;

use Supamask\Entry\DisposableEntry;
use Supamask\Entry\DisposableEntryState;
use Supamask\Entry\EntryClassification;
use Supamask\Http\Request;
use Supamask\Http\RequestContext;
use Supamask\Http\RequestContextFactory;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

/**
 * Carries the current request and configuration through the middleware pipeline.
 *
 * Provides lazy access to a RequestContext (parsed once, cached) so
 * middleware components do not need to re-parse the request themselves.
 */
class Context
{
    private ?RequestContext $requestContext = null;
    private ?EntryClassification $classification = null;
    private ?DisposableEntry $disposableEntry = null;
    private ?DisposableEntryState $invalidDisposableEntryState = null;
    private ?IpIntelligenceResult $ipIntelligence = null;
    private ?string $decisionReason = null;
    private RequestContextFactory $contextFactory;

    public function __construct(
        private Request $request,
        private ?Config $config = null,
        ?RequestContextFactory $contextFactory = null,
    ) {
        $this->contextFactory = $contextFactory ?? new RequestContextFactory();
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function config(): Config
    {
        return $this->config ?? new Config();
    }

    /**
     * Returns the parsed RequestContext for this request, creating it once
     * and caching the result for subsequent calls.
     */
    public function requestContext(): RequestContext
    {
        if ($this->requestContext === null) {
            $this->requestContext = $this->contextFactory->fromRequest($this->request);
        }

        return $this->requestContext;
    }

    public function classification(): ?EntryClassification
    {
        return $this->classification;
    }

    public function setClassification(EntryClassification $classification): void
    {
        $this->classification = $classification;
    }

    public function getDisposableEntry(): ?DisposableEntry
    {
        return $this->disposableEntry;
    }

    public function setDisposableEntry(DisposableEntry $entry): void
    {
        $this->disposableEntry = $entry;
    }

    /**
     * Tracks a disposable entry that was found but is no longer active (consumed/expired).
     *
     * This allows the Kernel to explicitly reject invalid disposable paths
     * rather than allowing them to reach normal application routing.
     */
    public function setInvalidDisposableEntryState(DisposableEntryState $state): void
    {
        $this->invalidDisposableEntryState = $state;
    }

    public function getInvalidDisposableEntryState(): ?DisposableEntryState
    {
        return $this->invalidDisposableEntryState;
    }

    public function setIpIntelligence(IpIntelligenceResult $intelligence): void
    {
        $this->ipIntelligence = $intelligence;
    }

    public function ipIntelligence(): ?IpIntelligenceResult
    {
        return $this->ipIntelligence;
    }

    public function setDecisionReason(string $reason): void
    {
        $this->decisionReason = $reason;
    }

    public function decisionReason(): ?string
    {
        return $this->decisionReason;
    }
}