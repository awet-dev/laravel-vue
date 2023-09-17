<?php

namespace App\Http\Controllers\Shipment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\InventoryModelController;
use App\Http\Controllers\Variant\VariantModelController;
use App\Models\Shipment;
use App\Models\Variant;

class ShipmentModelController extends Controller
{
    public static function delete(mixed $ids): int
    {
        return Shipment::destroy($ids);
    }

    public static function getById(string $id): ?Shipment
    {
        return Shipment::find($id);
    }

    public static function addVariant(Shipment $shipment, Variant $variant, int $quantity): bool
    {
        $shipment->variants()->syncWithoutDetaching([
            $variant->id => ['quantity' => $quantity]
        ]);

        if ($added = self::hasVariant($shipment, $variant)) {
            $inventory = VariantModelController::getInventoryInCloset($variant, $shipment->closet);

            InventoryModelController::update($inventory, [
                'in_stock' => $inventory->in_stock - $quantity,
                'in_reserve' => $inventory->in_reserve + $quantity,
            ]);
        }

        return $added;
    }

    private static function hasVariant(Shipment $shipment, Variant $variant): bool
    {
        return $shipment->variants()
            ->wherePivot('variant_id', '=', $variant->id)
            ->exists();
    }

    public static function removeVariant(Shipment $shipment, Variant $variant): int
    {
        $variant = self::getVariantByShipment($shipment, $variant);
        $quantity = $variant->pivot->quantity;

        if ($removed = $shipment->variants()->detach($variant->id)) {
            $inventory = VariantModelController::getInventoryInCloset($variant, $shipment->closet);

            InventoryModelController::update($inventory, [
                'in_stock' => $inventory->in_stock + $quantity,
                'in_reserve' => $inventory->in_reserve - $quantity,
            ]);
        }

        return $removed;
    }

    public static function getVariantByShipment(Shipment $shipment, Variant $variant): ?Variant
    {
        return $shipment->variants()
            ->where('id', '=', $variant->id)
            ->first();
    }
}
