<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Supply;
use App\Models\UsageIngredient;
use App\Traits\MonitorsExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessAddToBill implements ShouldQueue
{
    use Queueable, MonitorsExecution;

    public function __construct($customerId, $menuItemId, $quantity, $note, $categoryId)
    {
        $this->customerId = $customerId;
        $this->menuItemId = $menuItemId;
        $this->quantity   = $quantity;
        $this->note       = $note;
        $this->categoryId = $categoryId;
    }

    public function handle(): void
    {
        $this->monitorExecution('QUEUE_WORKER_ADD_ITEM', function () {

            $customer = Customer::find($this->customerId);
            if (!$customer) {
                return;
            }

            DB::transaction(function () use ($customer) {

                $ingredients = UsageIngredient::where('menu_item_id', $this->menuItemId)->get();
                $supplyIds   = $ingredients->pluck('supply_id');

                $supplies = Supply::whereIn('id', $supplyIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $this->quantity;
                    $supply = $supplies[$ing->supply_id];

                    if ($supply->quantity < $needed) {
                        \Log::warning('Stock not enough', [
                            'menu_item_id' => $this->menuItemId
                        ]);
                        return;
                    }
                }

                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $this->quantity;
                    $supplies[$ing->supply_id]->decrement('quantity', $needed);
                }

                $order = DeliveryOrder::where('customer_id', $customer->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    $order = DeliveryOrder::create([
                        'customer_id' => $customer->id,
                        'status' => 'pending',
                        'delivery_address' => '',
                        'total_price' => 0,
                    ]);
                }

                OrderItem::create([
                    'delivery_order_id' => $order->id,
                    'menu_item_id' => $this->menuItemId,
                    'status' => 'pending',
                    'quantity' => $this->quantity,
                    'order_type' => 'delivery',
                    'notes' => $this->note
                ]);

                $menuItem = MenuItem::find($this->menuItemId);

                $order->increment('total_price', $menuItem->price * $this->quantity);

                Customer::where('id', $customer->id)
                    ->lockForUpdate()
                    ->increment(
                        'loyalty_points',
                        $menuItem->gifted_loyalty_points * $this->quantity
                    );
            });

        }, [
            'user_id' => $this->customerId,
            'menu_item_id' => $this->menuItemId,
            'quantity' => $this->quantity,
        ]);
    }
}
