<?php

namespace App\Observers;

use App\Models\Deposit;
use App\Models\DepositIpchain;

class DepositIpchainObserver
{
    /**
     * Handle the deposit ipchain "created" event.
     *
     * @param \App\Models\DepositIpchain $depositIpchain
     * @return void
     */
    public function created(DepositIpchain $depositIpchain)
    {
        if ($deposit = Deposit::find($depositIpchain->deposit_id)) {
            $deposit->status = 'in_progress';
            $deposit->save();

            $deposit_subscription = $deposit->user->customer->subscriptions()->latest('updated_at')->first();
            $deposit_subscription->deposits_added++;
            $deposit_subscription->save();
        }

        return true;
    }

    /**
     * Handle the deposit ipchain "updated" event.
     *
     * @param \App\Models\DepositIpchain $depositIpchain
     * @return void
     */
    public function updated(DepositIpchain $depositIpchain)
    {
        if ($depositIpchain->wasChanged('transactionIdA')) {
            Deposit::find($depositIpchain->deposit_id)->update([
                'status' => 'registered',
                'registered_at' => $depositIpchain->transactionCreatedAtA,
            ]);
        }

        return true;
    }
}
