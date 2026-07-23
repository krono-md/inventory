<?php

namespace Modules\Inventory\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait BelongsToClient
{
    private static array $clientColumnCache = [];

    public function getConnectionName(): ?string
    {
        return 'inventory';
    }

    protected static function bootBelongsToClient(): void
    {
        static::addGlobalScope('client', function (Builder $query): void {
            $table = $query->getModel()->getTable();

            if (! isset(self::$clientColumnCache[$table])) {
                self::$clientColumnCache[$table] = Schema::connection('inventory')->hasColumn($table, 'client_id');
            }

            if (! self::$clientColumnCache[$table]) {
                $query->whereRaw('1 = 0');

                return;
            }

            if ($clientId = session('employee_client_id')) {
                $query->where($table.'.client_id', $clientId);
            }
        });

        static::creating(function ($model): void {
            if (! $model->client_id && ($clientId = session('employee_client_id'))) {
                $model->client_id = $clientId;
            }
        });
    }
}
