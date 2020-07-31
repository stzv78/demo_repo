<?php

namespace App\Listeners;

use App\Jobs\DepositRegister;
use Illuminate\Support\Facades\Log;

class DepositEventSubscriber
{
    /**
     * Handle deposit pushed to register events.
     */
    public function onDepositPushed($event)
    {
        $event->deposit->setNextStatus();

        Log::info("Deposit id=" . $event->deposit->id . " pushed to register");

        return DepositRegister::dispatch($event->deposit)->onQueue('toRegister');
    }

    /**
     * Handle deposit send to register events.
     */
    public function onDepositSend($event)
    {
        try {
            $event->deposit->update([
                'pds_ois_id' => $event->ois->getId(),
            ]);

            Log::info("Deposit id=" . $event->deposit->id . " set pds_ois_id=" . $event->deposit->pds_ois_id);

            $status = $this->deposit->statuses()->create([
                'pds_ois_id' => $event->ois->getId(),
                'name' => $event->ois->getStatus(),
                'status_at' => \Carbon::createFromTimeString($event->ois->getStatusDate()),
                'reason' => $event->ois->getReason(),
            ]);

            Log::info("Deposit id=" . $event->deposit->id . " set status=" . $status->name . ", status_at=" . $status->status_at);

            event(new DepositNew($this->model));

        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Handle deposit registering events set new status.
     */
    public function onDepositNew($event)
    {
        $event->deposit->checkList()->create();

        Log::info("Deposit id=" . $event->deposit->id . " add to checkList, pds_ois_id=" . $event->deposit->checkList->pds_ois_id);
    }

    /**
     * Handle deposit registering events set rejected status.
     */
    public function onDepositRejected($event)
    {
        $event->deposit->status = config('business.deposit.map_nris_status.' . $event->ois->getStatus());
        $event->deposit->save();

        $event->deposit->checkList()->delete();
    }

    /**
     * Handle deposit registering events set deposit status.
     */
    public function onDepositRegistered($event)
    {
        $event->deposit->status = config('business.deposit.map_nris_status.' . $event->ois->getStatus());
        $event->deposit->registered_at = $event->ois->getStatusDate();
        $event->deposit->save();

        $event->deposit->checkList()->delete();
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'App\Events\DepositPushed',
            'App\Listeners\DepositEventSubscriber@onDepositPushed'
        );

        $events->listen(
            'App\Events\DepositSend',
            'App\Listeners\DepositEventSubscriber@onDepositSend'
        );

        $events->listen(
            'App\Events\DepositNew',
            'App\Listeners\DepositEventSubscriber@onDepositNew'
        );

        $events->listen(
            'App\Events\DepositRejected',
            'App\Listeners\DepositEventSubscriber@onDepositRejected'
        );

        $events->listen(
            'App\Events\DepositRegistered',
            'App\Listeners\DepositEventSubscriber@onDepositRegistered'
        );
    }
}
