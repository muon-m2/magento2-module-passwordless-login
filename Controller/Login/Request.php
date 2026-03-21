<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Controller\Login;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Request implements HttpGetActionInterface
{
    /**
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     */
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    /**
     * Render the passwordless login request form.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute(): \Magento\Framework\View\Result\Page
    {
        return $this->pageFactory->create();
    }
}
