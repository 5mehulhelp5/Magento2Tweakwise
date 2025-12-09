<?php

declare(strict_types=1);

namespace Tweakwise\Magento2Tweakwise\Service;

use Magento\Customer\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;

class SessionStartEventService
{
    private const SESSION_START_EVENT_SENT_COOKIE_NAME = 'tw_session_start_event_sent';

    /**
     * @param Session $customerSession
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Session $customerSession,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param Request $tweakwiseRequest
     * @return void
     */
    public function handleSessionStartType(Request $tweakwiseRequest): void
    {
        $tweakwiseRequest->setParameter('SessionKey', $this->customerSession->getSessionId());
        $tweakwiseRequest->setParameter('Source', 'magento2');
        $tweakwiseRequest->setPath('sessionstart');

        $this->setSessionStartEventSentCookie();
    }

    /**
     * @return bool
     */
    public function isSessionStartEventSent(): bool
    {
        return (bool) $this->cookieManager->getCookie(self::SESSION_START_EVENT_SENT_COOKIE_NAME);
    }

    /**
     * @return void
     */
    public function deleteSessionStartEventSentCookie(): void
    {
        try {
            $this->cookieManager->deleteCookie(self::SESSION_START_EVENT_SENT_COOKIE_NAME, $this->getCookieMetaData());
        } catch (InputException | FailureToSendException $e) {
            $this->logger->error(
                sprintf('Could not delete %s cookie', self::SESSION_START_EVENT_SENT_COOKIE_NAME),
                ['message' => $e->getMessage()]
            );
        }
    }

    /**
     * @return void
     */
    protected function setSessionStartEventSentCookie(): void
    {
        try {
            $this->cookieManager->setPublicCookie(
                self::SESSION_START_EVENT_SENT_COOKIE_NAME,
                '1',
                $this->getCookieMetaData()
            );
        } catch (InputException | CookieSizeLimitReachedException | FailureToSendException $e) {
            $this->logger->error(
                sprintf('Could not set %s cookie', self::SESSION_START_EVENT_SENT_COOKIE_NAME),
                ['message' => $e->getMessage()]
            );
        }
    }

    /**
     * @return PublicCookieMetadata
     */
    protected function getCookieMetaData(): PublicCookieMetadata
    {
        return $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration(86400)
            ->setPath('/')
            ->setSecure(true);
    }
}
