<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model\ResourceModel\Token;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Muon\PasswordlessLogin\Model\Token;
use Muon\PasswordlessLogin\Model\ResourceModel\Token as TokenResource;

/**
 * Magic link token collection.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize collection model and resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Token::class, TokenResource::class);
    }
}
