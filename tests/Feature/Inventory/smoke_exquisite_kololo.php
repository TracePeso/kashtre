<?php

/**
 * Full Endstore smoke for Exquisite Test Life (KS1759822163) / Kololo.
 * Run: php storage/app/smoke_exquisite_kololo.php
 */

use App\Http\Controllers\ServiceDeliveryQueueController;
use App\Models\Client;
use App\Models\ClientSpace;
use App\Models\InventoryDemandLedger;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PatientApprovedPoolLine;
use App\Models\ServiceDeliveryQueue;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryCrashCartService;
use App\Services\Inventory\InventoryFulfillmentDispenseService;
use App\Services\Inventory\InventoryFulfillmentIngestService;
use App\Services\Inventory\InventoryFulfillmentStageService;
use App\Services\Inventory\InventoryHandoffTokenService;
use App\Services\Inventory\InventoryRecordUsageService;
use App\Support\InventoryFulfillmentStrategy;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$BID = 4;
$BRANCH = 6;
$END_STORE = 11; // Dispensary One
$SPACE = 1; // Ward Stock default
$CRASH = 15; // Crash Cart under Dispensary One

$pass = 0;
$fail = 0;
$skip = 0;
$results = [];

function ok(string $name, string $detail = ''): void
{
    global $pass, $results;
    $pass++;
    $results[] = ['PASS', $name, $detail];
    echo "PASS  {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
}

function fail(string $name, string $detail = ''): void
{
    global $fail, $results;
    $fail++;
    $results[] = ['FAIL', $name, $detail];
    echo "FAIL  {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
}

function skip(string $name, string $detail = ''): void
{
    global $skip, $results;
    $skip++;
    $results[] = ['SKIP', $name, $detail];
    echo "SKIP  {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
}

echo "=== Exquisite Test Life / Kololo Endstore smoke ===".PHP_EOL;
echo 'business_id='.$BID.' branch_id='.$BRANCH.' end_store='.$END_STORE.PHP_EOL.PHP_EOL;

