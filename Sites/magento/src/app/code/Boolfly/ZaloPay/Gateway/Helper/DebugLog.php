<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Helper;

class DebugLog
{
    public function log(string $message, array $context = []): void
    {
        $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents(BP . '/var/log/zalopay.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
