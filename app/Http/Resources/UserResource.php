<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * Celý profil (e-mail, telefon, trust score). Vnořené uživatele vracej přes UserResource::nested().
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'trust_score' => $this->trust_score,
            'phone' => $this->phone,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    /**
     * Vnořený uživatel (nájemník, autor, hodnotitel …): celý profil jen tomu, kdo ho smí vidět, jinak souhrn.
     * Pravidlo je stejné jako UserPolicy::view (scope User::visibleTo), jen dávkově: viditelná id se načtou
     * jednou za request, ne dotazem na každého uživatele.
     */
    public static function nested(mixed $user, Request $request): JsonResource|MissingValue|null
    {
        if (! $user instanceof User) {
            return $user instanceof MissingValue ? $user : null;
        }

        return self::viewerSeesDetails($user, $request) ? new self($user) : new UserSummaryResource($user);
    }

    private static function viewerSeesDetails(User $user, Request $request): bool
    {
        $viewer = $request->user();
        if (! $viewer instanceof User) {
            return false;
        }
        if ($viewer->is($user)) {
            return true;
        }

        $visibleIds = $request->attributes->get(self::class);
        if (! is_array($visibleIds)) {
            $visibleIds = array_flip(User::query()->visibleTo($viewer)->pluck('id')->all());
            $request->attributes->set(self::class, $visibleIds);
        }

        return isset($visibleIds[$user->id]);
    }
}
