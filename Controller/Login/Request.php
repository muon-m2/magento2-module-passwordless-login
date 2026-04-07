<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Controller\Login;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Muon\PasswordlessLogin\Model\Config;

class Request implements HttpGetActionInterface
{
    /**
     * @param \Magento\Framework\View\Result\PageFactory           $pageFactory
     * @param \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
     * @param \Muon\PasswordlessLogin\Model\Config                 $config
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config,
    ) {
    }

    /**
     * Render the passwordless login request form.
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
    {
        if (!$this->config->isEnabled()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        return $this->pageFactory->create();
    }
}
