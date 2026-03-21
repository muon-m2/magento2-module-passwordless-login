<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Magic link token resource model.
 */
class Token extends AbstractDb
{
    public const TABLE_NAME = 'muon_passwordless_login_token';
    public const ID_FIELD   = 'token_id';

    /**
     * Initialize resource model table and primary key.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }
}
