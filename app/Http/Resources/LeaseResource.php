<?php

namespace App\Http\Resources;

use App\Enums\LeaseStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WeakMap;

/**
 * @mixin Lease
 */
class LeaseResource extends JsonResource
{
    /**
     * Id nemovitostí přihlášeného za request (klíč = Request, uvolní se s ním).
     *
     * @var WeakMap<Request, array{owned: array<array-key, int|string>, managed: array<array-key, int|string>}>|null
     */
    private static ?WeakMap $propertyIds = null;

    /** @return array<string, mixed> */
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
            // správce bez kaucí jako Payment::visibleTo (B-2)
            'payments' => $this->when($payments, fn () => PaymentResource::collection($this->whenLoaded(
                'payments',
                fn () => $finance ? $this->payments : $this->payments->reject(fn (Payment $payment) => $payment->type === PaymentType::Deposit)->values(),
            ))),
            'ratings' => $this->whenLoaded('ratings', fn () => $this->ratings->map(fn (Rating $rating) => new RatingResource($rating, $this->resource))),
        ];
    }

    /**
     * Kauce a všechny platby: vlastník nemovitosti a nájemník smlouvy, dávkově: id nemovitostí se načtou
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
     * @return array{owned: array<array-key, int|string>, managed: array<array-key, int|string>}
     */
    private static function propertyIds(User $viewer, Request $request): array
    {
        self::$propertyIds ??= new WeakMap;

        return self::$propertyIds[$request] ??= [
            'owned' => array_flip($viewer->ownedProperties()->pluck('id')->all()),
            'managed' => array_flip($viewer->managedProperties()->pluck('properties.id')->all()),
        ];
    }
}
