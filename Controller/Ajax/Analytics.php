<?php

namespace Tweakwise\Magento2Tweakwise\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\PersonalMerchandisingConfig;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Magento\Store\Model\StoreManagerInterface;
use Exception;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Analytics extends Action
{
    /**
     * Constructor.
     *
     * @param Context                     $context
     * @param JsonFactory                 $resultJsonFactory
     * @param Client                      $client
     * @param PersonalMerchandisingConfig $config
     * @param RequestFactory              $requestFactory
     * @param Helper                      $helper
     * @param StoreManagerInterface       $storeManager
     * @param JsonSerializer              $jsonSerializer
     */
    public function __construct(
        private Context $context,
        private JsonFactory $resultJsonFactory,
        private Client $client,
        private PersonalMerchandisingConfig $config,
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonSerializer $jsonSerializer
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $result->setData(['success' => false, 'message' => 'Invalid request.']);

        if (!$this->config->isAnalyticsEnabled()) {
            return $result->setData(['success' => false, 'message' => 'Analytics is disabled.']);
        }

        $request = $this->getRequest();
        $type = $this->getRequest()->getParam('type');
        $value = $this->getRequest()->getParam('value');

        //hyva theme
        if (empty($type)) {
            $content = $this->jsonSerializer->unserialize($request->getContent());
            $type = $content['type'] ?? null;
            $value = $content['value'] ?? null;
        }

        if (empty($type) || empty($value)) {
            return $result->setData(['success' => false, 'message' => 'Missing required parameters.']);
        }

        $profileKey = $this->config->getProfileKey();
        $tweakwiseRequest = $this->requestFactory->create();
        $tweakwiseRequest->setProfileKey($profileKey);
        $storeId = (int)$this->storeManager->getStore()->getId();

        try {
            switch ($type) {
                case 'product':
                    $this->handleProductType($tweakwiseRequest, $value);
                    break;
                case 'search':
                    $this->handleSearchType($tweakwiseRequest, $value);
                    break;
                case 'itemclick':
                    $this->handleItemClickType($tweakwiseRequest, $value, $storeId);
                    break;
                default:
                    return $result->setData(['success' => false, 'message' => 'Invalid type parameter.']);
            }

            $this->client->request($tweakwiseRequest);
            return $result->setData(['success' => true]);
        } catch (Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * @param Request $tweakwiseRequest
     * @param string  $value
     *
     * @return void
     */
    private function handleProductType(Request $tweakwiseRequest, string $value): void
    {
        $tweakwiseRequest->setParameter('productKey', $value);
        $tweakwiseRequest->setPath('pageview');
    }

    /**
     * @param Request $tweakwiseRequest
     * @param string  $value
     *
     * @return void
     */
    private function handleSearchType(Request $tweakwiseRequest, string $value): void
    {
        $tweakwiseRequest->setParameter('searchTerm', $value);
        $tweakwiseRequest->setPath('search');
    }

    /**
     * @param Request $tweakwiseRequest
     * @param string  $value
     * @param int     $storeId
     *
     * @throws InvalidArgumentException
     * @return void
     */
    private function handleItemClickType(Request $tweakwiseRequest, string $value, int $storeId): void
    {
        $twRequestId = $this->getRequest()->getParam('requestId');
        if (empty($twRequestId)) {
            throw new InvalidArgumentException('Missing requestId for itemclick.');
        }

        if (ctype_digit($value)) {
            $value = $this->helper->getTweakwiseId($storeId, $value);
        }

        $tweakwiseRequest->setParameter('requestId', $twRequestId);
        $tweakwiseRequest->setParameter('itemId', $value);
        $tweakwiseRequest->setPath('itemclick');
    }
}
