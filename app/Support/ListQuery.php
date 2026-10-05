<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Liste paginée au format attendu par le frontend :
 *   ?search=kon&page=2&per_page=10&sort=-date&filter[class_id]=3
 *   → { data: [...], meta: { current_page, per_page, total, last_page } }
 *
 * Seuls les filtres et tris déclarés sont acceptés.
 */
class ListQuery
{
    /** Concaténation SQL portable (SQLite / MySQL / PostgreSQL). */
    public static function concat(string $connection, string ...$columns): string
    {
        $parts = implode(", ' ', ", $columns);

        return \Illuminate\Support\Facades\DB::connection($connection)->getDriverName() === 'sqlite'
            ? implode(" || ' ' || ", $columns)
            : "CONCAT({$parts})";
    }

    /**
     * @param  array<int, string|Closure>  $search  colonnes (« campus.name » = relation) ou closures
     * @param  array<string, string|Closure>  $filters  clé de filtre → colonne ou closure($query, $value)
     * @param  array<string, string|Closure>  $sorts  clé de tri → colonne ou closure($query, $direction)
     */
    public static function paginate(Builder $query, Request $request, array $search = [], array $filters = [], array $sorts = [], string $default = '-id'): LengthAwarePaginator
    {
        $term = trim((string) $request->input('search'));

        if ($term !== '' && $search) {
            $query->where(function (Builder $q) use ($search, $term) {
                foreach ($search as $column) {
                    if ($column instanceof Closure) {
                        $q->orWhere(fn ($sub) => $column($sub, $term));
                    } elseif (str_contains($column, '.')) {
                        $relation = Str::beforeLast($column, '.');
                        $field = Str::afterLast($column, '.');
                        $q->orWhereHas($relation, fn ($r) => $r->where($field, 'like', "%{$term}%"));
                    } else {
                        $q->orWhere($column, 'like', "%{$term}%");
                    }
                }
            });
        }

        foreach ((array) $request->input('filter', []) as $key => $value) {
            if ($value === null || $value === '' || ! array_key_exists($key, $filters)) {
                continue;
            }

            $filter = $filters[$key];
            $filter instanceof Closure ? $filter($query, $value) : $query->where($filter, $value);
        }

        $sort = (string) ($request->input('sort') ?: $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        if (isset($sorts[$key])) {
            $sorts[$key] instanceof Closure ? $sorts[$key]($query, $direction) : $query->orderBy($sorts[$key], $direction);
        } else {
            $query->orderBy($query->getModel()->getQualifiedKeyName(), str_starts_with($default, '-') ? 'desc' : 'asc');
        }

        $perPage = min(200, max(1, (int) $request->input('per_page', 15)));

        return $query->paginate($perPage)->withQueryString();
    }

    /** Réponse { data, meta } pour une pagination dont les éléments sont déjà transformés. */
    public static function json(LengthAwarePaginator $page): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
