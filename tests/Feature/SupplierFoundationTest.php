<?php

use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationPenaltyType;
use App\Enums\CancellationSupplierStatus;
use App\Enums\GuestType;
use App\Enums\RateRefundability;
use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\SupplierOperationLog;
use App\Models\User;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Data\CancellationPolicyData;
use App\Services\Supplier\Data\CheckRateRequestData;
use App\Services\Supplier\Data\GuestData;
use App\Services\Supplier\Data\HotelSearchRequestData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\Data\SupplierBookingRequestData;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\Exceptions\DisabledSupplierException;
use App\Services\Supplier\Exceptions\DuplicateSupplierRequestException;
use App\Services\Supplier\Exceptions\InvalidSupplierResponseException;
use App\Services\Supplier\Exceptions\MissingSupplierException;
use App\Services\Supplier\Exceptions\SupplierAuthenticationException;
use App\Services\Supplier\Exceptions\UnsupportedSupplierOperationException;
use App\Services\Supplier\PayloadSanitizer;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\Tbo\TboHotelSupplier;
use App\Services\Supplier\Transport\SecureSupplierXmlTransport;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
});

function supplierSearchRequest(array $metadata = [], array $rooms = [], string $currency = 'EGP'): HotelSearchRequestData
{
    return new HotelSearchRequestData(
        destinationIdentifier: $metadata['destination'] ?? 'Cairo',
        checkIn: CarbonImmutable::now()->addDays(10),
        checkOut: CarbonImmutable::now()->addDays(13),
        rooms: $rooms ?: [new RoomOccupancyData(2)],
        currency: $currency,
        locale: 'ar',
        correlationId: $metadata['correlation_id'] ?? null,
        metadata: $metadata,
    );
}

function mockSupplierAdapter()
{
    return app(SupplierManager::class)->resolve('mock_hotels', SupplierOperation::Search);
}

