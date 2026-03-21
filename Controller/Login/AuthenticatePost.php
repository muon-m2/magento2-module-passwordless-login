<?php

declare(strict_types=1);

namespace Muon\PasswordlessLogin\Controller\Login;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Muon\PasswordlessLogin\Api\MagicLinkServiceInterface;
use Muon\PasswordlessLogin\Model\Config;

class AuthenticatePost implements HttpPostActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Magento\Framework\Controller\Result\RedirectFactory  $redirectFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator        $formKeyValidator
     * @param \Magento\Framework\Message\ManagerInterface           $messageManager
     * @param \Muon\PasswordlessLogin\Api\MagicLinkServiceInterface $magicLinkService
     * @param \Muon\PasswordlessLogin\Model\Config                  $config
     * @param \Magento\Customer\Model\Session                       $customerSession
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly MagicLinkServiceInterface $magicLinkService,
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
    ) {
    }

    /**
     * Authenticate the customer using the submitted magic-link token.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        $rawToken = (string)$this->request->getParam('token');

        if (!$rawToken) {
            $this->messageManager->addErrorMessage(
                __('The login link is invalid or has expired. Please request a new one.'),
            );

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        try {
            $customer = $this->magicLinkService->authenticate($rawToken);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        $this->customerSession->loginById($customer->getId());
        $this->customerSession->regenerateId();

        return $redirect->setPath('customer/account');
    }
}
