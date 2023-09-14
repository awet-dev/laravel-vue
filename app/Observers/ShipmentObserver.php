<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ShipmentObserver
{
    /**
     * Handle the Shipment "creating" event.
     */
    public function creating(Shipment $shipment): void
    {
        /** @var User $user */
        $user = Auth::user();
        $closet = $user->currentCloset();

        $shipment->closet_id = $closet->id;
        $shipment->supplier_id = $user->id;
    }

    /**
     * Handle the Shipment "created" event.
     */
    public function created(Shipment $shipment): void
    {
        //
    }

    /**
     * Handle the Shipment "updated" event.
     */
    public function updated(Shipment $shipment): void
    {
        //
    }

    /**
     * Handle the Shipment "deleted" event.
     */
    public function deleted(Shipment $shipment): void
    {
        //
    }

    /**
     * Handle the Shipment "restored" event.
     */
    public function restored(Shipment $shipment): void
    {
        //
    }

    /**
     * Handle the Shipment "force deleted" event.
     */
    public function forceDeleted(Shipment $shipment): void
    {
        //
    }
}
