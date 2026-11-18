<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Text s neplatným UTF-8 (z query stringu nebo z formulářového či multipart těla) by se uložil a potom shodil
 * json_encode odpovědi (500) pro každého, kdo záznam čte. Takový vstup odmítne jako chybu validace (422).
 */
class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        $field = $this->findInvalidField($request->query->all()) ?? $this->findInvalidField($request->request->all());

        if ($field !== null) {
            throw ValidationException::withMessages([
                $field => __('The :attribute field must be valid UTF-8 text.', ['attribute' => $field]),
            ]);
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    private function findInvalidField(array $input, string $prefix = ''): ?string
    {
        foreach ($input as $key => $value) {
            $field = $prefix.$key;

            if (! mb_check_encoding((string) $key, 'UTF-8')) {
                return mb_scrub($field, 'UTF-8');
            }
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                return $field;
            }
            if (is_array($value)) {
                $nested = $this->findInvalidField($value, $field.'.');
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
