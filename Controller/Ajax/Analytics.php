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
<<<<<<< HEAD
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Magento\Store\Model\StoreManagerInterface;
use InvalidArgumentException;
use Exception;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;
=======
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
>>>>>>> beta

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
<<<<<<< HEAD
     * @param Helper                      $helper
     * @param StoreManagerInterface       $storeManager
=======
     * @param JsonSerializer              $jsonSerializer
>>>>>>> beta
     */
    public function __construct(
        private Context $context,
        private JsonFactory $resultJsonFactory,
        private Client $client,
        private PersonalMerchandisingConfig $config,
<<<<<<< HEAD
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager,
=======
        private RequestFactory $requestFactory,
        private readonly JsonSerializer $jsonSerializer
>>>>>>> beta
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
<<<<<<< HEAD

        if (!$this->config->isAnalyticsEnabled()) {
            return $result->setData(['success' => false, 'message' => 'Analytics is disabled.']);
        }
=======
        if ($this->config->isAnalyticsEnabled()) {
            $request = $this->getRequest();
            $type = $request->getParam('type');
            $value = $request->getParam('value');
            $profileKey = $this->config->getProfileKey();

            //hyva theme
            if (empty($type)) {
                $content = $this->jsonSerializer->unserialize($request->getContent());
                $type = $content['type'] ?? null;
                $value = $content['value'] ?? null;
            }

            $tweakwiseRequest = $this->requestFactory->create();
            $tweakwiseRequest->setProfileKey($profileKey);
>>>>>>> beta

        $type = $this->getRequest()->getParam('type');
        $value = $this->getRequest()->getParam('value');

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
            $result->setData(['success' => true]);
        } catch (InvalidArgumentException $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
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
