<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\ReservationHarness;

beforeEach(function () {
    ReservationHarness::createTables();
    DB::table(ReservationHarness::RESOURCES)->insert(['id' => 1, 'capacity' => 3]);
});

afterEach(function () {
    DB::purge('mysql_contender');
    ReservationHarness::dropTables();
});

it('runs against MySQL 8.4 InnoDB in a dedicated testing database [ADR-0002]', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toEndWith('_testing')
        ->and(DB::scalar('select version()'))->toStartWith('8.4')
        ->and(DB::scalar('select @@default_storage_engine'))->toBe('InnoDB');
})->group('mysql');

it('makes a competing transaction wait for a row lock instead of ignoring it [ADR-0004]', function () {
    config(['database.connections.mysql_contender' => config('database.connections.'.config('database.default'))]);

    DB::beginTransaction();
    DB::table(ReservationHarness::RESOURCES)->where('id', 1)->lockForUpdate()->first();

    DB::connection('mysql_contender')->statement('SET SESSION innodb_lock_wait_timeout = 1');

    expect(fn () => DB::connection('mysql_contender')
        ->table(ReservationHarness::RESOURCES)
        ->where('id', 1)
        ->lockForUpdate()
        ->first()
    )->toThrow(QueryException::class, 'Lock wait timeout exceeded');

    DB::rollBack();
})->group('mysql');

it('rolls back every write when a transaction fails part-way', function () {
    expect(fn () => DB::transaction(function () {
        DB::table(ReservationHarness::RESERVATIONS)->insert([
            'resource_id' => 1,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 11:00:00',
            'worker' => 'rollback-test',
        ]);

        DB::table(ReservationHarness::RESOURCES)->where('id', 1)->decrement('capacity');

        throw new RuntimeException('Simulated failure after partial writes');
    }))->toThrow(RuntimeException::class);

    expect(DB::table(ReservationHarness::RESERVATIONS)->count())->toBe(0)
        ->and(DB::table(ReservationHarness::RESOURCES)->where('id', 1)->value('capacity'))->toBe(3);
})->group('mysql');

it('confirms exactly one of several concurrent overlapping requests when the parent row is locked first [BR-BK-1, BR-BK-5]', function () {
    $outcomes = ReservationHarness::runWorkers(1, [
        ['starts_at' => '2030-01-01 10:00:00', 'ends_at' => '2030-01-01 11:00:00'],
        ['starts_at' => '2030-01-01 10:30:00', 'ends_at' => '2030-01-01 11:30:00'],
        ['starts_at' => '2030-01-01 09:30:00', 'ends_at' => '2030-01-01 10:45:00'],
        ['starts_at' => '2030-01-01 10:15:00', 'ends_at' => '2030-01-01 10:45:00'],
        ['starts_at' => '2030-01-01 10:00:00', 'ends_at' => '2030-01-01 11:00:00'],
        ['starts_at' => '2030-01-01 14:00:00', 'ends_at' => '2030-01-01 15:00:00'],
    ], ReservationHarness::LOCKED, 'exclusive');

    expect(ReservationHarness::tally($outcomes))->toBe(['confirmed' => 2, 'conflict' => 4])
        ->and($outcomes['worker-5'])->toBe('confirmed')
        ->and(ReservationHarness::overlappingPairs())->toBe(0);
})->group('mysql', 'concurrency');

it('never exceeds shared capacity under concurrent requests when the parent row is locked first [BR-BK-2, BR-BK-5]', function () {
    $requests = array_fill(0, 8, ['starts_at' => '2030-01-01 09:00:00', 'ends_at' => '2030-01-01 18:00:00', 'quantity' => 1]);

    $outcomes = ReservationHarness::runWorkers(1, $requests, ReservationHarness::LOCKED, 'capacity');

    expect(ReservationHarness::tally($outcomes))->toBe(['confirmed' => 3, 'conflict' => 5])
        ->and((int) DB::table(ReservationHarness::RESERVATIONS)->sum('quantity'))->toBe(3);
})->group('mysql', 'concurrency');

it('detects double booking when requests check then insert without a lock (negative control)', function () {
    ReservationHarness::runWorkers(1, array_fill(0, 4, [
        'starts_at' => '2030-01-01 10:00:00',
        'ends_at' => '2030-01-01 11:00:00',
    ]), ReservationHarness::NAIVE, 'exclusive');

    expect(ReservationHarness::overlappingPairs())->toBeGreaterThan(0);
})->group('mysql', 'concurrency');

it('detects double booking when a snapshot read happens before the lock (negative control)', function () {
    ReservationHarness::runWorkers(1, array_fill(0, 4, [
        'starts_at' => '2030-01-01 10:00:00',
        'ends_at' => '2030-01-01 11:00:00',
    ]), ReservationHarness::STALE_SNAPSHOT, 'exclusive');

    expect(ReservationHarness::overlappingPairs())->toBeGreaterThan(0);
})->group('mysql', 'concurrency');
