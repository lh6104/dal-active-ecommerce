<?php

namespace Dalactive\StockAlert\Controller\Subscribe;

use Dalactive\StockAlert\Model\SubscriberFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Validator\EmailAddress as EmailValidator;

class Save implements HttpPostActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private ManagerInterface $messageManager;
    private SubscriberFactory $subscriberFactory;
    private ProductRepositoryInterface $productRepository;
    private EmailValidator $emailValidator;
    private DateTime $dateTime;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        SubscriberFactory $subscriberFactory,
        ProductRepositoryInterface $productRepository,
        EmailValidator $emailValidator,
        DateTime $dateTime
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->subscriberFactory = $subscriberFactory;
        $this->productRepository = $productRepository;
        $this->emailValidator = $emailValidator;
        $this->dateTime = $dateTime;
    }

    public function execute()
    {
        $result = $this->redirectFactory->create();
        $referer = (string) $this->request->getServer('HTTP_REFERER');
        $result->setUrl($referer ?: '/');

        $email = trim((string) $this->request->getParam('email'));
        $productId = (int) $this->request->getParam('product_id');
        if (!$this->emailValidator->isValid($email) || $productId <= 0) {
            $this->messageManager->addErrorMessage(__('Please enter a valid email address.'));
            return $result;
        }

        try {
            $product = $this->productRepository->getById($productId);
            $subscriber = $this->subscriberFactory->create();
            $subscriber->setData([
                'email' => $email,
                'product_id' => (int) $product->getId(),
                'product_option' => (string) $this->request->getParam('product_option', ''),
                'subscribe_date' => $this->dateTime->gmtDate(),
                'notification_status' => 0,
            ]);
            $subscriber->save();
            $this->messageManager->addSuccessMessage(__('We will notify you when this item is back in stock.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to save your stock alert right now.'));
        }

        return $result;
    }
}
