<?php

declare(strict_types=1);

namespace Muon\PasswordlessLogin\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Provides template data for the magic-link confirmation page.
 */
class AuthenticateViewModel implements ArgumentInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * Return the raw token from the current request.
     *
     * @return string
     */
    public function getToken(): string
    {
        return (string) $this->request->getParam('token');
    }
}
