<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Cron;

use Muon\PasswordlessLogin\Api\TokenRepositoryInterface;
use Psr\Log\LoggerInterface;

class CleanupTokens
{
    /**
     * @param \Muon\PasswordlessLogin\Api\TokenRepositoryInterface $tokenRepository
     * @param \Psr\Log\LoggerInterface                             $logger
     */
    public function __construct(
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Delete expired and consumed tokens.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->tokenRepository->deleteExpired();
        } catch (\Exception $e) {
            $this->logger->error(
                'Muon_PasswordlessLogin: token cleanup failed.',
                ['exception' => $e]
            );
        }
    }
}
