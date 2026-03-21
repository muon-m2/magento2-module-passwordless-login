<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Controller\Login;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\View\Result\PageFactory;
use Muon\PasswordlessLogin\Model\Config;

class Authenticate implements HttpGetActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface              $request
     * @param \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
     * @param \Magento\Framework\Message\ManagerInterface          $messageManager
     * @param \Magento\Framework\View\Result\PageFactory           $pageFactory
     * @param \Muon\PasswordlessLogin\Model\Config                 $config
     * @param \Magento\Customer\Model\Session                      $customerSession
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly PageFactory $pageFactory,
        private readonly Config $config,
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Render the magic-link confirmation page.
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('customer/account/login');
        }

        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account');
        }

        if (!$this->request->getParam('token')) {
            $this->messageManager->addErrorMessage(
                __('The login link is invalid or has expired. Please request a new one.')
            );
            return $redirect->setPath('passwordlesslogin/login/request');
        }

        return $this->pageFactory->create();
    }
}