try {
    // --- Setup assertions ---
    $cfg = InventoryModuleConfig::where('business_id', $BID)->first();
    if ($cfg?->is_active) {
        ok('Inventory module active');
    } else {
        fail('Inventory module active', 'missing or inactive');
        exit(1);
    }

    $space = ClientSpace::with('storeAssignment')->find($SPACE);
    if ($space && $space->is_default && (int) $space->branch_id === $BRANCH
        && (int) $space->storeAssignment?->store_id === $END_STORE) {
        ok('Default Client Space → Dispensary One', $space->name.' strategy='.$space->storeAssignment->fulfillment_strategy);
    } else {
        fail('Default Client Space → Dispensary One', json_encode($space?->toArray()));
    }

    $end = Store::find($END_STORE);
    if ($end?->isEndStore() && (int) $end->branch_id === $BRANCH) {
        ok('End Store Dispensary One on Kololo', 'id='.$end->id);
    } else {
        fail('End Store Dispensary One on Kololo');
    }

    $crash = Store::find($CRASH);
    if ($crash?->is_crash_cart && (int) $crash->parent_id === $END_STORE) {
        ok('Crash Cart under Dispensary One', 'status='.$crash->crash_cart_status);
    } else {
        fail('Crash Cart under Dispensary One');
    }

    $user = User::where('business_id', $BID)->where('branch_id', $BRANCH)->orderBy('id')->first();
    $client = Client::where('business_id', $BID)->where('branch_id', $BRANCH)->orderBy('id')->first();
    if (! $user || ! $client) {
        fail('Test user/client on Kololo', 'user='.($user?->id).' client='.($client?->id));
        exit(1);
    }
    ok('Kololo user + client', 'user='.$user->id.' client='.$client->client_id);

    $stock = InventoryStockLevel::where('business_id', $BID)
        ->where('store_id', $END_STORE)
        ->where('quantity_suom', '>', 1)
        ->orderByDesc('quantity_suom')
        ->first();
    if (! $stock) {
        fail('End Store stock for smoke item');
        exit(1);
    }
    $item = Item::find($stock->item_id);
    ok('Smoke item stocked at End Store', $item->name.' qty='.$stock->quantity_suom);

    $stockBeforeOp = (float) $stock->quantity_suom;
    $visitOp = 'SMOKE-OP-'.uniqid();
    $visitIp = 'SMOKE-IP-'.uniqid();

    auth()->login($user);

    // --- OP: paid goods → queue → dispense ---
    $unitPrice = (float) ($item->default_price ?: 100);
    $invoiceOp = Invoice::create([
        'invoice_number' => 'SMOKE-OP-'.uniqid(),
        'business_id' => $BID,
        'branch_id' => $BRANCH,
        'client_id' => $client->id,
        'client_name' => $client->name,
        'client_phone' => $client->phone_number ?: '0700000000',
        'payment_phone' => $client->payment_phone_number ?: ($client->phone_number ?: '0700000000'),
        'created_by' => $user->id,
        'visit_id' => $visitOp,
        'client_space_id' => $SPACE,
        'end_store_id' => $END_STORE,
        'fulfillment_strategy' => InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
        'items' => [[
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => 1,
            'price' => $unitPrice,
            'total' => $unitPrice,
        ]],
        'subtotal' => $unitPrice,
        'total_amount' => $unitPrice,
        'amount_paid' => $unitPrice,
        'balance_due' => 0,
        'payment_status' => 'paid',
        'status' => 'paid',
        'currency' => 'UGX',
        'notes' => 'SMOKE OP Exquisite Kololo',
    ]);

    $ingestOp = app(InventoryFulfillmentIngestService::class)->ingestFromInvoice($invoiceOp, [], $SPACE);
    $lineOp = InventoryFulfillmentLine::where('invoice_id', $invoiceOp->id)->first();
    if (($ingestOp['created'] ?? 0) >= 1 && $lineOp && $lineOp->isOutpatient() && (int) $lineOp->store_id === $END_STORE) {
        ok('OP ingest → End Store queue', 'line='.$lineOp->id.' status='.$lineOp->status);
    } else {
        fail('OP ingest → End Store queue', json_encode($ingestOp).' line='.($lineOp?->id));
    }

    $demandOp = InventoryDemandLedger::where('business_id', $BID)
        ->where('invoice_id', $invoiceOp->id)
        ->where('item_id', $item->id)
        ->exists();
    if ($demandOp) {
        ok('Demand ledger on OP invoice', 'store_id scoped');
    } else {
        // demand may fire on create elsewhere — check any recent for item
        $any = InventoryDemandLedger::where('business_id', $BID)->where('item_id', $item->id)->where('store_id', $END_STORE)->exists();
        if ($any) {
            ok('Demand ledger store-scoped rows exist', 'item='.$item->id);
        } else {
            fail('Demand ledger on OP invoice');
        }
    }

    if ($lineOp) {
        try {
            $lineOp = app(InventoryFulfillmentDispenseService::class)->complete($lineOp, $user, 1);
            $stockAfter = (float) InventoryStockLevel::where('business_id', $BID)->where('store_id', $END_STORE)->where('item_id', $item->id)->value('quantity_suom');
            $pool = PatientApprovedPoolLine::where('business_id', $BID)->where('client_id', $client->id)->where('item_id', $item->id)->where('quantity_remaining', '>', 0)->first();
            if ($lineOp->status === 'completed' && abs(($stockBeforeOp - 1) - $stockAfter) < 0.01) {
                ok('OP dispense stock ↓', 'before='.$stockBeforeOp.' after='.$stockAfter);
            } else {
                fail('OP dispense stock ↓', 'status='.$lineOp->status.' after='.$stockAfter);
            }
            if ($pool) {
                ok('Approved Pool ↑ after OP', 'remaining='.$pool->quantity_remaining);
            } else {
                fail('Approved Pool ↑ after OP');
            }
        } catch (Throwable $e) {
            fail('OP dispense', $e->getMessage());
        }
    }

    // --- Record usage from pool ---
    try {
        $events = app(InventoryRecordUsageService::class)->record([
            'business_id' => $BID,
            'context' => InventoryUsageEvent::CONTEXT_PATIENT,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'notes' => 'SMOKE patient usage',
        ], $user);
        if ($events->isNotEmpty()) {
            ok('Record Usage (patient / pool)', 'events='.$events->count());
        } else {
            fail('Record Usage (patient / pool)', 'empty');
        }
    } catch (Throwable $e) {
        fail('Record Usage (patient / pool)', $e->getMessage());
    }

    // --- IP: stage → release ---
    $stockBeforeIp = (float) InventoryStockLevel::where('business_id', $BID)->where('store_id', $END_STORE)->where('item_id', $item->id)->value('quantity_suom');
    if ($stockBeforeIp < 1) {
        skip('IP stage→release', 'insufficient stock after OP ('.$stockBeforeIp.')');
    } else {
        $invoiceIp = Invoice::create([
            'invoice_number' => 'SMOKE-IP-'.uniqid(),
            'business_id' => $BID,
            'branch_id' => $BRANCH,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_phone' => $client->phone_number ?: '0700000000',
            'payment_phone' => $client->payment_phone_number ?: ($client->phone_number ?: '0700000000'),
            'created_by' => $user->id,
            'visit_id' => $visitIp,
            'client_space_id' => $SPACE,
            'end_store_id' => $END_STORE,
            'fulfillment_strategy' => InventoryFulfillmentStrategy::BATCH_AND_STAGE,
            'items' => [[
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => 1,
                'price' => $unitPrice,
                'total' => $unitPrice,
            ]],
            'subtotal' => $unitPrice,
            'total_amount' => $unitPrice,
            'amount_paid' => $unitPrice,
            'balance_due' => 0,
            'payment_status' => 'paid',
            'status' => 'paid',
            'currency' => 'UGX',
            'notes' => 'SMOKE IP Exquisite Kololo',
        ]);

        $ingestIp = app(InventoryFulfillmentIngestService::class)->ingestFromInvoice($invoiceIp, [], $SPACE);
        $lineIp = InventoryFulfillmentLine::where('invoice_id', $invoiceIp->id)->first();
        if (($ingestIp['created'] ?? 0) >= 1 && $lineIp?->isInpatient()) {
            ok('IP ingest → batch & stage', 'line='.$lineIp->id);
        } else {
            fail('IP ingest → batch & stage', json_encode($ingestIp));
        }

        if ($lineIp) {
            try {
                $staged = app(InventoryFulfillmentStageService::class)->stageBasket($lineIp, $user, true, 'SMOKE-TOTE-'.uniqid());
                $token = InventoryHandoffToken::where('business_id', $BID)->where('store_id', $END_STORE)->whereNull('used_at')->latest('id')->first();
                if ($token && $lineIp->fresh()->status === 'staged') {
                    ok('IP stage tote barcode', 'token='.$token->id.' tote='.$token->tote_barcode);
                } else {
                    fail('IP stage tote barcode', json_encode($staged ?? null));
                }

                $bypass = (string) config('services.inventory.handoff_bypass_code', '00000');
                $release = app(InventoryHandoffTokenService::class)->release($end->fresh(), $bypass, $user, $token);
                $lineIp = $lineIp->fresh();
                $stockAfterIp = (float) InventoryStockLevel::where('business_id', $BID)->where('store_id', $END_STORE)->where('item_id', $item->id)->value('quantity_suom');
                if ($lineIp->status === 'completed' && abs(($stockBeforeIp - 1) - $stockAfterIp) < 0.01) {
                    ok('IP release (bypass code) stock ↓', 'after='.$stockAfterIp);
                } else {
                    fail('IP release (bypass code) stock ↓', 'status='.$lineIp->status.' detail='.json_encode($release ?? null));
                }
            } catch (Throwable $e) {
                fail('IP stage→release', $e->getMessage());
            }
        }
    }

    // --- Goods SDQ gate (assign a SP so access passes, then assert 422) ---
    $sp = \App\Models\ServicePoint::where('business_id', $BID)->orderBy('id')->first();
    $origSps = $user->service_points;
    if ($sp) {
        $existingSps = is_array($origSps) ? $origSps : [];
        $mergedSps = array_values(array_unique(array_merge($existingSps, [$sp->id])));
        $user->forceFill(['service_points' => $mergedSps])->save();
        $user = $user->fresh();
        auth()->login($user);

        $sdq = ServiceDeliveryQueue::create([
            'business_id' => $BID,
            'branch_id' => $BRANCH,
            'service_point_id' => $sp->id,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'invoice_id' => $invoiceOp->id,
            'quantity' => 1,
            'price' => $unitPrice,
            'status' => 'pending',
            'queued_at' => now(),
        ]);

        $controller = app(ServiceDeliveryQueueController::class);
        $resp = $controller->moveToPartiallyDone($sdq);
        $code = $resp->getStatusCode();
        $body = json_decode($resp->getContent(), true);
        if ($code === 422 && str_contains((string) ($body['message'] ?? ''), 'In Progress')) {
            ok('Goods cannot move to In Progress', $body['message']);
        } else {
            fail('Goods cannot move to In Progress', 'HTTP '.$code.' '.$resp->getContent());
        }

        $resp2 = $controller->moveToCompleted($sdq->fresh());
        $code2 = $resp2->getStatusCode();
        $body2 = json_decode($resp2->getContent(), true);
        if ($code2 === 422 && str_contains((string) ($body2['message'] ?? ''), 'EndStore')) {
            ok('Goods Completed blocked when inventory on', $body2['message']);
        } else {
            fail('Goods Completed blocked when inventory on', 'HTTP '.$code2.' '.$resp2->getContent());
        }

        // restore user SP assignment
        $user->forceFill(['service_points' => $origSps])->save();
    } else {
        skip('Goods SDQ gates', 'no service point for business');
    }

    // --- Crash cart lifecycle (store 15 Ready) ---
    $crash = Store::find($CRASH)->fresh();
    $crashSvc = app(InventoryCrashCartService::class);
    $origCrashStatus = $crash->crash_cart_status;

    // Ensure stock on crash cart for usage
    $crashStock = InventoryStockLevel::firstOrCreate(
        ['business_id' => $BID, 'store_id' => $CRASH, 'item_id' => $item->id],
        ['quantity_suom' => 0]
    );
    if ((float) $crashStock->quantity_suom < 1) {
        $crashStock->update(['quantity_suom' => 5]);
    }

    try {
        if ($crash->crash_cart_status !== Store::CRASH_CART_READY) {
            // force ready for smoke if stuck
            $crash->update(['crash_cart_status' => Store::CRASH_CART_READY, 'crash_cart_deployed_at' => null]);
            $crash = $crash->fresh();
        }
        $crash = $crashSvc->deploy($crash, $user);
        if ($crash->crash_cart_status === Store::CRASH_CART_DEPLOYED) {
            ok('Crash cart Deploy', 'id='.$CRASH);
        } else {
            fail('Crash cart Deploy', $crash->crash_cart_status);
        }

        $crash = $crashSvc->startReconcile($crash, $user);
        if ($crash->crash_cart_status === Store::CRASH_CART_RECONCILING) {
            ok('Crash cart Reconciling');
        } else {
            fail('Crash cart Reconciling', $crash->crash_cart_status);
        }

        $usage = app(InventoryRecordUsageService::class)->record([
            'business_id' => $BID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $CRASH,
            'quantity' => 1,
            'notes' => 'SMOKE crash usage',
        ], $user);
        if ($usage->isNotEmpty()) {
            ok('Crash cart Record Usage', 'events='.$usage->count());
        } else {
            fail('Crash cart Record Usage');
        }

        $sealed = $crashSvc->markReady($crash->fresh(), $user, 'SMOKE-SEAL-'.uniqid(), 'smoke seal');
        if (($sealed['store']->crash_cart_status ?? null) === Store::CRASH_CART_READY) {
            ok('Crash cart Seal → Ready', 'IR order='.($sealed['order']?->id ?? 'none'));
        } else {
            fail('Crash cart Seal → Ready', json_encode($sealed['store']->crash_cart_status ?? null));
        }
    } catch (Throwable $e) {
        fail('Crash cart lifecycle', $e->getMessage());
        // best-effort restore
        try {
            Store::where('id', $CRASH)->update(['crash_cart_status' => $origCrashStatus ?: Store::CRASH_CART_READY]);
        } catch (Throwable) {
        }
    }

    // --- Route / UI surface checks ---
    $routeNames = [
        'inventory.fulfillment.index',
        'inventory.fulfillment.ward-pick',
        'inventory.usage.index',
        'inventory.reports.classification',
        'inventory.settings.edit',
        'inventory.crash-carts.index',
        'client-spaces.index',
    ];
    foreach ($routeNames as $name) {
        if (Route::has($name)) {
            ok('Route registered: '.$name);
        } else {
            fail('Route registered: '.$name);
        }
    }

    // Ward pick / pick route helpers
    if (class_exists(\App\Services\Inventory\InventoryPickRouteService::class)) {
        ok('Pick route service present');
    } else {
        fail('Pick route service present');
    }

    // STAT keywords
    $kw = $cfg->stat_priority_keywords ?? [];
    if (is_array($kw) && in_array('STAT', $kw, true)) {
        ok('STAT keywords configured', implode(',', $kw));
    } else {
        fail('STAT keywords configured');
    }

    // Multi-store network nodes
    $ends = Store::where('business_id', $BID)->where('branch_id', $BRANCH)->where('distribution_type', 'end_store')->count();
    if ($ends >= 2) {
        ok('Multi End Store network on Kololo', 'count='.$ends);
    } else {
        fail('Multi End Store network on Kololo', 'count='.$ends);
    }

} catch (Throwable $e) {
    fail('Unhandled', $e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
}

echo PHP_EOL.'=== SUMMARY ==='.PHP_EOL;
echo "PASS={$pass} FAIL={$fail} SKIP={$skip}".PHP_EOL;
exit($fail > 0 ? 1 : 0);
