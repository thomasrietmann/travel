<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MysqlIdentityLockTest extends TestCase
{
    public function test_identity_mutex_serializes_two_real_mysql_connections(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL CI job or a dedicated MySQL test database.');
        }
        config(['database.connections.contender' => config('database.connections.mysql')]);
        $contender = DB::connection('contender');
        $contender->statement('SET SESSION innodb_lock_wait_timeout = 1');
        DB::beginTransaction();
        DB::table('email_identity_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
        try {
            $contender->transaction(fn () => $contender->table('email_identity_lock')->where('id', 1)->lockForUpdate()->firstOrFail());
            $this->fail('Second writer must wait for the identity transaction');
        } catch (QueryException $exception) {
            $this->assertSame(1205, (int) $exception->errorInfo[1]);
        } finally {
            DB::rollBack();
        }
        $row = $contender->transaction(fn () => $contender->table('email_identity_lock')->where('id', 1)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, $row->id);
        DB::purge('contender');
    }
}