it('validates supplier request dto inputs and money safely', function () {
    expect(fn () => new HotelSearchRequestData('Cairo', CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay(), [new RoomOccupancyData(1)]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new HotelSearchRequestData('Cairo', CarbonImmutable::now()->addDays(2), CarbonImmutable::now()->addDay(), [new RoomOccupancyData(1)]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RoomOccupancyData(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RoomOccupancyData(1, 2, [7]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new GuestData('Child', 'Lead', GuestType::Child, 7, true))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new HotelSearchRequestData('Cairo', CarbonImmutable::now()->addDay(), CarbonImmutable::now()->addDays(2), [new RoomOccupancyData(1)], 'JPY'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new HotelSearchRequestData('Cairo', CarbonImmutable::now()->addDay(), CarbonImmutable::now()->addDays(2), [new RoomOccupancyData(1)], 'EGP', 'fr'))->toThrow(InvalidArgumentException::class);

    $money = Money::fromDecimalString('10.10', 'EGP');

    expect($money->minorAmount)->toBe(1010)
        ->and($money->decimal())->toBe('10.10');
});

it('validates cancellation windows', function () {
    expect(new CancellationPolicyData(CarbonImmutable::now(), CarbonImmutable::now()->addDay(), CancellationPenaltyType::Amount, Money::fromDecimalString('100.00', 'EGP')))->toBeInstanceOf(CancellationPolicyData::class)
        ->and(fn () => new CancellationPolicyData(CarbonImmutable::now()->addDay(), CarbonImmutable::now(), CancellationPenaltyType::Amount))->toThrow(InvalidArgumentException::class);
});

it('resolves and filters suppliers through the manager', function () {
    $manager = app(SupplierManager::class);

    expect($manager->resolve('mock_hotels', SupplierOperation::Search))->not->toBeNull()
        ->and(fn () => $manager->resolve('missing_supplier'))->toThrow(MissingSupplierException::class);

    Supplier::query()->where('code', 'mock_hotels')->update(['status' => SupplierStatus::Inactive]);

    expect(fn () => $manager->resolve('mock_hotels', SupplierOperation::Search))->toThrow(DisabledSupplierException::class);

    Supplier::query()->where('code', 'mock_hotels')->update(['status' => SupplierStatus::Active, 'booking_enabled' => false]);

    expect(fn () => $manager->resolve('mock_hotels', SupplierOperation::Book))->toThrow(UnsupportedSupplierOperationException::class);
});

it('seeds TBO as a safe inactive supplier shell', function () {
    $supplier = Supplier::query()->where('code', 'tbo_hotels')->firstOrFail();

    expect($supplier->status)->toBe(SupplierStatus::Inactive)
        ->and($supplier->integration_type->value)->toBe('rest')
        ->and($supplier->search_enabled)->toBeFalse()
        ->and($supplier->booking_enabled)->toBeFalse()
        ->and($supplier->cancellation_enabled)->toBeFalse()
        ->and(fn () => app(SupplierManager::class)->resolve('tbo_hotels', SupplierOperation::Search))->toThrow(DisabledSupplierException::class);
});

it('resolves TBO only when explicitly activated and keeps operations closed', function () {
    Supplier::query()->where('code', 'tbo_hotels')->update([
        'status' => SupplierStatus::Active,
        'search_enabled' => true,
    ]);

    $adapter = app(SupplierManager::class)->resolve('tbo_hotels', SupplierOperation::Search);

    expect($adapter)->toBeInstanceOf(TboHotelSupplier::class)
        ->and(fn () => $adapter->search(supplierSearchRequest()))->toThrow(SupplierAuthenticationException::class)
        ->and($adapter->healthCheck()->healthy)->toBeFalse();
});

it('safely diagnoses TBO without live requests while inactive', function () {
    $this->artisan('tbo:test-connection --diagnostic')
        ->expectsOutputToContain('Supplier: tbo_hotels')
        ->expectsOutputToContain('Status: inactive')
        ->expectsOutputToContain('Endpoint keys:')
        ->expectsOutputToContain('No external request was sent.')
        ->assertSuccessful();
});

it('normalizes a fake TBO search response without exposing credentials', function () {
    $supplier = Supplier::query()->where('code', 'tbo_hotels')->firstOrFail();
    $supplier->update([
        'status' => SupplierStatus::Active,
        'search_enabled' => true,
        'base_url' => 'https://tbo.test',
    ]);
    SupplierCredential::query()->create([
        'supplier_id' => $supplier->id,
        'credential_key' => 'username',
        'encrypted_value' => 'safe-user',
        'is_secret' => true,
    ]);
    SupplierCredential::query()->create([
        'supplier_id' => $supplier->id,
        'credential_key' => 'password',
        'encrypted_value' => 'safe-pass',
        'is_secret' => true,
    ]);

    Http::fake([
        'https://tbo.test/HotelBookingApi/HotelSearch' => Http::response([
            'HotelSearchResult' => [
                'TraceId' => 'tbo-trace-1',
                'SearchResults' => [[
                    'ResultIndex' => '1',
                    'HotelCode' => 'TBO1001',
                    'HotelName' => 'TBO Cairo Test Hotel',
                    'HotelCategory' => '4 Star',
                    'HotelAddress' => 'Cairo',
                    'Price' => ['CurrencyCode' => 'USD', 'OfferedPrice' => '120.50'],
                    'IsRefundable' => true,
                ]],
            ],
        ], 200),
    ]);

    $result = app(SupplierManager::class)
        ->resolve('tbo_hotels', SupplierOperation::Search)
        ->search(supplierSearchRequest(['city_id' => '12345', 'country_code' => 'EG'], currency: 'USD'));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://tbo.test/HotelBookingApi/HotelSearch'
        && $request['UserName'] === 'safe-user'
        && $request['Password'] === 'safe-pass'
        && $request['CityId'] === '12345'
        && $request['NoOfRooms'] === 1);

    $log = SupplierOperationLog::query()->latest('id')->firstOrFail();

    expect($result->supplierCode)->toBe('tbo_hotels')
        ->and($result->searchId)->toBe('tbo-trace-1')
        ->and($result->hotels)->toHaveCount(1)
        ->and($result->hotels[0]->supplierHotelId)->toBe('TBO1001')
        ->and($result->hotels[0]->minimumTotalPrice->decimal())->toBe('120.50')
        ->and($log->request_payload['UserName'])->toBe('[REDACTED]')
        ->and($log->request_payload['Password'])->toBe('[REDACTED]');
});

it('orders enabled suppliers by priority', function () {
    Supplier::query()->create([
        'name' => 'Second Mock',
        'code' => 'second_mock',
        'integration_type' => 'mock',
        'environment' => 'sandbox',
        'status' => 'active',
        'priority' => 50,
        'search_enabled' => true,
    ]);

    expect(app(SupplierManager::class)->enabledFor(SupplierOperation::Search)->pluck('code')->all())->toBe(['mock_hotels', 'second_mock']);
});

it('returns deterministic mock search results with rooms boards taxes and cancellation data', function () {
    $result = mockSupplierAdapter()->search(supplierSearchRequest(rooms: [
        new RoomOccupancyData(2),
        new RoomOccupancyData(1, 1, [8]),
    ]));

    expect($result->supplierCode)->toBe('mock_hotels')
        ->and($result->hotels)->toHaveCount(4)
        ->and($result->hotels[0]->rooms)->toHaveCount(2)
        ->and($result->hotels[0]->rooms[0]->refundability)->toBe(RateRefundability::Refundable)
        ->and($result->hotels[0]->rooms[1]->refundability)->toBe(RateRefundability::NonRefundable)
        ->and($result->hotels[0]->taxesAndFees)->toHaveKeys(['tax', 'fee']);

    $empty = mockSupplierAdapter()->search(supplierSearchRequest(['scenario' => 'no_availability']));

    expect($empty->hotels)->toBe([])
        ->and($empty->warnings)->not->toBeEmpty();
});

it('checks rates for success price change expiry and sold out scenarios', function () {
    $adapter = mockSupplierAdapter();
    $base = ['MCK-CAI-001', 'MCK-CAI-001|bb|ref', ['MCK-CAI-001-room-deluxe'], [new RoomOccupancyData(2)], 'EGP'];

    expect($adapter->checkRate(new CheckRateRequestData(...$base))->available)->toBeTrue()
        ->and($adapter->checkRate(new CheckRateRequestData(...$base, metadata: ['scenario' => 'price_changed']))->priceChanged)->toBeTrue()
        ->and($adapter->checkRate(new CheckRateRequestData(...$base, metadata: ['scenario' => 'rate_expired']))->failureReason)->toBe('rate_expired')
        ->and($adapter->checkRate(new CheckRateRequestData(...$base, metadata: ['scenario' => 'sold_out']))->failureReason)->toBe('sold_out');
});

it('handles booking idempotency duplicates conflicts rejection and uncertain lookup', function () {
    $adapter = mockSupplierAdapter();
    $lead = new GuestData('Ahmed', 'Test', GuestType::Adult, null, true);
    $request = new SupplierBookingRequestData('book-key-1', 'MCK-CAI-001|bb|ref', 'MCK-CAI-001', [['room' => 'deluxe']], $lead, [$lead], ['email' => 'guest@example.test'], Money::fromDecimalString('2500.00', 'EGP'));

    $first = $adapter->book($request);
    $duplicate = $adapter->book($request);

    expect($first->supplierBookingReference)->toBe($duplicate->supplierBookingReference)
        ->and(fn () => $adapter->book(new SupplierBookingRequestData('book-key-1', 'MCK-CAI-001|hb|nr', 'MCK-CAI-001', [['room' => 'family']], $lead, [$lead], [], Money::fromDecimalString('3100.00', 'EGP'))))->toThrow(DuplicateSupplierRequestException::class);

    $rejected = $adapter->book(new SupplierBookingRequestData('book-key-2', 'booking_rejected', 'MCK-CAI-001', [], $lead, [$lead], [], Money::fromDecimalString('2500.00', 'EGP'), metadata: ['scenario' => 'booking_rejected']));
    $uncertain = $adapter->book(new SupplierBookingRequestData('book-key-3', 'uncertain', 'MCK-CAI-001', [], $lead, [$lead], [], Money::fromDecimalString('2500.00', 'EGP'), metadata: ['scenario' => 'uncertain']));
    $lookup = $adapter->getBooking(new SupplierBookingLookupRequestData($uncertain->supplierBookingReference));

    expect($rejected->status)->toBe(BookingSupplierStatus::Rejected)
        ->and($uncertain->requiresManualReview)->toBeTrue()
        ->and($lookup->found)->toBeTrue();
});

it('handles free penalized non refundable and duplicate cancellations', function () {
    $adapter = mockSupplierAdapter();

    $free = $adapter->cancel(new SupplierCancellationRequestData('MHB-FREE', 'cancel-key-1'));
    $duplicate = $adapter->cancel(new SupplierCancellationRequestData('MHB-FREE', 'cancel-key-1'));
    $penalty = $adapter->cancel(new SupplierCancellationRequestData('MHB-PENALTY', 'cancel-key-2'));
    $nonRefundable = $adapter->cancel(new SupplierCancellationRequestData('MHB-NR', 'cancel-key-3'));

    expect($free->status)->toBe(CancellationSupplierStatus::Cancelled)
        ->and($duplicate->cancellationReference)->toBe($free->cancellationReference)
        ->and($penalty->penaltyAmount->minorAmount)->toBe(50000)
        ->and($nonRefundable->status)->toBe(CancellationSupplierStatus::Rejected);
});

it('redacts secrets rejects unsafe xml and propagates correlation ids into logs', function () {
    $sanitized = app(PayloadSanitizer::class)->sanitize([
        'Authorization' => 'Bearer abc',
        'nested' => ['password' => 'secret', 'safe' => 'ok'],
    ]);

    expect($sanitized['Authorization'])->toBe('[REDACTED]')
        ->and($sanitized['nested']['password'])->toBe('[REDACTED]')
        ->and($sanitized['nested']['safe'])->toBe('ok')
        ->and(fn () => app(SecureSupplierXmlTransport::class)->parse('<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>'))->toThrow(InvalidSupplierResponseException::class)
        ->and(fn () => app(SecureSupplierXmlTransport::class)->parse('<foo>'))->toThrow(InvalidSupplierResponseException::class)
        ->and(SupplierOperation::Book->isAutomaticallyRetryable())->toBeFalse()
        ->and(SupplierOperation::Search->isAutomaticallyRetryable())->toBeTrue();

    $correlationId = app(CorrelationIdFactory::class)->make('fixed-correlation');
    mockSupplierAdapter()->search(supplierSearchRequest(['correlation_id' => $correlationId]));

    expect(SupplierOperationLog::query()->where('correlation_id', $correlationId)->exists())->toBeTrue();
});

it('encrypts and hides supplier credential values', function () {
    $supplier = Supplier::query()->where('code', 'mock_hotels')->firstOrFail();
    $credential = SupplierCredential::query()->create([
        'supplier_id' => $supplier->id,
        'credential_key' => 'api_token',
        'encrypted_value' => 'plain-secret',
        'is_secret' => true,
    ]);

    $raw = DB::table('supplier_credentials')->where('id', $credential->id)->value('encrypted_value');

    expect($raw)->not->toBe('plain-secret')
        ->and($credential->refresh()->encrypted_value)->toBe('plain-secret')
        ->and($credential->toArray())->not->toHaveKey('encrypted_value');
});

it('enforces supplier permissions for api manager auditors and unauthorized users', function () {
    $supplier = Supplier::query()->where('code', 'mock_hotels')->firstOrFail();
    $apiManager = User::factory()->create();
    $apiManager->assignRole('api_manager');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $unauthorized = User::factory()->create();
    $unauthorized->assignRole('reservation_agent');

    $this->actingAs($apiManager);
    expect(Gate::allows('update', $supplier))->toBeTrue()
        ->and(Gate::allows('manageCredentials', $supplier))->toBeTrue();

    $this->actingAs($auditor);
    expect(Gate::allows('view', $supplier))->toBeTrue()
        ->and(Gate::denies('update', $supplier))->toBeTrue()
        ->and(Gate::denies('manageCredentials', $supplier))->toBeTrue();

    $this->actingAs($unauthorized)
        ->get('/admin/suppliers')
        ->assertForbidden();
});

it('keeps Arabic default and English fallback', function () {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('en');
});
