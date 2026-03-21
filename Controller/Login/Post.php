<?php

declare(strict_types=1);

namespace Muon\PasswordlessLogin\Controller\Login;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Validator\EmailAddress;
use Muon\PasswordlessLogin\Api\MagicLinkServiceInterface;
use Muon\PasswordlessLogin\Model\Config;

class Post implements HttpPostActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Magento\Framework\Controller\Result\RedirectFactory  $redirectFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator        $formKeyValidator
     * @param \Magento\Framework\Message\ManagerInterface           $messageManager
     * @param \Magento\Framework\Validator\EmailAddress             $emailValidator
     * @param \Muon\PasswordlessLogin\Api\MagicLinkServiceInterface $magicLinkService
     * @param \Muon\PasswordlessLogin\Model\Config                  $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly EmailAddress $emailValidator,
        private readonly MagicLinkServiceInterface $magicLinkService,
        private readonly Config $config,
    ) {
    }

    /**
     * Handle the passwordless login request form submission.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Login link feature is currently disabled.'));

            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        $email = trim((string)$this->request->getParam('email'));

        if (!$this->emailValidator->isValid($email)) {
            $this->messageManager->addErrorMessage(__('Please enter a valid email address.'));

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        try {
            $this->magicLinkService->sendLink($email);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('passwordlesslogin/login/request');
        }

        // Always show success — do not reveal whether the email is registered
        $this->messageManager->addSuccessMessage(
            __('If an account exists for this email, a login link has been sent. Please check your inbox.'),
        );

        return $redirect->setPath('passwordlesslogin/login/request');
    }
}
