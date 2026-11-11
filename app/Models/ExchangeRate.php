<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ExchangeRate extends BaseModel
{
    protected string $table = 'exchange_rates';

    public function latestRates(): array
    {
        return Database::fetchAll(
            "SELECT er.* FROM exchange_rates er
             INNER JOIN (
                 SELECT from_currency, to_currency, MAX(effective_date) AS max_date
                 FROM exchange_rates
                 GROUP BY from_currency, to_currency
             ) latest ON er.from_currency = latest.from_currency
                     AND er.to_currency = latest.to_currency
                     AND er.effective_date = latest.max_date
             ORDER BY er.from_currency, er.to_currency"
        );
    }
}
