<?php

namespace App\Http\Resources;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lease
 */
class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $finance = self::showsFinance($this->resource, $request);
        $payments = $finance || self::managedByViewer($this->resource, $request);

        return [
            'id' => $this->id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            // nájemné, zálohy, VS a platby i přiřazenému správci (páruje platby ručně), kauce jen vlastníkovi a nájemníkovi
            'rent_amount' => $this->when($payments, $this->rent_amount),
            'deposit_amount' => $this->when($finance, $this->deposit_amount),
            'utility_advances' => $this->when($payments, $this->utility_advances),
            'variable_symbol' => $this->when($payments, $this->variable_symbol),
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'tenant' => UserResource::nested($this->whenLoaded('tenant'), $request),
            'payments' => $this->when($payments, fn () => PaymentResource::collection($this->whenLoaded('payments'))),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }

    /**
     * Stejné pravidlo jako LeasePolicy::viewFinance (vlastník a nájemník), jen dávkově: id nemovitostí se načtou
     * jednou za request (smlouvy vnořené v nemovitosti, platbách …), ne dotazem na každou smlouvu.
     */
    public static function showsFinance(Lease $lease, Request $request): bool
    {
        $viewer = $request->user();
        if (! $viewer instanceof User) {
            return false;
        }

        return $lease->tenant_id === $viewer->id || isset(self::propertyIds($viewer, $request)['owned'][$lease->property_id]);
    }

    /**
     * Aktivní smlouva na nemovitosti, kam je přihlášený přiřazený jako správce (Lease::visibleTo, Payment::visibleTo).
     */
    private static function managedByViewer(Lease $lease, Request $request): bool
    {
        $viewer = $request->user();

        return $viewer instanceof User
            && $lease->status === LeaseStatus::Active
            && isset(self::propertyIds($viewer, $request)['managed'][$lease->property_id]);
    }

    /**
     * @return array{owned: array<int, int>, managed: array<int, int>}
     */
    private static function propertyIds(User $viewer, Request $request): array
    {
        $ids = $request->attributes->get(self::class);
        if (! is_array($ids)) {
            $ids = [
                'owned' => array_flip($viewer->ownedProperties()->pluck('id')->all()),
                'managed' => array_flip($viewer->managedProperties()->pluck('properties.id')->all()),
            ];
            $request->attributes->set(self::class, $ids);
        }

        return $ids;
    }
}
