<?php

declare(strict_types=1);

namespace Tweakwise\Magento2Tweakwise\Observer\Event;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Tweakwise\Magento2Tweakwise\Service\SessionStartEventService;

class DeleteSessionStartEventSentCookie implements ObserverInterface
{
    /**
     * @param SessionStartEventService $sessionStartEventService
     */
    public function __construct(
        private readonly SessionStartEventService $sessionStartEventService,
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterface
     */
    public function execute(Observer $observer): void
    {
        $this->sessionStartEventService->deleteSessionStartEventSentCookie();
    }
}
